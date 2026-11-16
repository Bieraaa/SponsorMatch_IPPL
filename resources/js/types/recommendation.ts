/**
 * Kontrak data halaman "Rekomendasi Sponsor".
 * Dipetakan dari SponsorRecommendationController::index.
 */

export type RecommendationCampaign = {
    id: number;
    title: string;
    description: string | null;
    banner_url: string | null;
    category_id: number;
    category: string | null;
    purpose: string | null;
    target_audience: string | null;
    budget_min: number;
    budget_max: number;
    support_type: string | null;
    benefits: string | null;
    estimated_participant: number | null;
    period_start: string | null;
    period_end: string | null;
    status: string;
    is_active: boolean;
};

export type CampaignOption = {    id: number;
    title: string;
    budget_min: number;
    budget_max: number;
    period_end: string | null;
};

export type RecommendationItem = {
    company_id: number;
    company_name: string;
    company_slug: string;
    company_url: string;
    logo_url: string | null;
    industry: string | null;
    description: string | null;
    rating: string | number | null;
    score: number;
    source: 'profile' | 'campaign';
    source_label: string;
    funding_min: number | null;
    funding_max: number | null;
    reason: string | null;
    factors: string[];
    campaign: RecommendationCampaign | null;
    campaign_options: CampaignOption[];
    active_campaign_count: number;
    already_sent: boolean;
    proposal_id: number;
};

export type RecommendationPagination = {
    data: RecommendationItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type ProposalOption = {
    id: number;
    title: string;
    kategori: string | null;
    status: string;
    budget_min: number;
    budget_max: number;
};

export type LatestPost = {
    id: number;
    description: string;
    image_url: string | null;
    created_at: string | null;
    company_name: string | null;
};

export type RecommendationAiStatus = {
    generated: boolean;
    message: string | null;
};

/**
 * Data minimal yang dibutuhkan modal "Kirim Proposal".
 * Dipakai halaman rekomendasi dan halaman detail perusahaan, sehingga
 * pengiriman berbasis profil tetap tersedia tanpa campaign aktif.
 */
export type SendableCompany = {
    company_id: number;
    company_name: string;
    logo_url: string | null;
    industry: string | null;
    campaign_options: CampaignOption[];
};

/**
 * Satu kartu rekomendasi proposal EO untuk perusahaan.
 * Dipetakan dari CompanyRecommendationController::buildItems().
 */
export type ProposalRecommendationItem = {
    proposal_id: number;
    title: string;
    category: string | null;
    description: string | null;
    eo_name: string | null;
    location: string | null;
    start_date: string | null;
    end_date: string | null;
    budget_min: number;
    budget_max: number;
    need_type: string | null;
    benefits: string | null;
    score: number;
    source: 'profile' | 'campaign';
    source_label: string;
    reason: string | null;
    factors: string[];
    campaign: {
        id: number;
        title: string;
        purpose: string | null;
        budget_min: number;
        budget_max: number;
    } | null;
    already_contacted: boolean;
    tracking_id: number | null;
};

export type ProposalRecommendationPagination = {
    data: ProposalRecommendationItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};
