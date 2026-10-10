<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P-102 (isi pernyataan hak cipta bahasa Inggris) dicek otomatis dengan regex bunyi baku pedoman:
 * "It is prohibited to reproduce and/or duplicate part or all of this book for commercial purpose
 * without permission from [nama BPS]". Toleran terhadap jeda baris, "purposes", dan "written permission".
 *
 * Tidak ditemukan → PERLU DICEK (bukan TIDAK ADA): pernyataan bahasa Inggris hanya wajib untuk
 * publikasi dwibahasa, jadi publikasi berbahasa Indonesia saja cukup ditandai Skip oleh petugas.
 */
return new class extends Migration
{
    private const PATTERN = 'It\s+is\s+prohibited\s+to\s+reproduce\s+and\s*/\s*or\s+duplicate\s+part\s+or\s+all\s+of\s+this\s+book\s+for\s+commercial\s+purposes?\s+without\s+(?:written\s+)?permission\s+from';

    public function up(): void
    {
        DB::table('kriteria_pemeriksaan')->where('kode', 'P-102')->update([
            'tipe_cek'     => 'regex',
            'target'       => 'front',
            'parameter'    => json_encode(['pattern' => self::PATTERN, 'flags' => 'iu'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status_gagal' => 'PERLU DICEK',
            'pesan_ok'     => 'Pernyataan hak cipta bahasa Inggris sesuai',
            'pesan_gagal'  => 'Pernyataan hak cipta bahasa Inggris tidak ditemukan/tidak sesuai bunyi baku (Skip bila bukan publikasi dwibahasa)',
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('kriteria_pemeriksaan')->where('kode', 'P-102')->update([
            'tipe_cek'     => 'manual',
            'target'       => 'all',
            'parameter'    => null,
            'status_gagal' => 'TIDAK ADA',
            'pesan_ok'     => null,
            'pesan_gagal'  => 'Perlu diperiksa manual oleh petugas sesuai Pedoman Pembuatan Publikasi 2023.',
            'updated_at'   => now(),
        ]);
    }
};
