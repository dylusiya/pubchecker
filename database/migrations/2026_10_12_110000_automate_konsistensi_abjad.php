<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Otomasi 13 kriteria lagi:
 *  - target halaman belakang (dikenali dari judulnya): daftar_pustaka, glosarium, indeks, lampiran
 *  - tipe cek baru: abjad (entri urut abjad), judul_kover (judul & tahun judul sama dengan kover)
 *  - edisi terbitan di Katalog/Tim Penyusun/Daftar Isi sama dengan kover (sama_kover)
 *  - pemisah desimal bertitik pada publikasi berbahasa Indonesia (not_regex)
 * Semua kriteria semula manual, target all, status gagal TIDAK ADA — down() mengembalikannya.
 */
return new class extends Migration
{
    private const TIPE_LAMA   = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap','jumlah_min','nomor_urut','bab','bulan_rilis'";
    private const TIPE_BARU   = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap','jumlah_min','nomor_urut','bab','bulan_rilis','abjad','judul_kover'";
    private const TARGET_LAMA = "'cover','page2','front','last','all','tim_penyusun','katalog','daftar_isi','kata_pengantar','kata_sambutan','abstraksi','daftar_tabel','daftar_gambar','daftar_lampiran'";
    private const TARGET_BARU = "'cover','page2','front','last','all','tim_penyusun','katalog','daftar_isi','kata_pengantar','kata_sambutan','abstraksi','daftar_tabel','daftar_gambar','daftar_lampiran','daftar_pustaka','glosarium','indeks','lampiran'";

    /** Edisi terbitan, mis. "Volume 15, 2026" atau "Volume 3, Nomor 2, 2026". */
    private const EDISI = '\b((?:Volume|Vol\.?)\s*\d+(?:\s*,\s*(?:Nomor|No\.?)\s*\d+)?\s*,\s*(?:19|20)\d{2})';

    private function aturan(): array
    {
        $a = fn(string $tipe, string $target, ?array $param, string $ok, string $err) => [
            'tipe_cek' => $tipe, 'target' => $target, 'parameter' => $param,
            'status_gagal' => 'PERLU DICEK', 'pesan_ok' => $ok, 'pesan_gagal' => $err,
        ];
        $edisi = fn(string $target, string $nama) => $a('sama_kover', $target,
            ['pattern' => self::EDISI, 'flags' => 'iu', 'pesan_syarat' => "Edisi terbitan tidak ditemukan di halaman {$nama}"],
            "Edisi terbitan di {$nama} sama dengan kover", "Edisi terbitan di {$nama} berbeda dengan kover");
        $judul = fn(string $target, string $nama) => $a('judul_kover', $target, null,
            "Judul & tahun judul di {$nama} sama dengan kover", "Judul/tahun judul di {$nama} berbeda dengan kover");
        $ganjil = fn(string $target, string $nama) => $a('paritas', $target, ['posisi' => 'ganjil'],
            "{$nama} dimulai di halaman ganjil", "{$nama} tidak dimulai di halaman ganjil");

        return [
            // ── Konsistensi dengan kover ──
            'P-054' => $edisi('katalog', 'halaman Katalog'),
            'P-106' => $edisi('tim_penyusun', 'halaman Tim Penyusun'),
            'P-151' => $edisi('daftar_isi', 'halaman Daftar Isi'),
            'P-053' => $judul('katalog', 'halaman Katalog'),
            'P-105' => $judul('tim_penyusun', 'halaman Tim Penyusun'),
            'P-150' => $judul('daftar_isi', 'halaman Daftar Isi'),

            // ── Urut abjad ──
            'P-302' => $a('abjad', 'daftar_pustaka', [
                // entri: "Penulis. (2025)." / "Penulis. 2025." / "Penulis. n.d." di awal baris; kunci = nama penulis
                'pattern' => '^(\p{Lu}[^\n(]{1,120}?)\.\s*\(?(?:(?:19|20)\d{2}[a-z]?|n\.\s?d\.?)\)?[.,]',
                'flags'   => 'mu',
            ], 'Daftar pustaka tersusun menurut abjad', 'Daftar pustaka tidak tersusun menurut abjad'),
            'P-312' => $a('abjad', 'indeks', [
                'pattern' => '^[ \t]*(\p{L}[\p{L}\d \-\'/]{1,60}?)[ \t]*,[ \t]*\d',
                'flags'   => 'mu',
            ], 'Indeks tersusun menurut abjad', 'Indeks tidak tersusun menurut abjad'),
            'P-318' => $a('abjad', 'glosarium', [
                'pattern' => '^[ \t]*(\p{Lu}[\p{L}\d \-\'/()]{1,60}?)[ \t]*(?::|[\x{2013}\x{2014}]|\s-\s)',
                'flags'   => 'mu',
            ], 'Glosarium tersusun menurut abjad', 'Glosarium tidak tersusun menurut abjad'),

            // ── Halaman ganjil ──
            'P-310' => $ganjil('indeks', 'Indeks'),
            'P-315' => $ganjil('glosarium', 'Glosarium'),
            'P-321' => $ganjil('lampiran', 'Lampiran'),

            // ── Pemisah desimal koma (bahasa Indonesia) ──
            'P-354' => $a('not_regex', 'all', [
                // angka bertitik dengan 1–2 angka di belakang (4.5, 12.75), bukan nomor tabel/gambar/bab,
                // jam (pukul 08.30), ribuan (1.250), tanggal/alamat, atau nomor yang diikuti judul berhuruf kapital
                'pattern' => '(?<![\w.,\/:-])(?<!Tabel\s)(?<!Gambar\s)(?<!Lampiran\s)(?<!BAB\s)(?<!Bab\s)(?<!pukul\s)(?<!Pukul\s)(?<!jam\s)(?<!Jam\s)\d{1,3}\.\d{1,2}(?![\d.,\/:])(?!\s+\p{Lu})',
                'flags'   => 'u',
            ], 'Tidak ditemukan pemisah desimal berupa titik',
               'Ditemukan bilangan desimal bertitik (seharusnya koma; abaikan bila bagian bahasa Inggris)'),
        ];
    }

    public function up(): void
    {
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM(' . self::TIPE_BARU . ") NOT NULL DEFAULT 'manual'");
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY target ENUM(' . self::TARGET_BARU . ") NOT NULL DEFAULT 'cover'");

        foreach ($this->aturan() as $kode => $a) {
            DB::table('kriteria_pemeriksaan')->where('kode', $kode)->where('tipe_cek', 'manual')->update([
                'tipe_cek'     => $a['tipe_cek'],
                'target'       => $a['target'],
                'parameter'    => $a['parameter'] === null ? null : json_encode($a['parameter'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status_gagal' => $a['status_gagal'],
                'pesan_ok'     => $a['pesan_ok'],
                'pesan_gagal'  => $a['pesan_gagal'],
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('kriteria_pemeriksaan')->whereIn('kode', array_keys($this->aturan()))->update([
            'tipe_cek'     => 'manual',
            'target'       => 'all',
            'parameter'    => null,
            'status_gagal' => 'TIDAK ADA',
            'pesan_ok'     => null,
            'pesan_gagal'  => 'Perlu diperiksa manual oleh petugas sesuai Pedoman Pembuatan Publikasi 2023.',
            'updated_at'   => now(),
        ]);
        DB::table('kriteria_pemeriksaan')->whereIn('tipe_cek', ['abjad', 'judul_kover'])->update(['tipe_cek' => 'manual']);
        DB::table('kriteria_pemeriksaan')->whereIn('target', ['daftar_pustaka', 'glosarium', 'indeks', 'lampiran'])->update(['target' => 'all']);
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM(' . self::TIPE_LAMA . ") NOT NULL DEFAULT 'manual'");
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY target ENUM(' . self::TARGET_LAMA . ") NOT NULL DEFAULT 'cover'");
    }
};
