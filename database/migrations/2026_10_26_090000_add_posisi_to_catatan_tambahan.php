<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posisi komentar di halaman PDF (petugas mengklik area tertentu di viewer).
 * Pecahan 0–1 dari lebar/tinggi halaman, supaya tetap pas di semua tingkat zoom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catatan_tambahan', function (Blueprint $table) {
            $table->decimal('pos_x', 5, 4)->nullable()->after('halaman');
            $table->decimal('pos_y', 5, 4)->nullable()->after('pos_x');
        });
    }

    public function down(): void
    {
        Schema::table('catatan_tambahan', function (Blueprint $table) {
            $table->dropColumn(['pos_x', 'pos_y']);
        });
    }
};
