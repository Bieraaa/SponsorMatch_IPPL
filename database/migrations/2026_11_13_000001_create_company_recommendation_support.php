<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan skema untuk rekomendasi sponsor, profil perusahaan, dan campaign.
 *
 * KONTEKS
 * -------
 * Sebagian tabel domain (campaign, company_profiles, matches, post, dst.)
 * berasal dari export ERD di luar Laravel, sehingga kolomnya bisa berbeda
 * antara database MySQL pengguna dan database uji SQLite.
 *
 * Karena itu SELURUH langkah di migrasi ini bersifat "guarded":
 *   - tabel   → hanya dibuat bila belum ada
 *   - kolom   → hanya ditambahkan bila belum ada
 *   - indeks  → hanya diubah bila memang ada
 *
 * Tidak ada tabel/kolom/data yang dihapus. Di MySQL yang sudah memiliki
 * kolom tersebut, langkah terkait dilewati; di SQLite (pengujian) kolom
 * dibuat dari nol sehingga fitur bisa diuji end-to-end.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->extendCompanyProfiles();
        $this->extendCampaign();
        $this->ensureMatchesTable();
        $this->ensureTrackingPipelineColumns();
        $this->ensurePostTable();
        $this->ensureCompanyCategoriesTable();
    }

    /**
     * Kolom profil perusahaan yang dipakai sistem rekomendasi.
     */
    private function extendCompanyProfiles(): void
    {
        if (! Schema::hasTable('company_profiles')) {
            return;
        }

        $columns = [
            'description' => fn (Blueprint $table) => $table->text('description')->nullable(),
            'website' => fn (Blueprint $table) => $table->string('website', 255)->nullable(),
            'logo_path' => fn (Blueprint $table) => $table->string('logo_path', 255)->nullable(),
            'sponsorship_min' => fn (Blueprint $table) => $table->bigInteger('sponsorship_min')->nullable(),
            'sponsorship_max' => fn (Blueprint $table) => $table->bigInteger('sponsorship_max')->nullable(),
            'event_types' => fn (Blueprint $table) => $table->text('event_types')->nullable(),
            'target_audience' => fn (Blueprint $table) => $table->text('target_audience')->nullable(),
        ];

        $this->addMissingColumns('company_profiles', $columns);
    }

    /**
     * Kolom campaign perusahaan yang belum ada pada skema ERD awal.
     */
    private function extendCampaign(): void
    {
        if (! Schema::hasTable('campaign')) {
            return;
        }

        $columns = [
            'purpose' => fn (Blueprint $table) => $table->text('purpose')->nullable(),
            'benefits' => fn (Blueprint $table) => $table->text('benefits')->nullable(),
            'banner_path' => fn (Blueprint $table) => $table->string('banner_path', 255)->nullable(),
        ];

        $this->addMissingColumns('campaign', $columns);
    }

    /**
     * Tabel hasil matching proposal ↔ perusahaan.
     *
     * `idCampaign` WAJIB nullable: rekomendasi berbasis profil tidak
     * terikat campaign. Bila di MySQL lama ada UNIQUE index pada
     * `idCampaign` (penyebab error "Duplicate entry ... for key
     * matches.idCampaign"), index itu dilepas — datanya tidak dihapus.
     */
    private function ensureMatchesTable(): void
    {
        if (! Schema::hasTable('matches')) {
            Schema::create('matches', function (Blueprint $table) {
                $table->increments('idMatches');
                $table->unsignedInteger('idProposal');
                $table->unsignedInteger('idCampaign')->nullable();
                $table->unsignedBigInteger('company_user_id')->nullable();
                $table->unsignedTinyInteger('score')->default(0);
                $table->text('reason')->nullable();
                $table->text('factors')->nullable();
                $table->string('source', 20)->default('profile');
                $table->string('input_hash', 64)->nullable();
                $table->timestamp('generateAt')->nullable();
                $table->timestamp('notifiedAt')->nullable();

                $table->index(['idProposal', 'company_user_id'], 'matches_proposal_company_index');
                $table->index('idCampaign', 'matches_campaign_index');
            });

            return;
        }

        $this->addMissingColumns('matches', [
            'idProposal' => fn (Blueprint $table) => $table->unsignedInteger('idProposal')->nullable(),
            'idCampaign' => fn (Blueprint $table) => $table->unsignedInteger('idCampaign')->nullable(),
            'company_user_id' => fn (Blueprint $table) => $table->unsignedBigInteger('company_user_id')->nullable(),
            'score' => fn (Blueprint $table) => $table->unsignedTinyInteger('score')->default(0),
            'reason' => fn (Blueprint $table) => $table->text('reason')->nullable(),
            'factors' => fn (Blueprint $table) => $table->text('factors')->nullable(),
            'source' => fn (Blueprint $table) => $table->string('source', 20)->default('profile'),
            'input_hash' => fn (Blueprint $table) => $table->string('input_hash', 64)->nullable(),
            'generateAt' => fn (Blueprint $table) => $table->timestamp('generateAt')->nullable(),
            'notifiedAt' => fn (Blueprint $table) => $table->timestamp('notifiedAt')->nullable(),
        ]);

        // Lepas UNIQUE index satu kolom pada idCampaign (bila ada).
        try {
            foreach (Schema::getIndexes('matches') as $index) {
                $columns = $index['columns'] ?? [];

                if (($index['unique'] ?? false)
                    && ! ($index['primary'] ?? false)
                    && $columns === ['idCampaign']) {
                    Schema::table('matches', function (Blueprint $table) use ($index) {
                        $table->dropUnique($index['name']);
                    });
                }
            }
        } catch (Throwable $e) {
            Log::warning('Tidak dapat melepas unique index matches.idCampaign: '.$e->getMessage());
        }

        // Pastikan idCampaign boleh NULL.
        try {
            $column = collect(Schema::getColumns('matches'))->firstWhere('name', 'idCampaign');

            if ($column && ! ($column['nullable'] ?? true)) {
                Schema::table('matches', function (Blueprint $table) {
                    $table->unsignedInteger('idCampaign')->nullable()->change();
                });
            }
        } catch (Throwable $e) {
            Log::warning('Tidak dapat mengubah matches.idCampaign menjadi nullable: '.$e->getMessage());
        }

        // Index bantu untuk query daftar rekomendasi.
        try {
            $hasIndex = collect(Schema::getIndexes('matches'))->contains(
                fn (array $index) => ($index['columns'] ?? []) === ['idProposal', 'company_user_id'],
            );

            if (! $hasIndex) {
                Schema::table('matches', function (Blueprint $table) {
                    $table->index(['idProposal', 'company_user_id'], 'matches_proposal_company_index');
                });
            }
        } catch (Throwable $e) {
            Log::warning('Tidak dapat menambah index matches: '.$e->getMessage());
        }
    }

    /**
     * Pesan pengantar proposal (opsional saat mengirim).
     */
    private function ensureTrackingPipelineColumns(): void
    {
        if (! Schema::hasTable('trackingpipeline')) {
            return;
        }

        $this->addMissingColumns('trackingpipeline', [
            'message' => fn (Blueprint $table) => $table->text('message')->nullable(),
        ]);
    }

    /**
     * Postingan perusahaan (tab "Postingan" pada detail perusahaan).
     */
    private function ensurePostTable(): void
    {
        if (Schema::hasTable('post')) {
            return;
        }

        Schema::create('post', function (Blueprint $table) {
            // Mengikuti nama kolom tabel `post` yang sudah ada di MySQL ERD
            // (primary key `idPost`), supaya model Post berperilaku sama di
            // SQLite (pengujian) dan MySQL.
            $table->increments('idPost');
            $table->unsignedBigInteger('idUser');
            $table->text('description');
            $table->string('imagePath', 255)->nullable();
            $table->timestamp('deleteAt')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updateAt')->nullable();

            $table->index('idUser', 'post_user_index');
        });
    }

    /**
     * Pivot minat kategori sponsorship milik profil perusahaan.
     */
    private function ensureCompanyCategoriesTable(): void
    {
        if (Schema::hasTable('company_categories')) {
            return;
        }

        Schema::create('company_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('idCompany');
            $table->unsignedInteger('idKategori');

            $table->primary(['idCompany', 'idKategori']);
        });
    }

    /**
     * Tambahkan kolom yang belum ada tanpa menyentuh kolom lain.
     *
     * @param  array<string, callable(Blueprint): mixed>  $definitions
     */
    private function addMissingColumns(string $table, array $definitions): void
    {
        $missing = [];

        foreach ($definitions as $name => $definition) {
            if (! Schema::hasColumn($table, $name)) {
                $missing[$name] = $definition;
            }
        }

        if ($missing === []) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($missing) {
                foreach ($missing as $definition) {
                    $definition($blueprint);
                }
            });
        } catch (Throwable $e) {
            Log::warning("Tidak dapat menambah kolom pada {$table}: ".$e->getMessage());
        }
    }

    /**
     * Tidak menghapus apa pun. Tabel/kolom yang berasal dari ERD MySQL
     * kemungkinan berisi data produksi, jadi rollback dibuat no-op.
     */
    public function down(): void
    {
        // no-op: sengaja tidak menghapus data.
    }
};
