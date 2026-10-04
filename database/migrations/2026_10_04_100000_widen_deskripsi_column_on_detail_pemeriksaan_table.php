<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // deskripsi disalin dari kriteria_pemeriksaan (TEXT), sebagian >300 karakter
        // sehingga insert hasil pemeriksaan gagal "Data too long" — samakan jadi TEXT.
        DB::statement('ALTER TABLE detail_pemeriksaan MODIFY deskripsi TEXT NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE detail_pemeriksaan MODIFY deskripsi VARCHAR(300) NOT NULL');
    }
};
