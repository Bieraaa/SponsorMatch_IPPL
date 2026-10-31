<?php

namespace Tests\Feature\Auth;

use App\Models\CompanyProfile;
use App\Models\EoProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * Pendaftaran 2 peran (Blok A).
 *
 * Alur: pilih peran → form berbeda per peran → satu transaksi menulis
 * `users` + (`eo_profiles` ATAU `company_profiles`).
 *
 * Test ini memverifikasi:
 *   - ketiga layar bisa dibuka (role pick, form EO, form Perusahaan)
 *   - field wajib divalidasi per peran
 *   - baris profil yang benar dibuat di tabel yang benar
 *   - NPWP/NIB tersimpan terenkripsi & hash-nya terisi
 *   - role tidak sah ditolak
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    // ------------------------------------------------------------------
    // Layar
    // ------------------------------------------------------------------

    public function test_role_pick_screen_can_be_rendered(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_eo_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register.eo'))->assertOk();
    }

    public function test_company_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register.perusahaan'))->assertOk();
    }

    // ------------------------------------------------------------------
    // Event Organizer
    // ------------------------------------------------------------------

    public function test_event_organizer_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'role' => 'eo',
            'name' => 'Budi Santoso',
            'email' => 'budi@eo.test',
            'jabatan' => 'Ketua',
            'nomor_telepon' => '081234567890',
            'nama_event_organizer' => 'Komunitas Seni Nusantara',
            'eo_field' => 'Seni & Budaya',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        // SETELAH register, user diarahkan ke halaman OTP untuk verifikasi
        // email — bukan langsung ke dashboard.
        $response->assertRedirect(route('otp.show', absolute: false));

        $user = User::where('email', 'budi@eo.test')->firstOrFail();
        $this->assertSame('eo', $user->role->value);
        $this->assertSame('Ketua', $user->jabatan);
        $this->assertSame('081234567890', $user->nomor_telepon);

        // Email BELUM terverifikasi — ini justru Feature-nya.
        // Dulu `email_verified_at` di-force jadi `now()` saat register,
        // artinya siapa pun bisa daftar pakai email orang lain.
        $this->assertNull($user->email_verified_at);

        // Profil EO dibuat di tabel yang benar (bukan company_profiles)
        $this->assertDatabaseHas('eo_profiles', [
            'idUser' => $user->id,
            'eo_name' => 'Komunitas Seni Nusantara',
        ]);
        $this->assertDatabaseMissing('company_profiles', ['idUser' => $user->id]);
    }

    public function test_event_organizer_requires_eo_name(): void
    {
        $response = $this->post(route('register.store'), [
            'role' => 'eo',
            'name' => 'Tanpa Nama EO',
            'email' => 'tanpa-eo@test.id',
            'jabatan' => 'Ketua',
            'nomor_telepon' => '0812',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('nama_event_organizer');
        $this->assertGuest();
    }

    
    public function test_registration_rejects_invalid_phone_number(): void
    {
        $response = $this->post(route('register.store'), [
            'role' => 'eo',
            'name' => 'Telepon Salah',
            'email' => 'telepon-salah@test.id',
            'jabatan' => 'Ketua',
            'nomor_telepon' => 'abc',
            'nama_event_organizer' => 'EO Uji',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('nomor_telepon');
        $this->assertGuest();
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            \Illuminate\Support\Facades\RateLimiter::hit('register:127.0.0.1', 3600);
        }

        $response = $this->post(route('register.store'), [
            'role' => 'eo',
            'name' => 'Dibatasi',
            'email' => 'dibatasi@test.id',
            'jabatan' => 'Ketua',
            'nomor_telepon' => '081234567890',
            'nama_event_organizer' => 'EO Uji',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'dibatasi@test.id']);
    }

    // ------------------------------------------------------------------
    // Perusahaan
    // ------------------------------------------------------------------

    public function test_company_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'role' => 'sponsor',
            'name' => 'Siti Aminah',
            'email' => 'siti@corp.test',
            'jabatan' => 'Marketing Manager',
            'nomor_telepon' => '089876543210',
            'nama_perusahaan' => 'PT Maju Jaya',
            'npwp' => '12.345.678.9-012.000',
            'nib' => '1234567890123',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('otp.show', absolute: false));

        $user = User::where('email', 'siti@corp.test')->firstOrFail();
        $this->assertSame('sponsor', $user->role->value);
        $this->assertNull($user->email_verified_at);

        // Profil perusahaan dibuat di tabel yang benar
        $profile = CompanyProfile::where('idUser', $user->id)->firstOrFail();
        $this->assertSame('PT Maju Jaya', $profile->companyName);
        $this->assertDatabaseMissing('eo_profiles', ['idUser' => $user->id]);

        // NPWP/NIB kembali dalam bentuk asli saat dibaca (decrypt otomatis)
        $this->assertSame('12.345.678.9-012.000', $profile->npwp);
        $this->assertSame('1234567890123', $profile->nib);

        // Tetapi TERSIMPAN terenkripsi di database (bukan plaintext).
        $raw = \DB::table('company_profiles')->where('idUser', $user->id)->first();
        $this->assertNotSame('12.345.678.9-012.000', $raw->npwp);
        $this->assertNotSame('1234567890123', $raw->nib);

        // Hash HMAC terisi untuk keperluan deteksi duplikat
        $this->assertNotEmpty($raw->npwp_hash);
        $this->assertNotEmpty($raw->nib_hash);
        $this->assertSame(CompanyProfile::hashSensitive('1234567890123'), $raw->nib_hash);
    }

    public function test_company_requires_company_name_and_npwp(): void
    {
        $response = $this->post(route('register.store'), [
            'role' => 'sponsor',
            'name' => 'Tanpa Perusahaan',
            'email' => 'tanpa-corps@test.id',
            'jabatan' => 'Staf',
            'nomor_telepon' => '0812',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['nama_perusahaan', 'npwp']);
        $this->assertGuest();
    }

    public function test_duplicate_npwp_is_rejected(): void
    {
        CompanyProfile::create([
            'idUser' => User::factory()->create(['role' => 'sponsor'])->id,
            'companyName' => 'PT Pertama',
            'npwp' => '11.111.111.1-111.000',
        ]);

        $response = $this->post(route('register.store'), [
            'role' => 'sponsor',
            'name' => 'Duplikat',
            'email' => 'duplikat@corp.test',
            'jabatan' => 'Staf',
            'nomor_telepon' => '0812',
            'nama_perusahaan' => 'PT Kedua',
            'npwp' => '111111111111000', // format berbeda, nilai sama
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('npwp');
        $this->assertGuest();
    }

    // ------------------------------------------------------------------
    // Role
    // ------------------------------------------------------------------

    public function test_role_is_required_when_registering(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'jabatan' => 'Staf',
            'nomor_telepon' => '0812',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
    }

    public function test_role_must_be_a_valid_value(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test3@example.com',
            'role' => 'hacker',
            'jabatan' => 'Staf',
            'nomor_telepon' => '0812',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
    }

    public function test_jabatan_and_phone_are_required(): void
    {
        $response = $this->post(route('register.store'), [
            'role' => 'eo',
            'name' => 'Test User',
            'email' => 'test4@example.com',
            'nama_event_organizer' => 'EO Test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['jabatan', 'nomor_telepon']);
        $this->assertGuest();
    }
}
