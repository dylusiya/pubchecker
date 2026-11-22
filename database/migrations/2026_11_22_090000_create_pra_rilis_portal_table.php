<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Langkah 5 pra rilis: tanda (ceklis) dari admin/pemeriksa bahwa PDF final publikasi SIPOTRET (publikasi.pub_id)
 * sudah diupload ke portal publikasi BPS. SIPOTRET tidak punya kolom untuk ini, jadi disimpan di pubchecker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pra_rilis_portal', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sipotret_pub_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pra_rilis_portal');
    }
};
