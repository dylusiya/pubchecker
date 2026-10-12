<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas publikasi BPS (dari Import API) supaya hasil "Tidak Sesuai" bisa dikirim ke SIPOTRET
 * (pasca_rilis dicocokkan lewat pub_id_api), plus jejak pengiriman terakhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_pemeriksaan', function (Blueprint $table) {
            $table->string('pub_id_api', 100)->nullable()->after('pdf_url');
            $table->string('pub_domain', 10)->nullable()->after('pub_id_api');
            $table->string('pub_issn', 30)->nullable()->after('pub_domain');
            $table->date('pub_tanggal_rilis')->nullable()->after('pub_issn');
            $table->timestamp('sipotret_terkirim_at')->nullable()->after('pub_tanggal_rilis');
            $table->unsignedInteger('sipotret_history_id')->nullable()->after('sipotret_terkirim_at');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_pemeriksaan', function (Blueprint $table) {
            $table->dropColumn(['pub_id_api', 'pub_domain', 'pub_issn', 'pub_tanggal_rilis', 'sipotret_terkirim_at', 'sipotret_history_id']);
        });
    }
};
