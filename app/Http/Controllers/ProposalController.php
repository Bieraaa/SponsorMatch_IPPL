<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Models\Campaign;
use App\Models\Kategori;
use App\Models\Proposal;
use App\Models\TrackingPipeline;
use App\Models\User;
use App\Services\PdfExtractionService;
use App\Services\ProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman "Proposal Saya" untuk Event Organizer.
 *
 * Semua data diambil dari tabel nyata melalui ProposalService — tidak ada
 * angka dummy. Query string yang didukung:
 *   ?search=...    → cari judul/lokasi/kategori/nama sponsor
 *   ?status=...    → tab status (draft|waiting|sent|approved|rejected)
 *   ?periode=7|30|90 → periode grafik
 *   ?selected=ID   → proposal yang ditampilkan pada panel detail
 */
class ProposalController extends Controller
{
    /** Jumlah baris per halaman pada tabel proposal. */
    private const PER_PAGE = 10;

    /** Periode grafik yang diizinkan. */
    private const PERIODS = [7, 30, 90];

    public function __construct(private readonly ProposalService $proposals) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $search = trim((string) $request->query('search', ''));
        $status = ProposalStatus::tryFromRequest($request->query('status'));
        $periode = (int) $request->query('periode', 30);
        $perPage = (int) $request->query('per_page', self::PER_PAGE);

        if (! in_array($periode, self::PERIODS, true)) {
            $periode = 30;
        }

        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : self::PER_PAGE;

        $query = $this->proposals->baseQuery($user, $search);

        // Nomor urut proposal milik user ini (1, 2, 3, ...), dihitung dari
        // SELURUH proposalnya — bukan dari hasil filter/pagination — supaya
        // nomor tetap sama walau tabel sedang dicari atau difilter status.
        $numbers = $this->proposals->numbersFor($user);

        if ($status !== null) {
            $this->proposals->filterByStatus($query, $status);
        }

        $kategoriId = $request->query('kategori');

        if ($kategoriId) {
            $query->where('proposal.idKategori', (int) $kategoriId);
        }

        $paginator = $query
            ->select('proposal.*')
            ->selectRaw($this->proposals->statusSql().' as proposal_status')
            ->orderByDesc('proposal.createdAt')
            ->orderByDesc('proposal.idProposal')
            ->paginate($perPage)
            ->withQueryString();

        $rows = collect($paginator->items())
            ->map(fn (Proposal $proposal) => $this->proposals->row($proposal, $numbers[$proposal->idProposal] ?? 0))
            ->values();

        $selectedId = (int) $request->query('selected', 0);
        $selectedProposal = null;

        if ($selectedId > 0) {
            $selected = $this->proposals->baseQuery($user)
                ->select('proposal.*')
                ->selectRaw($this->proposals->statusSql().' as proposal_status')
                ->where('proposal.idProposal', $selectedId)
                ->first();

            // Proposal milik orang lain / tidak ada → panel detail kosong.
            $selectedProposal = $this->proposals->detail(
                $selected,
                $selected ? ($numbers[$selected->idProposal] ?? 0) : 0,
            );
        }

        $followUps = $this->proposals->followUps($user);

        return Inertia::render('proposals/Index', [
            'stats' => $this->proposals->stats($user, $periode),
            'chart' => $this->proposals->trend($user, $periode),
            'followUps' => $followUps,
            'proposals' => [
                'data' => $rows,
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'selectedProposal' => $selectedProposal,
            'statusCounts' => $this->proposals->statusCounts($user, $search),
            'kategori' => Kategori::query()->orderBy('name')->get(['idKategori', 'name'])
                ->map(fn (Kategori $item) => ['id' => $item->idKategori, 'name' => $item->name])
                ->values(),
            'filters' => [
                'search' => $search,
                'status' => $status?->value ?? 'all',
                'periode' => $periode,
                'perPage' => $perPage,
                'kategori' => $request->query('kategori') ? (int) $request->query('kategori') : null,
            ],
        ]);
    }

