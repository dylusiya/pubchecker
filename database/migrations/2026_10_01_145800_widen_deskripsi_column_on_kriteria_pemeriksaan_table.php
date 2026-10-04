<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // deskripsi berisi teks kriteria pemeriksaan manual yang panjang (bisa >255 karakter),
        // sedangkan varchar(255) tidak cukup — doctrine/dbal tidak terpasang, jadi pakai raw SQL.
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY deskripsi TEXT NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY deskripsi VARCHAR(255) NOT NULL');
    }
};
