<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Proposal;
use App\Models\SponsorMatch;
use App\Models\TrackingPipeline;
use App\Models\User;
use App\Services\CompanyPresenter;
use App\Services\SponsorMatchingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman "Rekomendasi Sponsor" untuk Event Organizer.
 *
 * Alur:
 *   proposal EO → pre-filter (SponsorMatchingService) → Gemini →
 *   validasi → simpan di `matches` → tampilkan 6 per halaman.
 *
 * Halaman TIDAK memuat seluruh hasil ke frontend: pagination dilakukan
 * di server (LengthAwarePaginator) dan hanya 6 kartu yang dikirim.
 */
class SponsorRecommendationController extends Controller
{
    public function __construct(
        private readonly SponsorMatchingService $matching,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->isEventOrganizer(), 403, 'Halaman ini khusus Event Organizer.');

        $proposals = Proposal::query()
            ->where('idUser', $user->id)
            ->whereNull('deleteAt')
            ->with('kategori')
            ->orderByDesc('idProposal')
            ->get();

        $proposalOptions = $proposals->map(fn (Proposal $proposal) => [
            'id' => (int) $proposal->idProposal,
            'title' => $proposal->title,
            'kategori' => $proposal->kategori?->name,
            'status' => $proposal->status,
            'budget_min' => (int) $proposal->budgetMin,
            'budget_max' => (int) $proposal->budgetMax,
        ])->values();

        $requestedId = (int) $request->query('proposal', 0);
        $proposal = $proposals->firstWhere('idProposal', $requestedId) ?? $proposals->first();

        $ai = $proposal !== null
            ? $this->matching->ensure($proposal)
            : ['generated' => false, 'message' => null];

        $items = $proposal !== null ? $this->buildItems($proposal) : collect();

        $perPage = max(1, (int) config('sponsormatch.recommendations.per_page', 6));
        $page = max(1, (int) $request->query('page', 1));

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => route('recommendations.index'),
                'query' => array_filter([
                    'proposal' => $proposal?->idProposal,
                ]),
            ],
        );

        return Inertia::render('recommendations/Index', [
            'proposals' => $proposalOptions,
            'selectedProposalId' => $proposal?->idProposal,
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
            'latestPosts' => $this->latestPosts(),
        ]);
    }

    /**
     * Paksa analisis ulang (mis. setelah profil perusahaan diperbarui).
     */
    public function refresh(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isEventOrganizer(), 403);

        $validated = $request->validate([
            'proposal' => ['required', 'integer', 'exists:proposal,idProposal'],
        ]);

        $proposal = Proposal::query()
            ->where('idUser', $user->id)
            ->where('idProposal', $validated['proposal'])
            ->firstOrFail();

        $result = $this->matching->ensure($proposal, force: true);

        Inertia::flash('toast', [
            'type' => $result['generated'] ? 'success' : 'error',
            'message' => $result['generated']
                ? 'Rekomendasi berhasil diperbarui dengan analisis AI.'
                : ($result['message'] ?? 'Rekomendasi berhasil diperbarui.'),
        ]);

        return back();
    }

    /**
     * Susun kartu rekomendasi dari tabel `matches`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildItems(Proposal $proposal): Collection
    {
        $matches = SponsorMatch::query()
            ->where('direction', SponsorMatch::DIRECTION_EO_TO_COMPANY)
            ->where('idProposal', $proposal->idProposal)
            ->orderByDesc('score')
            ->orderBy('idMatches')
            ->get();

        if ($matches->isEmpty()) {
            return collect();
        }

        $companies = User::query()
            ->whereIn('id', $matches->pluck('company_user_id')->unique()->all())
            ->where('role', 'sponsor')
            ->where('isActive', true)
            ->with(['companyProfile.categories'])
            ->get()
            ->keyBy('id');

        $campaigns = Campaign::query()
            ->whereIn('idUser', $companies->keys()->all())
            ->active()
            ->with('kategori')
            ->orderByDesc('periodStart')
            ->get()
            ->groupBy('idUser');

        $sentTo = TrackingPipeline::query()
            ->where('idProposal', $proposal->idProposal)
            ->pluck('company_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $matches
            ->map(function (SponsorMatch $match) use ($proposal, $companies, $campaigns, $sentTo): ?array {
                $company = $companies->get((int) $match->company_user_id);

                if (! $company || ! $company->companyProfile) {
                    return null;
                }

                $profile = $company->companyProfile;

                /** @var Collection<int, Campaign> $activeCampaigns */
                $activeCampaigns = $campaigns->get($company->id, collect());

                $campaign = $activeCampaigns->first(
                    fn (Campaign $item) => (int) $item->idCampaign === (int) $match->idCampaign,
                );

                $source = $campaign !== null ? 'campaign' : 'profile';

                $fundingMin = $profile->sponsorship_min ?? $campaign?->budgetMin;
                $fundingMax = $profile->sponsorship_max ?? $campaign?->budgetMax;

                $companyName = $profile->companyName ?? $company->name;

                return [
                    'company_id' => (int) $company->id,
                    'company_name' => $companyName,
                    'company_slug' => Str::slug($companyName),
                    'company_url' => route('companies.show', $company->id),
                    'logo_url' => $profile->logoUrl(),
                    'industry' => $profile->industry,
                    'description' => $profile->description,
                    'rating' => $company->rating,
                    'score' => (int) $match->score,
                    'source' => $source,
                    'source_label' => $source === 'campaign'
                        ? 'Berdasarkan Campaign'
                        : 'Berdasarkan Profil',
                    'funding_min' => $fundingMin,
                    'funding_max' => $fundingMax,
                    'reason' => $match->reason,
                    'factors' => $match->factors ?? [],
                    'campaign' => $campaign !== null
                        ? CompanyPresenter::campaign($campaign)
                        : null,
                    'campaign_options' => $activeCampaigns
                        ->map(fn (Campaign $item) => [
                            'id' => (int) $item->idCampaign,
                            'title' => $item->title,
                            'budget_min' => (int) $item->budgetMin,
                            'budget_max' => (int) $item->budgetMax,
                            'period_end' => $item->periodEnd?->toDateString(),
                        ])
                        ->values()
                        ->all(),
                    'active_campaign_count' => $activeCampaigns->count(),
                    'already_sent' => in_array((int) $company->id, $sentTo, true),
                    'proposal_id' => (int) $proposal->idProposal,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Postingan terbaru untuk panel kanan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function latestPosts(): array
    {
        return Post::query()
            ->whereNull('deleteAt')
            ->with(['user.companyProfile'])
            ->orderByDesc('createdAt')
            ->limit(4)
            ->get()
            ->map(fn (Post $post) => CompanyPresenter::post($post))
            ->values()
            ->all();
    }
}
