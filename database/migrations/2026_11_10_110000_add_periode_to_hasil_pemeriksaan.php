<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periode pemeriksaan (SIPOTRET) yang dituju sebuah hasil, dicatat saat Periksa ditekan
 * (Publikasi Bermasalah / Tugas Saya / detail publikasi "lanjutkan dari SIPOTRET").
 * Dipakai halaman Tugas Saya supaya status per periode tidak ditebak dari tanggal pemeriksaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_pemeriksaan', function (Blueprint $table) {
            $table->unsignedTinyInteger('periode_bulan')->nullable()->after('sipotret_acuan_tahun');
            $table->unsignedSmallInteger('periode_tahun')->nullable()->after('periode_bulan');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_pemeriksaan', function (Blueprint $table) {
            $table->dropColumn(['periode_bulan', 'periode_tahun']);
        });
    }
};
