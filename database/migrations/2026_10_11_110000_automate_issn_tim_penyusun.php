<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Target baru "tim_penyusun" (halaman Tim Penyusun, dikenali dari isinya — lihat
 * PdfCheckerService::timPenyusunIndex) dan otomasi:
 *   P-107 Posisi ISSN pada Halaman Tim Penyusun   → posisi_area: kata ISSN di pojok kanan atas
 *   P-108 Format Penulisan ISSN (Tim Penyusun)    → not_regex: "ISSN:" (bertitik dua) dilarang
 */
return new class extends Migration
{
    private const TARGETS_LAMA = "'cover','page2','front','last','all'";
    private const TARGETS_BARU = "'cover','page2','front','last','all','tim_penyusun'";

    public function up(): void
    {
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY target ENUM(' . self::TARGETS_BARU . ") NOT NULL DEFAULT 'cover'");

        DB::table('kriteria_pemeriksaan')->where('kode', 'P-107')->update([
            'tipe_cek'     => 'posisi_area',
            'target'       => 'tim_penyusun',
            'parameter'    => json_encode(['word' => 'ISSN', 'area' => 'top_right']),
            'status_gagal' => 'PERLU DICEK',
            'pesan_ok'     => 'ISSN berada di pojok kanan atas halaman Tim Penyusun',
            'pesan_gagal'  => 'ISSN tidak berada di pojok kanan atas halaman Tim Penyusun',
            'updated_at'   => now(),
        ]);

        DB::table('kriteria_pemeriksaan')->where('kode', 'P-108')->update([
            'tipe_cek'     => 'not_regex',
            'target'       => 'tim_penyusun',
            // "syarat": halaman tanpa ISSN → Skip, bukan OK
            'parameter'    => json_encode([
                'pattern'      => 'ISSN\s*:',
                'flags'        => 'iu',
                'syarat'       => '\bISSN\b',
                'pesan_syarat' => 'Tidak ada ISSN di halaman Tim Penyusun',
            ], JSON_UNESCAPED_SLASHES),
            'status_gagal' => 'TIDAK ADA',
            'pesan_ok'     => 'ISSN di halaman Tim Penyusun ditulis tanpa titik dua',
            'pesan_gagal'  => 'ISSN di halaman Tim Penyusun ditulis dengan titik dua (seharusnya: ISSN XXXX-XXXX)',
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('kriteria_pemeriksaan')->whereIn('kode', ['P-107', 'P-108'])->update([
            'tipe_cek'     => 'manual',
            'target'       => 'all',
            'parameter'    => null,
            'status_gagal' => 'TIDAK ADA',
            'pesan_ok'     => null,
            'pesan_gagal'  => 'Perlu diperiksa manual oleh petugas sesuai Pedoman Pembuatan Publikasi 2023.',
            'updated_at'   => now(),
        ]);
        DB::table('kriteria_pemeriksaan')->where('target', 'tim_penyusun')->update(['target' => 'front']);
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY target ENUM(' . self::TARGETS_LAMA . ") NOT NULL DEFAULT 'cover'");
    }
};
