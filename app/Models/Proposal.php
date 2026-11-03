<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Proposal sponsorship (tabel `proposal`, rancangan ERD).
 *
 * Satu proposal dibuat oleh SATU Event Organizer (`idUser`) dan dimiliki
 * oleh satu kategori event (`idKategori`). Setelah dikirim, proposal bisa
 * menyentuh banyak sponsor; hubungan itu dicatat di tabel
 * `trackingpipeline` (satu baris = satu proposal ke satu perusahaan).
 *
 * Relasi:
 *  - `user`     : pemilik proposal (Event Organizer)
 *  - `kategori` : kategori event
 *  - `trackings`: jejak pengiriman ke sponsor (satu proposal bisa > 1)
 *  - `viewLogs` : sponsor yang membuka proposal
 *  - `offers`   : seluruh penawaran sponsor atas proposal ini
 *
 * @property int $idProposal
 * @property int $idUser
 * @property int $idKategori
 * @property string $title
 * @property string $desctiption
 * @property string $target_audience
 * @property string $location
 * @property string $needType
 * @property int $budgetMin
 * @property int $budgetMax
 * @property string $benefits
 * @property string $file_path
 * @property string $status
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updateAt
 */
#[Fillable([
    'idUser',
    'idKategori',
    'title',
    'desctiption',
    'target_audience',
    'location',
    'needType',
    'budgetMin',
    'budgetMax',
    'benefits',
    'file_path',
    'status',
    'start_date',
    'end_date',
])]
class Proposal extends Model
{
    protected $table = 'proposal';

    protected $primaryKey = 'idProposal';

    /** ERD memakai nama kolom `createdAt` / `updateAt`. */
    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updateAt';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budgetMin' => 'integer',
            'budgetMax' => 'integer',
            'createdAt' => 'datetime',
            'updateAt' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser', 'id') ->withTrashed() ;
    }

    /**
     * @return BelongsTo<Kategori, $this>
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'idKategori', 'idKategori');
    }

    /**
     * Jejak proposal yang sudah dikirim ke sponsor.
     *
     * @return HasMany<TrackingPipeline, $this>
     */
    public function trackings(): HasMany
    {
        return $this->hasMany(TrackingPipeline::class, 'idProposal', 'idProposal');
    }

    /**
     * @return HasMany<ProposalViewLog, $this>
     */
    public function viewLogs(): HasMany
    {
        return $this->hasMany(ProposalViewLog::class, 'idProposal', 'idProposal');
    }

    /**
     * Semua penawaran sponsor atas proposal ini (melalui trackingpipeline).
     *
     * @return HasManyThrough<Offer, TrackingPipeline, $this>
     */
    public function offers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Offer::class,
            TrackingPipeline::class,
            'idProposal',
            'idTracking',
            'idProposal',
            'idTracking',
        );
    }
}
