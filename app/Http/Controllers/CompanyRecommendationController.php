<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Proposal;
use App\Models\SponsorMatch;
use App\Models\TrackingPipeline;
use App\Models\User;
use App\Services\CompanyMatchingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman "Rekomendasi Event" untuk perusahaan.
 *
 * Alur:
 *   profil + campaign perusahaan → pre-filter proposal (CompanyMatchingService)
 *   → Gemini → validasi → simpan di `matches` (direction company_to_eo)
 *   → tampilkan 6 per halaman.
 *
 * Tombol "Ajukan Penawaran" memakai alur yang SUDAH ADA: membuat/menemukan
 * baris `trackingpipeline` lalu membuka halaman penawaran sponsor. Tidak ada
 * jalur penawaran baru yang menduplikasi fitur lama.
 */
class CompanyRecommendationController extends Controller
{
    public function __construct(
        private readonly CompanyMatchingService $matching,
    ) {}

    public function index(Request $request): Response
    {
        $company = $this->company($request);

        $company->loadMissing(['companyProfile.categories', 'campaigns.kategori']);

        $ai = $this->matching->ensure($company);

        $items = $this->buildItems($company);

        $perPage = max(1, (int) config('sponsormatch.recommendations.per_page', 6));
        $page = max(1, (int) $request->query('page', 1));

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => route('company.recommendations.index'),
            ],
        );

        $profile = $company->companyProfile;

        return Inertia::render('company/recommendations/Index', [
            'recommendations' => [
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'ai' => $ai,
            'profile' => [
                'has_profile' => $profile !== null,
                'is_complete' => $profile?->isComplete() ?? false,
                'has_categories' => $profile?->categories->isNotEmpty() ?? false,
                'has_campaigns' => $company->campaigns->isNotEmpty(),
            ],
        ]);
    }

    /**
     * Paksa analisis ulang (mis. profil/campaign baru diperbarui).
     */
    public function refresh(Request $request): RedirectResponse
    {
        $company = $this->company($request);

        $result = $this->matching->ensure($company, force: true);

        Inertia::flash('toast', [
            'type' => $result['generated'] ? 'success' : 'error',
            'message' => $result['generated']
                ? 'Rekomendasi proposal berhasil diperbarui dengan analisis AI.'
                : ($result['message'] ?? 'Rekomendasi proposal berhasil diperbarui.'),
        ]);

        return back();
    }

    /**
     * Ajukan penawaran atas proposal yang direkomendasikan.
     *
     * Langkah ini hanya menyiapkan `trackingpipeline` (initiatedBy =
     * Perusahaan). Pengajuan penawaran (nominal, jenis dukungan, pesan)
     * tetap dilakukan di halaman sponsor/proposals/{tracking} yang sudah
     * tersedia, sehingga validasi & status pipeline tidak diduplikasi.
     */
    public function interest(Request $request, Proposal $proposal): RedirectResponse
    {
        $company = $this->company($request);

        $recommended = SponsorMatch::query()
            ->where('direction', SponsorMatch::DIRECTION_COMPANY_TO_EO)
            ->where('company_user_id', $company->id)
            ->where('idProposal', $proposal->idProposal)
            ->exists();

        abort_unless($recommended, 403, 'Proposal ini bukan bagian dari rekomendasi Anda.');

        abort_unless($proposal->status === 'published' && $proposal->deleteAt === null, 404);

        $validated = $request->validate([
            'campaign_id' => ['nullable', 'integer', 'exists:campaign,idCampaign'],
        ]);

        $campaign = null;

        if (! empty($validated['campaign_id'])) {
            $campaign = Campaign::query()
                ->where('idUser', $company->id)
                ->where('idCampaign', (int) $validated['campaign_id'])
                ->first();

            if ($campaign === null || ! $campaign->isActive()) {
                return back()->withErrors([
                    'campaign_id' => 'Campaign tidak ditemukan, bukan milik Anda, atau sudah tidak aktif.',
                ]);
            }
        }

        $tracking = DB::transaction(function () use ($proposal, $company, $campaign): TrackingPipeline {
            $tracking = TrackingPipeline::firstOrCreate(
                [
                    'idProposal' => $proposal->idProposal,
                    'company_user_id' => $company->id,
                ],
                [
                    'idCampaign' => $campaign?->idCampaign,
                    'stage' => 'sent',
                    'initiatedBy' => 'Perusahaan',
                    'message' => null,
                ],
            );

            if (! $tracking->wasRecentlyCreated && $campaign !== null && $tracking->idCampaign === null) {
                $tracking->update(['idCampaign' => $campaign->idCampaign]);
            }

            return $tracking;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Minat Anda dicatat. Lengkapi detail penawaran pada halaman berikut.',
        ]);

        return to_route('sponsor.proposals.show', $tracking);
    }

    /**
     * Susun kartu rekomendasi dari tabel `matches`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildItems(User $company): Collection
    {
        $matches = SponsorMatch::query()
            ->where('direction', SponsorMatch::DIRECTION_COMPANY_TO_EO)
            ->where('company_user_id', $company->id)
            ->orderByDesc('score')
            ->orderBy('idMatches')
            ->get();

        if ($matches->isEmpty()) {
            return collect();
        }

        $proposals = Proposal::query()
            ->whereIn('idProposal', $matches->pluck('idProposal')->unique()->all())
            ->whereNull('deleteAt')
            ->with(['kategori', 'user.eoProfile'])
            ->get()
            ->keyBy('idProposal');

        $campaigns = Campaign::query()
            ->where('idUser', $company->id)
            ->whereIn('idCampaign', $matches->pluck('idCampaign')->filter()->unique()->all())
            ->with('kategori')
            ->get()
            ->keyBy('idCampaign');

        $trackings = TrackingPipeline::query()
            ->where('company_user_id', $company->id)
            ->whereIn('idProposal', $proposals->keys()->all())
            ->get()
            ->keyBy('idProposal');

        return $matches
            ->map(function (SponsorMatch $match) use ($proposals, $campaigns, $trackings): ?array {
                /** @var Proposal|null $proposal */
                $proposal = $proposals->get((int) $match->idProposal);

                if (! $proposal) {
                    return null;
                }

                /** @var Campaign|null $campaign */
                $campaign = $campaigns->get((int) $match->idCampaign);
                $tracking = $trackings->get((int) $proposal->idProposal);

                $source = $campaign !== null ? 'campaign' : 'profile';

                return [
                    'proposal_id' => (int) $proposal->idProposal,
                    'title' => $proposal->title,
                    'category' => $proposal->kategori?->name,
                    'description' => $proposal->desctiption,
                    'eo_name' => $proposal->user?->eoProfile?->eo_name ?? $proposal->user?->name,
                    'location' => $proposal->location,
                    'start_date' => $proposal->start_date?->toDateString(),
                    'end_date' => $proposal->end_date?->toDateString(),
                    'budget_min' => (int) $proposal->budgetMin,
                    'budget_max' => (int) $proposal->budgetMax,
                    'need_type' => $proposal->needType,
                    'benefits' => $proposal->benefits,
                    'score' => (int) $match->score,
                    'source' => $source,
                    'source_label' => $source === 'campaign' ? 'Berdasarkan Campaign' : 'Berdasarkan Profil',
                    'reason' => $match->reason,
                    'factors' => $match->factors ?? [],
                    'campaign' => $campaign !== null
                        ? [
                            'id' => (int) $campaign->idCampaign,
                            'title' => $campaign->title,
                            'purpose' => $campaign->purpose,
                            'budget_min' => (int) $campaign->budgetMin,
                            'budget_max' => (int) $campaign->budgetMax,
                        ]
                        : null,
                    'already_contacted' => $tracking !== null,
                    'tracking_id' => $tracking?->idTracking,
                ];
            })
            ->filter()
            ->values();
    }

    private function company(Request $request): User
    {
        $user = $request->user();

        abort_unless($user && $user->isCompany(), 403, 'Halaman ini khusus akun perusahaan.');

        return $user;
    }
}
