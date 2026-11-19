<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CompanyProfile;
use App\Models\Post;

/**
 * Pemetaan model perusahaan/campaign/postingan ke bentuk props Inertia.
 *
 * Dikumpulkan di satu tempat supaya halaman rekomendasi, detail perusahaan,
 * dan pengelolaan campaign memakai kontrak data yang sama.
 */
class CompanyPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function profile(?CompanyProfile $profile): array
    {
        return [
            'company_name' => $profile?->companyName,
            'logo_url' => $profile?->logoUrl(),
            'industry' => $profile?->industry,
            'description' => $profile?->description,
            'website' => $profile?->website,
            'sponsorship_min' => $profile?->sponsorship_min,
            'sponsorship_max' => $profile?->sponsorship_max,
            'event_types' => $profile?->event_types,
            'target_audience' => $profile?->target_audience,
            'categories' => $profile
                ? $profile->categories->pluck('idKategori')->map(fn ($id) => (int) $id)->values()->all()
                : [],
        ];
    }

    /**
     * @param  bool  $forOwner  Sertakan informasi internal (mis. jumlah proposal).
     * @return array<string, mixed>
     */
    public static function campaign(Campaign $campaign, bool $forOwner = false, ?int $proposalCount = null): array
    {
        return [
            'id' => (int) $campaign->idCampaign,
            'title' => $campaign->title,
            'description' => $campaign->description,
            'banner_url' => $campaign->bannerUrl(),
            'category_id' => (int) $campaign->idKategori,
            'category' => $campaign->kategori?->name,
            'purpose' => $campaign->purpose,
            'target_audience' => $campaign->target_audience,
            'budget_min' => (int) $campaign->budgetMin,
            'budget_max' => (int) $campaign->budgetMax,
            'support_type' => $campaign->supportType,
            'benefits' => $campaign->benefits,
            'estimated_participant' => $campaign->estimatedParticipant,
            'period_start' => $campaign->periodStart?->toDateString(),
            'period_end' => $campaign->periodEnd?->toDateString(),
            'status' => $campaign->status,
            'is_active' => $campaign->isActive(),
            'proposal_count' => $forOwner ? (int) ($proposalCount ?? 0) : null,
        ];
    }

    /**
     * Postingan komunitas (feed, dashboard, dan profil akun).
     *
     * `company_name` dipertahankan demi kompatibilitas halaman detail
     * perusahaan. Halaman feed memakai `author_*` agar postingan milik
     * Event Organizer juga punya identitas yang jelas.
     *
     * @return array<string, mixed>
     */
    public static function post(Post $post): array
    {
        $author = $post->user;
        $profile = $author?->companyProfile;
        $isCompany = $author?->isCompany() ?? false;

        $authorName = $isCompany
            ? ($profile?->companyName ?? $author?->name)
            : $author?->name;

        return [
            'id' => (int) $post->idPost,
            'description' => $post->description,
            'image_url' => $post->imageUrl(),
            'created_at' => $post->createdAt?->toIso8601String(),
            'updated_at' => $post->updateAt?->toIso8601String(),
            'author_id' => (int) $post->idUser,
            'author_name' => $authorName ?? 'Pengguna',
            'author_avatar' => $author?->avatar,
            'author_role' => $isCompany ? 'sponsor' : 'eo',
            'author_role_label' => $isCompany ? 'Perusahaan' : 'Event Organizer',
            'logo_url' => $profile?->logoUrl(),
            'company_name' => $authorName,
            'href' => $isCompany
                ? route('companies.show', $post->idUser)
                : route('accounts.show', $post->idUser),
        ];
    }
}
