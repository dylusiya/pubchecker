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
        // Sumber PDF disimpan supaya tinjauan manual bisa dilanjutkan dari riwayat:
        // pdf_path = file upload yang disimpan di storage, pdf_url = URL publikasi BPS.
        DB::statement('ALTER TABLE hasil_pemeriksaan ADD pdf_path VARCHAR(500) NULL AFTER error_msg, ADD pdf_url TEXT NULL AFTER pdf_path');
        // Penanda kriteria yang sudah diverifikasi petugas (disimpan per kategori).
        DB::statement('ALTER TABLE detail_pemeriksaan ADD ditinjau_at TIMESTAMP NULL DEFAULT NULL AFTER catatan');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE detail_pemeriksaan DROP COLUMN ditinjau_at');
        DB::statement('ALTER TABLE hasil_pemeriksaan DROP COLUMN pdf_path, DROP COLUMN pdf_url');
    }
};
