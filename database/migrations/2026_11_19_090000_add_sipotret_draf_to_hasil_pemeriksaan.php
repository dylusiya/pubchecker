<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil pemeriksaan draf publikasi yang diupload PJK di SIPOTRET (transaksi.tr_id = satu versi upload),
 * supaya halaman Draf SIPOTRET bisa menandai versi yang sudah diperiksa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_pemeriksaan', function (Blueprint $table) {
            $table->unsignedInteger('sipotret_tr_id')->nullable()->after('sipotret_history_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('hasil_pemeriksaan', function (Blueprint $table) {
            $table->dropIndex(['sipotret_tr_id']);
            $table->dropColumn('sipotret_tr_id');
        });
    }
};
