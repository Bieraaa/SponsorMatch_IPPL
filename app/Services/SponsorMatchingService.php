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
 * Mesin rekomendasi sponsor.
 *
 * ALUR
 * ----
 * 1. PRE-FILTER (Laravel, tanpa AI)
 *    Hanya perusahaan aktif yang punya profil, dengan minat kategori dan
 *    rentang dana yang bersinggungan dengan proposal. Campaign aktif ikut
 *    dipertimbangkan, tetapi TIDAK wajib.
 * 2. SKOR LOKAL
 *    Setiap kandidat diberi skor deterministik 0-100 sebagai dasar dan
 *    sebagai fallback bila Gemini tidak tersedia.
 * 3. GEMINI
 *    Hanya kandidat hasil pre-filter (maks. `max_candidates`) yang dikirim.
 *    Respons JSON divalidasi ketat: company_id dan campaign_id harus berasal
 *    dari daftar kandidat, sehingga AI tidak bisa mengarang entitas.
 * 4. PENYIMPANAN
 *    Hasil disimpan di tabel `matches` (satu baris per proposal+perusahaan).
 *    `input_hash` mencegah pemanggilan AI berulang untuk input yang sama.
 *
 * Satu perusahaan hanya muncul sekali karena hasil di-key berdasarkan
 * `company_user_id`, bukan campaign.
 */
class SponsorMatchingService
{
    private const DIRECTION = SponsorMatch::DIRECTION_EO_TO_COMPANY;

    public function __construct(private readonly GeminiClient $gemini) {}

    /**
     * Pastikan hasil matching untuk proposal tersedia.
     *
     * @return array{generated: bool, message: string|null}
     */
    public function ensure(Proposal $proposal, bool $force = false): array
    {
        $proposal->loadMissing('kategori');

        $candidates = $this->candidates($proposal);

        if ($candidates->isEmpty()) {
            SponsorMatch::query()
                ->where('direction', self::DIRECTION)
                ->where('idProposal', $proposal->idProposal)
                ->delete();

            return [
                'generated' => false,
                'message' => 'Belum ada perusahaan yang lolos penyaringan untuk proposal ini.',
            ];
        }

        $hash = $this->inputHash($proposal, $candidates);

        if (! $force) {
            $existing = SponsorMatch::query()
                ->where('direction', self::DIRECTION)
                ->where('idProposal', $proposal->idProposal)
                ->get();

            if ($existing->isNotEmpty()
                && $existing->every(fn (SponsorMatch $match) => $match->input_hash === $hash)) {
                return ['generated' => false, 'message' => null];
            }
        }

        $local = $candidates
            ->map(fn (array $candidate) => $this->localResult($candidate))
            ->keyBy('company_id')
            ->all();

        $aiResults = $this->analyseWithGemini($proposal, $candidates);
        $results = $this->mergeResults($local, $aiResults);

        $this->persist($proposal, $results, $hash);

        return [
            'generated' => $aiResults !== null,
            'message' => $aiResults === null
                ? 'Analisis AI sedang tidak tersedia. Rekomendasi dihitung dari profil dan campaign perusahaan.'
                : null,
        ];
    }

