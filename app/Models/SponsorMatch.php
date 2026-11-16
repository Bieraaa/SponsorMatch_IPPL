<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil matching AI untuk satu proposal dan satu perusahaan (tabel `matches`).
 *
 * Nama kelas sengaja BUKAN `Match` karena `match` adalah kata kunci PHP.
 *
 * Satu baris mewakili satu rekomendasi. `company_user_id` memastikan satu
 * perusahaan hanya muncul sekali untuk satu proposal, sedangkan
 * `idCampaign` menyimpan campaign paling relevan (boleh NULL untuk
 * rekomendasi berbasis profil).
 *
 * @property int $idMatches
 * @property int $idProposal
 * @property int|null $idCampaign
 * @property int $company_user_id
 * @property int $score
 * @property string|null $reason
 * @property array<int, string>|null $factors
 * @property string $source
 * @property string $direction
 * @property string|null $input_hash
 */
#[Fillable([
    'idProposal',
    'idCampaign',
    'company_user_id',
    'score',
    'reason',
    'factors',
    'source',
    'direction',
    'input_hash',
    'generateAt',
    'notifiedAt',
])]
class SponsorMatch extends Model
{
    protected $table = 'matches';

    protected $primaryKey = 'idMatches';

    /** Arah rekomendasi: EO → perusahaan. */
    public const DIRECTION_EO_TO_COMPANY = 'eo_to_company';

    /** Arah rekomendasi: perusahaan → proposal EO. */
    public const DIRECTION_COMPANY_TO_EO = 'company_to_eo';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'company_user_id' => 'integer',
            'factors' => 'array',
            'generateAt' => 'datetime',
            'notifiedAt' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_user_id', 'id');
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'idCampaign', 'idCampaign');
    }
}
