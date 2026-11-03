<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ulasan antar pengguna setelah kerja sama (tabel `riviews`).
 *
 * Satu baris menghubungkan tracking pipeline dengan dua akun: pengirim
 * ulasan (`from_user_id`) dan akun yang dinilai (`to_user_id`).
 *
 * Nama tabel/kolom mengikuti ERD apa adanya, termasuk `riviews` dan
 * `idRiviews` yang sengaja tidak diubah agar cocok dengan database MySQL.
 *
 * @property int $idRiviews
 * @property int $idTracking
 * @property int $from_user_id
 * @property int $to_user_id
 * @property int $rating
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon $createdAt
 * @property \Illuminate\Support\Carbon|null $updateAt
 */
#[Fillable([
    'idTracking',
    'from_user_id',
    'to_user_id',
    'rating',
    'comment',
])]
class Review extends Model
{
    protected $table = 'riviews';

    protected $primaryKey = 'idRiviews';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updateAt';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'createdAt' => 'datetime',
            'updateAt' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TrackingPipeline, $this>
     */
    public function tracking(): BelongsTo
    {
        return $this->belongsTo(TrackingPipeline::class, 'idTracking', 'idTracking');
    }

    /**
     * Akun yang menulis ulasan.
     *
     * @return BelongsTo<User, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id', 'id') ->withTrashed() ;
    }

    /**
     * Akun yang menerima ulasan.
     *
     * @return BelongsTo<User, $this>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id', 'id');
    }
}
