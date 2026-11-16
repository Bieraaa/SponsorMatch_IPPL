
<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\CompanyCampaignController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\CompanyRecommendationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SponsorRecommendationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::get('/', fn () => Inertia::render('landing/Index'))->name('home');

/*
|--------------------------------------------------------------------------
| Login dengan Google (OAuth 2.0)
|--------------------------------------------------------------------------
| /auth/google/redirect  → diarahkan ke halaman persetujuan Google
| /auth/google/callback  → Google mengembalikan user ke sini
|
| Catatan: route ini SENGAJA di luar middleware `guest`. Kalau user punya
| session lama yang masih hidup, middleware `guest` akan memantulkannya ke
| dashboard sebelum callback sempat diproses. Pengecekan "sudah login atau
| belum" dilakukan di dalam controller.
*/
Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->name('auth.google.redirect');

Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('auth.google.callback');

/*
|--------------------------------------------------------------------------
| Pendaftaran per peran (Blok A)
|--------------------------------------------------------------------------
| Alur: pengguna memilih peran di /register, lalu diarahkan ke form yang
| sesuai. Keduanya memakai controller Fortify yang sama (RegisteredUserController)
| agar Inertia merender halaman sesuai nama route (lihat FortifyServiceProvider).
| Form di setiap halaman tetap POST ke route `register.store` (/register).
*/
Route::middleware('guest')->group(function () {
    Route::get('register/eo', [RegisteredUserController::class, 'create'])
        ->name('register.eo');

    Route::get('register/perusahaan', [RegisteredUserController::class, 'create'])
        ->name('register.perusahaan');
});

