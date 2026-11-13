<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periode catatan SIPOTRET yang dipilih petugas sebagai acuan isian awal tinjauan (mis. September 2026),
 * disimpan per hasil supaya tinjauan yang dilanjutkan dari Riwayat memakai acuan yang sama
 * (bukan "periode terakhir" yang bisa berubah) dan terlihat acuan mana yang sudah dicek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_pemeriksaan', function (Blueprint $table) {
            $table->unsignedTinyInteger('sipotret_acuan_bulan')->nullable()->after('sipotret_history_id');
            $table->unsignedSmallInteger('sipotret_acuan_tahun')->nullable()->after('sipotret_acuan_bulan');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_pemeriksaan', function (Blueprint $table) {
            $table->dropColumn(['sipotret_acuan_bulan', 'sipotret_acuan_tahun']);
        });
    }
};
