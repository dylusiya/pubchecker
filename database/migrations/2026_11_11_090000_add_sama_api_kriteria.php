<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipe cek baru sama_api: isian ISSN/ISBN, nomor katalog & nomor publikasi di halaman depan publikasi
 * dibandingkan dengan isian Web API BPS (hanya untuk pemeriksaan lewat Import API BPS).
 * Tiga kriteria baru di Halaman Katalog, sesudah P-057.
 */
return new class extends Migration
{
    private const TIPE_LAMA = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap','jumlah_min','nomor_urut','bab','bulan_rilis','abjad','judul_kover'";
    private const TIPE_BARU = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap','jumlah_min','nomor_urut','bab','bulan_rilis','abjad','judul_kover','sama_api'";

    private function kriteria(): array
    {
        $k = fn(string $kode, int $urutan, string $isian, string $nama, string $label) => [
            'kode'         => $kode,
            'kategori'     => '3.Halaman Katalog',
            'deskripsi'    => "{$nama} — Isian {$label} pada publikasi (kover, halaman katalog, dll.) harus sama dengan yang tercantum di website BPS (Web API).",
            'tipe_cek'     => 'sama_api',
            'parameter'    => json_encode(['isian' => $isian]),
            'target'       => 'front',
            'status_gagal' => 'TIDAK ADA',
            'pesan_ok'     => "{$label} sama dengan Web API BPS",
            'pesan_gagal'  => "{$label} berbeda dengan Web API BPS",
            'aktif'        => true,
            'urutan'       => $urutan,
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
        return [
            $k('P-361', 571, 'issn', 'Kesesuaian ISSN/ISBN dengan Website BPS', 'ISSN/ISBN'),
            $k('P-362', 572, 'katalog', 'Kesesuaian Nomor Katalog dengan Website BPS', 'Nomor Katalog'),
            $k('P-363', 573, 'nomor_publikasi', 'Kesesuaian Nomor Publikasi dengan Website BPS', 'Nomor Publikasi'),
        ];
    }

    public function up(): void
    {
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM(' . self::TIPE_BARU . ") NOT NULL DEFAULT 'manual'");
        foreach ($this->kriteria() as $row) {
            if (!DB::table('kriteria_pemeriksaan')->where('kode', $row['kode'])->exists()) {
                DB::table('kriteria_pemeriksaan')->insert($row);
            }
        }
    }

    public function down(): void
    {
        DB::table('kriteria_pemeriksaan')->whereIn('kode', array_column($this->kriteria(), 'kode'))->delete();
        DB::table('kriteria_pemeriksaan')->where('tipe_cek', 'sama_api')->update(['tipe_cek' => 'manual']);
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM(' . self::TIPE_LAMA . ") NOT NULL DEFAULT 'manual'");
    }
};
