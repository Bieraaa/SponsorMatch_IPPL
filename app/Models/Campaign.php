<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Campaign sponsorship milik perusahaan (tabel `campaign`).
 *
 * Campaign adalah penawaran/kebutuhan sponsorship yang dibuat perusahaan.
 * Campaign bersifat OPSIONAL: perusahaan tetap bisa muncul di rekomendasi
 * EO hanya dengan profil perusahaan.
 *
 * Hanya campaign berstatus `active` yang belum melewati `periodEnd` dan
 * belum dihapus (`deleteAt`) yang dianggap sebagai peluang aktif.
 *
 * @property int $idCampaign
 * @property int $idUser
 * @property int $idKategori
 * @property string $title
 * @property string $description
 * @property string|null $purpose
 * @property string|null $benefits
 * @property string|null $target_audience
 * @property int $budgetMin
 * @property int $budgetMax
 * @property string $supportType
 * @property int $estimatedParticipant
 * @property Carbon $periodStart
 * @property Carbon $periodEnd
 * @property string $file_path
 * @property string|null $banner_path
 * @property string $status
 * @property Carbon|null $deleteAt
 */
#[Fillable([
    'idUser',
    'idKategori',
    'title',
    'description',
    'purpose',
    'benefits',
    'target_audience',
    'budgetMin',
    'budgetMax',
    'supportType',
    'estimatedParticipant',
    'periodStart',
    'periodEnd',
    'file_path',
    'banner_path',
    'status',
    'deleteAt',
])]
class Campaign extends Model
{
    protected $table = 'campaign';

    protected $primaryKey = 'idCampaign';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updateAt';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budgetMin' => 'integer',
            'budgetMax' => 'integer',
            'estimatedParticipant' => 'integer',
            'periodStart' => 'date',
            'periodEnd' => 'date',
            'deleteAt' => 'datetime',
            'createdAt' => 'datetime',
            'updateAt' => 'datetime',
        ];
    }

    /**
     * Campaign yang belum dihapus/berakhir secara manual.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('deleteAt');
    }

    /**
     * Campaign yang benar-benar aktif sebagai peluang sponsorship:
     * status `active`, belum dihapus, dan `periodEnd` belum lewat.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereNull('deleteAt')
            ->whereDate('periodEnd', '>=', now()->toDateString());
    }

    /**
     * Apakah campaign ini masih dianggap aktif pada hari ini.
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->deleteAt === null
            && $this->periodEnd !== null
            && $this->periodEnd->endOfDay()->isFuture();
    }

    /**
     * URL publik banner campaign (null bila belum diunggah).
     */
    public function bannerUrl(): ?string
    {
        if (blank($this->banner_path)) {
            return null;
        }

        if (str_starts_with((string) $this->banner_path, 'http://')
            || str_starts_with((string) $this->banner_path, 'https://')) {
            return $this->banner_path;
        }

        return Storage::disk('public')->url($this->banner_path);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser', 'id');
    }

    /**
     * @return BelongsTo<Kategori, $this>
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'idKategori', 'idKategori');
    }

    /**
     * @return HasMany<TrackingPipeline, $this>
     */
    public function trackings(): HasMany
    {
        return $this->hasMany(TrackingPipeline::class, 'idCampaign', 'idCampaign');
    }
}
