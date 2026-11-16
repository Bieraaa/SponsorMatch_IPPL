<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\StoreCampaignRequest;
use App\Http\Requests\Company\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\Kategori;
use App\Models\TrackingPipeline;
use App\Models\User;
use App\Services\CompanyPresenter;
use App\Services\PdfExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengelolaan campaign milik perusahaan.
 *
 * Campaign bersifat OPSIONAL. Perusahaan tanpa campaign tetap dapat
 * menerima proposal karena profil perusahaan sudah cukup untuk rekomendasi.
 *
 * Otorisasi: setiap aksi memeriksa `campaign.idUser === user.id`, sehingga
 * perusahaan tidak bisa mengubah campaign milik perusahaan lain.
 */
class CompanyCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->isCompany(), 403, 'Halaman ini khusus akun perusahaan.');

        $campaigns = Campaign::query()
            ->where('idUser', $user->id)
            ->visible()
            ->with('kategori')
            ->orderByDesc('createdAt')
            ->get();

        $counts = TrackingPipeline::query()
            ->selectRaw('idCampaign, count(*) as aggregate')
            ->whereIn('idCampaign', $campaigns->pluck('idCampaign')->all())
            ->groupBy('idCampaign')
            ->pluck('aggregate', 'idCampaign');

        $mapped = $campaigns->map(fn (Campaign $campaign) => CompanyPresenter::campaign(
            $campaign,
            forOwner: true,
            proposalCount: (int) ($counts[$campaign->idCampaign] ?? 0),
        ))->values();

        $banner = config('sponsormatch.campaign_banner');

        return Inertia::render('company/campaigns/Index', [
            'campaigns' => $mapped,
            'categories' => Kategori::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Kategori $kategori) => [
                    'id' => (int) $kategori->idKategori,
                    'name' => $kategori->name,
                    'description' => $kategori->deskripsiKategori,
                ])
                ->values(),
            'stats' => [
                'total' => $mapped->count(),
                'active' => $mapped->where('is_active', true)->count(),
                'draft' => $mapped->where('status', 'draft')->count(),
                'closed' => $mapped->where('status', 'close')->count(),
                'proposals' => (int) $counts->sum(),
            ],
            'bannerConfig' => [
                'max_kb' => $banner['max_kb'],
                'extensions' => $banner['extensions'],
            ],
        ]);
    }

    /**
     * Ekstrak informasi campaign dari PDF menggunakan Gemini.
     *
     * Endpoint ini TIDAK menyimpan campaign. Hasil ekstraksi hanya
     * dikembalikan ke browser untuk mengisi form; penyimpanan tetap
     * terjadi saat form disubmit ke store()/update().
     */
    public function extract(Request $request, PdfExtractionService $extractor): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->isCompany(), 403, 'Halaman ini khusus akun perusahaan.');

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'max:'.(int) config('sponsormatch.pdf.max_kb', 5120),
            ],
        ], [
            'file.required' => 'Pilih file PDF campaign terlebih dahulu.',
            'file.mimes' => 'File harus berformat PDF.',
            'file.max' => 'Ukuran PDF maksimal '.(int) config('sponsormatch.pdf.max_kb', 5120).' KB.',
        ]);

        try {
            $result = $extractor->extractCampaign($request->file('file')->getRealPath());
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'AI_UNAVAILABLE') {
                return response()->json([
                    'message' => 'Layanan AI sedang tidak tersedia. Isi form secara manual atau coba lagi beberapa saat.',
                ], 503);
            }

            return response()->json([
                'message' => 'Dokumen PDF tidak bisa dibaca. Pastikan file tidak rusak atau terkunci.',
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Dokumen PDF tidak bisa dibaca. Pastikan file tidak rusak atau terkunci.',
            ], 422);
        }

        if ($result['fields'] === []) {
            return response()->json([
                'message' => 'Tidak ada informasi campaign yang dikenali dari PDF ini. Kamu bisa mengisi form secara manual.',
            ], 422);
        }

        return response()->json($result);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $status = $data['status'] ?? 'draft';

        if ($status === 'active' && $this->hasEnded($data['periodEnd'])) {
            return back()->withErrors([
                'status' => 'Campaign tidak bisa diaktifkan karena tanggal berakhir sudah lewat.',
            ]);
        }

        $campaign = new Campaign;
        $campaign->fill($this->attributes($data, $request));
        $campaign->idUser = $user->id;
        $campaign->status = $status;
        $campaign->file_path = $campaign->banner_path ?? '';
        $campaign->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Campaign berhasil dibuat.',
        ]);

        return to_route('company.campaigns.index');
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request->user(), $campaign);

        $data = $request->validated();

        if (($data['status'] ?? null) === 'active') {
            $periodEnd = $data['periodEnd'] ?? $campaign->periodEnd?->toDateString();

            if ($periodEnd !== null && $this->hasEnded($periodEnd)) {
                return back()->withErrors([
                    'status' => 'Campaign tidak bisa diaktifkan karena tanggal berakhir sudah lewat.',
                ]);
            }
        }

        $campaign->fill($this->attributes($data, $request));
        $campaign->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Campaign berhasil diperbarui.',
        ]);

        return to_route('company.campaigns.index');
    }

    /**
     * Aktifkan / nonaktifkan / akhiri campaign.
     */
    public function updateStatus(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request->user(), $campaign);

        $validated = $request->validate([
            'status' => ['required', 'in:draft,active,close'],
        ]);

        if ($validated['status'] === 'active') {
            if ($campaign->periodEnd === null || $this->hasEnded($campaign->periodEnd->toDateString())) {
                return back()->withErrors([
                    'status' => 'Campaign tidak bisa diaktifkan karena tanggal berakhir sudah lewat.',
                ]);
            }

            $campaign->deleteAt = null;
        }

        $campaign->status = $validated['status'];
        $campaign->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match ($validated['status']) {
                'active' => 'Campaign berhasil diaktifkan.',
                'close' => 'Campaign telah diakhiri.',
                default => 'Campaign dinonaktifkan menjadi draft.',
            },
        ]);

        return back();
    }

    /**
     * Akhiri campaign tanpa menghapus data historis.
     */
    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request->user(), $campaign);

        $campaign->deleteAt = now();
        $campaign->status = 'close';
        $campaign->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Campaign telah diakhiri dan tidak lagi tampil sebagai peluang aktif.',
        ]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, Request $request): array
    {
        $attributes = [
            'idKategori' => $data['idKategori'],
            'title' => $data['title'],
            'description' => $data['description'],
            'purpose' => $data['purpose'] ?? null,
            'target_audience' => $data['target_audience'] ?? '',
            'budgetMin' => $data['budgetMin'],
            'budgetMax' => $data['budgetMax'],
            'supportType' => $data['supportType'] ?? 'Dana',
            'benefits' => $data['benefits'] ?? null,
            'estimatedParticipant' => $data['estimatedParticipant'] ?? 0,
            'periodStart' => $data['periodStart'],
            'periodEnd' => $data['periodEnd'],
        ];

        // Saat update, hanya sertakan field yang memang dikirim.
        if ($request->isMethod('patch') || $request->isMethod('put')) {
            $attributes = array_intersect_key($attributes, $data);
        }

        if ($request->hasFile('banner')) {
            $directory = config('sponsormatch.campaign_banner.directory', 'campaign-banners');
            $oldPath = $request->isMethod('patch') || $request->isMethod('put')
                ? $request->route('campaign')?->getRawOriginal('banner_path')
                : null;

            $attributes['banner_path'] = $request->file('banner')->store($directory, 'public');

            if (filled($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        return $attributes;
    }

    private function hasEnded(string $periodEnd): bool
    {
        return now()->startOfDay()->greaterThan(
            Carbon::parse($periodEnd)->startOfDay(),
        );
    }

    private function authorizeCampaign(?User $user, Campaign $campaign): void
    {
        abort_unless($user?->isCompany(), 403);
        abort_unless((int) $campaign->idUser === (int) $user->id, 403, 'Anda tidak berhak mengubah campaign ini.');
    }
}
