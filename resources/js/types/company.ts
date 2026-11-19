/**
 * Kontrak data profil, campaign, dan postingan perusahaan.
 * Dipetakan dari CompanyProfileController & CompanyCampaignController.
 */

export type CompanyCategory = {
    id: number;
    name: string;
};

export type CompanyDetail = {
    id: number;
    name: string;
    company_name: string | null;
    logo_url: string | null;
    industry: string | null;
    description: string | null;
    website: string | null;
    funding_min: number | null;
    funding_max: number | null;
    sponsorship_min: number | null;
    sponsorship_max: number | null;
    event_types: string[];
    target_audience: string | null;
    categories: CompanyCategory[];
    rating: number;
    review_count: number;
    contact_email: string | null;
    contact_phone: string | null;
    email: string | null;
};

export type CompanyProfileForm = {
    company_name: string | null;
    logo_url: string | null;
    industry: string | null;
    description: string | null;
    website: string | null;
    sponsorship_min: number | null;
    sponsorship_max: number | null;
    event_types: string | null;
    target_audience: string | null;
    categories: number[];
};

export type CompanyCampaign = {
    id: number;
    title: string;
    description: string;
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
    status: 'draft' | 'active' | 'close';
    is_active: boolean;
    proposal_count: number | null;
};

export type CompanyTab = 'profile' | 'campaign' | 'posts';

export type CompanyPost = {
    id: number;
    description: string;
    image_url: string | null;
    created_at: string | null;
    company_name: string | null;
};

export type CampaignCategoryOption = {
    id: number;
    name: string;
    description: string | null;
};

export type CampaignStats = {
    total: number;
    active: number;
    draft: number;
    closed: number;
    proposals: number;
};

export type UploadConfig = {
    max_kb: number;
    extensions: string[];
};
