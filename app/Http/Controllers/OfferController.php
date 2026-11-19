<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\TrackingPipeline;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman negosiasi "Penawaran" untuk kedua peran.
 *
 * Satu baris `offer` selalu dikirim oleh salah satu pihak:
 *   - perusahaan → EO (penawaran awal / balasan)
 *   - EO → perusahaan (counter offer)
 *
 * Hanya PIHAK PENERIMA yang boleh merespons, dan hanya selama statusnya
 * masih `pending`. Aksi yang tersedia:
 *   accept  → penawaran diterima, tracking menjadi `deal`, penawaran lain
 *             pada tracking yang sama ditutup.
 *   reject  → penawaran ditolak. Tracking tetap terbuka (kembali ke
 *             `viewed`) bila tidak ada penawaran lain yang menggantung.
 *   counter → penerima mengirim penawaran balik sebagai baris baru
 *             (parent_offer_id), sedangkan penawaran lama menjadi `counter`.
 *
 * Halaman dan endpoint ini TIDAK menduplikasi alur pengiriman proposal.
 * Semuanya memakai `trackingpipeline` + `offer` yang sudah ada.
 */
class OfferController extends Controller
{
    /** Jenis dukungan yang sama dengan form penawaran sponsor. */
    private const SUPPORT_TYPES = ['Dana', 'Produk', 'Dana & produk', 'Jasa'];

    public function index(Request $request): Response
    {
        $user = $this->participant($request);

        $offers = Offer::query()
            ->whereHas('tracking', fn (Builder $query) => $this->scopeForUser($query, $user))
            ->with([
                'tracking.proposal.kategori',
                'tracking.proposal.user',
                'tracking.company.companyProfile',
                'tracking.campaign',
                'fromUser.companyProfile',
            ])
            ->orderByDesc('createdAt')
            ->orderByDesc('idOffer')
            ->get();

        $items = $offers
            ->map(fn (Offer $offer) => $this->present($offer, $user))
            ->filter()
            ->values();

        return Inertia::render('offers/Index', [
            'items' => $items,
            'counts' => [
                'all' => $items->count(),
                'action' => $items->where('can_respond', true)->count(),
                'negotiating' => $items->whereIn('status', ['pending', 'counter'])->count(),
                'accepted' => $items->where('status', 'accepted')->count(),
                'rejected' => $items->where('status', 'rejected')->count(),
            ],
            'supportTypes' => self::SUPPORT_TYPES,
            'role' => $user->isCompany() ? 'company' : 'eo',
        ]);
    }

    public function respond(Request $request, Offer $offer): RedirectResponse
    {
        $user = $this->participant($request);

        $offer->loadMissing(['tracking.proposal', 'tracking.company.companyProfile']);
        $tracking = $offer->tracking;

        abort_if($tracking === null, 404);
        abort_if($tracking->proposal === null, 404);

        $this->authorizeParticipant($tracking, $user);

        // Pengirim tidak boleh merespons penawarannya sendiri.
        abort_if(
            (int) $offer->from_user_id === (int) $user->id,
            403,
            'Anda tidak dapat merespons penawaran yang Anda kirim sendiri.',
        );

        if ($offer->status !== 'pending') {
            return $this->error('Penawaran ini sudah direspons.');
        }

        if (in_array($tracking->stage, ['deal', 'done', 'rejected'], true)) {
            return $this->error('Negosiasi untuk proposal ini sudah selesai.');
        }

        // Nilai rupiah boleh dikirim dengan pemisah ribuan.
        $rawAmount = $request->input('amount');

        if (is_string($rawAmount) && preg_replace('/\D/', '', $rawAmount) !== '') {
            $request->merge(['amount' => (int) preg_replace('/\D/', '', $rawAmount)]);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:accept,reject,counter'],
            'reject_reason' => ['nullable', 'string', 'max:1000'],
            'support_type' => [
                'nullable',
                'required_if:action,counter',
                'string',
                'in:'.implode(',', self::SUPPORT_TYPES),
            ],
            'amount' => ['nullable', 'required_if:action,counter', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:1000'],
            'benefits' => ['nullable', 'string', 'max:1000'],
        ]);

        return match ($validated['action']) {
            'accept' => $this->accept($offer, $tracking),
            'reject' => $this->reject($offer, $tracking, $validated),
            'counter' => $this->counter($offer, $tracking, $user, $validated),
        };
    }

