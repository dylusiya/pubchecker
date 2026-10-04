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
        // Lokasi temuan cek otomatis [{hal, teks}] — tombol "lompat ke halaman" di panel tinjauan.
        DB::statement('ALTER TABLE detail_pemeriksaan ADD lokasi TEXT NULL AFTER catatan');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE detail_pemeriksaan DROP COLUMN lokasi');
    }
};
