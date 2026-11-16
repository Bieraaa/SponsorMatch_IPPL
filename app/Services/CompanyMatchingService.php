<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CompanyProfile;
use App\Models\Proposal;
use App\Models\SponsorMatch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rekomendasi proposal EO untuk perusahaan (arah perusahaan → EO).
 *
 * ALUR
 * ----
 * 1. PRE-FILTER (Laravel, tanpa AI)
 *    Hanya proposal `published` yang belum pernah dikirim ke perusahaan
 *    ini, disaring dengan kategori yang diminati perusahaan (profil +
 *    campaign aktif) dan rentang anggaran sponsorship. Bila tidak ada
 *    yang cocok pada kedua kriteria, filter dilonggarkan SATU kriteria
 *    (bukan mengirim seluruh database). Tidak ada kandidat → tampilkan
 *    kondisi kosong, bukan rekomendasi palsu.
 * 2. SKOR LOKAL
 *    Skor deterministik 0-100 sebagai fallback bila Gemini tidak tersedia.
 * 3. GEMINI
 *    Hanya kandidat hasil pre-filter (maks. config) yang dikirim. Respons
 *    divalidasi ketat: `proposal_id` harus ada di kandidat dan
 *    `campaign_id` harus campaign aktif milik perusahaan.
 * 4. PENYIMPANAN
 *    Disimpan di tabel `matches` dengan `direction = company_to_eo`,
 *    satu baris per (proposal, perusahaan). `input_hash` mencegah
 *    pemanggilan AI berulang untuk input yang sama.
 */
class CompanyMatchingService
{
    private const DIRECTION = SponsorMatch::DIRECTION_COMPANY_TO_EO;

    public function __construct(private readonly GeminiClient $gemini) {}

    /**
     * Pastikan hasil matching untuk perusahaan tersedia.
     *
     * @return array{generated: bool, message: string|null}
     */
    public function ensure(User $company, bool $force = false): array
    {
        $company->loadMissing(['companyProfile.categories', 'campaigns.kategori']);

        $profile = $company->companyProfile;

        if (! $profile instanceof CompanyProfile || ! $profile->isComplete()) {
            $this->clear($company);

            return [
                'generated' => false,
                'message' => 'Lengkapi profil perusahaan (nama dan bidang industri) agar sistem dapat mencocokkan proposal EO.',
            ];
        }

        $candidates = $this->candidates($company);

        if ($candidates->isEmpty()) {
            $this->clear($company);

            return [
                'generated' => false,
                'message' => 'Belum ada proposal yang lolos penyaringan untuk profil dan campaign perusahaan ini.',
            ];
        }

        $hash = $this->inputHash($company, $candidates);

        if (! $force) {
            $existing = SponsorMatch::query()
                ->where('direction', self::DIRECTION)
                ->where('company_user_id', $company->id)
                ->get();

            if ($existing->isNotEmpty()
                && $existing->every(fn (SponsorMatch $match) => $match->input_hash === $hash)) {
                return ['generated' => false, 'message' => null];
            }
        }

        $local = $candidates
            ->map(fn (array $candidate) => $this->localResult($candidate))
            ->keyBy('proposal_id')
            ->all();

        $ai = $this->analyseWithGemini($company, $profile, $candidates);
        $results = $this->mergeResults($local, $ai);

        $this->persist($company, $results, $hash);

        return [
            'generated' => $ai !== null,
            'message' => $ai === null
                ? 'Analisis AI sedang tidak tersedia. Rekomendasi dihitung dari profil perusahaan, campaign, dan data proposal.'
                : null,
        ];
    }

    /**
     * Kandidat proposal hasil pre-filter.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function candidates(User $company): Collection
    {
        /** @var CompanyProfile $profile */
        $profile = $company->companyProfile;

        $activeCampaigns = $company->campaigns
            ->filter(fn (Campaign $campaign) => $campaign->isActive())
            ->values();

        $targetCategories = $profile->categories
            ->pluck('idKategori')
            ->merge($activeCampaigns->pluck('idKategori'))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $fundingMin = $profile->sponsorship_min;
        $fundingMax = $profile->sponsorship_max;

        if ($fundingMin === null && $fundingMax === null) {
            $fundingMin = $activeCampaigns->min('budgetMin');
            $fundingMax = $activeCampaigns->max('budgetMax');
        }

        $hasCategoryFilter = $targetCategories !== [];
        $hasBudgetFilter = $fundingMin !== null && $fundingMax !== null;