    /**
     * Kandidat hasil pre-filter, sudah diberi skor lokal dan diurutkan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function candidates(Proposal $proposal): Collection
    {
        $companies = User::query()
            ->where('role', 'sponsor')
            ->where('isActive', true)
            ->whereHas('companyProfile')
            ->with([
                'companyProfile.categories',
                'campaigns' => fn ($query) => $query
                    ->active()
                    ->with('kategori')
                    ->orderByDesc('periodStart'),
            ])
            ->get();

        $rows = $companies
            ->map(function (User $company) use ($proposal): ?array {
                $profile = $company->companyProfile;

                if (! $profile instanceof CompanyProfile || ! $profile->isComplete()) {
                    return null;
                }

                $activeCampaigns = $company->campaigns
                    ->filter(fn (Campaign $campaign) => $campaign->isActive())
                    ->values();

                $campaign = $this->pickCampaign($proposal, $activeCampaigns);

                $fundingMin = $profile->sponsorship_min ?? $campaign?->budgetMin;
                $fundingMax = $profile->sponsorship_max ?? $campaign?->budgetMax;

                if ($fundingMin === null && $fundingMax === null) {
                    $fundingMin = $activeCampaigns->min('budgetMin');
                    $fundingMax = $activeCampaigns->max('budgetMax');
                }

                $categories = $profile->categories
                    ->pluck('idKategori')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $categoryFit = in_array((int) $proposal->idKategori, $categories, true)
                    || $campaign !== null;

                $budgetFit = $this->budgetOverlaps(
                    (int) $proposal->budgetMin,
                    (int) $proposal->budgetMax,
                    $fundingMin,
                    $fundingMax,
                );

                $candidate = [
                    'company' => $company,
                    'profile' => $profile,
                    'categories' => $categories,
                    'campaigns' => $activeCampaigns,
                    'campaign' => $campaign,
                    'active_campaign_count' => $activeCampaigns->count(),
                    'funding_min' => $fundingMin,
                    'funding_max' => $fundingMax,
                    'category_fit' => $categoryFit,
                    'budget_fit' => $budgetFit,
                ];

                $candidate['local'] = $this->scoreCandidate($proposal, $candidate);

                return $candidate;
            })
            ->filter()
            ->values();

        // Pre-filter bertingkat: utamakan yang cocok kategori + anggaran.
        // Bila tidak ada, longgarkan SATU kriteria (kategori, lalu anggaran).
        // Tidak pernah mengirim seluruh perusahaan tanpa filter: bila tidak
        // ada yang lolos sama sekali, hasilnya kosong dan halaman menampilkan
        // kondisi "belum ada kandidat" — bukan rekomendasi karangan.
        $both = $rows->filter(fn (array $row) => $row['category_fit'] && $row['budget_fit']);
        $categoryOnly = $rows->filter(fn (array $row) => $row['category_fit']);
        $budgetOnly = $rows->filter(fn (array $row) => $row['budget_fit']);

        $pool = $both->isNotEmpty()
            ? $both
            : ($categoryOnly->isNotEmpty() ? $categoryOnly : $budgetOnly);

        if ($pool->isEmpty()) {
            return collect();
        }

        $limit = max(1, (int) config('sponsormatch.recommendations.max_candidates', 24));

        return $pool
            ->sortByDesc(fn (array $row) => $row['local']['score'])
            ->take($limit)
            ->values();
    }

    /**
     * Pilih campaign paling relevan untuk proposal.
     *
     * Campaign hanya dipakai bila kategorinya sama dengan kategori event
     * proposal. Bila tidak ada yang cocok, rekomendasi memakai profil.
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
     * Skor lokal deterministik (0-100).
     *
     * @param  array<string, mixed>  $candidate
     * @return array{score: int, reason: string, factors: array<int, string>}
     */
    private function scoreCandidate(Proposal $proposal, array $candidate): array
    {
        /** @var CompanyProfile $profile */
        $profile = $candidate['profile'];
        /** @var Campaign|null $campaign */
        $campaign = $candidate['campaign'];

        $factors = [];
        $score = 52;

        $categoryName = $proposal->kategori?->name ?? 'kategori event';

        if ($candidate['category_fit']) {
            $score += 20;
            $factors[] = 'Kategori event sesuai dengan minat sponsorship perusahaan.';
        } else {
            $factors[] = 'Kategori event belum tercantum pada minat perusahaan, perlu konfirmasi.';
        }

        if ($candidate['funding_min'] !== null && $candidate['funding_max'] !== null) {
            if ($candidate['budget_fit']) {
                $score += 12;
                $factors[] = 'Rentang dana perusahaan bersinggungan dengan kebutuhan proposal.';

                $overlap = min((int) $candidate['funding_max'], (int) $proposal->budgetMax)
                    - max((int) $candidate['funding_min'], (int) $proposal->budgetMin);
                $span = max(1, (int) $proposal->budgetMax - (int) $proposal->budgetMin);
                $score += (int) round(max(0, min(1, $overlap / $span)) * 8);
            } else {
                $score -= 10;
                $factors[] = 'Rentang dana perusahaan berada di luar kebutuhan proposal.';
            }
        } else {
            $factors[] = 'Rentang dana belum diisi pada profil perusahaan.';
        }

        $proposalKeywords = $this->keywords(implode(' ', array_filter([
            (string) $proposal->title,
            (string) $proposal->desctiption,
            (string) $proposal->target_audience,
            (string) $proposal->needType,
        ])));

        $industry = mb_strtolower((string) $profile->industry);

        if ($industry !== '' && $this->sharesKeyword($industry, $proposalKeywords)) {
            $score += 8;
            $factors[] = 'Bidang industri perusahaan relevan dengan isi proposal.';
        }

        if ($campaign !== null) {
            $campaignText = mb_strtolower(implode(' ', array_filter([
                (string) $campaign->title,
                (string) $campaign->description,
                (string) $campaign->purpose,
                (string) $campaign->target_audience,
                (string) $campaign->benefits,
            ])));

            if ($this->sharesKeyword($campaignText, $proposalKeywords)) {
                $score += 5;
                $factors[] = 'Tujuan campaign relevan dengan kebutuhan event.';
            }

            $factors[] = 'Perusahaan memiliki campaign aktif yang relevan.';
        } elseif ($candidate['active_campaign_count'] > 0) {
            $factors[] = 'Perusahaan memiliki campaign aktif lain di luar kategori proposal.';
        } else {
            $factors[] = 'Perusahaan dapat mendukung melalui profilnya meski belum membuka campaign.';
        }

        $score = max(20, min(98, $score));

        $range = $this->formatRange($candidate['funding_min'], $candidate['funding_max']);

        $reason = $campaign !== null
            ? sprintf(
                'Campaign %s selaras dengan %s dan rentang dana %s.',
                $campaign->title,
                $categoryName,
                $range,
            )
            : sprintf(
                'Profil perusahaan menunjukkan minat pada %s dengan rentang dana %s.',
                $categoryName,
                $range,
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
        /** @var User $company */
        $company = $candidate['company'];
        /** @var Campaign|null $campaign */
        $campaign = $candidate['campaign'];

        return [
            'company_id' => (int) $company->id,
            'company_name' => $company->companyProfile?->companyName ?? $company->name,
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
     * @return array<int, array<string, mixed>>|null Keyed by company_id.
     */
    private function analyseWithGemini(Proposal $proposal, Collection $candidates): ?array
    {
        if (! $this->gemini->enabled()) {
            return null;
        }

        $payload = $candidates->map(function (array $candidate) {
            /** @var User $company */
            $company = $candidate['company'];
            /** @var CompanyProfile $profile */
            $profile = $candidate['profile'];

            return [
                'company_id' => (int) $company->id,
                'company_name' => $profile->companyName ?? $company->name,
                'industry' => $profile->industry,
                'description' => Str::limit((string) $profile->description, 400),
                'event_types' => $profile->event_types,
                'target_audience' => $profile->target_audience,
                'interest_categories' => $profile->categories->pluck('name')->values()->all(),
                'funding_min' => $candidate['funding_min'],
                'funding_max' => $candidate['funding_max'],
                'has_active_campaign' => $candidate['active_campaign_count'] > 0,
                'active_campaign_count' => $candidate['active_campaign_count'],
                'campaigns' => $candidate['campaigns']->map(fn (Campaign $campaign) => [
                    'id' => (int) $campaign->idCampaign,
                    'title' => $campaign->title,
                    'description' => Str::limit((string) $campaign->description, 300),
                    'purpose' => Str::limit((string) $campaign->purpose, 250),
                    'target_audience' => $campaign->target_audience,
                    'budget_min' => $campaign->budgetMin,
                    'budget_max' => $campaign->budgetMax,
                    'support_type' => $campaign->supportType,
                    'benefits' => $campaign->benefits,
                    'period_start' => $campaign->periodStart?->toDateString(),
                    'period_end' => $campaign->periodEnd?->toDateString(),
                ])->values()->all(),
            ];
        })->values()->all();

        $proposalPayload = [
            'title' => $proposal->title,
            'description' => Str::limit((string) $proposal->desctiption, 800),
            'category' => $proposal->kategori?->name,
            'location' => $proposal->location,
            'target_audience' => $proposal->target_audience,
            'participant_estimate' => null,
            'budget_min' => (int) $proposal->budgetMin,
            'budget_max' => (int) $proposal->budgetMax,
            'need_type' => $proposal->needType,
            'benefits' => Str::limit((string) $proposal->benefits, 400),
            'start_date' => $proposal->start_date?->toDateString(),
            'end_date' => $proposal->end_date?->toDateString(),
        ];

        $prompt = <<<'PROMPT'
Nilai kecocokan antara satu proposal sponsorship event dan daftar perusahaan sponsor di bawah ini.

ATURAN WAJIB:
- Hanya gunakan data yang diberikan. Jangan menambah perusahaan atau campaign yang tidak ada.
- company_id harus persis salah satu company_id pada data kandidat.
- campaign_id hanya boleh salah satu id pada campaigns kandidat tersebut, atau null.
- score berupa bilangan bulat 0-100 (semakin tinggi semakin cocok).
- reason singkat dalam Bahasa Indonesia, maksimal 240 karakter.
- factors berisi maksimal 4 poin singkat pendukung kecocokan.

FORMAT BALASAN (JSON saja, tanpa penjelasan tambahan):
{"matches":[{"company_id":1,"campaign_id":null,"score":85,"reason":"...","factors":["...","..."]}]}

DATA PROPOSAL:
PROPOSAL_JSON

DATA KANDIDAT PERUSAHAAN:
CANDIDATES_JSON
PROMPT;

        $prompt = str_replace(
            ['PROPOSAL_JSON', 'CANDIDATES_JSON'],
            [
                (string) json_encode($proposalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

        $byCompany = [];

        foreach ($candidates as $candidate) {
            $byCompany[(int) $candidate['company']->id] = $candidate;
        }

        $validated = [];

        foreach ($response['matches'] as $match) {
            if (! is_array($match)) {
                continue;
            }

            $companyId = (int) ($match['company_id'] ?? 0);

            if (! isset($byCompany[$companyId])) {
                continue;
            }

            $score = (int) ($match['score'] ?? 0);
            $score = max(0, min(100, $score));

            $reason = isset($match['reason']) && is_string($match['reason'])
                ? Str::limit(trim($match['reason']), 300)
                : null;

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

            $allowedCampaigns = collect($byCompany[$companyId]['campaigns'])
                ->pluck('idCampaign')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($campaignId !== null && ! in_array($campaignId, $allowedCampaigns, true)) {
                $campaignId = null;
            }

            if ($reason === null || $reason === '') {
                continue;
            }

            $validated[$companyId] = [
                'campaign_id' => $campaignId,
                'score' => $score,
                'reason' => $reason,
                'factors' => array_slice($factors, 0, 4),
            ];
        }

        return $validated === [] ? null : $validated;
    }

    /**
     * Gabungkan hasil lokal dengan hasil AI yang sudah divalidasi.
     *
     * @param  array<int, array<string, mixed>>  $local
     * @param  array<int, array<string, mixed>>|null  $ai
     * @return array<int, array<string, mixed>>
     */
    private function mergeResults(array $local, ?array $ai): array
    {
        $results = [];

        foreach ($local as $companyId => $row) {
            if ($ai !== null && isset($ai[$companyId])) {
                $row['score'] = $ai[$companyId]['score'];
                $row['reason'] = $ai[$companyId]['reason'] !== ''
                    ? $ai[$companyId]['reason']
                    : $row['reason'];
                $row['factors'] = $ai[$companyId]['factors'] !== []
                    ? $ai[$companyId]['factors']
                    : $row['factors'];
                $row['campaign_id'] = $ai[$companyId]['campaign_id'] ?? $row['campaign_id'];
                $row['source'] = $row['campaign_id'] !== null ? 'campaign' : 'profile';
            }

            $results[] = $row;
        }

        usort($results, function (array $a, array $b) {
            return $b['score'] <=> $a['score']
                ?: strcmp((string) $a['company_name'], (string) $b['company_name']);
        });

        return $results;
    }

    /**
     * Simpan hasil ke tabel `matches` (idempoten per proposal).
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    private function persist(Proposal $proposal, array $results, string $hash): void
    {
        try {
            SponsorMatch::query()
                ->where('direction', self::DIRECTION)
                ->where('idProposal', $proposal->idProposal)
                ->delete();

            foreach ($results as $result) {
                try {
                    SponsorMatch::query()->create([
                        'idProposal' => $proposal->idProposal,
                        'idCampaign' => $result['campaign_id'],
                        'company_user_id' => $result['company_id'],
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
                    // Mis. index unik lama pada idCampaign di MySQL. Satu
                    // baris gagal tidak boleh menggagalkan seluruh daftar.
                    Log::warning('Gagal menyimpan satu hasil matching: '.$e->getMessage());
                }
            }
        } catch (Throwable $e) {
            Log::error('Gagal menyimpan hasil matching: '.$e->getMessage());
        }
    }

    /**
     * Hash input agar AI tidak dipanggil ulang bila data tidak berubah.
     *
     * @param  Collection<int, array<string, mixed>>  $candidates
     */
    private function inputHash(Proposal $proposal, Collection $candidates): string
    {
        return hash('sha256', (string) json_encode([
            'proposal' => [
                'id' => $proposal->idProposal,
                'updated' => optional($proposal->updateAt)->toDateTimeString(),
                'title' => $proposal->title,
                'category' => $proposal->idKategori,
                'budget' => [$proposal->budgetMin, $proposal->budgetMax],
            ],
            'model' => $this->gemini->model(),
            'candidates' => $candidates->map(function (array $candidate): array {
                /** @var CompanyProfile $profile */
                $profile = $candidate['profile'];
                /** @var Campaign|null $campaign */
                $campaign = $candidate['campaign'];

                return [
                    'company' => (int) $candidate['company']->id,
                    'profile' => [
                        'name' => $profile->companyName,
                        'industry' => $profile->industry,
                        'description' => $profile->description,
                        'target_audience' => $profile->target_audience,
                        'event_types' => $profile->event_types,
                        'sponsorship' => [$profile->sponsorship_min, $profile->sponsorship_max],
                        'categories' => $candidate['categories'],
                    ],
                    'campaign' => $campaign === null ? null : [
                        'id' => (int) $campaign->idCampaign,
                        'title' => $campaign->title,
                        'purpose' => $campaign->purpose,
                        'budget' => [$campaign->budgetMin, $campaign->budgetMax],
                        'updated' => optional($campaign->updateAt)->toDateTimeString(),
                    ],
                    'active_campaigns' => $candidate['active_campaign_count'],
                ];
            })->values()->all(),
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
     * Kata kunci sederhana (>= 4 huruf, unik).
     *
     * @return array<int, string>
     */
    private function keywords(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/i', mb_strtolower($text)) ?: [];

        $stop = [
            'yang', 'untuk', 'dengan', 'dari', 'pada', 'akan', 'dalam', 'adalah',
            'kami', 'kita', 'dan', 'atau', 'ini', 'itu', 'para', 'para', 'the',
            'and', 'for', 'with', 'event', 'acara', 'kegiatan', 'proposal',
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
