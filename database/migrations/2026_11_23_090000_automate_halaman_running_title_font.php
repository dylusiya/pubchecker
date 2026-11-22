<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Empat tipe cek baru dari data tepi halaman & nama font (js/pdf-extract.js tepiHalaman / fontHalaman):
 *   daftar_halaman — entri Daftar Isi/Tabel/Gambar ada di halaman yang disebut (tabel/gambar: judul juga sama)
 *   nomor_halaman  — nomor halaman romawi & arab berurutan
 *   running_title  — ada di halaman arab, tidak di halaman romawi, ganjil = judul bab, genap = judul buku
 *   font_bps       — nama BPS penerbit Arial Bold Italic UPPERCASE (kover depan, halaman judul utama, kover belakang)
 * Kriteria manual yang sudah ada diotomatiskan (hanya bila masih manual), ditambah 5 kriteria baru (P-364 s.d. P-368).
 */
return new class extends Migration
{
    private const TIPE_LAMA = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap','jumlah_min','nomor_urut','bab','bulan_rilis','abjad','judul_kover','sama_api','rata_angka'";
    private const TIPE_BARU = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap','jumlah_min','nomor_urut','bab','bulan_rilis','abjad','judul_kover','sama_api','rata_angka','nomor_halaman','running_title','daftar_halaman','font_bps'";
    private const PESAN_MANUAL = 'Perlu diperiksa manual oleh petugas sesuai Pedoman Pembuatan Publikasi 2023.';

    /** Kriteria manual yang diotomatiskan: kode => isian baru. */
    private function ubah(): array
    {
        $k = fn(string $tipe, array $param, string $target, string $gagal, string $ok, string $salah) => [
            'tipe_cek' => $tipe, 'parameter' => json_encode($param), 'target' => $target,
            'status_gagal' => $gagal, 'pesan_ok' => $ok, 'pesan_gagal' => $salah,
        ];
        return [
            'P-163' => $k('daftar_halaman', ['daftar' => 'tabel'], 'daftar_tabel', 'TIDAK ADA',
                'Nomor, judul, dan halaman di Daftar Tabel sesuai dengan isi', 'Daftar Tabel seharusnya sama dengan tabel di halaman isi'),
            'P-173' => $k('daftar_halaman', ['daftar' => 'gambar'], 'daftar_gambar', 'TIDAK ADA',
                'Nomor, judul, dan halaman di Daftar Gambar sesuai dengan isi', 'Daftar Gambar seharusnya sama dengan gambar di halaman isi'),
            'P-227' => $k('running_title', ['cek' => 'arab'], 'all', 'TIDAK ADA',
                'Running title ada di halaman bernomor arab', 'Running title seharusnya ada di setiap halaman bernomor arab'),
            'P-228' => $k('running_title', ['cek' => 'romawi'], 'all', 'TIDAK ADA',
                'Halaman romawi tanpa running title', 'Halaman bernomor romawi seharusnya tanpa running title'),
            'P-229' => $k('running_title', ['cek' => 'ganjil'], 'all', 'PERLU DICEK',
                'Running title halaman ganjil berisi judul bab', 'Running title halaman ganjil seharusnya berisi judul bab'),
            'P-230' => $k('running_title', ['cek' => 'genap'], 'all', 'PERLU DICEK',
                'Running title halaman genap berisi judul buku', 'Running title halaman genap seharusnya berisi judul buku'),
        ];
    }

    /** Kriteria baru. */
    private function baru(): array
    {
        $k = fn(string $kode, int $urutan, string $kategori, string $deskripsi, string $tipe, ?array $param, string $target, string $ok, string $salah) => [
            'kode' => $kode, 'kategori' => $kategori, 'deskripsi' => $deskripsi, 'tipe_cek' => $tipe,
            'parameter' => $param ? json_encode($param) : null, 'target' => $target, 'status_gagal' => 'TIDAK ADA',
            'pesan_ok' => $ok, 'pesan_gagal' => $salah, 'aktif' => true, 'urutan' => $urutan, 'created_at' => now(), 'updated_at' => now(),
        ];
        $bps = 'harus menggunakan huruf Arial Bold Italic dengan format UPPERCASE (kapital semua).';
        return [
            $k('P-364', 1525, '8.Daftar Isi', 'Kesesuaian Nomor Halaman Daftar Isi — Nomor halaman setiap bab/subbab pada daftar isi harus sesuai dengan halaman isinya.',
                'daftar_halaman', ['daftar' => 'isi'], 'daftar_isi', 'Nomor halaman di Daftar Isi sesuai dengan isi', 'Nomor halaman di Daftar Isi seharusnya sesuai dengan halaman isinya'),
            $k('P-365', 3595, '25.All', 'Urutan Nomor Halaman — Nomor halaman romawi dan arab harus berurutan, tanpa loncat atau ganda.',
                'nomor_halaman', null, 'all', 'Nomor halaman berurutan', 'Nomor halaman seharusnya berurutan'),
            $k('P-366', 325, '1.Kover Depan', "Huruf Nama BPS Penerbit pada Kover Depan — Nama BPS penerbit {$bps}",
                'font_bps', ['halaman' => 'kover'], 'cover', 'Nama BPS penerbit Arial Bold Italic UPPERCASE', 'Nama BPS penerbit seharusnya Arial Bold Italic UPPERCASE'),
            $k('P-367', 475, '2.Halaman Judul Utama', "Huruf Nama BPS Penerbit pada Halaman Judul Utama — Nama BPS penerbit {$bps}",
                'font_bps', ['halaman' => 'judul'], 'front', 'Nama BPS penerbit Arial Bold Italic UPPERCASE', 'Nama BPS penerbit seharusnya Arial Bold Italic UPPERCASE'),
            $k('P-368', 3505, '24.Kover Belakang', "Huruf Nama BPS Penerbit pada Kover Belakang — Nama BPS penerbit {$bps}",
                'font_bps', ['halaman' => 'belakang'], 'last', 'Nama BPS penerbit Arial Bold Italic UPPERCASE', 'Nama BPS penerbit seharusnya Arial Bold Italic UPPERCASE'),
        ];
    }

    public function up(): void
    {
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM(' . self::TIPE_BARU . ") NOT NULL DEFAULT 'manual'");
        foreach ($this->ubah() as $kode => $isian) {
            DB::table('kriteria_pemeriksaan')->where(['kode' => $kode, 'tipe_cek' => 'manual'])->update($isian + ['updated_at' => now()]);
        }
        foreach ($this->baru() as $row) {
            if (!DB::table('kriteria_pemeriksaan')->where('kode', $row['kode'])->exists()) {
                DB::table('kriteria_pemeriksaan')->insert($row);
            }
        }
    }

    public function down(): void
    {
        DB::table('kriteria_pemeriksaan')->whereIn('kode', array_column($this->baru(), 'kode'))->delete();
        DB::table('kriteria_pemeriksaan')->whereIn('kode', array_keys($this->ubah()))
            ->whereIn('tipe_cek', ['nomor_halaman', 'running_title', 'daftar_halaman', 'font_bps'])
            ->update(['tipe_cek' => 'manual', 'parameter' => null, 'target' => 'all', 'status_gagal' => 'TIDAK ADA',
                      'pesan_ok' => null, 'pesan_gagal' => self::PESAN_MANUAL, 'updated_at' => now()]);
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM(' . self::TIPE_LAMA . ") NOT NULL DEFAULT 'manual'");
    }
};
