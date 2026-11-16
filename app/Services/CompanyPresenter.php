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
     * @return array<string, mixed>
     */
    public static function post(Post $post): array
    {
        $companyName = $post->user?->companyProfile?->companyName ?? $post->user?->name;

        return [
            'id' => (int) $post->idPost,
            'description' => $post->description,
            'image_url' => $post->imageUrl(),
            'created_at' => $post->createdAt?->toIso8601String(),
            'company_name' => $companyName,
        ];
    }
}
