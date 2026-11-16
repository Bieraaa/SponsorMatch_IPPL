<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom `direction` pada tabel `matches`.
 *
 * Satu tabel dipakai untuk dua arah rekomendasi:
 *   - `eo_to_company`   : EO melihat perusahaan yang cocok untuk proposalnya
 *   - `company_to_eo`   : perusahaan melihat proposal EO yang cocok
 *
 * Tanpa kolom ini, pasangan (proposal, perusahaan) yang sama bisa saling
 * menimpa karena skor/alasan kedua arah berbeda. default-nya
 * `eo_to_company` agar baris lama (bila ada) tetap terbaca sebagai
 * rekomendasi EO.
 *
 * Skema MySQL lama juga memasang UNIQUE satu kolom pada `idProposal` dan
 * `idCampaign`, serta UNIQUE (idProposal, company_user_id) tanpa `direction`.
 * Ketiganya tidak cocok dengan kebutuhan rekomendasi (banyak perusahaan per
 * proposal, dan dua arah untuk pasangan yang sama), sehingga dilepas dan
 * diganti UNIQUE (idProposal, company_user_id, direction). Tidak ada baris
 * data yang dihapus.
 *
 * Seluruh langkah bersifat guarded.
 */
return new class extends Migration
{
    /** Nama unique baru; dipakai juga saat memeriksa idempotensi. */
    private const UNIQUE_NAME = 'matches_proposal_company_direction_unique';

    public function up(): void
    {
        if (! Schema::hasTable('matches')) {
            return;
        }

        if (! Schema::hasColumn('matches', 'direction')) {
            try {
                Schema::table('matches', function (Blueprint $table) {
                    $table->string('direction', 30)->default('eo_to_company');
                });
            } catch (Throwable $e) {
                Log::warning('Tidak dapat menambah kolom matches.direction: '.$e->getMessage());

                return;
            }
        }

        $this->dropLegacyUniqueIndexes();

        // Satu perusahaan hanya boleh muncul SEKALI per proposal per arah.
        $this->ensureUnique(
            ['idProposal', 'company_user_id', 'direction'],
            self::UNIQUE_NAME,
        );

        $this->ensureIndex(['direction', 'company_user_id'], 'matches_direction_company_index');
        $this->ensureIndex(['direction', 'idProposal'], 'matches_direction_proposal_index');
    }

    /**
     * Lepas UNIQUE lama yang menghalangi banyak baris per proposal.
     *
     * Yang dilepas hanya UNIQUE (bukan index biasa) dengan susunan kolom
     * persis seperti skema ERD lama:
     *   - [idProposal]
     *   - [idCampaign]
     *   - [idProposal, company_user_id]        (belum memuat direction)
     *   - [idProposal, idCampaign]
     */
    private function dropLegacyUniqueIndexes(): void
    {
        $legacy = [
            ['idProposal'],
            ['idCampaign'],
            ['idProposal', 'company_user_id'],
            ['idProposal', 'idCampaign'],
        ];

        try {
            foreach (Schema::getIndexes('matches') as $index) {
                if (! ($index['unique'] ?? false) || ($index['primary'] ?? false)) {
                    continue;
                }

                $columns = $index['columns'] ?? [];

                if (! in_array($columns, $legacy, true)) {
                    continue;
                }

                try {
                    Schema::table('matches', function (Blueprint $table) use ($index) {
                        $table->dropUnique($index['name']);
                    });
                } catch (Throwable $e) {
                    Log::warning('Tidak dapat melepas unique index lama matches: '.$e->getMessage());
                }
            }
        } catch (Throwable $e) {
            Log::warning('Tidak dapat membaca index matches: '.$e->getMessage());
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function ensureUnique(array $columns, string $name): void
    {
        try {
            $exists = collect(Schema::getIndexes('matches'))->contains(
                fn (array $index) => ($index['columns'] ?? []) === $columns
                    && ($index['unique'] ?? false),
            );

            if (! $exists) {
                Schema::table('matches', function (Blueprint $table) use ($columns, $name) {
                    $table->unique($columns, $name);
                });
            }
        } catch (Throwable $e) {
            Log::warning("Tidak dapat menambah unique {$name}: ".$e->getMessage());
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function ensureIndex(array $columns, string $name): void
    {
        try {
            $exists = collect(Schema::getIndexes('matches'))->contains(
                fn (array $index) => ($index['columns'] ?? []) === $columns,
            );

            if (! $exists) {
                Schema::table('matches', function (Blueprint $table) use ($columns, $name) {
                    $table->index($columns, $name);
                });
            }
        } catch (Throwable $e) {
            Log::warning("Tidak dapat menambah index {$name}: ".$e->getMessage());
        }
    }

    public function down(): void
    {
        // no-op: tidak menghapus kolom/data.
    }
};
