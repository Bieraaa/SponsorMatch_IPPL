<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Foto profil pengguna
    |--------------------------------------------------------------------------
    |
    | Konfigurasi ini dipakai bersama oleh validasi backend
    | (ProfileUpdateRequest) dan tampilan frontend (halaman Profil) supaya
    | batas ukuran / format yang ditampilkan selalu sama dengan yang benar-
    | benar divalidasi server.
    |
    | `max_kb` mengikuti satuan aturan validasi `max` untuk file di Laravel
    | (kilobyte). Nilai bawaan 2048 KB = 2 MB, sesuai kebutuhan aplikasi.
    |
    */

    'profile_photo' => [
        'disk' => 'public',
        'max_kb' => (int) env('PROFILE_PHOTO_MAX_KB', 2048),
        'extensions' => ['jpg', 'jpeg', 'png'],
        'mimetypes' => ['image/jpeg', 'image/png'],
        'directory' => 'avatars',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logo perusahaan & banner campaign
    |--------------------------------------------------------------------------
    | Dipakai bersama oleh validasi backend dan halaman Vue supaya batas
    | ukuran/format yang ditampilkan sama dengan yang divalidasi server.
    */
    'company_logo' => [
        'disk' => 'public',
        'max_kb' => (int) env('COMPANY_LOGO_MAX_KB', 2048),
        'extensions' => ['jpg', 'jpeg', 'png'],
        'mimetypes' => ['image/jpeg', 'image/png'],
        'directory' => 'company-logos',
    ],

    'campaign_banner' => [
        'disk' => 'public',
        'max_kb' => (int) env('CAMPAIGN_BANNER_MAX_KB', 2048),
        'extensions' => ['jpg', 'jpeg', 'png'],
        'mimetypes' => ['image/jpeg', 'image/png'],
        'directory' => 'campaign-banners',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rekomendasi sponsor
    |--------------------------------------------------------------------------
    | `per_page` dikunci 6 sesuai desain (grid 3 kolom × 2 baris) dan
    | `max_candidates` membatasi jumlah perusahaan yang dikirim ke Gemini
    | setelah pre-filter agar hemat kuota API.
    */
    'recommendations' => [
        'per_page' => 6,
        'max_candidates' => 24,
        'company_max_candidates' => 24,
        'max_active_campaigns' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ekstraksi PDF (proposal & campaign)
    |--------------------------------------------------------------------------
    | `max_kb` mengikuti satuan validasi `max` Laravel. Nilai ini dipakai
    | bersama oleh backend dan area upload di frontend.
    */
    'pdf' => [
        'max_kb' => (int) env('PDF_MAX_KB', 30720),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gambar postingan
    |--------------------------------------------------------------------------
    | Dipakai bersama oleh PostController dan halaman Postingan. `max_kb`
    | mengikuti satuan validasi `max` Laravel (kilobyte).
    */
    'post_image' => [
        'disk' => 'public',
        'max_kb' => (int) env('POST_IMAGE_MAX_KB', 4096),
        'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'mimetypes' => ['image/jpeg', 'image/png', 'image/webp'],
        'directory' => 'posts',
    ],

];
