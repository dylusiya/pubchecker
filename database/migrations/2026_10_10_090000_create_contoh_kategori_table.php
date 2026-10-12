<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Gambar contoh yang benar per kategori kriteria, ditampilkan di panel tinjauan.
        Schema::create('contoh_kategori', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kategori', 100)->index();
            $table->string('path', 500);
            $table->string('keterangan', 255)->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contoh_kategori');
    }
};
