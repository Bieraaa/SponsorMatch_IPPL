<?php

namespace Tests\Feature\Auth;

use App\Models\CompanyProfile;
use App\Models\EoProfile;
use App\Models\User;
use App\Support\GoogleRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Login & pendaftaran melalui Google (OAuth 2.0).
 *
 * Alur yang diuji:
 *   /auth/google/redirect  → ke halaman persetujuan Google
 *   /auth/google/callback  → Google mengembalikan user
 *       - email SUDAH terdaftar → login + sinkron + ke dashboard
 *       - email BELUM terdaftar → simpan profil di session → ke /register
 *
 * Test ini memverifikasi:
 *   - route redirect benar-benar mengarah ke Google
 *   - user yang emailnya sudah ada langsung bisa login
 *   - email Google otomatis ditandai terverifikasi
 *   - user nonaktif tetap tidak bisa masuk
 *   - email baru → profil disimpan di session, akun BELUM dibuat
 *   - pendaftaran lanjutan memakai email dari SESSION, bukan dari form
 *   - password tidak diminta pada mode Google (dibuat acak server)
 *   - tabel profil per peran tetap terbentuk
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pasang tiruan Socialite yang mengembalikan user Google palsu.
     */
    private function fakeGoogleUser(string $email, string $name = 'Budi Santoso'): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.test/avatar.png');

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);
    }

    // ------------------------------------------------------------------
    // Route redirect
    // ------------------------------------------------------------------

    public function test_redirect_route_sends_user_to_google(): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/v2/auth?client_id=x'));

        Socialite::shouldReceive('driver')->andReturn($provider);

        $this->get(route('auth.google.redirect'))
            ->assertRedirectContains('accounts.google.com');
    }

    // ------------------------------------------------------------------
    // Callback — email sudah terdaftar
    // ------------------------------------------------------------------

    public function test_existing_user_is_logged_in_through_google(): void
    {
        $user = User::factory()->create(['email' => 'lama@sponsormatch.test']);

        $this->fakeGoogleUser('lama@sponsormatch.test', 'Budi Santoso');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
    }

    public function test_google_login_marks_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'baru@sponsormatch.test']);

        $this->fakeGoogleUser('baru@sponsormatch.test');

        $this->get(route('auth.google.callback'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_google_login_invalidates_password_of_unverified_account(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'korban@sponsormatch.test',
            'password' => 'password-penyerang-123',
        ]);

        $this->fakeGoogleUser('korban@sponsormatch.test');

        $this->get(route('auth.google.callback'));

        $fresh = $user->fresh();

        $this->assertNotNull($fresh->email_verified_at);
        $this->assertFalse(
            \Illuminate\Support\Facades\Hash::check('password-penyerang-123', $fresh->password),
        );
    }

    public function test_google_login_fills_name_only_when_it_is_still_empty(): void
    {
        $user = User::factory()->create([
            'email' => 'lama@sponsormatch.test',
            'name' => 'Nama Manual User',
        ]);

        $this->fakeGoogleUser('lama@sponsormatch.test', 'Nama Dari Google');

        $this->get(route('auth.google.callback'));

        // Nama yang sudah diedit user manual TIDAK boleh ditimpa diam-diam.
        $this->assertSame('Nama Manual User', $user->fresh()->name);
    }

    public function test_inactive_user_cannot_login_through_google(): void
    {
        User::factory()->create([
            'email' => 'nonaktif@sponsormatch.test',
            'isActive' => false,
        ]);

        $this->fakeGoogleUser('nonaktif@sponsormatch.test');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'));

        $this->assertFalse(Auth::check());
    }

    // ------------------------------------------------------------------
    // Callback — email baru
    // ------------------------------------------------------------------

    public function test_new_email_is_stored_in_session_and_redirected_to_register(): void
    {
        $this->fakeGoogleUser('baru@sponsormatch.test', 'Budi Santoso');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('register'));

        // Akun BELUM dibuat di tahap ini — baru setelah peran dipilih.
        $this->assertDatabaseMissing('users', ['email' => 'baru@sponsormatch.test']);

        $this->assertSame('baru@sponsormatch.test', session(GoogleRegistration::SESSION_KEY)['email']);
    }

    public function test_register_screen_receives_google_profile(): void
    {
        $this->fakeGoogleUser('baru@sponsormatch.test', 'Budi Santoso');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('register'));

        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/register/Index')
                ->where('google.email', 'baru@sponsormatch.test')
                ->where('google.name', 'Budi Santoso')
            );
    }

    // ------------------------------------------------------------------
    // Pendaftaran lanjutan (mode Google)
    // ------------------------------------------------------------------

    public function test_registration_with_google_profile_creates_verified_account(): void
    {
        $this->fakeGoogleUser('baru@sponsormatch.test', 'Budi Santoso');

        $this->get(route('auth.google.callback'));

        $response = $this->post(route('register.store'), [
            'role' => 'eo',
            'name' => 'Budi Santoso',
            'email' => 'baru@sponsormatch.test',
            'jabatan' => 'Ketua Komunitas',
            'nomor_telepon' => '081234567890',
            'nama_event_organizer' => 'TechFest Bandung',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'baru@sponsormatch.test')->firstOrFail();

        $this->assertSame('eo', $user->role->value);
        // Email sudah diverifikasi oleh Google → tidak perlu OTP.
        $this->assertNotNull($user->email_verified_at);
        // Password dibuat acak server, tapi tetap ter-hash (tidak polos).
        $this->assertStringStartsWith('$2y$', $user->password);
        $this->assertTrue($user->hasVerifiedEmail());

        // Profil per peran tetap dibuat.
        $this->assertDatabaseHas('eo_profiles', [
            'idUser' => $user->id,
            'eo_name' => 'TechFest Bandung',
        ]);

        // Sesi Google dibersihkan setelah pendaftaran selesai.
        $this->assertNull(session(GoogleRegistration::SESSION_KEY));
    }

    public function test_registration_with_google_profile_ignores_email_from_form(): void
    {
        $this->fakeGoogleUser('asli@sponsormatch.test', 'Budi Santoso');

        $this->get(route('auth.google.callback'));

        // User mencoba menulis email lain di form.
        $this->post(route('register.store'), [
            'role' => 'eo',
            'name' => 'Budi Santoso',
            'email' => 'entah@sponsormatch.test',
            'jabatan' => 'Ketua Komunitas',
            'nomor_telepon' => '081234567890',
            'nama_event_organizer' => 'TechFest Bandung',
        ])->assertRedirect(route('dashboard'));

        // Akun DIBUAT dengan email dari Google, bukan dari form.
        $this->assertDatabaseHas('users', ['email' => 'asli@sponsormatch.test']);
        $this->assertDatabaseMissing('users', ['email' => 'entah@sponsormatch.test']);
    }

    public function test_registration_with_google_profile_does_not_require_password(): void
    {
        $this->fakeGoogleUser('baru@sponsormatch.test', 'Budi Santoso');

        $this->get(route('auth.google.callback'));

        // Tidak ada field password sama sekali.
        $this->post(route('register.store'), [
            'role' => 'sponsor',
            'name' => 'Budi Santoso',
            'email' => 'baru@sponsormatch.test',
            'jabatan' => 'Marketing Manager',
            'nomor_telepon' => '081234567890',
            'nama_perusahaan' => 'PT Contoh',
            'npwp' => '12.345.678.9-012.000',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'baru@sponsormatch.test')->firstOrFail();

        $this->assertSame('sponsor', $user->role->value);
        $this->assertDatabaseHas('company_profiles', [
            'idUser' => $user->id,
            'companyName' => 'PT Contoh',
        ]);
    }

    public function test_registration_without_google_profile_still_requires_password(): void
    {
        // Tidak ada profil Google di session → password tetap wajib.
        $this->post(route('register.store'), [
            'role' => 'eo',
            'name' => 'Budi Santoso',
            'email' => 'biasa@sponsormatch.test',
            'jabatan' => 'Ketua Komunitas',
            'nomor_telepon' => '081234567890',
            'nama_event_organizer' => 'TechFest Bandung',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'biasa@sponsormatch.test']);
    }
}
