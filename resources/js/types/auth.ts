export type User = {
    id: number;
    name: string;
    email: string;
    /** Peran akun: 'eo' (Event Organizer) atau 'sponsor' (Perusahaan). */
    role?: 'eo' | 'sponsor';
    /** URL publik foto profil (hasil accessor User::avatar di backend). */
    avatar?: string | null;
    /** Nomor telepon internasional, boleh kosong. */
    nomor_telepon?: string | null;
    /** Jabatan profesional yang diisi saat pendaftaran. */
    jabatan?: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