        // Tidak ada kriteria yang bisa dipakai untuk menyaring. Dari pada
        // mengirim semua proposal ke Gemini, minta pengguna melengkapi
        // profil/campaign terlebih dahulu.
        if (! $hasCategoryFilter && ! $hasBudgetFilter) {
            return collect();
        }

        $proposals = Proposal::query()
            ->whereNull('deleteAt')
            ->where('status', 'published')
            ->whereDoesntHave('trackings', fn ($query) => $query->where('company_user_id', $company->id))
            ->with('kategori')
            ->orderByDesc('createdAt')
            ->get();

        $rows = $proposals->map(function (Proposal $proposal) use (
            $profile,
            $activeCampaigns,
            $targetCategories,
            $fundingMin,
            $fundingMax,
        ): array {
            $campaign = $this->pickCampaign($proposal, $activeCampaigns);

            $categoryFit = in_array((int) $proposal->idKategori, $targetCategories, true)
                || $campaign !== null;

            $budgetFit = $this->budgetOverlaps(
                (int) $proposal->budgetMin,
                (int) $proposal->budgetMax,
                $fundingMin,
                $fundingMax,
            );

            $candidate = [
                'proposal' => $proposal,
                'campaign' => $campaign,
                'category_fit' => $categoryFit,
                'budget_fit' => $budgetFit,
                'funding_min' => $fundingMin,
                'funding_max' => $fundingMax,
                'active_campaign_count' => $activeCampaigns->count(),
            ];

            $candidate['local'] = $this->scoreCandidate($proposal, $candidate, $profile);

            return $candidate;
        })->values();

        $pool = $this->applyFilters($rows, $hasCategoryFilter, $hasBudgetFilter);

        if ($pool->isEmpty()) {
            return collect();
        }

        $limit = max(1, (int) config('sponsormatch.recommendations.company_max_candidates', 24));

