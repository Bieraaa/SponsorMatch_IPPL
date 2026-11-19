<?php

namespace App\Http\Controllers;

use App\Models\EventReport;
use App\Models\Offer;
use App\Models\Review;
use App\Models\TrackingPipeline;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Riwayat kolaborasi EO ↔ perusahaan.
 *
 * Halaman ini menampilkan tracking yang SUDAH menjadi kerja sama
 * (`deal`, `waiting_report`, `done`). Dua aksi melengkapinya:
 *
 *  - EO mengirim laporan realisasi event (tabel `event_reports`). Setelah
 *    laporan tersimpan, tracking berpindah ke `done`.
 *  - Kedua pihak dapat menulis satu ulasan (tabel `riviews`) setelah
 *    kolaborasi selesai. Rating rata-rata akun yang dinilai ikut
 *    diperbarui agar profil publik menampilkan nilai terbaru.
 *
 * Tidak ada tabel baru: semuanya memakai skema ERD yang sudah ada.
 */
class CollaborationController extends Controller
{
    /** Stage yang dianggap bagian dari riwayat kerja sama. */
    private const STAGES = ['deal', 'waiting_report', 'done'];

    public function index(Request $request): Response
    {
        $user = $this->participant($request);

        $trackings = $this->scopedQuery($user)
            ->with([
                'proposal.kategori',
                'proposal.user.eoProfile',
                'company.companyProfile',
                'campaign',
                'offers',
                'report',
            ])
            ->orderByDesc('updatedAt')
            ->get();

        $reviewedTrackingIds = Review::query()
            ->where('from_user_id', $user->id)
            ->whereIn('idTracking', $trackings->pluck('idTracking')->all())
            ->pluck('idTracking')
            ->map(fn ($id) => (int) $id)
            ->all();

        $acceptedOffers = Offer::query()
            ->whereIn('idTracking', $trackings->pluck('idTracking')->all())
            ->where('status', 'accepted')
            ->orderBy('createdAt')
            ->get()
            ->keyBy('idTracking');

        $items = $trackings
            ->map(fn (TrackingPipeline $tracking) => $this->present(
                $tracking,
                $user,
                $reviewedTrackingIds,
                $acceptedOffers->get($tracking->idTracking),
            ))
            ->values();

        $totalValue = $items->sum(fn (array $item) => (int) ($item['amount'] ?? 0));
        $partners = $items->pluck('counterpart_id')->filter()->unique()->count();

        return Inertia::render('collaborations/Index', [
            'role' => $user->isCompany() ? 'company' : 'eo',
            'items' => $items,
            'stats' => [
                'total' => $items->count(),
                'active' => $items->whereIn('stage', ['deal', 'waiting_report'])->count(),
                'completed' => $items->where('stage', 'done')->count(),
                'partners' => $partners,
                'total_value' => $totalValue,
                'total_value_label' => 'Rp '.number_format($totalValue, 0, ',', '.'),
            ],
        ]);
    }

    /**
     * EO mengirim laporan realisasi event.
     */
    public function report(Request $request, TrackingPipeline $tracking): RedirectResponse
    {
        $user = $this->participant($request);

        $tracking->loadMissing('proposal');

        abort_unless(
            $user->isEventOrganizer()
            && (int) $tracking->proposal?->idUser === (int) $user->id,
            403,
            'Hanya Event Organizer pemilik proposal yang dapat mengirim laporan.',
        );

        abort_unless(
            in_array($tracking->stage, ['deal', 'waiting_report'], true),
            422,
            'Laporan hanya dapat dikirim untuk kolaborasi yang sedang berjalan.',
        );

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:5000'],
            'goal_achieved' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($tracking, $validated): void {
            EventReport::updateOrCreate(
                ['idTracking' => $tracking->idTracking],
                [
                    'decription' => trim($validated['description']),
                    'goal_achieved' => (bool) $validated['goal_achieved'],
                ],
            );

            $tracking->update(['stage' => 'done']);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Laporan event terkirim. Kolaborasi ditandai selesai.',
        ]);

