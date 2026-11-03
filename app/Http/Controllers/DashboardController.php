<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Proposal;
use App\Models\TrackingPipeline;
use App\Services\ProposalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProposalService $proposalService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Pastikan hanya EO yang memakai data dashboard ini.
        if (! $user->isEventOrganizer()) {
            return Inertia::render('dashboard/Index');
        }

        $proposalIds = Proposal::query()
            ->where('idUser', $user->id)
            ->select('idProposal');

        // Total proposal unik yang pernah dikirim ke sponsor.
        $terkirim = TrackingPipeline::query()
            ->whereIn('idProposal', $proposalIds)
            ->distinct('idProposal')
            ->count('idProposal');

        // Tracking yang sudah disetujui.
        $disetujui = TrackingPipeline::query()
            ->whereIn('idProposal', $proposalIds)
            ->where(function ($query) {
                $query->whereIn('stage', ['deal', 'done'])
                    ->orWhereHas('offers', function ($offerQuery) {
                        $offerQuery->where('status', 'accepted');
                    });
            })
            ->count();

        // Total nominal penawaran yang diterima sponsor.
        $pendanaan = Offer::query()
            ->where('status', 'accepted')
            ->whereHas('tracking.proposal', function ($query) use ($user) {
                $query->where('idUser', $user->id);
            })
            ->sum('amount');

        // Total target anggaran dari proposal yang sudah disetujui.
        $targetPendanaan = Proposal::query()
            ->where('idUser', $user->id)
            ->whereHas('trackings', function ($query) {
                $query->where(function ($trackingQuery) {
                    $trackingQuery->whereIn('stage', ['deal', 'done'])
                        ->orWhereHas('offers', function ($offerQuery) {
                            $offerQuery->where('status', 'accepted');
                        });
                });
            })
            ->sum('budgetMax');

        $persentasePendanaan = $targetPendanaan > 0
            ? round(($pendanaan / $targetPendanaan) * 100, 1)
            : 0;

        // Data pipeline.
        $pipeline = [
            'draft' => Proposal::query()
                ->where('idUser', $user->id)
                ->where('status', 'draft')
                ->count(),

            'dikirim' => TrackingPipeline::query()
                ->whereIn('idProposal', $proposalIds)
                ->where('stage', 'sent')
                ->count(),

            'ditinjau' => TrackingPipeline::query()
                ->whereIn('idProposal', $proposalIds)
                ->where('stage', 'viewed')
                ->count(),

            'negosiasi' => TrackingPipeline::query()
                ->whereIn('idProposal', $proposalIds)
                ->where('stage', 'negotiating')
                ->count(),

            'disetujui' => $disetujui,
        ];

        // Gunakan logika tindak lanjut yang sudah tersedia.
        $followUpData = $this->proposalService->followUps($user);

        $followUps = collect($followUpData['items'])
            ->map(function ($item) {
                $days = $item['sentAt']
                    ? now()->diffInDays($item['sentAt'])
                    : null;

                return [
                    'id' => $item['id'],
                    'proposal' => $item['proposalTitle'],
                    'company' => $item['companyName'],
                    'stage' => $item['action'],
                    'days' => $days,
                ];
            })
            ->values();

        return Inertia::render('dashboard/Index', [
            'dashboardEo' => [
                'stats' => [
                    'terkirim' => $terkirim,
                    'disetujui' => $disetujui,
                    'pendanaan' => (int) $pendanaan,
                    'target_pendanaan' => (int) $targetPendanaan,
                    'persentase_pendanaan' => $persentasePendanaan,
                    'perlu_tindak_lanjut' => $followUpData['total'],
                ],
                'pipeline' => $pipeline,
                'followUps' => $followUps,
            ],
        ]);
    }
}
