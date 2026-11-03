<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penawaran sponsor terhadap proposal (tabel `offer`).
 *
 * `status`:
 *   pending  → penawaran baru, belum direspons EO
 *   accepted → disetujui (proposal menjadi deal)
 *   rejected → ditolak (disertakan `rejectReason`)
 *   counter  → sponsor countering (perlu revisi EO)
 *
 * @property int $idOffer
 * @property int $idTracking
 * @property int $from_user_id
 * @property int|null $parent_offer_id
 * @property string $support_type
 * @property int|null $amount
 * @property string|null $description
 * @property string|null $benefits
 * @property string $status
 * @property string|null $rejectReason
 */
#[Fillable([
    'idTracking',
    'from_user_id',
    'parent_offer_id',
    'support_type',
    'amount',
    'description',
    'benefits',
    'status',
    'rejectReason',
])]
class Offer extends Model
{
    protected $table = 'offer';

    protected $primaryKey = 'idOffer';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updateAt';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
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
     * Pengirim penawaran (biasanya akun sponsor).
     *
     * @return BelongsTo<User, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id', 'id') ->withTrashed() ;
    }
}
