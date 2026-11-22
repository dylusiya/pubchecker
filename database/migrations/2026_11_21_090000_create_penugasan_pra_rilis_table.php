<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan pemeriksaan pra rilis: satu publikasi SIPOTRET (publikasi.pub_id) ditugaskan ke satu pemeriksa.
 * Selama ditugaskan, hanya pemeriksa itu (dan admin) yang boleh memeriksa drafnya, mengirim hasil ke SIPOTRET,
 * memutus SPRP, dan menandai rilis. Terpisah dari penugasan_pemeriksaan (pasca rilis, kunci pub_id Web API).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_pra_rilis', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sipotret_pub_id')->unique();
            $table->string('judul', 500)->nullable();
            $table->date('tgl_rilis')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ditugaskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_pra_rilis');
    }
};