    /**
     * Ekstrak isi dokumen PDF menjadi nilai form (JSON).
     *
     * Dipakai tombol "Ekstrak dari PDF" pada dialog Tambah Proposal.
     * Endpoint ini TIDAK menyimpan apa pun: file hanya dibaca dari lokasi
     * sementara upload, hasilnya dikirim ke browser untuk mengisi form.
     * Penyimpanan tetap terjadi saat form disubmit (store()).
     */
    public function extract(Request $request, PdfExtractionService $extractor): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'max:'.(int) config('sponsormatch.pdf.max_kb', 5120),
            ],
        ]);

        try {
            $result = $extractor->extractProposal($request->file('file')->getRealPath());
        } catch (\RuntimeException $exception) {
            // Kode EMPTY_TEXT = PDF tidak punya lapisan teks (hasil scan).
            if ($exception->getMessage() === 'EMPTY_TEXT') {
                return response()->json([
                    'message' => 'PDF ini tidak berisi teks yang bisa dibaca (kemungkinan hasil scan). Silakan isi form secara manual.',
                ], 422);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Dokumen PDF tidak bisa dibaca. Pastikan file tidak rusak atau terkunci.',
            ], 422);
        }

        if ($result['fields'] === []) {
            return response()->json([
                'message' => 'Tidak ada isian yang dikenali dari PDF ini. Kamu bisa mengisi form secara manual.',
            ], 422);
        }

        return response()->json($result);
    }

    /**
     * Terbitkan proposal draft menjadi siap dikirim.
     */
    public function publish(Request $request, Proposal $proposal): RedirectResponse
    {
        $user = $request->user();

        if ((int) $proposal->idUser !== (int) $user->id) {
            abort(403);
        }

        if ($proposal->status !== 'draft') {
            return back();
        }

        $path = (string) $proposal->file_path;

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Unggah dokumen proposal terlebih dahulu untuk menerbitkan.',
            ]);

            return back();
        }

        $proposal->update(['status' => 'published']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Proposal berhasil diterbitkan. Kamu bisa mengirimnya ke sponsor.',
        ]);

        return back();
    }

    /**
     * Form/pilihan sponsor untuk mengirim proposal.
     */
    public function showSendForm(Request $request, Proposal $proposal): Response
    {
        $user = $request->user();

        if ((int) $proposal->idUser !== (int) $user->id) {
            abort(403);
        }

        $sentTo = $proposal->trackings()->pluck('company_user_id')->all();

        $sponsors = User::query()
            ->where('role', 'sponsor')
            ->where('id', '!=', $user->id)
            ->with('companyProfile')
            ->orderBy('name')
            ->get()
            ->map(fn (User $company) => [
                'id' => $company->id,
                'name' => $company->companyProfile?->companyName ?? $company->name,
                'industry' => $company->companyProfile?->industry,
                'email' => $company->email,
                'alreadySent' => in_array($company->id, $sentTo, true),
            ])
            ->values();

        return Inertia::render('proposals/Send', [
            'proposal' => [
                'id' => $proposal->idProposal,
                'nomor' => (string) ($this->proposals->numbersFor($user)[$proposal->idProposal] ?? 1),
                'title' => $proposal->title,
                'kategori' => $proposal->kategori?->name,
            ],
            'sponsors' => $sponsors,
        ]);
    }

    /**
     * Kirim proposal ke sponsor terpilih.
     */
    public function send(Request $request, Proposal $proposal): RedirectResponse
    {
        $user = $request->user();

        if ((int) $proposal->idUser !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'sponsor_ids' => ['required', 'array', 'min:1'],
            'sponsor_ids.*' => ['integer', 'exists:users,id'],
            // Campaign OPSIONAL. NULL berarti dikirim langsung ke perusahaan.
            'campaign_id' => ['nullable', 'integer', 'exists:campaign,idCampaign'],
            'message' => ['nullable', 'string', 'max:1000'],
            // Halaman asal pengiriman: 'recommendations' → kembali ke daftar
            // rekomendasi agar modal tidak melempar EO ke halaman lain.
            'redirect' => ['nullable', 'in:recommendations'],
        ]);

        $sponsors = User::query()
            ->where('role', 'sponsor')
            ->where('isActive', true)
            ->whereIn('id', $validated['sponsor_ids'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($sponsors)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Pilih minimal satu sponsor yang valid.',
            ]);

            return back();
        }

        $campaign = null;

        if (! empty($validated['campaign_id'])) {
            $campaign = Campaign::query()->find((int) $validated['campaign_id']);

            if ($campaign === null || ! in_array((int) $campaign->idUser, $sponsors, true)) {
                return back()->withErrors([
                    'campaign_id' => 'Campaign yang dipilih bukan milik perusahaan tujuan.',
                ]);
            }

            if (! $campaign->isActive()) {
                return back()->withErrors([
                    'campaign_id' => 'Campaign sudah tidak aktif atau tanggal berakhirnya sudah lewat.',
                ]);
            }

            if (count($sponsors) > 1) {
                return back()->withErrors([
                    'campaign_id' => 'Campaign hanya dapat dipilih saat mengirim ke satu perusahaan.',
                ]);
            }
        }

        $message = filled($validated['message'] ?? null)
            ? trim((string) $validated['message'])
            : null;

        DB::transaction(function () use ($proposal, $sponsors, $campaign, $message) {
            if ($proposal->status === 'draft') {
                $proposal->update(['status' => 'published']);
            }

            foreach ($sponsors as $sponsorId) {
                $tracking = TrackingPipeline::firstOrCreate(
                    [
                        'idProposal' => $proposal->idProposal,
                        'company_user_id' => $sponsorId,
                    ],
                    [
                        'idCampaign' => $campaign?->idCampaign,
                        'stage' => 'sent',
                        'initiatedBy' => 'Event_Organizer',
                        'message' => $message,
                    ],
                );

                if (! $tracking->wasRecentlyCreated) {
                    $updates = [];

                    if ($campaign !== null && $tracking->idCampaign === null) {
                        $updates['idCampaign'] = $campaign->idCampaign;
                    }

                    if ($message !== null && blank($tracking->message)) {
                        $updates['message'] = $message;
                    }

                    if ($updates !== []) {
                        $tracking->update($updates);
                    }
                }
            }
        });

        $target = User::query()
            ->with('companyProfile')
            ->find($sponsors[0]);

        $targetName = $target?->companyProfile?->companyName ?? $target?->name ?? 'perusahaan';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $campaign !== null
                ? "Proposal berhasil dikirim ke {$targetName} untuk campaign {$campaign->title}."
                : 'Proposal berhasil dikirim ke '.$targetName.'.',
        ]);

        if (($validated['redirect'] ?? null) === 'recommendations') {
            return back();
        }

        return to_route('proposals.index', ['selected' => $proposal->idProposal]);
    }

    /**
     * Unduh dokumen proposal.
     *
     * Dokumen hanya boleh diunduh oleh pemilik proposal (EO) atau sponsor
     * yang memang menerima proposal tersebut.
     */
    public function download(Request $request, Proposal $proposal): RedirectResponse|StreamedResponse
    {
        $user = $request->user();

        if (! $this->canAccessProposal($user, $proposal)) {
            abort(403, 'Anda tidak berhak mengunduh dokumen proposal ini.');
        }

        $path = (string) $proposal->file_path;

        if ($path === '') {
            return back()->with('error', 'Proposal ini belum memiliki dokumen.');
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->download($path);
            }
        }

        return back()->with('error', 'Dokumen proposal belum tersedia di server.');
    }

    /**
     * Catat follow-up baru terhadap sponsor (tabel `follow_up_logs`).
     */
    public function followUp(Request $request, TrackingPipeline $tracking): RedirectResponse
    {
        $user = $request->user();

        if (! $this->ownsTracking($user, $tracking)) {
            abort(403, 'Anda tidak berhak melakukan follow-up pada proposal ini.');
        }

        $tracking->followUps()->create([]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Follow-up berhasil dicatat untuk '.($tracking->company?->name ?? 'sponsor').'.',
        ]);

        return back();
    }

    /**
     * Buat proposal baru (status draft) dari tombol "+ Tambah Proposal".
     */
    public function store(Request $request): RedirectResponse
    {
        // Input anggaran diformat dengan pemisah ribuan di browser
        // ("100.000.000"). Sebelum divalidasi sebagai integer, bersihkan
        // semua karakter non-angka supaya user bisa mengetik dengan nyaman
        // (bahkan menempel "Rp 100.000.000").
        foreach (['budgetMin', 'budgetMax'] as $field) {
            $raw = $request->input($field);

            if (is_string($raw) && preg_replace('/\D/', '', $raw) !== '') {
                $request->merge([$field => (int) preg_replace('/\D/', '', $raw)]);
            }
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'idKategori' => ['required', 'integer', 'exists:kategori,idKategori'],
            'location' => ['required', 'string', 'max:255'],
            'needType' => ['required', 'string', 'max:255'],
            'desctiption' => ['required', 'string', 'max:2000'],
            'target_audience' => ['required', 'string', 'max:2000'],
            'budgetMin' => ['required', 'integer', 'min:0'],
            'budgetMax' => ['required', 'integer', 'min:0', 'gte:budgetMin'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'benefits' => ['nullable', 'string', 'max:2000'],
            'file' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:'.(int) config('sponsormatch.pdf.max_kb', 5120),
            ],
        ]);

        $filePath = '';

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('proposals', 'local');
        }

        $proposal = Proposal::create([
            'idUser' => $request->user()->id,
            'idKategori' => $validated['idKategori'],
            'title' => $validated['title'],
            'desctiption' => $validated['desctiption'],
            'target_audience' => $validated['target_audience'],
            'location' => $validated['location'],
            'needType' => $validated['needType'],
            'budgetMin' => $validated['budgetMin'],
            'budgetMax' => $validated['budgetMax'],
            'benefits' => $validated['benefits'] ?? '',
            'file_path' => $filePath,
            'status' => 'draft',
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Proposal "'.$proposal->title.'" dibuat dan disimpan sebagai draft.',
        ]);

        return to_route('proposals.index', ['status' => 'draft', 'selected' => $proposal->idProposal]);
    }

    /**
     * Apakah user boleh melihat/mengunduh proposal ini?
     */
    private function canAccessProposal(User $user, Proposal $proposal): bool
    {
        if ((int) $proposal->idUser === (int) $user->id) {
            return true;
        }

        // Sponsor yang menerima proposal (ada tracking Towards dia).
        return $proposal->trackings()
            ->where('company_user_id', $user->id)
            ->exists();
    }

    /**
     * Apakah user adalah pemilik proposal yang terkait tracking ini?
     */
    private function ownsTracking(User $user, TrackingPipeline $tracking): bool
    {
        return $tracking->proposal()
            ->where('idUser', $user->id)
            ->exists();
    }
}
