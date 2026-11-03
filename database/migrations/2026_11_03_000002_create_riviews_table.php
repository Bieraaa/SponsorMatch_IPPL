<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel ulasan antar pengguna (`riviews`).
 *
 * Di MySQL tabel ini sudah ada dari hasil impor ERD, sehingga pembuatan
 * tabel dilewati. Di SQLite (database pengujian) tabel belum ada, jadi
 * dibuat di sini agar rating & ulasan di halaman profil bisa diuji.
 *
 * Nama tabel dan kolom mengikuti ERD apa adanya (termasuk typo `riviews`
 * dan camelCase `idRiviews`/`createdAt`/`updateAt`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('riviews')) {
            return;
        }

        Schema::create('riviews', function (Blueprint $table) {
            $table->increments('idRiviews');
            $table->unsignedInteger('idTracking');
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updateAt')->nullable()->useCurrent();

            $table->foreign('idTracking')
                ->references('idTracking')
                ->on('trackingpipeline')
                ->onDelete('cascade');
            $table->foreign('from_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
            $table->foreign('to_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });
    }

    /**
     * TIDAK menghapus tabel.
     *
     * Di MySQL tabel `riviews` sudah ada sebelum migration ini dan berisi
     * data produksi. `migrate:rollback` tidak boleh menghapusnya. Tabel
     * yang dibuat di SQLite ikut hilang saat `migrate:fresh`.
     */
    public function down(): void
    {
        // no-op: lihat penjelasan di atas.
    }
};
