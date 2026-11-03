<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Proposal;

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
}