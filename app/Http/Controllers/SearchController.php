<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pencarian akun pada topbar.
 *
 * Endpoint ini dipakai search bar global untuk mencari akun Event Organizer
 * maupun Perusahaan. Selain nama/email akun, pencarian ikut membaca data
 * profil:
 *  - perusahaan : nama badan usaha, industri, target audiens, kategori
 *  - EO         : nama organisasi dan bidang
 *
 * Saat query kosong (search bar baru dibuka), endpoint mengembalikan daftar
 * akun aktif sebagai saran awal supaya dropdown tidak terlihat kosong.
 */
class SearchController extends Controller
{
    private const DEFAULT_LIMIT = 8;

    private const MAX_LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $q = mb_substr($q, 0, 100);
        $limit = (int) $request->query('limit', self::DEFAULT_LIMIT);
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $query = User::query()
            ->whereIn('role', ['eo', 'sponsor'])
            ->where('isActive', true)
            ->with(['companyProfile.categories', 'eoProfile'])
            ->withCount('reviewsReceived');

        if ($q !== '') {
            $this->applySearch($query, $q);

            // Akun yang namanya diawali kata kunci tampil lebih dulu.
            $query->orderByRaw(
                'CASE WHEN name LIKE ? OR email LIKE ? THEN 0 ELSE 1 END',
                [$q.'%', $q.'%'],
            );
        }

        $results = $query
            ->orderByDesc('rating')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $user): array => $this->present($user, $request))
            ->values();

        return response()->json([
            'query' => $q,
            'count' => $results->count(),
            'results' => $results,
        ]);
    }

    /**
     * Filter akun berdasarkan nama/email akun dan data profilnya.
     *
     * @param  Builder<User>  $query
     */
    private function applySearch(Builder $query, string $q): void
    {
        $like = '%'.$q.'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('nomor_telepon', 'like', $like)
                ->orWhereHas('companyProfile', function (Builder $profile) use ($like): void {
                    $profile->where('companyName', 'like', $like)
                        ->orWhere('industry', 'like', $like)
                        ->orWhere('target_audience', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('categories', fn (Builder $category) => $category->where('name', 'like', $like));
                })
                ->orWhereHas('eoProfile', function (Builder $profile) use ($like): void {
                    $profile->where('eo_name', 'like', $like)
                        ->orWhere('eo_field', 'like', $like);
                });
        });
    }

    /**
     * Bentuk data yang aman dikirim ke dropdown search.
     *
     * @return array<string, mixed>
     */
    private function present(User $user, Request $request): array
    {
        $isCompany = $user->isCompany();
        $company = $user->companyProfile;
        $eo = $user->eoProfile;

        $displayName = $isCompany
            ? ($company?->companyName ?: $user->name)
            : ($eo?->eo_name ?: $user->name);

        $subtitle = $isCompany
            ? ($company?->industry ?: 'Perusahaan sponsor')
            : ($eo?->eo_field ?: 'Event Organizer');

        return [
            'id' => (int) $user->id,
            'name' => $displayName,
            'account_name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'role_label' => $user->role?->label() ?? 'Akun',
            'subtitle' => $subtitle,
            'industry' => $company?->industry,
            'location' => $company?->target_audience,
            'rating' => $user->rating !== null ? round((float) $user->rating, 1) : 0.0,
            'review_count' => (int) ($user->reviews_received_count ?? 0),
            'avatar_url' => $user->avatar,
            'logo_url' => $company?->logoUrl(),
            'is_self' => (int) $request->user()?->id === (int) $user->id,
            'url' => $isCompany
                ? route('companies.show', $user->id)
                : route('accounts.show', $user->id),
        ];
    }
}
