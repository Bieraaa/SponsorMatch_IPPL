<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Models\FollowUpLog;
use App\Models\Offer;
use App\Models\Proposal;
use App\Models\TrackingPipeline;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seluruh logika data untuk halaman "Proposal Saya".
 *
 * Prinsip: TIDAK ADA angka dummy. Semua statistik, grafik, daftar tindak
 * lanjut, dan panel detail dihitung dari tabel nyata
 * (proposal, trackingpipeline, offer, follow_up_logs, proposalviewlogs,
 * event_reports).
 *
 * Status proposal tidak disimpan terpisah di database, tapi DITURUNKAN:
 *   proposal.status = 'draft'                        → Draft
 *   tracking stage 'deal'/'done' atau offer accepted → Disetujui
 *   tracking stage 'viewed'/'negotiating'/...        → Menunggu Respons
 *   tracking stage 'rejected' atau offer rejected    → Ditolak
 *   sisanya                                          → Terkirim
 *
 * Ekspresi SQL yang sama dipakai untuk SELECT (isi tabel) dan WHERE
 * (filter tab), jadi hasil filter selalu konsisten dengan isi tabel.
 */
class ProposalService
{
    /** Periode follow-up otomatis: dianggap "sudah ditindaklanjuti" bila ada log < 7 hari. */
    private const FOLLOW_UP_WINDOW_DAYS = 7;

    /** Jumlah item maksimal pada panel "Perlu Tindak Lanjut". */
    private const FOLLOW_UP_LIMIT = 5;

    /**
     * Ekspresi SQL status proposal yang diturunkan dari tracking & offer.
     *
     * @param  string  $alias  nama tabel/alias proposal pada query
     */
    public function statusSql(string $alias = 'proposal'): string
    {
        return <<<SQL
            CASE
                WHEN {$alias}.status = 'draft' THEN 'draft'
                WHEN EXISTS (
                        SELECT 1 FROM trackingpipeline t
                        WHERE t.idProposal = {$alias}.idProposal
                          AND t.stage IN ('deal', 'done')
                    )
                  OR EXISTS (
                        SELECT 1 FROM trackingpipeline t
                        JOIN offer o ON o.idTracking = t.idTracking
                        WHERE t.idProposal = {$alias}.idProposal
                          AND o.status = 'accepted'
                    )
                    THEN 'approved'
                WHEN EXISTS (
                        SELECT 1 FROM trackingpipeline t
                        WHERE t.idProposal = {$alias}.idProposal
                          AND t.stage IN ('viewed', 'negotiating', 'waiting_report')
                    )
                    THEN 'waiting'
                WHEN EXISTS (
                        SELECT 1 FROM trackingpipeline t
                        WHERE t.idProposal = {$alias}.idProposal
                          AND t.stage = 'rejected'
                    )
                  OR EXISTS (
                        SELECT 1 FROM trackingpipeline t
                        JOIN offer o ON o.idTracking = t.idTracking
                        WHERE t.idProposal = {$alias}.idProposal
                          AND o.status = 'rejected'
                    )
                    THEN 'rejected'
                WHEN EXISTS (
                        SELECT 1 FROM trackingpipeline t
                        WHERE t.idProposal = {$alias}.idProposal
                    )
                    THEN 'sent'
                WHEN {$alias}.status = 'published' THEN 'ready'
                ELSE 'sent'
            END
        SQL;
    }

