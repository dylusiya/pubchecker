<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda kriteria Tidak Sesuai di halaman PDF (petugas mengklik area di viewer).
 * pin_x/pin_y: pecahan 0–1 dari lebar/tinggi halaman; ikut tertanam sebagai komentar saat PDF diunduh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_pemeriksaan', function (Blueprint $table) {
            $table->unsignedSmallInteger('pin_halaman')->nullable()->after('keterangan');
            $table->decimal('pin_x', 5, 4)->nullable()->after('pin_halaman');
            $table->decimal('pin_y', 5, 4)->nullable()->after('pin_x');
        });
    }

    public function down(): void
    {
        Schema::table('detail_pemeriksaan', function (Blueprint $table) {
            $table->dropColumn(['pin_halaman', 'pin_x', 'pin_y']);
        });
    }
};
