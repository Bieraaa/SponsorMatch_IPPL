
<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
Route::get('auth/google/redirect', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])
    ->name('auth.google.redirect');

Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])
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
    Route::get('register/eo', [\Laravel\Fortify\Http\Controllers\RegisteredUserController::class, 'create'])
        ->name('register.eo');

    Route::get('register/perusahaan', [\Laravel\Fortify\Http\Controllers\RegisteredUserController::class, 'create'])
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
    Route::get('verify-otp', [\App\Http\Controllers\OtpController::class, 'show'])
        ->middleware('throttle:10,1')
        ->name('otp.show');

    Route::post('verify-otp/send', [\App\Http\Controllers\OtpController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('otp.send');

    Route::post('verify-otp/verify', [\App\Http\Controllers\OtpController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('otp.verify');
});


Route::middleware(['auth', 'verified:otp.show'])->group(function () {
    Route::inertia('dashboard', 'dashboard/Index')->name('dashboard');

    Route::get('/search', [\App\Http\Controllers\SearchController::class, 'index'])->name('search');

    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

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
    Route::get('proposals', [\App\Http\Controllers\ProposalController::class, 'index'])
        ->name('proposals.index');

    Route::post('proposals', [\App\Http\Controllers\ProposalController::class, 'store'])
        ->name('proposals.store');

    // Ekstraksi isi PDF → isi form otomatis (sebelum route dengan parameter).
    Route::post('proposals/ekstrak', [\App\Http\Controllers\ProposalController::class, 'extract'])
        ->name('proposals.ekstrak');

    Route::post('proposals/{proposal}/publish', [\App\Http\Controllers\ProposalController::class, 'publish'])
        ->name('proposals.publish');

    Route::get('proposals/{proposal}/send', [\App\Http\Controllers\ProposalController::class, 'showSendForm'])
        ->name('proposals.send');

    Route::post('proposals/{proposal}/send', [\App\Http\Controllers\ProposalController::class, 'send'])
        ->name('proposals.send.store');

    Route::get('proposals/{proposal}/dokumen', [\App\Http\Controllers\ProposalController::class, 'download'])
        ->name('proposals.dokumen');

    Route::post('proposals/{tracking}/tindak-lanjut', [\App\Http\Controllers\ProposalController::class, 'followUp'])
        ->name('proposals.tindak-lanjut');
});

/*
|--------------------------------------------------------------------------
| Halaman Sponsor (Perusahaan)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified:otp.show'])->prefix('sponsor')->name('sponsor.')->group(function () {
    Route::get('proposals', [\App\Http\Controllers\Sponsor\ProposalController::class, 'index'])->name('proposals.index');
    Route::get('proposals/{tracking}', [\App\Http\Controllers\Sponsor\ProposalController::class, 'show'])->name('proposals.show');
    Route::post('proposals/{tracking}/offer', [\App\Http\Controllers\Sponsor\ProposalController::class, 'offer'])->name('proposals.offer');
    Route::post('proposals/{tracking}/respond', [\App\Http\Controllers\Sponsor\ProposalController::class, 'respond'])->name('proposals.respond');
    Route::get('proposals/{proposal}/dokumen', [\App\Http\Controllers\Sponsor\ProposalController::class, 'download'])->name('proposals.dokumen');
});

require __DIR__.'/settings.php';
