<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan tambahan petugas per publikasi & kategori untuk temuan yang tidak ada di daftar kriteria.
 * Dikirim ke SIPOTRET sebagai "Item Lainnya (Custom)": item_periksa_custom (maks. 50 karakter)
 * + item_flag_level (minor/moderate/major) + item_keterangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_tambahan', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('hasil_id');
            $table->string('kategori', 150)->nullable(); // kategori kriteria tempat catatan dibuat
            $table->string('item', 50);
            $table->text('keterangan')->nullable();
            $table->enum('flag_level', ['minor', 'moderate', 'major'])->default('minor');
            $table->unsignedSmallInteger('halaman')->nullable();
            $table->string('dibuat_oleh', 100)->nullable();
            $table->timestamps();

            $table->foreign('hasil_id')->references('id')->on('hasil_pemeriksaan')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_tambahan');
    }
};
