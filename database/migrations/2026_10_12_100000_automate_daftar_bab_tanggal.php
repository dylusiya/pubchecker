<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Otomasi 29 kriteria lagi:
 *  - target halaman baru (dikenali dari isinya): kata_sambutan, abstraksi, daftar_tabel, daftar_gambar, daftar_lampiran
 *  - tipe cek baru: jumlah_min (minimal N entri), nomor_urut (nomor berurutan & format konsisten),
 *    bab (awal bab di halaman ganjil / nomor bab berurutan), bulan_rilis (bulan tanda tangan vs tanggal rilis)
 * Semua kriteria semula manual dengan target all — down() mengembalikannya.
 */
return new class extends Migration
{
    private const TIPE_LAMA   = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap'";
    private const TIPE_BARU   = "'regex','not_regex','contains','not_contains','posisi_area','min_pages','manual','urutan','sama_kover','paritas','romawi_genap','jumlah_min','nomor_urut','bab','bulan_rilis'";
    private const TARGET_LAMA = "'cover','page2','front','last','all','tim_penyusun','katalog','daftar_isi','kata_pengantar'";
    private const TARGET_BARU = "'cover','page2','front','last','all','tim_penyusun','katalog','daftar_isi','kata_pengantar','kata_sambutan','abstraksi','daftar_tabel','daftar_gambar','daftar_lampiran'";

    /** Kata awal entri tiap jenis daftar. */
    private const ENTRI = ['tabel' => '(?:Tabel|Table)', 'gambar' => '(?:Gambar|Figure)', 'lampiran' => '(?:Lampiran|Appendix)'];

    private function aturan(): array
    {
        $a = fn(string $tipe, string $target, ?array $param, string $gagal, string $ok, string $err) => [
            'tipe_cek' => $tipe, 'target' => $target, 'parameter' => $param,
            'status_gagal' => $gagal, 'pesan_ok' => $ok, 'pesan_gagal' => $err,
        ];
        $paritas = fn(string $target, string $nama) => $a('paritas', $target, ['posisi' => 'ganjil'], 'PERLU DICEK',
            "{$nama} dimulai di halaman ganjil", "{$nama} tidak dimulai di halaman ganjil");
        $entri = fn(string $jenis) => '^\s*' . self::ENTRI[$jenis] . '\s+';

        return [
            // ── Halaman ganjil ──
            'P-115' => $paritas('kata_sambutan', 'Kata Sambutan'),
            'P-140' => $paritas('abstraksi', 'Abstraksi'),
            'P-156' => $paritas('daftar_tabel', 'Daftar Tabel'),
            'P-165' => $paritas('daftar_gambar', 'Daftar Gambar'),
            'P-175' => $paritas('daftar_lampiran', 'Daftar Lampiran'),

            // ── Judul daftar (judul bisa berupa gambar → Perlu Dicek, bukan Tidak Ada) ──
            'P-159' => $a('regex', 'daftar_tabel', ['pattern' => 'DAFTAR\s+TABEL|LIST\s+OF\s+TABLES', 'flags' => 'iu'], 'PERLU DICEK',
                'Judul "Daftar Tabel" ditemukan', 'Judul "Daftar Tabel" tidak ditemukan sebagai teks di halaman daftar tabel'),
            'P-168' => $a('regex', 'daftar_gambar', ['pattern' => 'DAFTAR\s+GAMBAR|LIST\s+OF\s+FIGURES', 'flags' => 'iu'], 'PERLU DICEK',
                'Judul "Daftar Gambar" ditemukan', 'Judul "Daftar Gambar" tidak ditemukan sebagai teks di halaman daftar gambar'),
            'P-178' => $a('regex', 'daftar_lampiran', ['pattern' => 'DAFTAR\s+LAMPIRAN|LIST\s+OF\s+APPENDI', 'flags' => 'iu'], 'PERLU DICEK',
                'Judul "Daftar Lampiran" ditemukan', 'Judul "Daftar Lampiran" tidak ditemukan sebagai teks di halaman daftar lampiran'),
            'P-169' => $a('not_regex', 'front', ['pattern' => 'DAFTAR\s+(?:GRAFIK|DIAGRAM|PETA|ILUSTRASI)', 'flags' => 'iu'], 'TIDAK ADA',
                'Tidak ada "Daftar Grafik/Diagram/Peta" (memakai istilah Daftar Gambar)', 'Memakai istilah selain "Daftar Gambar"'),

            // ── Daftar dibuat bila minimal 3 entri ──
            'P-158' => $a('jumlah_min', 'daftar_tabel', ['pattern' => $entri('tabel') . '\d', 'flags' => 'imu', 'min' => 3], 'TIDAK ADA',
                'Daftar tabel berisi minimal 3 tabel', 'Daftar tabel dibuat padahal tabelnya kurang dari 3'),
            'P-167' => $a('jumlah_min', 'daftar_gambar', ['pattern' => $entri('gambar') . '\d', 'flags' => 'imu', 'min' => 3], 'TIDAK ADA',
                'Daftar gambar berisi minimal 3 gambar', 'Daftar gambar dibuat padahal gambarnya kurang dari 3'),
            'P-177' => $a('jumlah_min', 'daftar_lampiran', ['pattern' => $entri('lampiran') . '\d', 'flags' => 'imu', 'min' => 3], 'TIDAK ADA',
                'Daftar lampiran berisi minimal 3 lampiran', 'Daftar lampiran dibuat padahal lampirannya kurang dari 3'),

            // ── Penomoran berurutan & konsisten (dibaca dari daftar tabel/gambar/lampiran) ──
            'P-234' => $a('nomor_urut', 'daftar_tabel', ['pattern' => $entri('tabel') . '(\d+(?:\.\d+)*)', 'flags' => 'imu'], 'PERLU DICEK',
                'Nomor tabel berurutan', 'Nomor tabel tidak berurutan'),
            'P-278' => $a('nomor_urut', 'daftar_gambar', ['pattern' => $entri('gambar') . '(\d+(?:\.\d+)*)', 'flags' => 'imu'], 'PERLU DICEK',
                'Format nomor gambar konsisten dan berurutan', 'Format/urutan nomor gambar tidak konsisten'),
            'P-326' => $a('nomor_urut', 'daftar_lampiran', ['pattern' => $entri('lampiran') . '(\d+(?:\.\d+)*)', 'flags' => 'imu'], 'PERLU DICEK',
                'Nomor lampiran berurutan', 'Nomor lampiran tidak berurutan'),

            // ── Nomor tanpa titik di akhir ──
            'P-172' => $a('not_regex', 'daftar_gambar', ['pattern' => $entri('gambar') . '\d+(?:\.\d+)*\.(?=\s)', 'flags' => 'imu'], 'TIDAK ADA',
                'Nomor gambar tidak diakhiri titik', 'Nomor gambar diakhiri titik (seharusnya "Gambar 1.1", bukan "Gambar 1.1.")'),
            'P-181' => $a('not_regex', 'daftar_lampiran', ['pattern' => $entri('lampiran') . '\d+(?:\.\d+)*\.(?=\s)', 'flags' => 'imu'], 'TIDAK ADA',
                'Nomor lampiran tidak diakhiri titik', 'Nomor lampiran diakhiri titik (seharusnya "Lampiran 1", bukan "Lampiran 1.")'),

            // ── Penomoran memakai angka Arab (huruf besar romawi / huruf tunggal = pelanggaran) ──
            'P-245' => $a('not_regex', 'all', ['pattern' => '\b(?:Tabel|Table)\s+[IVXLC]{1,6}\b(?!\.?\s*\d)', 'flags' => 'u'], 'TIDAK ADA',
                'Nomor tabel memakai angka Arab', 'Nomor tabel memakai angka Romawi'),
            'P-277' => $a('not_regex', 'all', ['pattern' => '\b(?:Gambar|Figure)\s+[IVXLC]{1,6}\b(?!\.?\s*\d)', 'flags' => 'u'], 'TIDAK ADA',
                'Nomor gambar memakai angka Arab', 'Nomor gambar memakai angka Romawi'),
            'P-325' => $a('not_regex', 'all', ['pattern' => '\b(?:Lampiran|Appendix)\s+(?:[IVXLC]{1,6}|[A-Z])\b(?!\.?\s*\d)', 'flags' => 'u'], 'TIDAK ADA',
                'Nomor lampiran memakai angka Arab', 'Nomor lampiran memakai angka Romawi/huruf'),

            // ── Rentang tahun di judul tabel/gambar: en dash tanpa spasi (2020–2024) ──
            'P-260' => $a('not_regex', 'all', [
                'pattern' => '^[ \t]*(?:Tabel|Table)\s+\d[^\n]*?(?:19|20)\d{2}(?:[ \t]*-[ \t]*|[ \t]+–[ \t]*|[ \t]*–[ \t]+)(?:19|20)\d{2}',
                'flags'   => 'mu'], 'PERLU DICEK',
                'Rentang tahun di judul tabel memakai en dash tanpa spasi', 'Rentang tahun di judul tabel memakai tanda hubung (-) atau berspasi (seharusnya 2020–2024)'),
            'P-286' => $a('not_regex', 'all', [
                'pattern' => '^[ \t]*(?:Gambar|Figure)\s+\d[^\n]*?(?:19|20)\d{2}(?:[ \t]*-[ \t]*|[ \t]+–[ \t]*|[ \t]*–[ \t]+)(?:19|20)\d{2}',
                'flags'   => 'mu'], 'PERLU DICEK',
                'Rentang tahun di judul gambar memakai en dash tanpa spasi', 'Rentang tahun di judul gambar memakai tanda hubung (-) atau berspasi (seharusnya 2020–2024)'),

            // ── "Catatan:" bukan "Keterangan:" (tabel & gambar) ──
            'P-271' => $a('not_regex', 'all', ['pattern' => '^[ \t]*Keterangan[ \t]*:', 'flags' => 'mu'], 'PERLU DICEK',
                'Tidak ada "Keterangan:" (memakai "Catatan:")', 'Ditemukan "Keterangan:" di awal baris — penjelasan di bawah tabel seharusnya "Catatan:"'),
            'P-290' => $a('not_regex', 'all', ['pattern' => '^[ \t]*Keterangan[ \t]*:', 'flags' => 'mu'], 'PERLU DICEK',
                'Tidak ada "Keterangan:" (memakai "Catatan:")', 'Ditemukan "Keterangan:" di awal baris — catatan gambar seharusnya "Catatan:"'),

            // ── Bab ──
            'P-216' => $a('bab', 'all', ['cek' => 'ganjil'], 'PERLU DICEK', 'Setiap bab dimulai di halaman ganjil', 'Ada bab yang dimulai di halaman genap'),
            'P-217' => $a('bab', 'all', ['cek' => 'ganjil'], 'PERLU DICEK', 'Awal bab konsisten di halaman ganjil', 'Awal bab tidak konsisten di halaman ganjil'),
            'P-218' => $a('bab', 'all', ['cek' => 'berurutan'], 'PERLU DICEK', 'Nomor bab berurutan', 'Nomor bab tidak berurutan'),

            // ── Bulan tanda tangan maks. 1 bulan sebelum rilis (tanggal rilis dari API BPS) ──
            'P-131' => $a('bulan_rilis', 'kata_pengantar', null, 'PERLU DICEK',
                'Bulan penandatanganan kata pengantar sesuai (B atau B-1 rilis)', 'Bulan penandatanganan kata pengantar bukan bulan rilis atau sebulan sebelumnya'),
            'P-120' => $a('bulan_rilis', 'kata_sambutan', null, 'PERLU DICEK',
                'Bulan penandatanganan kata sambutan sesuai (B atau B-1 rilis)', 'Bulan penandatanganan kata sambutan bukan bulan rilis atau sebulan sebelumnya'),
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
                'target'       => 'all',
                'parameter'    => null,
                'status_gagal' => $kode === 'P-286' ? 'PERLU DICEK' : 'TIDAK ADA',
                'pesan_ok'     => null,
                'pesan_gagal'  => 'Perlu diperiksa manual oleh petugas sesuai Pedoman Pembuatan Publikasi 2023.',
                'updated_at'   => now(),
            ]);
        }
        DB::table('kriteria_pemeriksaan')->whereIn('tipe_cek', ['jumlah_min', 'nomor_urut', 'bab', 'bulan_rilis'])->update(['tipe_cek' => 'manual']);
        DB::table('kriteria_pemeriksaan')->whereIn('target', ['kata_sambutan', 'abstraksi', 'daftar_tabel', 'daftar_gambar', 'daftar_lampiran'])->update(['target' => 'all']);
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM(' . self::TIPE_LAMA . ") NOT NULL DEFAULT 'manual'");
        DB::statement('ALTER TABLE kriteria_pemeriksaan MODIFY target ENUM(' . self::TARGET_LAMA . ") NOT NULL DEFAULT 'cover'");
    }
};
