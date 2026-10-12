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
        // Keterangan petugas saat menilai kriteria "TIDAK SESUAI" (wajib diisi di panel tinjauan).
        // Terpisah dari `catatan` yang berisi hasil cek otomatis.
        DB::statement('ALTER TABLE detail_pemeriksaan ADD keterangan TEXT NULL AFTER catatan');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE detail_pemeriksaan DROP COLUMN keterangan');
    }
};
