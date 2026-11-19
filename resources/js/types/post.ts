/**
 * Kontrak data postingan komunitas.
 * Dipetakan dari PostController & CompanyPresenter::post().
 */

export type PostItem = {
    id: number;
    description: string;
    image_url: string | null;
    created_at: string | null;
    updated_at: string | null;
    author_id: number;
    author_name: string;
    author_avatar: string | null;
    author_role: 'eo' | 'sponsor';
    author_role_label: string;
    logo_url: string | null;
    company_name: string | null;
    href: string;
};

export type PostPaginator = {
    data: PostItem[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

export type PostImageConfig = {
    max_kb: number;
    extensions: string[];
};

export type PostScope = 'all' | 'mine';