        return back();
    }

    /**
     * Ulasan setelah kolaborasi selesai (satu ulasan per pihak).
     */
    public function review(Request $request, TrackingPipeline $tracking): RedirectResponse
    {
        $user = $this->participant($request);

        $tracking->loadMissing('proposal');

        abort_unless($this->isParticipant($tracking, $user), 403);
        abort_unless($tracking->stage === 'done', 422, 'Ulasan hanya dapat dikirim setelah kolaborasi selesai.');

        $alreadyReviewed = Review::query()
            ->where('idTracking', $tracking->idTracking)
            ->where('from_user_id', $user->id)
            ->exists();

        if ($alreadyReviewed) {
            return $this->error('Anda sudah mengirim ulasan untuk kolaborasi ini.');
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $toUserId = $user->isCompany()
            ? (int) $tracking->proposal->idUser
            : (int) $tracking->company_user_id;

        abort_if($toUserId === (int) $user->id, 422, 'Tidak dapat mengulas diri sendiri.');

        DB::transaction(function () use ($tracking, $user, $toUserId, $validated): void {
            Review::create([
                'idTracking' => $tracking->idTracking,
                'from_user_id' => $user->id,
                'to_user_id' => $toUserId,
                'rating' => (int) $validated['rating'],
                'comment' => filled($validated['comment'] ?? null)
                    ? trim((string) $validated['comment'])
                    : null,
            ]);

            $average = (float) Review::query()
                ->where('to_user_id', $toUserId)
                ->avg('rating');

            User::query()
                ->whereKey($toUserId)
                ->update(['rating' => round($average, 1)]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Terima kasih! Ulasan Anda sudah tersimpan.',
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(
        TrackingPipeline $tracking,
        User $viewer,
        array $reviewedTrackingIds,
        ?Offer $acceptedOffer,
    ): array {
        $proposal = $tracking->proposal;
        $company = $tracking->company;
        $companyProfile = $company?->companyProfile;
        $eo = $proposal?->user;
        $eoProfile = $eo?->eoProfile;

        $viewerIsCompany = (int) $tracking->company_user_id === (int) $viewer->id;

        $counterpartId = $viewerIsCompany ? (int) $eo?->id : (int) $company?->id;
        $counterpartName = $viewerIsCompany
            ? ($eoProfile?->eo_name ?? $eo?->name ?? 'Event Organizer')
            : ($companyProfile?->companyName ?? $company?->name ?? 'Perusahaan');
        $counterpartAvatar = $viewerIsCompany
            ? $eo?->avatar
            : $companyProfile?->logoUrl();
        $counterpartUrl = $viewerIsCompany
            ? route('accounts.show', $counterpartId)
            : route('companies.show', $counterpartId);

        $report = $tracking->report;
        $hasReviewed = in_array((int) $tracking->idTracking, $reviewedTrackingIds, true);

        return [
            'id' => (int) $tracking->idTracking,
            'proposal_id' => (int) $tracking->idProposal,
            'title' => $proposal?->title ?? 'Proposal',
            'category' => $proposal?->kategori?->name,
            'location' => $proposal?->location,
            'period' => $this->period($proposal?->start_date, $proposal?->end_date),
            'stage' => $tracking->stage,
            'stage_label' => $this->stageLabel($tracking->stage),
            'counterpart_id' => $counterpartId,
            'counterpart_name' => $counterpartName,
            'counterpart_avatar' => $counterpartAvatar,
            'counterpart_url' => $counterpartUrl,
            'campaign_title' => $tracking->campaign?->title,
            'amount' => $acceptedOffer?->amount === null ? null : (int) $acceptedOffer->amount,
            'amount_label' => $acceptedOffer?->amount === null
                ? 'Belum ada nominal'
                : 'Rp '.number_format((int) $acceptedOffer->amount, 0, ',', '.'),
            'support_type' => $acceptedOffer?->support_type,
            'agreed_at' => $acceptedOffer?->createdAt?->toIso8601String(),
            'updated_at' => $tracking->updatedAt?->toIso8601String(),
            'report' => $report === null
                ? null
                : [
                    'description' => $report->decription,
                    'goal_achieved' => $report->goal_achieved,
                    'submitted_at' => $report->submittedAt?->toIso8601String(),
                ],
            'can_submit_report' => $viewer->isEventOrganizer()
                && in_array($tracking->stage, ['deal', 'waiting_report'], true),
            'can_review' => $tracking->stage === 'done' && ! $hasReviewed,
            'reviewed' => $hasReviewed,
            'detail_url' => $viewerIsCompany
                ? route('sponsor.proposals.show', $tracking->idTracking)
                : route('proposals.index', ['selected' => $tracking->idProposal]),
        ];
    }

    private function period(mixed $start, mixed $end): string
    {
        if ($start === null) {
            return 'Tanggal belum ditentukan';
        }

        $format = fn ($date) => $date->locale('id')->translatedFormat('d M Y');

        if ($end === null || $start->toDateString() === $end->toDateString()) {
            return $format($start);
        }

        return $format($start).' – '.$format($end);
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'deal' => 'Berjalan',
            'waiting_report' => 'Menunggu laporan',
            'done' => 'Selesai',
            default => $stage,
        };
    }

    /**
     * Batasi tracking sesuai peran.
     */
    private function scopedQuery(User $user)
    {
        $query = TrackingPipeline::query()->whereIn('stage', self::STAGES);

        if ($user->isCompany()) {
            return $query->where('company_user_id', $user->id);
        }

        return $query->whereHas(
            'proposal',
            fn ($proposal) => $proposal->where('idUser', $user->id),
        );
    }

    private function isParticipant(TrackingPipeline $tracking, User $user): bool
    {
        return (int) $tracking->company_user_id === (int) $user->id
            || (int) $tracking->proposal?->idUser === (int) $user->id;
    }

    private function participant(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isCompany() || $user->isEventOrganizer()),
            403,
            'Halaman ini hanya untuk akun Event Organizer atau Perusahaan.',
        );

        return $user;
    }

    private function error(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
