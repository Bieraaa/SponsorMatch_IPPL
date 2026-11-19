<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\UpdateCompanyProfileRequest;
use App\Models\Campaign;
use App\Models\CompanyProfile;
use App\Models\Kategori;
use App\Models\Post;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;
use App\Services\CompanyPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil perusahaan.
 *
 * - `show`  : halaman publik (bagi pengguna login) berisi tab Profil,
 *             Campaign aktif, dan Postingan.
 * - `edit`  : formulir profil milik perusahaan yang sedang login.
 * - `update`: simpan profil + logo + minat kategori.
 *
 * Profil WAJIB ada agar perusahaan bisa muncul di rekomendasi, tetapi
 * campaign tetap opsional.
 */
class CompanyProfileController extends Controller
{
    /**
     * Detail perusahaan untuk EO (dan perusahaan itu sendiri).
     */
    public function show(Request $request, int $company): Response
    {
        $companyUser = User::query()
            ->where('role', 'sponsor')
            ->where('isActive', true)
            ->with(['companyProfile.categories'])
            ->findOrFail($company);

        $profile = $companyUser->companyProfile;

        $campaigns = Campaign::query()
            ->where('idUser', $companyUser->id)
            ->active()
            ->with('kategori')
            ->orderByDesc('periodStart')
            ->get()
            ->map(fn (Campaign $campaign) => CompanyPresenter::campaign($campaign))
            ->values();

        $posts = Post::query()
            ->where('idUser', $companyUser->id)
            ->visible()
            ->with(['user.companyProfile'])
            ->orderByDesc('createdAt')
            ->limit(12)
            ->get()
            ->map(fn (Post $post) => CompanyPresenter::post($post))
            ->values();

        /*
         * Rating perusahaan: pakai `users.rating` bila sudah diisi. Bila
         * masih kosong, hitung rata-rata ulasan yang diterima agar profil
         * baru tetap punya nilai yang masuk akal.
         */
        $reviews = Review::query()->where('to_user_id', $companyUser->id);
        $reviewCount = (clone $reviews)->count();
        $reviewAverage = (float) ((clone $reviews)->avg('rating') ?? 0);

        $rating = $companyUser->rating !== null
            ? (float) $companyUser->rating
            : ($reviewCount > 0 ? round($reviewAverage, 1) : 0.0);

        $companyName = $profile?->companyName ?? $companyUser->name;

        $eventTypes = collect(explode(',', (string) $profile?->event_types))
            ->map(fn (string $type) => trim($type))
            ->filter()
            ->values()
            ->all();

        return Inertia::render('companies/Show', [
            'company' => [
                'id' => (int) $companyUser->id,
                'name' => $companyName,
                'company_name' => $companyName,
                'logo_url' => $profile?->logoUrl(),
                'industry' => $profile?->industry,
                'description' => $profile?->description,
                'website' => $profile?->website,
                'funding_min' => $profile?->sponsorship_min,
                'funding_max' => $profile?->sponsorship_max,
                'sponsorship_min' => $profile?->sponsorship_min,
                'sponsorship_max' => $profile?->sponsorship_max,
                'event_types' => $eventTypes,
                'target_audience' => $profile?->target_audience,
                'categories' => $profile
                    ? $profile->categories
                        ->map(fn (Kategori $kategori) => [
                            'id' => (int) $kategori->idKategori,
                            'name' => $kategori->name,
                        ])
                        ->values()
                        ->all()
                    : [],
                'rating' => $rating,
                'review_count' => $reviewCount,
                'contact_email' => $companyUser->email,
                'contact_phone' => $companyUser->nomor_telepon,
                'email' => $companyUser->email,
            ],
            'campaigns' => $campaigns,
            'posts' => $posts,
            'proposals' => $request->user()?->isEventOrganizer()
                ? Proposal::query()
                    ->where('idUser', $request->user()->id)
                    ->whereNull('deleteAt')
                    ->with('kategori')
                    ->orderByDesc('idProposal')
                    ->get()
                    ->map(fn (Proposal $proposal) => [
                        'id' => (int) $proposal->idProposal,
                        'title' => $proposal->title,
                        'kategori' => $proposal->kategori?->name,
                        'status' => $proposal->status,
                        'budget_min' => (int) $proposal->budgetMin,
                        'budget_max' => (int) $proposal->budgetMax,
                    ])
                    ->values()
                    ->all()
                : [],
        ]);
    }

    /**
     * Form edit profil milik perusahaan yang login.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->isCompany(), 403, 'Halaman ini khusus akun perusahaan.');

        $profile = CompanyProfile::query()
            ->where('idUser', $user->id)
            ->with('categories')
            ->first();

        $logo = config('sponsormatch.company_logo');

        return Inertia::render('companies/Edit', [
            'profile' => CompanyPresenter::profile($profile),
            'categories' => Kategori::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Kategori $kategori) => [
                    'id' => (int) $kategori->idKategori,
                    'name' => $kategori->name,
                    'description' => $kategori->deskripsiKategori,
                ])
                ->values(),
            'logoConfig' => [
                'max_kb' => $logo['max_kb'],
                'extensions' => $logo['extensions'],
            ],
        ]);
    }

    /**
     * Simpan profil perusahaan.
     */
    public function update(UpdateCompanyProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $profile = CompanyProfile::query()->firstOrCreate(
            ['idUser' => $user->id],
            ['companyName' => $data['company_name']],
        );

        $profile->fill([
            'companyName' => $data['company_name'],
            'industry' => $data['industry'],
            'description' => $data['description'] ?? null,
            'website' => $data['website'] ?? null,
            'sponsorship_min' => $data['sponsorship_min'] ?? null,
            'sponsorship_max' => $data['sponsorship_max'] ?? null,
            'event_types' => $data['event_types'] ?? null,
            'target_audience' => $data['target_audience'] ?? null,
        ]);

        if ($request->hasFile('logo')) {
            $directory = config('sponsormatch.company_logo.directory', 'company-logos');
            $oldPath = $profile->getRawOriginal('logo_path');

            $profile->logo_path = $request->file('logo')->store($directory, 'public');

            if (filled($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $profile->save();

        $profile->categories()->sync($data['categories'] ?? []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Profil perusahaan berhasil disimpan.',
        ]);

        return to_route('company.profile.edit');
    }
}
