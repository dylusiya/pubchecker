<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan pemeriksaan: satu publikasi BPS (pub_id API) ditugaskan ke satu pemeriksa.
 * Selama ditugaskan, hanya pemeriksa itu (dan admin) yang boleh memeriksa, meninjau, dan mengirim ke SIPOTRET.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_pemeriksaan', function (Blueprint $table) {
            $table->id();
            $table->string('pub_id_api', 100)->unique();
            $table->string('judul', 500)->nullable();
            $table->string('pub_domain', 10)->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ditugaskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_pemeriksaan');
    }
};
