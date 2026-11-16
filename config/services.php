<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google (OAuth 2.0) — untuk login "Lanjutkan dengan Google"
    |--------------------------------------------------------------------------
    |
    | Kredensial diambil dari Google Cloud Console:
    |   APIs & Services → Credentials → Create OAuth client ID (Web app).
    |
    | `redirect` WAJIB sama persis dengan "Authorized redirect URIs" yang
    | didaftarkan di Google. Kalau tidak sama, Google akan menolak dengan
    | error "redirect_uri_mismatch".
    |
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/auth/google/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini — analisis kecocokan sponsor (AI Matching)
    |--------------------------------------------------------------------------
    |
    | API key dibuat di Google AI Studio. Model default `gemini-3.8-flash`
    | dipilih agar biaya rendah dan respons cepat; bisa diganti lewat .env
    | tanpa mengubah kode.
    |
    | `verify_ssl` dapat dimatikan SEMENTARA di lingkungan lokal yang
    | sertifikat CA-nya bermasalah. Jangan dimatikan di produksi.
    |
    */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 45),
        'pdf_timeout' => (int) env('GEMINI_PDF_TIMEOUT', 90),
        'verify_ssl' => filter_var(env('GEMINI_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    ],

];
