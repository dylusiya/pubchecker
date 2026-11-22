<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pemilik sesi pemeriksaan (Riwayat hanya menampilkan sesi milik sendiri, kecuali admin). Sebelumnya hanya tersimpan
 * nama (dibuat_oleh); sesi lama diisi dari nama itu bila cocok dengan tepat satu user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_pemeriksaan', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('dibuat_oleh')->constrained('users')->nullOnDelete();
        });

        DB::update("UPDATE sesi_pemeriksaan s
            JOIN (SELECT name, MIN(id) AS id FROM users GROUP BY name HAVING COUNT(*) = 1) u ON u.name = s.dibuat_oleh
            SET s.user_id = u.id
            WHERE s.user_id IS NULL");
    }

    public function down(): void
    {
        Schema::table('sesi_pemeriksaan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
