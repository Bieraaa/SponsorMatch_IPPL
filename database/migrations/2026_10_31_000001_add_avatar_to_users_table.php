<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom `avatar` pada tabel `users`.
 *
 * Yang disimpan HANYA path relatif di disk `public`
 * (contoh: `avatars/12/abc123.jpg`), BUKAN isi binary gambar.
 * URL publiknya dibentuk oleh accessor `User::avatar()` memakai
 * `Storage::disk('public')->url()`.
 *
 * `nullable` karena pengguna boleh belum punya foto profil; tampilan
 * frontend akan memakai avatar inisial nama sebagai fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'avatar')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Path relatif di disk `public` (bukan URL absolut).
            $table->string('avatar', 2048)->nullable()->after('jabatan');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'avatar')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
