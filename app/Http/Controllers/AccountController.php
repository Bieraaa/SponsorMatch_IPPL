<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Review;
use App\Models\User;
use App\Services\CompanyPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil akun publik yang dibuka dari hasil pencarian.
 *
 * Profil perusahaan sudah punya halaman khusus (`companies.show`), jadi akun
 * perusahaan diarahkan ke sana. Halaman ini menjadi profil publik untuk akun
 * Event Organizer: identitas organisasi, rating, dan ulasan yang diterima.
 */
class AccountController extends Controller
{
    public function show(Request $request, User $user): Response|RedirectResponse
    {
        abort_unless(
            $user->isCompany() || $user->isEventOrganizer(),
            404,
        );

        if ($user->isCompany()) {
            return redirect()->route('companies.show', $user->id);
        }

        $user->loadMissing('eoProfile');

        $reviewsQuery = Review::query()
            ->where('to_user_id', $user->id)
            ->with(['fromUser', 'tracking.proposal']);

        $reviewCount = (clone $reviewsQuery)->count();
        $average = (float) ((clone $reviewsQuery)->avg('rating') ?? 0);
        $rating = $user->rating !== null
            ? round((float) $user->rating, 1)
            : ($reviewCount > 0 ? round($average, 1) : 0.0);

        $reviews = $reviewsQuery
            ->orderByDesc('createdAt')
            ->limit(10)
            ->get()
            ->map(function (Review $review): array {
                $reviewer = $review->fromUser;

                return [
                    'id' => (int) $review->idRiviews,
                    'rating' => (int) $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->createdAt?->toIso8601String(),
                    'reviewer_name' => $reviewer?->name ?? 'Akun tidak tersedia',
                    'reviewer_avatar' => $reviewer?->avatar,
                    'reviewer_role' => $reviewer?->isCompany()
                        ? 'Perusahaan'
                        : ($reviewer !== null ? 'Event Organizer' : null),
                    'event_title' => $review->tracking?->proposal?->title,
                ];
            })
            ->values()
            ->all();

        $profile = $user->eoProfile;

        $posts = Post::query()
            ->visible()
            ->ownedBy($user)
            ->with(['user.companyProfile'])
            ->orderByDesc('createdAt')
            ->orderByDesc('idPost')
            ->limit(6)
            ->get()
            ->map(fn (Post $post) => CompanyPresenter::post($post))
            ->values()
            ->all();

        return Inertia::render('accounts/Show', [
            'account' => [
                'id' => (int) $user->id,
                'name' => $profile?->eo_name ?: $user->name,
                'person_name' => $user->name,
                'role_label' => 'Event Organizer',
                'field' => $profile?->eo_field,
                'email' => $user->email,
                'phone' => $user->nomor_telepon,
                'jabatan' => $user->jabatan,
                'avatar_url' => $user->avatar,
                'rating' => $rating,
                'review_count' => $reviewCount,
                'joined_at' => $user->created_at?->toIso8601String(),
                'is_self' => (int) $request->user()?->id === (int) $user->id,
            ],
            'reviews' => $reviews,
            'posts' => $posts,
        ]);
    }
}