/*
|--------------------------------------------------------------------------
| Verifikasi Kode OTP
|--------------------------------------------------------------------------
| Halaman OTP dipakai untuk memverifikasi alamat email user.
| Setelah register berhasil, user diarahkan ke sini (lihat
| App\Http\Responses\RegisterResponse) sebelum boleh masuk dashboard.
|
| Middleware 'auth' saja — TAPI 'verified', karena user yang belum
| verifikasi justru HARUS bisa membuka halaman ini.
*/
Route::middleware('auth')->group(function () {
    // Throttle 10/menit: halaman ini bisa MEMBUAT kode OTP baru (kalau
    // kode sebelumnya sudah expired). Tanpa throttle, user (atau bot yang
    // sudah login) bisa refresh terus-menerus → membanjiri inbox email
    // sendiri dengan kode baru (email bombing) tanpa batas.
    Route::get('verify-otp', [OtpController::class, 'show'])
        ->middleware('throttle:10,1')
        ->name('otp.show');

    Route::post('verify-otp/send', [OtpController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('otp.send');

    Route::post('verify-otp/verify', [OtpController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('otp.verify');
});

Route::middleware(['auth', 'verified:otp.show'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    /*
    |----------------------------------------------------------------------
    | Halaman "Proposal Saya" (Event Organizer)
    |----------------------------------------------------------------------
    | Satu route utama (GET /proposals) untuk seluruh interaksi halaman:
    | pencarian, tab status, periode grafik, pagination, dan pemilihan
    | proposal pada panel detail memakai query string + partial reload
    | Inertia (lihat resources/js/pages/proposals/Index.vue).
    |
    | Route aksi:
    |  - GET  /proposals/{proposal}/dokumen  → unduh dokumen (hanya pemilik
    |          proposal atau sponsor yang menerima proposal tersebut).
    |  - POST /proposals/{tracking}/tindak-lanjut → catat follow-up baru
    |          ke tabel `follow_up_logs`.
    */
    Route::get('proposals', [ProposalController::class, 'index'])
        ->name('proposals.index');

    Route::post('proposals', [ProposalController::class, 'store'])
        ->name('proposals.store');

    // Ekstraksi isi PDF → isi form otomatis (sebelum route dengan parameter).
    Route::post('proposals/ekstrak', [ProposalController::class, 'extract'])
        ->name('proposals.ekstrak');

    Route::post('proposals/{proposal}/publish', [ProposalController::class, 'publish'])
        ->name('proposals.publish');

    Route::get('proposals/{proposal}/send', [ProposalController::class, 'showSendForm'])
        ->name('proposals.send');

    Route::post('proposals/{proposal}/send', [ProposalController::class, 'send'])
        ->name('proposals.send.store');

    Route::get('proposals/{proposal}/dokumen', [ProposalController::class, 'download'])
        ->name('proposals.dokumen');

    Route::post('proposals/{tracking}/tindak-lanjut', [ProposalController::class, 'followUp'])
        ->name('proposals.tindak-lanjut');

    /*
    |----------------------------------------------------------------------
    | Rekomendasi Sponsor (Event Organizer)
    |----------------------------------------------------------------------
    | GET  /recommendations          → daftar rekomendasi (6 per halaman)
    | POST /recommendations/refresh  → paksa analisis ulang dengan AI
    */
    Route::get('recommendations', [SponsorRecommendationController::class, 'index'])
        ->name('recommendations.index');

    Route::post('recommendations/refresh', [SponsorRecommendationController::class, 'refresh'])
        ->name('recommendations.refresh');

    /*
    |----------------------------------------------------------------------
    | Profil & campaign perusahaan
    |----------------------------------------------------------------------
    | Halaman detail dapat dibuka semua pengguna login (EO melihat profil
    | sponsor). Pengelolaan profil/campaign dibatasi role perusahaan di
    | dalam controller.
    */
    Route::get('companies/{company}', [CompanyProfileController::class, 'show'])
        ->name('companies.show');

    Route::prefix('company')->name('company.')->group(function () {
        Route::get('profile', [CompanyProfileController::class, 'edit'])
            ->name('profile.edit');

        Route::patch('profile', [CompanyProfileController::class, 'update'])
            ->name('profile.update');

        Route::get('campaigns', [CompanyCampaignController::class, 'index'])
            ->name('campaigns.index');

        // Ekstraksi PDF campaign → isi form. Harus di atas route {campaign}.
        Route::post('campaigns/ekstrak', [CompanyCampaignController::class, 'extract'])
            ->name('campaigns.ekstrak');

        Route::post('campaigns', [CompanyCampaignController::class, 'store'])
            ->name('campaigns.store');

        Route::patch('campaigns/{campaign}', [CompanyCampaignController::class, 'update'])
            ->name('campaigns.update');

        Route::post('campaigns/{campaign}/status', [CompanyCampaignController::class, 'updateStatus'])
            ->name('campaigns.status');

        Route::delete('campaigns/{campaign}', [CompanyCampaignController::class, 'destroy'])
            ->name('campaigns.destroy');

        /*
        |------------------------------------------------------------------
        | Rekomendasi proposal EO untuk perusahaan
        |------------------------------------------------------------------
        | GET  /company/recommendations                          → daftar
        | POST /company/recommendations/refresh                  → analisis ulang
        | POST /company/recommendations/{proposal}/interest      → ajukan
        |      penawaran lewat alur trackingpipeline yang sudah ada
        */
        Route::get('recommendations', [CompanyRecommendationController::class, 'index'])
            ->name('recommendations.index');

        Route::post('recommendations/refresh', [CompanyRecommendationController::class, 'refresh'])
            ->name('recommendations.refresh');

        Route::post('recommendations/{proposal}/interest', [CompanyRecommendationController::class, 'interest'])
            ->name('recommendations.interest');
    });
});

/*
|--------------------------------------------------------------------------
| Halaman Sponsor (Perusahaan)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified:otp.show'])->prefix('sponsor')->name('sponsor.')->group(function () {
    Route::get('proposals', [App\Http\Controllers\Sponsor\ProposalController::class, 'index'])->name('proposals.index');
    Route::get('proposals/{tracking}', [App\Http\Controllers\Sponsor\ProposalController::class, 'show'])->name('proposals.show');
    Route::post('proposals/{tracking}/offer', [App\Http\Controllers\Sponsor\ProposalController::class, 'offer'])->name('proposals.offer');
    Route::post('proposals/{tracking}/respond', [App\Http\Controllers\Sponsor\ProposalController::class, 'respond'])->name('proposals.respond');
    Route::get('proposals/{proposal}/dokumen', [App\Http\Controllers\Sponsor\ProposalController::class, 'download'])->name('proposals.dokumen');
});

require __DIR__.'/settings.php';
