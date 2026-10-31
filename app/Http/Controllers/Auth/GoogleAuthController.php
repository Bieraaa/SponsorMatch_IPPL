<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GoogleRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Login & pendaftaran melalui Google (OAuth 2.0).
 *
 * DUA ROUTE:
 *   GET /auth/google/redirect  → Potter ke halaman persetujuan Google.
 *   GET /auth/google/callback  → Google mengembalikan user ke sini.
 *
 * KENAPA TIDAK PAKAI SCOPE 'openid'?
 *   Kita hanya butuh email + nama + foto. Scope default Socialite untuk
 *   Google sudah `email` + `profile`, jadi tidak perlu menambah scope apa pun
 *   dan tidak perlu mengaktifkan API tambahan di Google Cloud Console.
 *
 * KENAPA EMAIL TIDAK DIPERCAYA DARI FORM?
 *   Pada alur email baru, profil Google disimpan di session
 *   (App\Support\GoogleRegistration) dan pembuatan akun tetap dilakukan di
 *   App\Actions\Fortify\CreateNewUser. Jadi ada SATU jalan untuk membuat
 *   akun — tidak ada bypass validasi.
 */
class GoogleAuthController extends Controller
{
    /**
     * Arahkan user ke halaman persetujuan Google.
     */
    public function redirect(Request $request): SymfonyRedirect
    {
        // Sudah login? Tidak perlu daftar/masuk lagi.
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return Socialite::driver('google')
            ->scopes(['email', 'profile'])
            ->redirect();
    }

    /**
     * Callback dari Google: samakan akun Google dengan akun SponsorMatch.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // User menolak persetujuan, state tidak cocok, atau token kedaluwarsa.
            report($e);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Login dengan Google gagal. Silakan coba lagi.']);
        }

        $email = $googleUser->getEmail();

        if (blank($email)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Akun Google tersebut tidak memiliki email. '
                        .'Pastikan email Google kamu aktif, lalu coba lagi.',
                ]);
        }

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            return $this->loginExistingUser($request, $user, $googleUser);
        }

        // --- EMAIL BARU ---------------------------------------------------
        // Akun belum dibuat. Simpan profilnya dulu di session, lalu arahkan
        // user ke layar "pilih peran" (/register) untuk melengkapi data.
        GoogleRegistration::put([
            'email' => $email,
            'name' => $googleUser->getName() ?: ($googleUser->getNickname() ?? ''),
            'avatar' => $googleUser->getAvatar(),
        ]);

        return redirect()->route('register');
    }

    /**
     * Email sudah terdaftar → sinkronkan sedikit lalu masuk.
     */
    private function loginExistingUser(Request $request, User $user, mixed $googleUser): RedirectResponse
    {
        // Akun yang dinonaktifkan tidak boleh masuk, sama seperti login biasa.
        if (! $user->isActive) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Akun kamu sedang dinonaktifkan. Hubungi admin.']);
        }

        $changes = [];

        // Google sudah MEMBUKTIKAN kepemilikan email, jadi akun yang belum
        // terverifikasi boleh ditandai terverifikasi.
        //
        // TAPI akun itu bisa saja dibuat ORANG LAIN yang mengetik email ini
        // saat mendaftar (pre-hijacking) — dia tahu passwordnya. Maka semua
        // "kunci lama" dibuang: password diacak, remember token diganti,
        // dan semua session lama dihapus.
        if ($user->email_verified_at === null) {
            $changes['email_verified_at'] = now();
            $changes['password'] = Str::password(32);
            $changes['remember_token'] = Str::random(60);
            $this->revokeAllSessions($user);
        }

        // Sinkron nama HANYA kalau kolomnya masih kosong — supaya nama yang
        // sudah diedit user manual tidak ketimpa diam-diam.
        if (blank($user->name)) {
            $name = $googleUser->getName() ?: ($googleUser->getNickname() ?? '');
            if ($name !== '') {
                $changes['name'] = $name;
            }
        }

        if ($changes !== []) {
            $user->forceFill($changes)->save();
        }

        // remember: true → user tidak perlu login ulang di HP/WA yang sama.
        Auth::login($user, remember: true);

        // Regenerasi session ID mencegah session fixation.
        $request->session()->regenerate();
        $request->session()->forget(GoogleRegistration::SESSION_KEY);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Hapus semua session aktif milik user. Hanya berlaku bila session
     * disimpan di database (SESSION_DRIVER=database); selain itu dilewati.
     */
    private function revokeAllSessions(User $user): void 
    {
    if (config('session.driver') !== 'database') {
        return;
    }

    DB::table(config('session.table', 'sessions'))
        ->where('user_id', $user->getKey())
        ->delete();
    }
}