    /**
     * Query dasar daftar proposal milik user (EO) + eager loading relasi.
     *
     * @param  Builder<Proposal>  $query
     * @return Builder<Proposal>
     */
    public function baseQuery(User $user, string $search = ''): Builder
    {
        $query = Proposal::query()
            ->where('proposal.idUser', $user->id)
            ->with([
                'kategori',
                'trackings.company.companyProfile',
                'trackings.campaign.kategori',
                'trackings.offers',
            ]);

        $search = trim($search);

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

            $query->where(function (Builder $q) use ($like) {
                $q->where('proposal.title', 'like', $like)
                    ->orWhere('proposal.location', 'like', $like)
                    ->orWhereHas('kategori', fn (Builder $k) => $k->where('name', 'like', $like))
                    ->orWhereHas('trackings.company', fn (Builder $c) => $c->where('name', 'like', $like))
                    ->orWhereHas('trackings.company.companyProfile', fn (Builder $p) => $p->where('companyName', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * Filter berdasarkan status turunan.
     *
     * @param  Builder<Proposal>  $query
     * @return Builder<Proposal>
     */
    public function filterByStatus(Builder $query, ProposalStatus $status): Builder
    {
        return $query->whereRaw($this->statusSql().' = ?', [$status->value]);
    }

    /**
     * Jumlah proposal per status (untuk angka pada tab).
     *
     * @return array<string, int>
     */
    public function statusCounts(User $user, string $search = ''): array
    {
        $query = DB::table('proposal as p')->where('p.idUser', $user->id);

        $search = trim($search);

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

            $query->where(function ($sub) use ($like) {
                $sub->where('p.title', 'like', $like)
                    ->orWhere('p.location', 'like', $like)
                    ->orWhereExists(function ($q) use ($like) {
                        $q->select(DB::raw(1))
                            ->from('kategori as k')
                            ->whereColumn('k.idKategori', 'p.idKategori')
                            ->where('k.name', 'like', $like);
                    })
                    ->orWhereExists(function ($q) use ($like) {
                        $q->select(DB::raw(1))
                            ->from('trackingpipeline as t')
                            ->join('users as u', 'u.id', '=', 't.company_user_id')
                            ->leftJoin('company_profiles as cp', 'cp.idUser', '=', 'u.id')
                            ->whereColumn('t.idProposal', 'p.idProposal')
                            ->where(fn ($q2) => $q2->where('u.name', 'like', $like)->orWhere('cp.companyName', 'like', $like));
                    });
            });
        }

        $rows = $query
            ->selectRaw($this->statusSql('p').' as proposal_status')
            ->selectRaw('count(*) as total')
            ->groupBy('proposal_status')
            ->get();

        $counts = ['all' => 0];

        foreach (ProposalStatus::tabs() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($rows as $row) {
            $counts['all'] += (int) $row->total;
            $counts[(string) $row->proposal_status] = (int) $row->total;
        }

        return $counts;
    }

        /**
     * Hitung proposal per tahap pipeline untuk Dashboard EO.
     *
     * Memakai statusSql() supaya tiap proposal dihitung SATU kali dan angkanya
     * sama dengan halaman "Proposal Saya". Status 'waiting' dipecah menjadi
     * 'viewed' (Ditinjau) dan 'negotiating' (Negosiasi).
     *
     * @return array<string, int>
     */
    public function pipelineCounts(User $user): array
    {
        $base = $this->statusSql('p');

        $rows = DB::table('proposal as p')
            ->where('p.idUser', $user->id)
            ->selectRaw("CASE
                WHEN ({$base}) = 'waiting' THEN (
                    CASE WHEN EXISTS (
                        SELECT 1 FROM trackingpipeline t
                        WHERE t.idProposal = p.idProposal AND t.stage = 'negotiating'
                    ) THEN 'negotiating' ELSE 'viewed' END
                )
                ELSE ({$base})
            END as tahap")
            ->selectRaw('count(*) as total')
            ->groupBy('tahap')
            ->get()
            ->pluck('total', 'tahap');

        return [
            'draft' => (int) $rows->get('draft', 0),
            'dikirim' => (int) $rows->get('sent', 0),
            'ditinjau' => (int) $rows->get('viewed', 0),
            'negosiasi' => (int) $rows->get('negotiating', 0),
            'disetujui' => (int) $rows->get('approved', 0),
            'ditolak' => (int) $rows->get('rejected', 0),
            'siap_dikirim' => (int) $rows->get('ready', 0),
        ];
    }

    /**
     * Empat kartu statistik + indikator perubahan vs periode sebelumnya.
     *
     * Angka dihitung dari proposal yang DIKIRIM ke sponsor (ada tracking)
     * dalam periode berjalan, dibandingkan dengan periode sebelumnya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stats(User $user, int $days = 30): array
    {
        $now = Carbon::now();
        $current = $this->countStatuses($user, $now->copy()->subDays($days), $now);
        $previous = $this->countStatuses($user, $now->copy()->subDays($days * 2), $now->copy()->subDays($days));

        $definitions = [
            ['key' => 'sent', 'label' => 'Proposal Terkirim'],
            ['key' => 'approved', 'label' => 'Proposal Disetujui'],
            ['key' => 'waiting', 'label' => 'Menunggu Respons'],
            ['key' => 'rejected', 'label' => 'Proposal Ditolak'],
        ];

        return array_map(function (array $definition) use ($current, $previous) {
            $value = $current[$definition['key']] ?? 0;
            $before = $previous[$definition['key']] ?? 0;

            return [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'value' => $value,
                'previous' => $before,
                // null = tidak ada pembanding (periode sebelumnya kosong)
                'change' => $before > 0 ? round((($value - $before) / $before) * 100, 1) : null,
            ];
        }, $definitions);
    }

    /**
     * Hitung jumlah proposal per status dalam rentang tanggal tertentu.
     *
     * "sent" dihitung sebagai VOLUME pengiriman: jumlah proposal berbeda
     * yang DIKIRIM ke sponsor pada periode tersebut (apa pun statusnya
     * sekarang). Tiga status lain adalah hasil dari alur itu:
     *   terkirim = disetujui + menunggu respons + ditolak + yang masih 'sent'
     *
     * @return array<string, int>
     */
    private function countStatuses(User $user, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('proposal as p')
            ->where('p.idUser', $user->id)
            ->whereExists(function ($query) use ($from, $to) {
                $query->select(DB::raw(1))
                    ->from('trackingpipeline as t')
                    ->whereColumn('t.idProposal', 'p.idProposal')
                    ->whereBetween('t.createdAt', [$from, $to]);
            })
            ->selectRaw($this->statusSql('p').' as proposal_status')
            ->selectRaw('count(distinct p.idProposal) as total')
            ->groupBy('proposal_status')
            ->get();

        $counts = [];

        foreach (ProposalStatus::tabs() as $status) {
            $counts[$status->value] = 0;
        }

        $total = 0;

        foreach ($rows as $row) {
            $counts[(string) $row->proposal_status] = (int) $row->total;
            $total += (int) $row->total;
        }

        // Volume pengiriman = semua proposal yang masuk ke sponsor.
        $counts['sent'] = $total;

        return $counts;
    }

    /**
     * Data grafik tren (2 seri: proposal terkirim & proposal disetujui).
     *
     * - "Terkirim" dihitung dari tanggal proposal dikirim ke sponsor
     *   (`trackingpipeline.createdAt`).
     * - "Disetujui" dihitung dari tanggal deal tercatat
     *   (`trackingpipeline.updatedAt`).
     *
     * Untuk periode > 30 hari, data harian digabung per minggu supaya
     * grafik tetap terbaca.
     *
     * @return array<string, mixed>
     */
    public function trend(User $user, int $days = 30): array
    {
        $now = Carbon::now();
        $from = $now->copy()->subDays($days - 1)->startOfDay();

        $sent = $this->dailySeries(
            $user,
            $from,
            $now,
            't.createdAt',
            fn ($query) => $query
                ->selectRaw('date(t.createdAt) as d, count(distinct t.idProposal) as c')
                ->groupByRaw('date(t.createdAt)'),
        );

        $approved = $this->dailySeries(
            $user,
            $from,
            $now,
            't.updatedAt',
            fn ($query) => $query
                ->leftJoin('offer as ao', function ($join) {
                    $join->on('ao.idTracking', '=', 't.idTracking')->where('ao.status', 'accepted');
                })
                ->where(fn ($q) => $q->whereIn('t.stage', ['deal', 'done'])->orWhereNotNull('ao.idOffer'))
                ->selectRaw('date(t.updatedAt) as d, count(distinct t.idProposal) as c')
                ->groupByRaw('date(t.updatedAt)'),
        );

        $labels = [];
        $sentValues = [];
        $approvedValues = [];

        if ($days > 30) {
            // Gabung per minggu (Senin sebagai awal minggu).
            $buckets = [];

            for ($i = 0; $i < $days; $i++) {
                $date = $from->copy()->addDays($i);
                $key = $date->copy()->startOfWeek()->toDateString();
                $buckets[$key] ??= ['label' => $date->copy()->startOfWeek()->format('d M'), 'sent' => 0, 'approved' => 0];
            }

            foreach ($buckets as $key => $bucket) {
                $buckets[$key]['sent'] += $sent[$key] ?? 0;
                $buckets[$key]['approved'] += $approved[$key] ?? 0;
            }

            foreach ($buckets as $bucket) {
                $labels[] = $bucket['label'];
                $sentValues[] = $bucket['sent'];
                $approvedValues[] = $bucket['approved'];
            }
        } else {
            for ($i = 0; $i < $days; $i++) {
                $date = $from->copy()->addDays($i);
                $key = $date->toDateString();
                $labels[] = $date->format('d M');
                $sentValues[] = $sent[$key] ?? 0;
                $approvedValues[] = $approved[$key] ?? 0;
            }
        }

        return [
            'labels' => $labels,
            'sent' => $sentValues,
            'approved' => $approvedValues,
        ];
    }

    /**
     * Hitung jumlah per tanggal untuk satu seri grafik.
     *
     * @param  callable(\Illuminate\Database\Query\Builder): \Illuminate\Database\Query\Builder  $shape
     * @return array<string, int>
     */
    private function dailySeries(User $user, Carbon $from, Carbon $to, string $dateColumn, callable $shape): array
    {
        $rows = DB::table('trackingpipeline as t')
            ->join('proposal as p', 'p.idProposal', '=', 't.idProposal')
            ->where('p.idUser', $user->id)
            ->whereNotNull($dateColumn)
            ->whereBetween($dateColumn, [$from, $to])
            ->tap($shape)
            ->get();

        $series = [];

        foreach ($rows as $row) {
            $series[(string) $row->d] = (int) $row->c;
        }

        return $series;
    }

    /**
     * Panel "Perlu Tindak Lanjut" — proposal yang benar-benar butuh aksi.
     *
     * @return array{total: int, items: array<int, array<string, mixed>>}
     */
    public function followUps(User $user): array
    {
        $trackings = TrackingPipeline::query()
            ->whereIn('idProposal', Proposal::query()->where('idUser', $user->id)->select('idProposal'))
            ->with([
                'proposal.kategori',
                'company.companyProfile',
                'offers.fromUser',
                'followUps',
            ])
            ->orderByDesc('createdAt')
            ->get();

        $items = [];

        foreach ($trackings as $tracking) {
            $action = $this->followUpAction($tracking);

            if ($action === null) {
                continue;
            }

            $companyName = $tracking->company?->companyProfile?->companyName
                ?? $tracking->company?->name
                ?? 'Sponsor';

            $items[] = [
                'id' => $tracking->idTracking,
                'proposalId' => $tracking->idProposal,
                'proposalTitle' => $tracking->proposal?->title ?? '-',
                'companyName' => $companyName,
                'action' => $action['action'],
                'actionLabel' => $action['label'],
                'description' => $action['description'],
                'sentAt' => $tracking->createdAt?->toIso8601String(),
                'lastFollowUpAt' => $tracking->followUps->max('sentAt')?->toIso8601String(),
            ];
        }

        return [
            'total' => count($items),
            'items' => array_slice($items, 0, self::FOLLOW_UP_LIMIT),
        ];
    }

    /**
     * Tentukan aksi yang dibutuhkan sebuah tracking (null = tidak perlu).
     *
     * @return array{action: string, label: string, description: string}|null
     */
    private function followUpAction(TrackingPipeline $tracking): ?array
    {
        $companyName = $tracking->company?->companyProfile?->companyName
            ?? $tracking->company?->name
            ?? 'sponsor';

        $companyOffers = $tracking->offers->where('from_user_id', $tracking->company_user_id);

        // Penawaran sponsor yang belum direspons → perlu konfirmasi.
        $pending = $companyOffers->firstWhere('status', 'pending');

        if ($pending !== null) {
            return [
                'action' => 'konfirmasi',
                'label' => 'Konfirmasi penawaran dengan '.$companyName,
                'description' => $pending->amount
                    ? 'Penawaran '.ucfirst((string) $pending->support_type).' senilai Rp'.number_format($pending->amount, 0, ',', '.').' menunggu respons.'
                    : 'Sponsor mengirim penawaran baru yang menunggu respons.',
            ];
        }

        // Counter offer → proposal perlu direvisi.
        $counter = $companyOffers->firstWhere('status', 'counter');

        if ($counter !== null) {
            return [
                'action' => 'revisi',
                'label' => 'Revisi proposal '.$companyName,
                'description' => $counter->amount
                    ? 'Sponsor countering '.ucfirst((string) $counter->support_type).' Rp'.number_format($counter->amount, 0, ',', '.').'.'
                    : 'Sponsor countering penawaran — proposal perlu disesuaikan.',
            ];
        }

        // Sudah ada follow-up terbaru → tidak perlu ditindaklanjuti lagi.
        $recentFollowUp = $tracking->followUps
            ->filter(fn (FollowUpLog $log) => $log->sentAt && $log->sentAt->gt(Carbon::now()->subDays(self::FOLLOW_UP_WINDOW_DAYS)))
            ->last();

        if ($recentFollowUp !== null) {
            return null;
        }

        // Menunggu sponsor → perlu follow-up.
        if (in_array($tracking->stage, ['sent', 'viewed'], true)) {
            return [
                'action' => 'follow-up',
                'label' => 'Follow-up proposal '.$companyName,
                'description' => $tracking->stage === 'sent'
                    ? 'Belum ada respons sponsor sejak proposal dikirim.'
                    : 'Sponsor sudah membuka proposal tapi belum memberi jawaban.',
            ];
        }

        return null;
    }

    /**
     * Nomor urut proposal per Event Organizer (1, 2, 3, ...).
     *
     * Nomor ini BUKAN id database dan bukan nomor global: setiap user punya
     * urutan sendiri berdasarkan waktu pembuatan. Jadi proposal pertama
     * seorang user selalu bernomor 1, berapa pun id-nya di tabel `proposal`.
     * Urutan memakai `createdAt` lalu `idProposal` sebagai penentu terakhir
     * supaya hasilnya stabil (tidak berubah saat difilter/di-paginate).
     *
     * @return array<int, int> [idProposal => nomor]
     */
    public function numbersFor(User $user): array
    {
        return Proposal::query()
            ->where('idUser', $user->id)
            ->orderBy('createdAt')
            ->orderBy('idProposal')
            ->pluck('idProposal')
            ->values()
            ->mapWithKeys(fn (int $id, int $index) => [$id => $index + 1])
            ->all();
    }

    /**
     * Payload satu baris tabel proposal.
     *
     * @return array<string, mixed>
     */
    public function row(Proposal $proposal, int $number = 1): array
    {
        $status = $this->statusOf($proposal);
        $tracking = $this->primaryTracking($proposal);
        $company = $tracking?->company;
        $profile = $company?->companyProfile;
        $documentPath = (string) $proposal->file_path;

        return [
            'id' => $proposal->idProposal,
            'nomor' => (string) $number,
            'title' => $proposal->title,
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'kategori' => $proposal->kategori?->name,
            'eventName' => $tracking?->campaign?->title ?? $proposal->title,
            'eventDate' => ($tracking?->campaign?->periodStart ?? $proposal->start_date)?->format('Y-m-d'),
            'sponsorName' => $profile?->companyName ?? $company?->name,
            'sponsorIndustry' => $profile?->industry,
            'sponsorInitials' => $this->initials($profile?->companyName ?? $company?->name ?? '?'),
            'sentAt' => $tracking?->createdAt?->toIso8601String() ?? $proposal->createdAt?->toIso8601String(),
            'budgetMin' => $proposal->budgetMin,
            'budgetMax' => $proposal->budgetMax,
            'trackingId' => $tracking?->idTracking,
            'canFollowUp' => $tracking !== null && $this->followUpAction($tracking) !== null,
            'documentAvailable' => $documentPath !== '' && Storage::disk('local')->exists($documentPath),
        ];
    }

    /**
     * Status turunan dari proposal (dibaca dari kolom alias `proposal_status`).
     */
    public function statusOf(Proposal $proposal): ProposalStatus
    {
        $raw = $proposal->getAttribute('proposal_status');

        return ($raw ? ProposalStatus::tryFrom((string) $raw) : null) ?? ProposalStatus::Sent;
    }

    /**
     * Tracking sponsor yang paling menentukan status proposal.
     */
    public function primaryTracking(Proposal $proposal): ?TrackingPipeline
    {
        $priority = [
            'deal' => 5,
            'done' => 5,
            'negotiating' => 4,
            'viewed' => 3,
            'waiting_report' => 3,
            'rejected' => 2,
            'sent' => 1,
        ];

        return $proposal->trackings
            ->sortByDesc(fn (TrackingPipeline $tracking) => $priority[$tracking->stage] ?? 0)
            ->first();
    }

    /**
     * Payload panel detail proposal.
     *
     * @return array<string, mixed>|null
     */
    public function detail(?Proposal $proposal, int $number = 1): ?array
    {
        if ($proposal === null) {
            return null;
        }

        $proposal->loadMissing([
            'kategori',
            'trackings.company.companyProfile',
            'trackings.campaign.kategori',
            'trackings.offers',
            'trackings.followUps',
            'trackings.report',
            'viewLogs.viewer',
        ]);

        $status = $this->statusOf($proposal);
        $tracking = $this->primaryTracking($proposal);
        $campaign = $tracking?->campaign;
        $company = $tracking?->company;
        $profile = $company?->companyProfile;
        $offer = $tracking?->offers?->sortByDesc(fn (Offer $item) => $item->createdAt ?? now())->first();

        $documentPath = (string) $proposal->file_path;
        $documentExists = $documentPath !== '' && Storage::disk('local')->exists($documentPath);

        return [
            'id' => $proposal->idProposal,
            'nomor' => (string) $number,
            'title' => $proposal->title,
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'kategori' => $proposal->kategori?->name,
            'description' => $proposal->desctiption,
            'needType' => $proposal->needType,
            'event' => [
                'name' => $campaign?->title ?? $proposal->title,
                'kategori' => $campaign?->kategori?->name ?? $proposal->kategori?->name,
                'date' => ($campaign?->periodStart ?? $proposal->start_date)?->format('Y-m-d'),
                'endDate' => ($campaign?->periodEnd ?? $proposal->end_date)?->format('Y-m-d'),
                'location' => $proposal->location,
                'targetAudience' => $proposal->target_audience,
                'estimatedParticipant' => $campaign?->estimatedParticipant,
                // Tabel `campaign` tidak punya kolom gambar, jadi thumbnail
                // memakai warna kategori (lihat komponen frontend).
                'thumbnail' => null,
            ],
            'sponsor' => [
                'name' => $profile?->companyName ?? $company?->name,
                'industry' => $profile?->industry,
                'initials' => $this->initials($profile?->companyName ?? $company?->name ?? '?'),
            ],
            'offer' => [
                'budgetMin' => $proposal->budgetMin,
                'budgetMax' => $proposal->budgetMax,
                'amount' => $offer?->amount,
                'supportType' => $offer?->support_type,
                'status' => $offer?->status,
                'benefits' => $this->splitBenefits($proposal->benefits),
            ],
            'document' => [
                'fileName' => $documentPath === '' ? '-' : basename($documentPath),
                'path' => $documentPath,
                'size' => $documentExists ? Storage::disk('local')->size($documentPath) : null,
                'uploadedAt' => $proposal->createdAt?->toIso8601String(),
                'available' => $documentExists,
            ],
            'timeline' => $this->timeline($proposal),
            'report' => $tracking?->report
                ? [
                    'description' => (string) ($tracking->report->decription ?? ''),
                    'goalAchieved' => (bool) $tracking->report->goal_achieved,
                    'submittedAt' => $tracking->report->submittedAt?->toIso8601String(),
                ]
                : null,
            'actions' => [
                'hasReport' => $tracking?->report !== null,
                'canFollowUp' => $tracking !== null && $this->followUpAction($tracking) !== null,
                'trackingId' => $tracking?->idTracking,
            ],
        ];
    }

    /**
     * Kronologi status proposal — hanya dari data yang benar-benar ada.
     *
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Proposal $proposal): array
    {
        $events = [];

        if ($proposal->createdAt) {
            $events[] = [
                'label' => 'Proposal dibuat',
                // Proposal selalu dibuat sebagai draft; status sekarang bisa
                // sudah berubah, jadi jangan memakai $proposal->status di sini.
                'description' => 'Proposal dibuat dan disimpan sebagai draft.',
                'at' => $proposal->createdAt->toIso8601String(),
            ];
        }

        foreach ($proposal->trackings as $tracking) {
            $companyName = $tracking->company?->companyProfile?->companyName
                ?? $tracking->company?->name
                ?? 'sponsor';

            $events[] = [
                'label' => 'Proposal dikirim',
                'description' => 'Dikirim ke '.$companyName.' (status: '.$tracking->stage.').',
                'at' => $tracking->createdAt?->toIso8601String(),
            ];

            foreach ($tracking->followUps as $followUp) {
                $events[] = [
                    'label' => 'Follow-up dikirim',
                    'description' => 'Tindak lanjut dikirim ke '.$companyName.'.',
                    'at' => $followUp->sentAt?->toIso8601String(),
                ];
            }

            foreach ($tracking->offers as $offer) {
                $events[] = [
                    'label' => 'Penawaran '.strtoupper((string) $offer->status),
                    'description' => trim(ucfirst((string) $offer->support_type).' dari '.$companyName
                        .($offer->amount ? ' senilai Rp'.number_format($offer->amount, 0, ',', '.') : '')),
                    'at' => $offer->createdAt?->toIso8601String(),
                ];
            }

            if ($tracking->report !== null) {
                $events[] = [
                    'label' => 'Laporan acara dikirim',
                    'description' => trim((string) ($tracking->report->decription ?? '')),
                    'at' => $tracking->report->submittedAt?->toIso8601String(),
                ];
            }
        }

        foreach ($proposal->viewLogs as $viewLog) {
            $events[] = [
                'label' => 'Proposal dibuka',
                'description' => 'Dibuka oleh '.($viewLog->viewer?->name ?? 'sponsor').'.',
                'at' => $viewLog->viewedAt?->toIso8601String(),
            ];
        }

        return collect($events)
            ->filter(fn (array $event) => $event['at'] !== null)
            ->sortByDesc('at')
            ->values()
            ->all();
    }

    /**
     * Pisahkan teks benefits menjadi daftar poin.
     *
     * @return array<int, string>
     */
    private function splitBenefits(?string $benefits): array
    {
        if ($benefits === null || trim($benefits) === '') {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n|;|,/', $benefits) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter(fn (string $item) => $item !== '')
            ->values()
            ->all();
    }

    /**
     * Inisial nama sponsor untuk avatar.
     */
    private function initials(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '?';
        }

        $words = preg_split('/\s+/', $name) ?: [];

        return Str::upper(collect($words)->take(2)->map(fn (string $word) => mb_substr($word, 0, 1))->implode(''));
    }
}
