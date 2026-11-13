<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan tambahan disamakan dengan "Dari History" di SIPOTRET:
 * - item_periksa_id: item master SIPOTRET yang tidak punya kriteria di pubchecker (mis. dari acuan)
 *   tetap dikirim sebagai item master, bukan item custom.
 * - flag_level boleh kosong: di SIPOTRET flag kosong = tidak perlu perbaikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catatan_tambahan', function (Blueprint $table) {
            $table->unsignedInteger('item_periksa_id')->nullable()->after('item');
        });
        DB::statement("ALTER TABLE catatan_tambahan MODIFY flag_level ENUM('minor','moderate','major') NULL DEFAULT 'minor'");
    }

    public function down(): void
    {
        DB::statement("UPDATE catatan_tambahan SET flag_level = 'minor' WHERE flag_level IS NULL");
        DB::statement("ALTER TABLE catatan_tambahan MODIFY flag_level ENUM('minor','moderate','major') NOT NULL DEFAULT 'minor'");
        Schema::table('catatan_tambahan', function (Blueprint $table) {
            $table->dropColumn('item_periksa_id');
        });
    }
};
