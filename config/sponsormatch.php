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

];
