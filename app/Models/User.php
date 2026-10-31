<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Akun pengguna — SATU-SATUNYA tabel user aplikasi (`users`).
 */
#[Fillable(['name', 'email', 'role', 'password', 'nomor_telepon', 'jabatan', 'avatar'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'rating' => 'decimal:1',
            'isActive' => 'boolean',
            'last_login_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * URL publik foto profil.
     *
     * Di database hanya disimpan PATH relatif (mis. `avatars/12/x.jpg`).
     * Accessor ini mengubahnya menjadi URL yang bisa dipakai langsung oleh
     * `<img src>` / AvatarImage. Nilai absolut (mis. foto lama dari Google)
     * dibiarkan apa adanya.
     *
     * Gunakan `$user->getRawOriginal('avatar')` bila butuh path mentah
     * (mis. saat menghapus file lama dari storage).
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if (blank($value)) {
                    return null;
                }

                if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                    return $value;
                }

                return Storage::disk(config('sponsormatch.profile_photo.disk', 'public'))
                    ->url($value);
            },
        );
    }

    public function eoProfile(): HasOne
    {
        return $this->hasOne(EoProfile::class, 'user_id');
    }

    public function companyProfile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class, 'user_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function isEventOrganizer(): bool
    {
        return $this->role === UserRole::EventOrganizer;
    }

    public function isCompany(): bool
    {
        return $this->role === UserRole::Perusahaan;
    }

    /**
     * Relasi notifikasi (tabel notifications standar Laravel).
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->orderByDesc('created_at');
    }

    /**
     * Notifikasi yang belum dibaca.
     */
    public function unreadNotifications(): MorphMany
    {
        return $this->notifications()->whereNull('read_at');
    }
}
