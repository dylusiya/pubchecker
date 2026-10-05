<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dua kemampuan baru di PdfCheckerService (bukan cuma data, lihat tipeOptions() & evalRule()):
 *
 * - urutan_teks : kemunculan pertama pola "before" harus ada sebelum pola "after" di target text.
 *   Dipakai untuk rentetan "Posisi X diletakkan setelah Y" di halaman katalog.
 *
 * - syarat_min  : hitung kemunculan "item_pattern" di SELURUH dokumen; kalau sudah ≥ min,
 *   baru "require_pattern" wajib ada di target text rule ini.
 *   Dipakai untuk "Daftar X dibuat jika minimal N buah" (daftar tabel/gambar/lampiran).
 *
 * Istilah katalog (Nomor Publikasi, Jumlah Halaman, Penyusun Naskah, dst.) memakai pola yang sama
 * persis dengan kriteria presence-check yang sudah diotomatisasi di migration sebelumnya, supaya
 * konsisten kalau salah satu pattern-nya diperbaiki lagi nanti.
 */
return new class extends Migration
{
    private const MANUAL_MSG = 'Perlu diperiksa manual oleh petugas sesuai Pedoman Pembuatan Publikasi 2023.';

    // ── Istilah katalog (sama dengan pattern di migration automate_kriteria_pemeriksaan) ──
    private const NOMOR_PUBLIKASI    = 'Nomor\s+Publikasi';
    private const JUMLAH_HALAMAN     = 'Jumlah\s+Halaman(?:\s*/\s*Number\s+of\s+Pages)?\s*:';
    private const PENYUSUN_NASKAH    = 'Penyusun\s+Naskah(?:\s*/\s*Manuscript\s+Drafter)?\s*:';
    private const PENYUNTING         = 'Penyunting(?:\s*/\s*Editor)?\s*:';
    private const PEMBUAT_KOVER      = 'Pembuat\s+Kover(?:\s*/\s*Cover\s+Designer)?\s*:';
    private const PENERBIT           = 'Penerbit(?:\s*/\s*Publisher)?\s*:';
    private const DICETAK_OLEH       = 'Dicetak\s+oleh(?:\s*/\s*Printed\s+by)?\s*:';
    private const SUMBER_ILUSTRASI   = 'Sumber\s+Ilustrasi(?:\s*/\s*Illustration\s+Source)?\s*:';
    private const PERNYATAAN_HAKCIPTA= 'Dilarang\s+mereproduksi\s+dan\s*/\s*atau\s+menggandakan\s+sebagian\s+atau\s+seluruh\s+isi\s+buku\s+ini\s+untuk\s+tujuan\s+komersial\s+tanpa\s+izin\s+tertulis\s+dari';

    private function urutanRules(): array
    {
        $u = fn(string $before, string $after, string $ok, string $err) => [
            'tipe_cek' => 'urutan_teks', 'target' => 'front',
            'parameter' => ['before' => $before, 'after' => $after, 'flags' => 'iu'],
            'status_gagal' => 'PERLU DICEK', 'pesan_ok' => $ok, 'pesan_gagal' => $err,
        ];

        return [
            'P-067' => $u(self::NOMOR_PUBLIKASI,  self::JUMLAH_HALAMAN,
                'Jumlah Halaman berada setelah Nomor Publikasi', 'Jumlah Halaman tidak ditemukan setelah Nomor Publikasi'),
            'P-073' => $u(self::JUMLAH_HALAMAN,   self::PENYUSUN_NASKAH,
                'Penyusun Naskah berada setelah Jumlah Halaman', 'Penyusun Naskah tidak ditemukan setelah Jumlah Halaman'),
            'P-078' => $u(self::PENYUSUN_NASKAH,  self::PENYUNTING,
                'Penyunting berada setelah Penyusun Naskah', 'Penyunting tidak ditemukan setelah Penyusun Naskah'),
            'P-081' => $u(self::PENYUNTING,       self::PEMBUAT_KOVER,
                'Pembuat Kover berada setelah Penyunting', 'Pembuat Kover tidak ditemukan setelah Penyunting'),
            'P-086' => $u(self::PEMBUAT_KOVER,    self::PENERBIT,
                'Penerbit berada setelah Pembuat Kover', 'Penerbit tidak ditemukan setelah Pembuat Kover'),
            'P-090' => $u(self::PENERBIT,         self::DICETAK_OLEH,
                'Dicetak Oleh berada setelah Penerbit', 'Dicetak Oleh tidak ditemukan setelah Penerbit (abaikan jika publikasi tidak dicetak)'),
            'P-096' => $u(self::PENERBIT,         self::SUMBER_ILUSTRASI,
                'Sumber Ilustrasi berada setelah Penerbit', 'Sumber Ilustrasi tidak ditemukan setelah Penerbit (abaikan jika tidak ada ilustrasi)'),
            'P-100' => $u(self::SUMBER_ILUSTRASI, self::PERNYATAAN_HAKCIPTA,
                'Pernyataan Hak Cipta berada setelah Sumber Ilustrasi', 'Pernyataan Hak Cipta tidak ditemukan setelah Sumber Ilustrasi'),
        ];
    }

    private function syaratMinRules(): array
    {
        $s = fn(string $item, string $require, string $ok, string $err) => [
            'tipe_cek' => 'syarat_min', 'target' => 'front',
            'parameter' => ['item_pattern' => $item, 'require_pattern' => $require, 'min' => 3, 'flags' => 'iu'],
            'status_gagal' => 'PERLU DICEK', 'pesan_ok' => $ok, 'pesan_gagal' => $err,
        ];

        return [
            'P-158' => $s('\b(?:Tabel|Table)\s+\d',    'Daftar\s+Tabel',
                'Daftar Tabel ditemukan', 'Terdapat ≥ 3 tabel tapi "Daftar Tabel" tidak ditemukan'),
            'P-167' => $s('\b(?:Gambar|Figure)\s+\d',  'Daftar\s+Gambar',
                'Daftar Gambar ditemukan', 'Terdapat ≥ 3 gambar tapi "Daftar Gambar" tidak ditemukan'),
            'P-177' => $s('\b(?:Lampiran|Appendix)\s+\d', 'Daftar\s+Lampiran',
                'Daftar Lampiran ditemukan', 'Terdapat ≥ 3 lampiran tapi "Daftar Lampiran" tidak ditemukan'),
        ];
    }

    private function rules(): array
    {
        return $this->urutanRules() + $this->syaratMinRules();
    }

    public function up(): void
    {
        DB::statement("ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM('regex','not_regex','contains','not_contains','posisi_area','min_pages','urutan_teks','syarat_min','manual') NOT NULL DEFAULT 'manual'");

        foreach ($this->rules() as $kode => $rule) {
            $rule['parameter'] = json_encode($rule['parameter'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            DB::table('kriteria_pemeriksaan')->where('kode', $kode)->update($rule + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->rules()) as $kode) {
            DB::table('kriteria_pemeriksaan')->where('kode', $kode)->update([
                'tipe_cek'     => 'manual',
                'target'       => 'all',
                'parameter'    => null,
                'status_gagal' => 'TIDAK ADA',
                'pesan_ok'     => null,
                'pesan_gagal'  => self::MANUAL_MSG,
                'updated_at'   => now(),
            ]);
        }

        DB::statement("ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM('regex','not_regex','contains','not_contains','posisi_area','min_pages','manual') NOT NULL DEFAULT 'manual'");
    }
};
