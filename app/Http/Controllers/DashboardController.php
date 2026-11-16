<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Offer;
use App\Models\Proposal;
use App\Models\SponsorMatch;
use App\Models\TrackingPipeline;
use App\Models\User;
use App\Services\CompanyMatchingService;
use App\Services\CompanyPresenter;
use App\Services\ProposalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProposalService $proposalService,
        private readonly CompanyMatchingService $companyMatching,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Dashboard perusahaan memakai data nyata: campaign miliknya dan
        // proposal yang masuk. Campaign TIDAK wajib — perusahaan tanpa
        // campaign tetap bisa menerima proposal dari EO.
        if (! $user->isEventOrganizer()) {
            abort_unless($user->isCompany(), 403);

            $campaigns = Campaign::query()
                ->where('idUser', $user->id)
                ->visible()
                ->with('kategori')
                ->orderByDesc('createdAt')
                ->get();

            $trackings = TrackingPipeline::query()
                ->where('company_user_id', $user->id);

            $proposalsReceived = (clone $trackings)->count();
            $activeCollaborations = (clone $trackings)
                ->whereIn('stage', ['negotiating', 'deal', 'waiting_report'])
                ->count();
            $deals = (clone $trackings)
                ->whereIn('stage', ['deal', 'done'])
                ->count();

            $pendingOffers = Offer::query()
                ->where('status', 'pending')
                ->whereHas('tracking', fn ($query) => $query->where('company_user_id', $user->id))
                ->count();

            $recommendationAi = $this->companyMatching->ensure($user);

            return Inertia::render('dashboard/Index', [
                'companyDashboard' => [
                    'stats' => [
                        'campaign_total' => $campaigns->count(),
                        'campaign_active' => $campaigns->filter(fn (Campaign $campaign) => $campaign->isActive())->count(),
                        'proposals_received' => $proposalsReceived,
                        'active_collaborations' => $activeCollaborations,
                        'deals' => $deals,
                        'pending_offers' => $pendingOffers,
                    ],
                    'campaigns' => $campaigns
                        ->take(3)
                        ->map(fn (Campaign $campaign) => CompanyPresenter::campaign($campaign, forOwner: true))
                        ->values(),
                    'recommendations' => $this->recommendationHighlights($user),
                    'recommendation_ai' => $recommendationAi,
                    'has_campaigns' => $campaigns->isNotEmpty(),
                    'has_profile' => $user->companyProfile()->exists(),
                    'optional_message' => 'Campaign bersifat opsional. Perusahaan tetap dapat menerima proposal dari EO meski belum membuat campaign.',
                ],
            ]);
        }

        $pipeline = $this->proposalService->pipelineCounts($user);

        // Perubahan dibanding periode sebelumnya (30 hari vs 30 hari sebelumnya).
        $periode = collect($this->proposalService->stats($user, 30))->keyBy('key');
        $selisih = fn (string $key): int => (int) ($periode[$key]['value'] - $periode[$key]['previous']);

        // Total proposal yang pernah dikirim = semua tahap setelah Draft/Siap dikirim.
        $terkirim = $pipeline['dikirim'] + $pipeline['ditinjau']
            + $pipeline['negosiasi'] + $pipeline['disetujui'] + $pipeline['ditolak'];

        // Dana yang BENAR-BENAR dikonfirmasi = offer berstatus accepted.
        $pendanaan = (int) Offer::query()
            ->where('status', 'accepted')
            ->whereHas('tracking.proposal', fn ($q) => $q->where('idUser', $user->id))
            ->sum('amount');

        // Target = kebutuhan dana maksimum semua proposal non-draft (lihat pertanyaan 1).
        $targetPendanaan = (int) Proposal::query()
            ->where('idUser', $user->id)
            ->where('status', '!=', 'draft')
            ->sum('budgetMax');

        $followUpData = $this->proposalService->followUps($user);

        return Inertia::render('dashboard/Index', [
            'dashboardEo' => [
                'stats' => [
                    'terkirim' => ['value' => $terkirim, 'diff' => $selisih('sent')],
                    'disetujui' => ['value' => $pipeline['disetujui'], 'diff' => $selisih('approved')],
                    'pendanaan' => $pendanaan,
                    'target_pendanaan' => $targetPendanaan,
                    'persentase_pendanaan' => $targetPendanaan > 0
                        ? (int) round($pendanaan / $targetPendanaan * 100)
                        : 0,
                    'perlu_tindak_lanjut' => $followUpData['total'],
                ],
                'pipeline' => $pipeline,
                'followUps' => collect($followUpData['items'])
                    ->take(3)
                    ->map(fn ($item) => [
                        'id' => $item['id'],
                        'proposalId' => $item['proposalId'],
                        'title' => $item['actionLabel'],
                        'description' => $item['description'],
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * Tiga rekomendasi proposal teratas untuk kartu dashboard perusahaan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recommendationHighlights(User $company): array
    {
        $matches = SponsorMatch::query()
            ->where('direction', SponsorMatch::DIRECTION_COMPANY_TO_EO)
            ->where('company_user_id', $company->id)
            ->orderByDesc('score')
            ->orderBy('idMatches')
            ->limit(3)
            ->get();

        if ($matches->isEmpty()) {
            return [];
        }

        $proposals = Proposal::query()
            ->whereIn('idProposal', $matches->pluck('idProposal')->unique()->all())
            ->whereNull('deleteAt')
            ->with('kategori')
            ->get()
            ->keyBy('idProposal');

        return $matches
            ->map(function (SponsorMatch $match) use ($proposals): ?array {
                $proposal = $proposals->get((int) $match->idProposal);

                if (! $proposal) {
                    return null;
                }

                return [
                    'proposal_id' => (int) $proposal->idProposal,
                    'title' => $proposal->title,
                    'match' => (int) $match->score,
                    'category' => $proposal->kategori?->name ?? 'Event',
                    'date' => $this->dateRange($proposal->start_date, $proposal->end_date),
                    'location' => $proposal->location,
                    'budget' => $this->moneyRange((int) $proposal->budgetMin, (int) $proposal->budgetMax),
                    'description' => $proposal->desctiption,
                    'href' => route('company.recommendations.index'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function dateRange(mixed $start, mixed $end): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        if ($start === null) {
            return 'Tanggal belum ditentukan';
        }

        $format = fn ($date) => $date->format('d').' '.$months[(int) $date->format('n')];

        if ($end === null || $start->toDateString() === $end->toDateString()) {
            return $format($start).' '.$start->format('Y');
        }

        return $format($start).' - '.$format($end).' '.$end->format('Y');
    }

    private function moneyRange(int $min, int $max): string
    {
        $format = fn (int $value): string => 'Rp '.number_format($value, 0, ',', '.');

        return $min === $max
            ? $format($min)
            : $format($min).' - '.$format($max);
    }
}
