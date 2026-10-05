<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lanjutan dari 2026_10_04_090000_automate_kriteria_pemeriksaan.php: kriteria tambahan yang
 * ternyata bisa dicek dari teks PDF dengan tipe cek yang sudah ada (regex/not_regex), tapi
 * terlewat di batch pertama — kebanyakan "pasangan" dari kriteria yang sudah diotomatisasi
 * (versi bahasa Inggris, atau rule yang sama di kategori daftar isi/daftar tabel/daftar gambar).
 *
 * Kriteria yang butuh kemampuan baru (hitung minimal N kemunculan, atau cek urutan antar istilah)
 * sengaja belum dimasukkan di sini — itu perlu tipe_cek baru di PdfCheckerService, bukan cuma
 * data migration.
 */
return new class extends Migration
{
    private const MANUAL_MSG = 'Perlu diperiksa manual oleh petugas sesuai Pedoman Pembuatan Publikasi 2023.';

    private function rules(): array
    {
        $re  = fn(string $target, string $pattern, string $flags, string $gagal, string $ok, string $err) =>
            ['tipe_cek' => 'regex', 'target' => $target, 'parameter' => array_filter(['pattern' => $pattern, 'flags' => $flags]),
             'status_gagal' => $gagal, 'pesan_ok' => $ok, 'pesan_gagal' => $err];
        $not = fn(string $target, string $pattern, string $flags, string $gagal, string $ok, string $err) =>
            ['tipe_cek' => 'not_regex', 'target' => $target, 'parameter' => array_filter(['pattern' => $pattern, 'flags' => $flags]),
             'status_gagal' => $gagal, 'pesan_ok' => $ok, 'pesan_gagal' => $err];

        $P = 'PERLU DICEK';

        return [
            // ── 3. Halaman Katalog — pasangan bahasa Inggris dari P-101 (sudah otomatis) ──
            'P-102' => $re('front', 'It\s+is\s+prohibited\s+to\s+reproduce\s+and\s*/\s*or\s+duplicate\s+part\s+or\s+all\s+of\s+this\s+book\s+for\s+commercial\s+purpose\s+without\s+permission\s+from', 'iu', $P,
                'Pernyataan hak cipta bahasa Inggris sesuai', 'Pernyataan hak cipta bahasa Inggris tidak sesuai bunyi baku (abaikan jika publikasi satu bahasa)'),

            // ── 8. Daftar Isi — nomor subbab tanpa titik, sama seperti P-221 (Narasi) tapi di daftar isi ──
            'P-153' => $not('front', '^\s*\d+\.\d+(?:\.\d+)*\.\s+[A-Z][a-z]', 'mu', $P,
                'Nomor subbab di daftar isi tanpa titik akhir', 'Nomor subbab di daftar isi diakhiri titik (seharusnya "2.3 Latar Belakang")'),

            // ── 9/10/11. Daftar Tabel/Gambar/Lampiran — judul wajib ada (hanya berlaku jika dibuat, lihat P-158/167/177) ──
            'P-159' => $re('front', 'Daftar\s+Tabel', 'iu', $P,
                'Judul "Daftar Tabel" ditemukan', 'Judul "Daftar Tabel" tidak ditemukan (wajib jika ≥ 3 tabel dalam publikasi, abaikan jika tabel < 3)'),
            'P-168' => $re('front', 'Daftar\s+Gambar', 'iu', $P,
                'Judul "Daftar Gambar" ditemukan', 'Judul "Daftar Gambar" tidak ditemukan (wajib jika ≥ 3 gambar dalam publikasi, abaikan jika gambar < 3)'),
            'P-169' => $re('front', 'Daftar\s+Gambar', 'iu', $P,
                'Istilah "Daftar Gambar" ditemukan', 'Istilah "Daftar Gambar" tidak ditemukan (tetap wajib dipakai walau seluruh isinya grafik)'),
            'P-172' => $not('all', '^\s*(?:Gambar|Figure)\s+\d+(?:\.\d+)*\.(?=\s)', 'mu', $P,
                'Nomor gambar tanpa titik akhir', 'Nomor gambar diakhiri titik'),
            'P-178' => $re('front', 'Daftar\s+Lampiran', 'iu', $P,
                'Judul "Daftar Lampiran" ditemukan', 'Judul "Daftar Lampiran" tidak ditemukan (wajib jika ≥ 3 lampiran dalam publikasi, abaikan jika lampiran < 3)'),
            'P-181' => $not('all', '^\s*(?:Lampiran|Appendix)\s+\d+(?:\.\d+)*\.(?=\s)', 'mu', $P,
                'Nomor lampiran tanpa titik akhir', 'Nomor lampiran diakhiri titik'),

            // ── 12/13. Penjelasan Umum/Teknis — judulnya opsional, flag cuma buat dicek manusia ──
            'P-186' => $re('front', 'Penjelasan\s+Umum', 'iu', $P,
                'Judul "Penjelasan Umum" ditemukan', 'Judul "Penjelasan Umum" tidak ditemukan (opsional — abaikan jika memang tidak dibuat)'),
            // "Penjelasan" saja terlalu umum dipakai di kalimat biasa untuk dicek keberadaannya —
            // hanya "Penjelasan Teknis" (judul dwibahasa) yang cukup spesifik untuk regex.
            'P-191' => $re('all', 'Penjelasan\s+Teknis', 'iu', $P,
                'Judul "Penjelasan Teknis" ditemukan', 'Judul "Penjelasan Teknis"/"Penjelasan" tidak ditemukan (opsional — abaikan jika memang tidak dibuat, atau jika publikasi satu bahasa memakai judul "Penjelasan" saja)'),

            // ── 18. Tabel / 19. Gambar — salah pakai "Keterangan:" padahal seharusnya "Catatan:" ──
            'P-271' => $not('all', 'Keterangan\s*:', 'iu', $P,
                'Tidak ditemukan istilah "Keterangan:" yang salah', 'Ditemukan istilah "Keterangan:" di bawah tabel — seharusnya "Catatan:"'),
            'P-290' => $not('all', 'Keterangan\s*:', 'iu', $P,
                'Tidak ditemukan istilah "Keterangan:" yang salah', 'Ditemukan istilah "Keterangan:" di bawah gambar — seharusnya "Catatan:"'),
        ];
    }

    public function up(): void
    {
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
    }
};
