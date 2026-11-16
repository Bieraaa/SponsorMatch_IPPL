<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Postingan perusahaan (tabel `post`).
 *
 * Postingan berbeda dari campaign: campaign adalah peluang sponsorship,
 * sedangkan postingan adalah informasi/aktivitas/berita perusahaan.
 *
 * @property int $idPost
 * @property int $idUser
 * @property string $description
 * @property string|null $imagePath
 * @property Carbon|null $deleteAt
 */
#[Fillable(['idUser', 'description', 'imagePath'])]
class Post extends Model
{
    protected $table = 'post';

    protected $primaryKey = 'idPost';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updateAt';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
            'updateAt' => 'datetime',
            'deleteAt' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser', 'id');
    }

    /**
     * URL publik gambar postingan (null bila tidak ada).
     */
    public function imageUrl(): ?string
    {
        if (blank($this->imagePath)) {
            return null;
        }

        if (str_starts_with((string) $this->imagePath, 'http://')
            || str_starts_with((string) $this->imagePath, 'https://')) {
            return $this->imagePath;
        }

        return Storage::disk('public')->url($this->imagePath);
    }
}
