<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Tracking pipeline (tabel `trackingpipeline`).
 *
 * Satu baris = satu proposal yang dikirim ke satu perusahaan sponsor.
 * `stage` menyatakan posisi negoisasi:
 *   sent → viewed → negotiating → deal → waiting_report → done
 *   (dan `rejected` kalau sponsor menolak).
 *
 * @property int $idTracking
 * @property int $idProposal
 * @property int $company_user_id
 * @property int|null $idCampaign
 * @property string $stage
 * @property string $initiatedBy
 * @property string|null $agreementPath
 * @property string|null $message
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
#[Fillable([
    'idProposal',
    'company_user_id',
    'idCampaign',
    'stage',
    'initiatedBy',
    'agreementPath',
    'message',
])]
class TrackingPipeline extends Model
{
    protected $table = 'trackingpipeline';

    protected $primaryKey = 'idTracking';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'idProposal', 'idProposal');
    }

    /**
     * Akun perusahaan sponsor yang menerima proposal.
     *
     * @return BelongsTo<User, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_user_id', 'id')->withTrashed();
    }

    /**
     * Event yang terkait dengan proposal ini (bisa null).
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'idCampaign', 'idCampaign');
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'idTracking', 'idTracking');
    }

    /**
     * @return HasMany<FollowUpLog, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUpLog::class, 'idTracking', 'idTracking');
    }

    /**
     * @return HasOne<EventReport, $this>
     */
    public function report(): HasOne
    {
        return $this->hasOne(EventReport::class, 'idTracking', 'idTracking');
    }
}
