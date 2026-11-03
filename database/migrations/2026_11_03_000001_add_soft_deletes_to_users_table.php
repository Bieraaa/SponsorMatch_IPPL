<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom `deleted_at` untuk penghapusan akun secara SOFT DELETE.
 *
 * Akun yang dihapus tetap tersimpan di database (beserta relasi bisnisnya),
 * tetapi tidak lagi muncul pada query biasa dan tidak bisa login. Ini
 * menggantikan hard delete agar riwayat proposal/kerja sama tidak hilang.
 *
 * Kolom ditambahkan hanya kalau belum ada supaya migration aman dijalankan
 * pada database yang mungkin sudah memilikinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'deleted_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'deleted_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
