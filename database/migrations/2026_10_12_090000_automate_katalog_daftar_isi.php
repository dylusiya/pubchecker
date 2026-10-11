<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Otomasi 22 kriteria lagi:
 *  - target halaman baru (dikenali dari isinya): katalog, daftar_isi, kata_pengantar
 *  - tipe cek baru: urutan (komponen berurutan), sama_kover (isian sama dengan kover),
 *    paritas (bagian mulai di halaman ganjil/genap), romawi_genap (jumlah halaman romawi Katalog)
 *  - parameter "abaikan" untuk regex/not_regex (bagian teks yang diabaikan, mis. tahun edisi terbitan)
 * Semua kriteria semula manual (target all/cover, status gagal TIDAK ADA) — down() mengembalikannya.
 */
return new class extends Migration
{
    private const TIPE_LAMA   = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual'";
    private const TIPE_BARU   = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap'";
    private const TARGET_LAMA = "'cover','page2','front','last','all','tim_penyusun'";
    private const TARGET_BARU = "'cover','page2','front','last','all','tim_penyusun','katalog','daftar_isi','kata_pengantar'";

    /** Label komponen halaman Katalog (regex). */
    private const KATALOG     = '\bKatalog\b|\bCatalog';
    private const ISSN        = '\bIS[SB]N\b';
    private const NOMOR_PUB   = 'Nomor\s+Publikasi|Publication\s+Number';
    private const JML_HAL     = 'Jumlah\s+Halaman|Number\s+of\s+Pages';
    private const PENYUSUN    = 'Penyusun\s+Naskah|Manuscript';
    private const PENYUNTING  = '\bPenyunting\b|\bEditor\b';
    private const KOVER       = 'Pembuat\s+Kover|Cover\s+Design';
    private const PENERBIT    = '\bPenerbit\b|\bPublisher\b';
    private const DICETAK     = 'Dicetak\s+oleh|Printed\s+by';
    private const ILUSTRASI   = 'Sumber\s+Ilustrasi|Illustration';
    private const HAK_CIPTA   = 'Dilarang\s+mereproduksi|It\s+is\s+prohibited|Dilarang\s+mengumumkan';

    private function aturan(): array
    {
        $urutan = fn(array $label, string $ok, string $gagal) => [
            'tipe_cek' => 'urutan', 'target' => 'katalog', 'parameter' => ['label' => $label],
            'status_gagal' => 'PERLU DICEK', 'pesan_ok' => $ok, 'pesan_gagal' => $gagal,
        ];
        $paritas = fn(string $target, string $posisi, string $nama) => [
            'tipe_cek' => 'paritas', 'target' => $target, 'parameter' => ['posisi' => $posisi],
            'status_gagal' => 'PERLU DICEK',
            'pesan_ok' => "{$nama} dimulai di halaman {$posisi}",
            'pesan_gagal' => "{$nama} tidak dimulai di halaman {$posisi}",
        ];

        return [
            // ── Urutan komponen halaman Katalog ──
            'P-055' => $urutan([self::KATALOG, self::ISSN, self::NOMOR_PUB],
                'Urutan penomoran publikasi sesuai; letak di bawah judul/edisi dicek manual',
                'Urutan penomoran publikasi di halaman Katalog tidak sesuai'),
            'P-067' => $urutan([self::NOMOR_PUB, self::JML_HAL], 'Jumlah Halaman setelah Nomor Publikasi', 'Jumlah Halaman tidak setelah Nomor Publikasi'),
            'P-073' => $urutan([self::JML_HAL, self::PENYUSUN], 'Penyusun Naskah setelah Jumlah Halaman', 'Penyusun Naskah tidak setelah Jumlah Halaman'),
            'P-078' => $urutan([self::PENYUSUN, self::PENYUNTING], 'Penyunting setelah Penyusun Naskah', 'Penyunting tidak setelah Penyusun Naskah'),
            'P-081' => $urutan([self::PENYUNTING, self::KOVER], 'Pembuat Kover setelah Penyunting', 'Pembuat Kover tidak setelah Penyunting'),
            'P-086' => $urutan([self::KOVER, self::PENERBIT], 'Penerbit setelah Pembuat Kover', 'Penerbit tidak setelah Pembuat Kover'),
            'P-090' => $urutan([self::PENERBIT, self::DICETAK], 'Dicetak oleh setelah Penerbit', 'Dicetak oleh tidak setelah Penerbit'),
            'P-096' => $urutan([self::PENERBIT, self::ILUSTRASI], 'Sumber Ilustrasi setelah Penerbit/Dicetak oleh', 'Sumber Ilustrasi tidak setelah Penerbit/Dicetak oleh'),
            'P-100' => $urutan([self::ILUSTRASI, self::HAK_CIPTA], 'Pernyataan Hak Cipta setelah Sumber Ilustrasi', 'Pernyataan Hak Cipta tidak setelah Sumber Ilustrasi'),

            // ── Jumlah halaman di Katalog ──
            'P-069' => [
                'tipe_cek' => 'regex', 'target' => 'katalog',
                'parameter' => ['pattern' => '\b[ivxlcdm]+\s*\+\s*\d+\s*(?:halaman|hlm\.?|pages?)\b', 'flags' => 'iu'],
                'status_gagal' => 'PERLU DICEK',
                'pesan_ok' => 'Jumlah halaman mencakup halaman romawi dan arab',
                'pesan_gagal' => 'Format jumlah halaman romawi + arab (mis. x+52 halaman) tidak ditemukan',
            ],
            'P-070' => [
                'tipe_cek' => 'romawi_genap', 'target' => 'katalog', 'parameter' => null,
                'status_gagal' => 'TIDAK ADA',
                'pesan_ok' => 'Jumlah halaman romawi genap',
                'pesan_gagal' => 'Jumlah halaman romawi ganjil — tambahkan halaman kosong',
            ],

            // ── Halaman ganjil/genap (urutan halaman PDF, kover = hal. 1) ──
            'P-052' => $paritas('katalog', 'genap', 'Halaman Katalog'),
            'P-103' => $paritas('tim_penyusun', 'ganjil', 'Halaman Tim Penyusun'),
            'P-126' => $paritas('kata_pengantar', 'ganjil', 'Kata Pengantar'),
            'P-145' => $paritas('daftar_isi', 'ganjil', 'Daftar Isi'),

            // ── ISSN di Daftar Isi ──
            'P-146' => [
                'tipe_cek' => 'posisi_area', 'target' => 'daftar_isi',
                'parameter' => ['word' => 'ISSN', 'area' => 'top_right'],
                'status_gagal' => 'PERLU DICEK',
                'pesan_ok' => 'ISSN berada di pojok kanan atas halaman Daftar Isi',
                'pesan_gagal' => 'ISSN tidak berada di pojok kanan atas halaman Daftar Isi',
            ],
            'P-147' => [
                'tipe_cek' => 'not_regex', 'target' => 'daftar_isi',
                'parameter' => ['pattern' => 'ISSN\s*:', 'flags' => 'iu', 'syarat' => '\bISSN\b', 'pesan_syarat' => 'Tidak ada ISSN di halaman Daftar Isi'],
                'status_gagal' => 'TIDAK ADA',
                'pesan_ok' => 'ISSN di Daftar Isi ditulis tanpa titik dua',
                'pesan_gagal' => 'ISSN di Daftar Isi ditulis dengan titik dua (seharusnya: ISSN XXXX-XXXX)',
            ],
            'P-148' => [
                'tipe_cek' => 'sama_kover', 'target' => 'daftar_isi',
                'parameter' => ['pattern' => '\bISSN\s*:?\s*(\d{4}\s*[-\x{2013}]\s*\d{3}[\dXx])', 'flags' => 'iu', 'pesan_syarat' => 'Tidak ada ISSN di halaman Daftar Isi'],
                'status_gagal' => 'TIDAK ADA',
                'pesan_ok' => 'ISSN di Daftar Isi sama dengan kover',
                'pesan_gagal' => 'ISSN di Daftar Isi berbeda dengan kover',
            ],

            // ── Lainnya ──
            'P-153' => [
                'tipe_cek' => 'not_regex', 'target' => 'daftar_isi',
                'parameter' => ['pattern' => '^[ \t]*\d+(?:\.\d+)+\.[ \t]+\S', 'flags' => 'mu'],
                'status_gagal' => 'TIDAK ADA',
                'pesan_ok' => 'Nomor subbab di Daftar Isi tanpa titik di belakang',
                'pesan_gagal' => 'Nomor subbab di Daftar Isi diakhiri titik (seharusnya 2.3, bukan 2.3.)',
            ],
            'P-330' => [
                'tipe_cek' => 'not_regex', 'target' => 'last',
                'parameter' => ['pattern' => '\bAbstra(?:k|ksi|ct)\b', 'flags' => 'iu'],
                'status_gagal' => 'TIDAK ADA',
                'pesan_ok' => 'Kover belakang tidak memuat abstraksi',
                'pesan_gagal' => 'Kover belakang memuat abstraksi',
            ],
            'P-356' => [
                'tipe_cek' => 'not_regex', 'target' => 'all',
                // ≥ 2 kelompok ",ddd" pasti pemisah ribuan gaya Inggris (mis. 1,234,567)
                'parameter' => ['pattern' => '(?<![\d.,])\d{1,3}(?:,\d{3}){2,}(?![\d,])', 'flags' => 'u'],
                'status_gagal' => 'PERLU DICEK',
                'pesan_ok' => 'Tidak ditemukan pemisah ribuan berupa koma',
                'pesan_gagal' => 'Ditemukan bilangan dengan pemisah ribuan koma (seharusnya titik; abaikan bila bagian bahasa Inggris)',
            ],
            'P-018' => [
                'tipe_cek' => 'regex', 'target' => 'cover',
                'parameter' => [
                    'pattern' => '(?<![\d.,/-])(?:19|20)\d{2}',
                    'flags'   => 'iu',
                    // tahun pada edisi terbitan, katalog, ISSN, alamat web bukan tahun judul
                    'abaikan' => '(?:Volume|Vol\.?|Edisi|Nomor|No\.)\s*\d+[^\n]{0,15}?(?:19|20)\d{2}|Katalog[^\n]*|IS[SB]N[^\n]*|https?://\S+|\S+\.go\.id\S*',
                ],
                'status_gagal' => 'PERLU DICEK',
                'pesan_ok' => 'Tahun judul tercantum di kover',
                'pesan_gagal' => 'Tahun judul tidak ditemukan di kover (selain tahun edisi terbitan)',
            ],
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
        foreach (array_keys($this->aturan()) as $kode) {
            DB::table('kriteria_pemeriksaan')->where('kode', $kode)->update([
                'tipe_cek'     => 'manual',
                'target'       => $kode === 'P-018' ? 'cover' : 'all',
                'parameter'    => null,
                'status_gagal' => 'TIDAK ADA',
                'pesan_ok'     => null,
                'pesan_gagal'  => 'Perlu diperiksa manual oleh petugas sesuai Pedoman Pembuatan Publikasi 2023.',
                'updated_at'   => now(),
            ]);
        }
        DB::table('kriteria_pemeriksaan')->whereIn('tipe_cek', ['urutan', 'sama_kover', 'paritas', 'romawi_genap'])->update(['tipe_cek' => 'manual']);
        DB::table('kriteria_pemeriksaan')->whereIn('target', ['katalog', 'daftar_isi', 'kata_pengantar'])->update(['target' => 'all']);
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM(' . self::TIPE_LAMA . ") NOT NULL DEFAULT 'manual'");
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY target ENUM(' . self::TARGET_LAMA . ") NOT NULL DEFAULT 'cover'");
    }
};
