<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipe cek baru rata_angka: P-264 "Perataan Angka dalam Tabel" (angka rata kanan & di tengah kolom) diperiksa
 * otomatis dari posisi teks PDF (PdfExtract ukurTabel). Hasilnya heuristik (pengukuran posisi), jadi bila tidak
 * terpenuhi berstatus PERLU DICEK, bukan TIDAK ADA. Hanya diubah bila P-264 masih manual.
 */
return new class extends Migration
{
    private const TIPE_LAMA = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap','jumlah_min','nomor_urut','bab','bulan_rilis','abjad','judul_kover','sama_api'";
    private const TIPE_BARU = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap','jumlah_min','nomor_urut','bab','bulan_rilis','abjad','judul_kover','sama_api','rata_angka'";
    private const PESAN_MANUAL = 'Perlu diperiksa manual oleh petugas sesuai Pedoman Pembuatan Publikasi 2023.';

    public function up(): void
    {
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM(' . self::TIPE_BARU . ") NOT NULL DEFAULT 'manual'");
        DB::table('kriteria_pemeriksaan')->where(['kode' => 'P-264', 'tipe_cek' => 'manual'])->update([
            'tipe_cek'     => 'rata_angka',
            'parameter'    => null,
            'status_gagal' => 'PERLU DICEK',
            'pesan_ok'     => 'Angka dalam tabel rata kanan dan di tengah kolom',
            'pesan_gagal'  => 'Angka dalam tabel belum rata kanan / belum di tengah kolom',
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('kriteria_pemeriksaan')->where('tipe_cek', 'rata_angka')->update([
            'tipe_cek'     => 'manual',
            'status_gagal' => 'TIDAK ADA',
            'pesan_ok'     => null,
            'pesan_gagal'  => self::PESAN_MANUAL,
            'updated_at'   => now(),
        ]);
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM(' . self::TIPE_LAMA . ") NOT NULL DEFAULT 'manual'");
    }
};