    private function accept(Offer $offer, TrackingPipeline $tracking): RedirectResponse
    {
        DB::transaction(function () use ($offer, $tracking): void {
            $offer->update(['status' => 'accepted']);
            $tracking->update(['stage' => 'deal']);

            // Negosiasi selesai: semua penawaran lain yang masih terbuka
            // ditutup agar tidak ada dua kesepakatan pada tracking yang sama.
            $tracking->offers()
                ->where('idOffer', '!=', $offer->idOffer)
                ->whereIn('status', ['pending', 'counter'])
                ->update(['status' => 'rejected', 'rejectReason' => 'Negosiasi ditutup karena ada penawaran lain yang disetujui.']);
        });

        return $this->success('Penawaran disetujui. Proposal ini sekarang berstatus deal.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function reject(Offer $offer, TrackingPipeline $tracking, array $validated): RedirectResponse
    {
        DB::transaction(function () use ($offer, $tracking, $validated): void {
            $offer->update([
                'status' => 'rejected',
                'rejectReason' => filled($validated['reject_reason'] ?? null)
                    ? trim((string) $validated['reject_reason'])
                    : null,
            ]);

            $stillOpen = $tracking->offers()
                ->whereIn('status', ['pending', 'counter'])
                ->exists();

            // Penolakan tidak otomatis menutup jalur negosiasi. Bila tidak
            // ada penawaran lain, tracking kembali ke `viewed` supaya salah
            // satu pihak masih bisa mengirim penawaran baru.
            if (! $stillOpen && $tracking->stage === 'negotiating') {
                $tracking->update(['stage' => 'viewed']);
            }
        });

        return $this->success('Penawaran ditolak.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function counter(Offer $offer, TrackingPipeline $tracking, User $user, array $validated): RedirectResponse
    {
        DB::transaction(function () use ($offer, $tracking, $user, $validated): void {
            Offer::create([
                'idTracking' => $tracking->idTracking,
                'from_user_id' => $user->id,
                'parent_offer_id' => $offer->idOffer,
                'support_type' => $validated['support_type'],
                'amount' => (int) $validated['amount'],
                'description' => filled($validated['message'] ?? null)
                    ? trim((string) $validated['message'])
                    : null,
                'benefits' => filled($validated['benefits'] ?? null)
                    ? trim((string) $validated['benefits'])
                    : null,
                'status' => 'pending',
            ]);

            // Penawaran lama ditandai sebagai counter agar rantai negosiasi
            // tetap terlihat di riwayat.
            $offer->update(['status' => 'counter']);

            if ($tracking->stage !== 'negotiating') {
                $tracking->update(['stage' => 'negotiating']);
            }
        });

        return $this->success('Penawaran balik berhasil dikirim.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function present(Offer $offer, User $user): ?array
    {
        $tracking = $offer->tracking;
        $proposal = $tracking?->proposal;

        if ($tracking === null || $proposal === null) {
            return null;
        }

        $company = $tracking->company;
        $companyName = $company?->companyProfile?->companyName
            ?? $company?->name
            ?? 'Perusahaan';

        $fromMe = (int) $offer->from_user_id === (int) $user->id;
        $viewerIsCompany = (int) $tracking->company_user_id === (int) $user->id;

        $canRespond = ! $fromMe
            && $offer->status === 'pending'
            && ! in_array($tracking->stage, ['deal', 'done', 'rejected'], true);

        $proposalUrl = $viewerIsCompany
            ? route('sponsor.proposals.show', $tracking->idTracking)
            : route('proposals.index', ['selected' => $proposal->idProposal]);

        return [
            'id' => (int) $offer->idOffer,
            'parent_id' => $offer->parent_offer_id === null ? null : (int) $offer->parent_offer_id,
            'status' => $offer->status,
            'status_label' => $this->statusLabel($offer->status),
            'from_me' => $fromMe,
            'can_respond' => $canRespond,
            'support_type' => $offer->support_type,
            'amount' => $offer->amount === null ? null : (int) $offer->amount,
            'description' => $offer->description,
            'benefits' => $offer->benefits,
            'reject_reason' => $offer->rejectReason,
            'created_at' => $offer->createdAt?->toIso8601String(),
            'proposal' => [
                'id' => (int) $proposal->idProposal,
                'title' => $proposal->title,
                'category' => $proposal->kategori?->name,
                'url' => $proposalUrl,
            ],
            'company_name' => $companyName,
            'counterpart_name' => $viewerIsCompany
                ? ($proposal->user?->name ?? 'Event Organizer')
                : $companyName,
            'sender_label' => $fromMe
                ? 'Anda'
                : ($viewerIsCompany
                    ? ($proposal->user?->name ?? 'Event Organizer')
                    : $companyName),
            'campaign_title' => $tracking->campaign?->title,
            'deal' => in_array($tracking->stage, ['deal', 'waiting_report', 'done'], true),
        ];
    }

    /**
     * Batasi query tracking sesuai peran: EO sebagai pemilik proposal,
     * perusahaan sebagai penerima proposal.
     *
     * @param  Builder<TrackingPipeline>  $query
     */
    private function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isCompany()) {
            return $query->where('company_user_id', $user->id);
        }

        return $query->whereHas(
            'proposal',
            fn (Builder $proposal) => $proposal->where('idUser', $user->id),
        );
    }

    private function authorizeParticipant(TrackingPipeline $tracking, User $user): void
    {
        $isCompany = (int) $tracking->company_user_id === (int) $user->id;
        $isOwner = (int) $tracking->proposal?->idUser === (int) $user->id;

        abort_unless($isCompany || $isOwner, 403, 'Anda tidak berhak mengakses penawaran ini.');
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

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu respons',
            'accepted' => 'Diterima',
            'rejected' => 'Ditolak',
            'counter' => 'Penawaran balik',
            default => $status,
        };
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function error(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
