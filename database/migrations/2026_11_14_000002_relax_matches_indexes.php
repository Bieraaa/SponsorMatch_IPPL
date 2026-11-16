<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Melonggarkan UNIQUE index lama pada `matches` yang menempel ke FOREIGN KEY.
 *
 * LATAR BELAKANG
 * --------------
 * Skema ERD MySQL memasang:
 *   - UNIQUE (idProposal)
 *   - UNIQUE (idCampaign)
 *   - UNIQUE (idProposal, company_user_id)
 *   - UNIQUE (idProposal, idCampaign)
 *
 * Keempatnya menghalangi kebutuhan rekomendasi:
 *   - satu proposal harus bisa punya banyak perusahaan;
 *   - satu campaign boleh relevan untuk banyak proposal;
 *   - pasangan (proposal, perusahaan) punya dua arah rekomendasi.
 *
 * MySQL menolak `DROP INDEX idCampaign` / `uq_matches_pair` selama index
 * tersebut satu-satunya penopang FOREIGN KEY. Karena itu migrasi ini
 * menambahkan index biasa lebih dulu, baru melepas UNIQUE lama. Tidak ada
 * baris data yang dihapus.
 *
 * Catatan: migrasi sebelumnya (2026_11_14_000001) sudah mencoba melepas
 * index yang sama, tetapi gagal untuk index yang dipakai FK. Migrasi ini
 * melengkapinya secara aman untuk database yang sudah berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('matches')) {
            return;
        }

        // 1. Index biasa agar FK pada idCampaign tetap punya penopang.
        $this->ensurePlainIndex('idCampaign', 'matches_campaign_index');
        $this->ensurePlainIndex('idProposal', 'matches_proposal_index');

        // 2. Lepas UNIQUE lama (hanya bila susunan kolomnya persis).
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

                if (! in_array($index['columns'] ?? [], $legacy, true)) {
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

        // 3. Pastikan kombinasi yang benar tetap unik.
        try {
            $hasUnique = collect(Schema::getIndexes('matches'))->contains(
                fn (array $index) => ($index['columns'] ?? []) === ['idProposal', 'company_user_id', 'direction']
                    && ($index['unique'] ?? false),
            );

            if (! $hasUnique) {
                Schema::table('matches', function (Blueprint $table) {
                    $table->unique(
                        ['idProposal', 'company_user_id', 'direction'],
                        'matches_proposal_company_direction_unique',
                    );
                });
            }
        } catch (Throwable $e) {
            Log::warning('Tidak dapat memastikan unique matches per arah: '.$e->getMessage());
        }
    }

    /**
     * Tambahkan index biasa satu kolom bila index dengan nama ini belum ada.
     *
     * Sengaja tidak menganggap UNIQUE lama sebagai penopang: index itulah
     * yang akan dilepas, sehingga kita butuh index biasa sebagai pengganti
     * agar FOREIGN KEY tetap valid.
     */
    private function ensurePlainIndex(string $column, string $name): void
    {
        try {
            $exists = collect(Schema::getIndexes('matches'))->contains(
                fn (array $index) => ($index['name'] ?? '') === $name,
            );

            if ($exists) {
                return;
            }

            Schema::table('matches', function (Blueprint $table) use ($column, $name) {
                $table->index($column, $name);
            });
        } catch (Throwable $e) {
            Log::warning("Tidak dapat menambah index {$name}: ".$e->getMessage());
        }
    }

    public function down(): void
    {
        // no-op: tidak menghapus kolom/index/data.
    }
};
