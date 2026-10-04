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
        // Baris hasil OCR (koordinat PDF) dari js/pdf-extract.js, supaya saat tinjauan dilanjutkan
        // dari riwayat teks di gambar tetap bisa dicari (Ctrl+F) di viewer.
        DB::statement('ALTER TABLE hasil_pemeriksaan ADD ocr_lines LONGTEXT NULL AFTER pdf_url');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE hasil_pemeriksaan DROP COLUMN ocr_lines');
    }
};
