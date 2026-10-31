<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\CompanyProfile;
use App\Models\EoProfile;
use App\Models\User;
use App\Support\GoogleRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Membuat akun baru saat pendaftaran (Blok A).
 *
 * ALUR: pengguna memilih peran DULU (layar "Role Pick"), lalu mengisi
 * form yang BERBEDA per peran:
 *
 *   Event Organizer (/register/eo)
 *     nama, email, password, konfirmasi password, jabatan,
 *     nomor_telepon, nama_event_organizer
 *
 *   Perusahaan (/register/perusahaan)
 *     nama, email, nomor_telepon, jabatan, password, konfirmasi password,
 *     nama_perusahaan, npwp, nib
 *
 * KENAPA FORM DIPISAH: field yang diminta berbeda, jadi validasinya pun
 * berbeda. Memaksa satu form untuk dua peran hanya menghasilkan field
 * yang tidak relevan dan validasi yang membingungkan.
 *
 * PENYIMPANAN (2 tabel sekaligus):
 *   `users`                    → data akun (nama, email, password, role, jabatan, telepon)
 *   `eo_profiles` ATAU
 *   `company_profiles`         → data khusus peran (nama EO / nama perusahaan, npwp, nib)
 *
 * Keduanya dibungkus DB::transaction: kalau pembuatan profil gagal,
 * akun di `users` ikut dibatalkan. Jadi tidak akan pernah ada
 * "akun tanpa profil" yang setengah jadi.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;
    
    /** Maksimal pendaftaran valid per IP dalam 1 jam. */
    private const MAX_REGISTRATIONS_PER_HOUR = 10;

    /** Akun belum terverifikasi lebih tua dari ini boleh ditimpa pendaftaran baru. */
    private const UNVERIFIED_TTL_HOURS = 24;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $role = $input['role'] ?? null;

        // ---------------------------------------------------------------
        // MODE GOOGLE: profil Google menunggu di session.
        //
        // Kalau user datang lewat tombol "Lanjutkan dengan Google" dan email
        // nya belum terdaftar, callback Google TIDAK membuat akun — dia
        // hanya menyimpan profil di session lalu mengarahkan user ke
        // /register untuk memilih peran dan melengkapi data.
        //
        // Di sini (SATU-SATUNYA tempat pembuatan akun) kita memakai email
        // dari session, bukan dari input form. Jadi user tidak bisa menulis
        // email lain di form dan tetapdiklaim sebagai hasil login Google.
        // ---------------------------------------------------------------
        $google = GoogleRegistration::pending();
        $isGoogle = $google !== null;

        if ($isGoogle) {
            $input['email'] = $google['email'];

            if (blank($input['name'] ?? null)) {
                $input['name'] = $google['name'];
            }
        }

        // Batasi pendaftaran per IP supaya form tidak dipakai membanjiri
        // email orang lain dengan kode OTP.
        $limiterKey = 'register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_REGISTRATIONS_PER_HOUR)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan pendaftaran. Coba lagi dalam {$minutes} menit.",
            ]);
        }

        // Email yang "ditempati" akun lama yang tidak pernah diverifikasi
        // dibebaskan lagi supaya pemilik aslinya bisa mendaftar.
        $this->purgeStaleUnverifiedAccount($input['email'] ?? null);

        Validator::make($input, [
            ...$this->profileRules(),
            'role' => ['required', 'string', Rule::in(UserRole::values())],
            // Pada mode Google tidak ada password yang dibuat user — password
            // acak dibuat server. Jadi aturan password dilewati.
            'password' => $isGoogle ? ['nullable', 'string'] : $this->passwordRules(),
            // Jabatan & nomor telepon diminta di KEDUA form (sesuai Figma).
            'jabatan' => ['required', 'string', 'max:100'],
            'nomor_telepon' => ['required', ...$this->phoneRules()],

            // --- Field khusus Event Organizer ---
            'nama_event_organizer' => [
                Rule::requiredIf($role === UserRole::EventOrganizer->value),
                'nullable', 'string', 'max:150',
            ],
            'eo_field' => ['nullable', 'string', 'max:100'],

            // --- Field khusus Perusahaan ---
            'nama_perusahaan' => [
                Rule::requiredIf($role === UserRole::Perusahaan->value),
                'nullable', 'string', 'max:100',
            ],
            // NPWP: 15–16 digit (format lama 15, format baru 16), boleh
            // memakai titik/hubung. Cek duplikat lewat hash, bukan nilai asli.
            'npwp' => [
                Rule::requiredIf($role === UserRole::Perusahaan->value),
                'nullable', 'string', 'max:32',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    // Hitung jumlah DIGIT-nya, bukan panjang string. Dengan
                    // aturan lama, "..............." (15 titik) ikut lolos.
                    $digits = preg_replace('/\D/', '', (string) $value) ?? '';

                    if (preg_match('/[^0-9.\-]/', (string) $value) || ! in_array(strlen($digits), [15, 16], true)) {
                        $fail('Format NPWP tidak valid (15–16 digit angka).');
                    }

                    if (CompanyProfile::whereNpwp((string) $value)->exists()) {
                        $fail('NPWP ini sudah terdaftar.');
                    }
                },
            ],
            // NIB: opsional — perusahaan baru mungkin belum punya.
            'nib' => [
                'nullable', 'string', 'max:32',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! preg_match('/^[0-9]{8,20}$/', (string) $value)) {
                        $fail('Format NIB tidak valid (8–20 digit angka).');
                    }
                    if (CompanyProfile::whereNib((string) $value)->exists()) {
                        $fail('NIB ini sudah terdaftar.');
                    }
                },
            ],
                ], [
            'nomor_telepon.regex' => 'Format nomor telepon tidak valid. Contoh: +62 812-3456-7890.',
        ])->validate();

        // Data valid → hitung ke pembatas (kedaluwarsa dalam 1 jam).
        RateLimiter::hit($limiterKey, 3600);

        return DB::transaction(function () use ($input, $role, $isGoogle) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'role' => $role,
                'nomor_telepon' => $input['nomor_telepon'],
                'jabatan' => $input['jabatan'],
                // Mode Google: password DIACAK server (32 karakter) supaya
                // kolom `password` tetap terisi & ter-hash otomatis oleh cast
                // `hashed`. User ini tidak pernah memakai password form —
                // dia masuk lewat Google. Password acak ini menutup jalan
                // login paksa lewat form login biasa.
                'password' => $isGoogle ? Str::password(32) : $input['password'],
            ]);

            // Profil sesuai peran — ditulis dalam transaksi yang sama.
            if ($role === UserRole::EventOrganizer->value) {
                EoProfile::create([
                    'idUser' => $user->id,
                    'eo_name' => $input['nama_event_organizer'],
                    'eo_field' => $input['eo_field'] ?? null,
                    'eo_email' => $input['email'],
                ]);
            } else {
                CompanyProfile::create([
                    'idUser' => $user->id,
                    'companyName' => $input['nama_perusahaan'],
                    // Nilai asli → otomatis dienkripsi (cast `encrypted`)
                    // dan hash-nya dihitung oleh accessor di model.
                    'npwp' => $input['npwp'] ?? null,
                    'nib' => $input['nib'] ?? null,
                ]);
            }

            // PENTING: `email_verified_at` SENGAJA TIDAK diisi di sini.
            //
            // Sebelumnya baris ini ada:
            //     $user->forceFill(['email_verified_at' => now()])->save();
            // Itu berarti email TIDAK PERLU diverifikasi sama sekali — siapa
            // pun bisa mendaftar pakai email orang lain dan langsung masuk.
            //
            // Sekarang email diverifikasi beneran:
            //   1. Event `Registered` dilepas → Laravel listener memanggil
            //      User::sendEmailVerificationNotification() → email terkirim.
            //   2. User membuka link / memasukkan kode OTP → email_verified_at
            //      terisi → middleware `verified` mengizinkan akses dashboard.
            //
            // Catatan: user yang belum verifikasi akan diarahkan ke
            // /verify-otp (lihat Fortify::redirects di FortifyServiceProvider).

            // ---------------------------------------------------------------
            // MODE GOOGLE: email SUDAH terverifikasi oleh Google.
            //
            // Google hanya mengembalikan email yang sudah terverifikasi di
            // sisi Google (scope `email`). Jadi kita boleh menandai email
            // terverifikasi TANPA mengirim kode OTP.
            //
            // Efek samping yang bagus: listener `Registered` milik Laravel
            // hanya memanggil sendEmailVerificationNotification() kalau
            // hasVerifiedEmail() masih false — jadi user Google TIDAK
            // menerima email verifikasi yang sia-sia.
            // ---------------------------------------------------------------
            if ($isGoogle) {
                $user->forceFill(['email_verified_at' => now()])->save();

                // Pendaftaran selesai — hapus profil Google dari session
                // supaya tidak ikut terbalik ke form pendaftaran biasa.
                GoogleRegistration::forget();
            }

            return $user;
        });
    }
    
    /**
     * Hapus akun lama dengan email yang sama bila tidak pernah diverifikasi
     * dan sudah lebih dari UNVERIFIED_TTL_HOURS jam. Profil dan kode OTP-nya
     * ikut terhapus lewat foreign key `cascadeOnDelete`.
     */
    private function purgeStaleUnverifiedAccount(?string $email): void
    {
        if (blank($email)) {
            return;
        }

        try {
            User::query()
                ->where('email', $email)
                ->whereNull('email_verified_at')
                ->where('created_at', '<', now()->subHours(self::UNVERIFIED_TTL_HOURS))
                ->delete();
        } catch (\Throwable $e) {
            // Gagal hapus (mis. terhalang foreign key): biarkan aturan
            // `unique` yang menolak pendaftarannya seperti biasa.
            report($e);
        }
    }
}