        return $pool
            ->sortByDesc(fn (array $row) => $row['local']['score'])
            ->take($limit)
            ->values();
    }

    /**
     * Terapkan filter bertingkat: kategori + anggaran → kategori → anggaran.
     * Tidak pernah mengembalikan seluruh proposal tanpa filter.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $rows, bool $hasCategoryFilter, bool $hasBudgetFilter): Collection
    {
        if ($hasCategoryFilter && $hasBudgetFilter) {
            $both = $rows->filter(fn (array $row) => $row['category_fit'] && $row['budget_fit']);

            if ($both->isNotEmpty()) {
                return $both;
            }

            $category = $rows->filter(fn (array $row) => $row['category_fit']);

            if ($category->isNotEmpty()) {
                return $category;
            }

            return $rows->filter(fn (array $row) => $row['budget_fit']);
        }

        if ($hasCategoryFilter) {
            return $rows->filter(fn (array $row) => $row['category_fit']);
        }

        return $rows->filter(fn (array $row) => $row['budget_fit']);
    }

    /**
     * Pilih campaign aktif yang kategorinya sesuai proposal.
     *
     * @param  Collection<int, Campaign>  $campaigns
     */
    private function pickCampaign(Proposal $proposal, Collection $campaigns): ?Campaign
    {
        return $campaigns
            ->filter(fn (Campaign $campaign) => (int) $campaign->idKategori === (int) $proposal->idKategori)
            ->sortByDesc(fn (Campaign $campaign) => $this->budgetOverlaps(
                (int) $proposal->budgetMin,
                (int) $proposal->budgetMax,
                $campaign->budgetMin,
                $campaign->budgetMax,
            ) ? 1 : 0)
            ->first();
    }

    /**
     * Skor lokal deterministik 0-100.
     *
     * @param  array<string, mixed>  $candidate
     * @return array{score: int, reason: string, factors: array<int, string>}
     */
    private function scoreCandidate(Proposal $proposal, array $candidate, CompanyProfile $profile): array
    {
        /** @var Campaign|null $campaign */
        $campaign = $candidate['campaign'];

        $factors = [];
        $score = 50;

        if ($candidate['category_fit']) {
            $score += 22;
            $factors[] = 'Kategori proposal sesuai dengan minat perusahaan.';
        } else {
            $factors[] = 'Kategori proposal belum tercantum pada minat perusahaan.';
        }

        if ($candidate['funding_min'] !== null && $candidate['funding_max'] !== null) {
            if ($candidate['budget_fit']) {
                $score += 12;
                $factors[] = 'Anggaran proposal berada dalam rentang dukungan perusahaan.';

                $overlap = min((int) $candidate['funding_max'], (int) $proposal->budgetMax)
                    - max((int) $candidate['funding_min'], (int) $proposal->budgetMin);
                $span = max(1, (int) $candidate['funding_max'] - (int) $candidate['funding_min']);
                $score += (int) round(max(0, min(1, $overlap / $span)) * 8);
            } else {
                $score -= 12;
                $factors[] = 'Anggaran proposal berada di luar rentang dukungan perusahaan.';
            }
        } else {
            $factors[] = 'Rentang anggaran perusahaan belum diisi.';
        }

        $proposalKeywords = $this->keywords(implode(' ', array_filter([
            (string) $proposal->title,
            (string) $proposal->desctiption,
            (string) $proposal->target_audience,
            (string) $proposal->benefits,
        ])));

        $companyText = mb_strtolower(implode(' ', array_filter([
            (string) $profile->industry,
            (string) $profile->description,
            (string) $profile->campaign_Target,
            (string) $profile->event_types,
            (string) $profile->target_audience,
        ])));

        if ($this->sharesKeyword($companyText, $proposalKeywords)) {
            $score += 8;
            $factors[] = 'Bidang usaha perusahaan relevan dengan isi proposal.';
        }

        if ($campaign !== null) {
            $score += 4;
            $factors[] = 'Campaign aktif perusahaan selaras dengan kategori proposal.';
        } elseif ($candidate['active_campaign_count'] > 0) {
            $factors[] = 'Perusahaan memiliki campaign aktif di kategori lain.';
        } else {
            $factors[] = 'Perusahaan dapat mendukung melalui profilnya meski belum membuka campaign.';
        }

        $score = max(20, min(98, $score));

        $reason = $campaign !== null
            ? sprintf(
                'Campaign %s relevan dengan proposal %s dan rentang anggaran %s.',
                $campaign->title,
                $proposal->title,
                $this->formatRange($candidate['funding_min'], $candidate['funding_max']),
            )
            : sprintf(
                'Profil perusahaan relevan dengan proposal %s pada kategori %s.',
                $proposal->title,
                $proposal->kategori?->name ?? 'event',
            );

        return [
            'score' => $score,
            'reason' => $reason,
            'factors' => array_values(array_slice(array_unique($factors), 0, 4)),
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function localResult(array $candidate): array
    {
        /** @var Proposal $proposal */
        $proposal = $candidate['proposal'];
        /** @var Campaign|null $campaign */
        $campaign = $candidate['campaign'];

        return [
            'proposal_id' => (int) $proposal->idProposal,
            'campaign_id' => $campaign?->idCampaign,
            'score' => (int) $candidate['local']['score'],
            'reason' => (string) $candidate['local']['reason'],
            'factors' => $candidate['local']['factors'],
            'source' => $campaign !== null ? 'campaign' : 'profile',
        ];
    }

    /**
     * Kirim kandidat ke Gemini dan validasi hasilnya.
     *
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>|null Keyed by proposal_id.
     */
    private function analyseWithGemini(User $company, CompanyProfile $profile, Collection $candidates): ?array
    {
        if (! $this->gemini->enabled()) {
            return null;
        }

        $activeCampaigns = $company->campaigns
            ->filter(fn (Campaign $campaign) => $campaign->isActive())
            ->take(max(1, (int) config('sponsormatch.recommendations.max_active_campaigns', 5)))
            ->values();

        $companyPayload = [
            'company_name' => $profile->companyName ?? $company->name,
            'industry' => $profile->industry,
            'description' => Str::limit((string) $profile->description, 500),
            'target_market' => Str::limit((string) $profile->campaign_Target, 400),
            'target_audience' => $profile->target_audience,
            'interested_event_types' => $profile->event_types,
            'interest_categories' => $profile->categories->pluck('name')->values()->all(),
            'funding_min' => $profile->sponsorship_min ?? $activeCampaigns->min('budgetMin'),
            'funding_max' => $profile->sponsorship_max ?? $activeCampaigns->max('budgetMax'),
            'has_active_campaign' => $activeCampaigns->isNotEmpty(),
            'campaigns' => $activeCampaigns->map(fn (Campaign $campaign) => [
                'id' => (int) $campaign->idCampaign,
                'title' => $campaign->title,
                'description' => Str::limit((string) $campaign->description, 400),
                'purpose' => Str::limit((string) $campaign->purpose, 300),
                'category' => $campaign->kategori?->name,
                'target_audience' => $campaign->target_audience,
                'budget_min' => (int) $campaign->budgetMin,
                'budget_max' => (int) $campaign->budgetMax,
                'support_type' => $campaign->supportType,
                'benefits' => $campaign->benefits,
                'period_start' => $campaign->periodStart?->toDateString(),
                'period_end' => $campaign->periodEnd?->toDateString(),
            ])->values()->all(),
        ];

        $proposalPayload = $candidates->map(function (array $candidate): array {
            /** @var Proposal $proposal */
            $proposal = $candidate['proposal'];

            return [
                'proposal_id' => (int) $proposal->idProposal,
                'title' => $proposal->title,
                'category' => $proposal->kategori?->name,
                'description' => Str::limit((string) $proposal->desctiption, 700),
                'target_audience' => $proposal->target_audience,
                'location' => $proposal->location,
                'start_date' => $proposal->start_date?->toDateString(),
                'end_date' => $proposal->end_date?->toDateString(),
                'budget_min' => (int) $proposal->budgetMin,
                'budget_max' => (int) $proposal->budgetMax,
                'need_type' => $proposal->needType,
                'benefits' => Str::limit((string) $proposal->benefits, 400),
            ];
        })->values()->all();

        $prompt = <<<'PROMPT'
Nilai kecocokan antara profil perusahaan sponsor dan daftar proposal event di bawah ini.

ATURAN WAJIB:
- Hanya gunakan data yang diberikan. Jangan menambah proposal atau campaign.
- proposal_id harus persis salah satu proposal_id pada data kandidat.
- campaign_id hanya boleh salah satu id pada campaigns perusahaan, atau null.
- score berupa bilangan bulat 0-100 (semakin tinggi semakin cocok).
- reason singkat dalam Bahasa Indonesia, maksimal 240 karakter.
- factors berisi maksimal 4 poin singkat pendukung kecocokan.
- Pertimbangkan kategori, target audiens, tujuan, lokasi, anggaran, jenis dukungan, dan benefit.

FORMAT BALASAN (JSON saja, tanpa penjelasan tambahan):
{"matches":[{"proposal_id":1,"campaign_id":null,"score":85,"reason":"...","factors":["...","..."]}]}

PROFIL PERUSAHAAN:
COMPANY_JSON

PROPOSAL KANDIDAT:
PROPOSALS_JSON
PROMPT;

        $prompt = str_replace(
            ['COMPANY_JSON', 'PROPOSALS_JSON'],
            [
                (string) json_encode($companyPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (string) json_encode($proposalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
            $prompt,
        );

        $response = $this->gemini->generateJson(
            $prompt,
            'Anda adalah analis sponsorship yang objektif. Anda hanya boleh memakai data yang diberikan.',
        );

        if ($response === null || ! isset($response['matches']) || ! is_array($response['matches'])) {
            return null;
        }

        $byProposal = [];

        foreach ($candidates as $candidate) {
            $byProposal[(int) $candidate['proposal']->idProposal] = $candidate;
        }

        $allowedCampaigns = $activeCampaigns
            ->pluck('idCampaign')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validated = [];

        foreach ($response['matches'] as $match) {
            if (! is_array($match)) {
                continue;
            }

            $proposalId = (int) ($match['proposal_id'] ?? 0);

            if (! isset($byProposal[$proposalId])) {
                continue;
            }

            $score = max(0, min(100, (int) ($match['score'] ?? 0)));

            $reason = isset($match['reason']) && is_string($match['reason'])
                ? Str::limit(trim($match['reason']), 300)
                : null;

            if ($reason === null || $reason === '') {
                continue;
            }

            $factors = [];

            if (isset($match['factors']) && is_array($match['factors'])) {
                foreach ($match['factors'] as $factor) {
                    if (is_string($factor) && trim($factor) !== '') {
                        $factors[] = Str::limit(trim($factor), 160);
                    }
                }
            }

            $campaignId = isset($match['campaign_id']) && $match['campaign_id'] !== null
                ? (int) $match['campaign_id']
                : null;

            if ($campaignId !== null && ! in_array($campaignId, $allowedCampaigns, true)) {
                $campaignId = null;
            }

            $validated[$proposalId] = [
                'campaign_id' => $campaignId,
                'score' => $score,
                'reason' => $reason,
                'factors' => array_slice($factors, 0, 4),
            ];
        }

        return $validated === [] ? null : $validated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $local
     * @param  array<int, array<string, mixed>>|null  $ai
     * @return array<int, array<string, mixed>>
     */
    private function mergeResults(array $local, ?array $ai): array
    {
        $results = [];

        foreach ($local as $proposalId => $row) {
            if ($ai !== null && isset($ai[$proposalId])) {
                $row['score'] = $ai[$proposalId]['score'];
                $row['reason'] = $ai[$proposalId]['reason'];
                $row['factors'] = $ai[$proposalId]['factors'] !== []
                    ? $ai[$proposalId]['factors']
                    : $row['factors'];
                $row['campaign_id'] = $ai[$proposalId]['campaign_id'] ?? $row['campaign_id'];
                $row['source'] = $row['campaign_id'] !== null ? 'campaign' : 'profile';
            }

            $results[] = $row;
        }

        usort($results, function (array $a, array $b): int {
            return $b['score'] <=> $a['score']
                ?: strcmp((string) $a['proposal_id'], (string) $b['proposal_id']);
        });

        return $results;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function persist(User $company, array $results, string $hash): void
    {
        try {
            $this->clear($company);

            foreach ($results as $result) {
                try {
                    SponsorMatch::query()->create([
                        'idProposal' => $result['proposal_id'],
                        'idCampaign' => $result['campaign_id'],
                        'company_user_id' => $company->id,
                        'score' => $result['score'],
                        'reason' => $result['reason'],
                        'factors' => $result['factors'],
                        'source' => $result['source'],
                        'direction' => self::DIRECTION,
                        'input_hash' => $hash,
                        'generateAt' => now(),
                        'notifiedAt' => null,
                    ]);
                } catch (Throwable $e) {
                    Log::warning('Gagal menyimpan satu rekomendasi proposal: '.$e->getMessage());
                }
            }
        } catch (Throwable $e) {
            Log::error('Gagal menyimpan rekomendasi proposal: '.$e->getMessage());
        }
    }

    private function clear(User $company): void
    {
        SponsorMatch::query()
            ->where('direction', self::DIRECTION)
            ->where('company_user_id', $company->id)
            ->delete();
    }

    /**
     * Hash input agar AI tidak dipanggil ulang bila tidak ada perubahan.
     *
     * @param  Collection<int, array<string, mixed>>  $candidates
     */
    private function inputHash(User $company, Collection $candidates): string
    {
        $profile = $company->companyProfile;

        return hash('sha256', (string) json_encode([
            'direction' => self::DIRECTION,
            'model' => $this->gemini->model(),
            'company' => [
                'id' => (int) $company->id,
                'name' => $profile?->companyName,
                'industry' => $profile?->industry,
                'description' => $profile?->description,
                'target_market' => $profile?->campaign_Target,
                'target_audience' => $profile?->target_audience,
                'event_types' => $profile?->event_types,
                'funding' => [$profile?->sponsorship_min, $profile?->sponsorship_max],
                'categories' => $profile?->categories->pluck('idKategori')->map(fn ($id) => (int) $id)->all(),
                'campaigns' => $company->campaigns
                    ->filter(fn (Campaign $campaign) => $campaign->isActive())
                    ->map(fn (Campaign $campaign) => [
                        'id' => (int) $campaign->idCampaign,
                        'title' => $campaign->title,
                        'category' => (int) $campaign->idKategori,
                        'budget' => [$campaign->budgetMin, $campaign->budgetMax],
                        'updated' => optional($campaign->updateAt)->toDateTimeString(),
                    ])->values()->all(),
            ],
            'proposals' => $candidates->map(fn (array $candidate) => [
                'id' => (int) $candidate['proposal']->idProposal,
                'title' => $candidate['proposal']->title,
                'category' => (int) $candidate['proposal']->idKategori,
                'budget' => [(int) $candidate['proposal']->budgetMin, (int) $candidate['proposal']->budgetMax],
                'updated' => optional($candidate['proposal']->updateAt)->toDateTimeString(),
            ])->values()->all(),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function budgetOverlaps(int $needMin, int $needMax, ?int $fundMin, ?int $fundMax): bool
    {
        if ($fundMin === null || $fundMax === null) {
            return true;
        }

        return $fundMax >= $needMin && $fundMin <= $needMax;
    }

    /**
     * @return array<int, string>
     */
    private function keywords(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/i', mb_strtolower($text)) ?: [];

        $stop = [
            'yang', 'untuk', 'dengan', 'dari', 'pada', 'akan', 'dalam', 'adalah',
            'kami', 'kita', 'dan', 'atau', 'ini', 'itu', 'para', 'the', 'and',
            'for', 'with', 'event', 'acara', 'kegiatan', 'proposal',
        ];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) >= 4 && ! in_array($word, $stop, true),
        )));
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function sharesKeyword(string $text, array $keywords): bool
    {
        if ($text === '' || $keywords === []) {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (mb_strlen($keyword) >= 4 && str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function formatRange(?int $min, ?int $max): string
    {
        if ($min === null && $max === null) {
            return 'belum ditentukan';
        }

        $format = fn (?int $value) => $value === null ? '-' : 'Rp'.number_format($value, 0, ',', '.');

        return $format($min).' - '.$format($max);
    }
}
