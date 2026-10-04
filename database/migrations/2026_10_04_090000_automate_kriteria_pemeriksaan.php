<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ubah kriteria yang bisa dicek dari teks PDF dari tipe 'manual' menjadi otomatis.
 *
 * Kriteria visual (logo, warna, jenis huruf, tata letak) dan yang butuh penilaian
 * manusia tetap 'manual'. Sebagian besar cek otomatis memakai status gagal
 * PERLU DICEK karena hasil ekstraksi teks PDF tidak selalu sempurna.
 */
return new class extends Migration
{
    private const MANUAL_MSG = 'Perlu diperiksa manual oleh petugas sesuai Pedoman Pembuatan Publikasi 2023.';

    // Nama BPS bahasa Inggris yang salah format (benar: BPS-STATISTICS INDONESIA / BPS-STATISTICS XX PROVINCE|REGENCY|MUNICIPALITY)
    private const BPS_EN_SALAH = '(?!BPS-STATISTICS\s+(?:INDONESIA|[A-Z][A-Z\s]*?\s(?:PROVINCE|REGENCY|MUNICIPALITY))\b)(?i:BPS\s*[-–]?\s*Statistics)';
    private const BPS_DAERAH   = 'BADAN PUSAT STATISTIK\s+(?:PROVINSI|KABUPATEN|KOTA)\s+[A-Z]';

    private function rules(): array
    {
        $re  = fn(string $target, string $pattern, string $flags, string $gagal, string $ok, string $err) =>
            ['tipe_cek' => 'regex', 'target' => $target, 'parameter' => array_filter(['pattern' => $pattern, 'flags' => $flags]),
             'status_gagal' => $gagal, 'pesan_ok' => $ok, 'pesan_gagal' => $err];
        $not = fn(string $target, string $pattern, string $flags, string $gagal, string $ok, string $err) =>
            ['tipe_cek' => 'not_regex', 'target' => $target, 'parameter' => array_filter(['pattern' => $pattern, 'flags' => $flags]),
             'status_gagal' => $gagal, 'pesan_ok' => $ok, 'pesan_gagal' => $err];
        $pos = fn(string $word, string $area, string $ok, string $err) =>
            ['tipe_cek' => 'posisi_area', 'target' => 'cover', 'parameter' => ['word' => $word, 'area' => $area],
             'status_gagal' => 'PERLU DICEK', 'pesan_ok' => $ok, 'pesan_gagal' => $err];
        $blank = fn(int $maxChars, string $ok, string $err) =>
            ['tipe_cek' => 'min_pages', 'target' => 'page2', 'parameter' => ['min' => 2, 'max_chars_page2' => $maxChars],
             'status_gagal' => 'PERLU DICEK', 'pesan_ok' => $ok, 'pesan_gagal' => $err];

        $P = 'PERLU DICEK';
        $T = 'TIDAK ADA';

        return [
            // ── 1. Kover Depan ────────────────────────────────────
            'P-003' => $pos('Katalog', 'top_right', 'Nomor katalog terdeteksi di kanan atas kover', 'Nomor katalog tidak terdeteksi di kanan atas kover'),
            'P-004' => $re('cover', 'Katalog(?:/Catalogue)?:\s*\d', 'u', $P,
                'Format "Katalog:" sesuai', 'Format "Katalog:" / "Katalog/Catalogue:" tidak ditemukan (cek spasi sebelum titik dua dan di sekitar garis miring)'),
            'P-005' => $pos('ISSN', 'below_katalog', 'ISSN berada di bawah nomor katalog', 'ISSN tidak berada di bawah nomor katalog'),
            'P-006' => $not('cover', 'ISSN\s*:', 'u', $T, 'ISSN ditulis tanpa titik dua', 'ISSN di kover memakai titik dua (seharusnya "ISSN XXXX-XXXX")'),
            'P-015' => $not('cover', '\b(?:South|North|East|West|Central|Southeast|Northern|Southern)\s+(?:Kalimantan|Borneo|Sumatera|Sumatra|Java|Sulawesi|Celebes|Papua|Nusa\s+Tenggara|Maluku|Moluccas)\b|\bSpecial\s+(?:Capital\s+)?Region\b', 'iu', $P,
                'Nama wilayah tidak diterjemahkan', 'Nama wilayah diterjemahkan ke bahasa asing'),
            'P-017' => $not('cover', '\b(?:Kab|Prov|Kec|Kel|Kep)\.|\b(?:Kalsel|Kalteng|Kaltim|Kalbar|Kaltara|Jatim|Jateng|Jabar|Sumut|Sumbar|Sumsel|Sulsel|Sulteng|Sultra|Sulut|Sulbar|Kepri|Babel|HSS|HST|HSU|Batola)\b', 'iu', $P,
                'Tidak ada singkatan wilayah pada kover', 'Kover memuat singkatan wilayah'),
            'P-020' => $not('cover', '(?<!ISSN )\b(?:19|20)\d{2}(?:\s*[-—]\s*|\s+–\s*|\s*–\s+)(?:19|20)\d{2}\b', 'u', $P,
                'Format rentang tahun sesuai', 'Rentang tahun tidak memakai en dash (–) tanpa spasi'),
            'P-022' => $not('cover', '\bTahun\s+(?:19|20)\d{2}\b', 'iu', $T,
                'Judul tidak memuat kata "Tahun"', 'Judul memuat kata "Tahun" sebelum angka tahun'),
            'P-025' => $not('cover', '\bVol\.\s*\d|\bNo\.\s*\d|\bVolume\s+\d+\s+(?:Nomor|Number)\b', 'iu', $P,
                'Tidak ada format edisi yang menyimpang', 'Format edisi tidak sesuai "Volume xx, Nomor xx, tahun"'),
            'P-032' => $pos('BADAN', 'bottom', 'Nama BPS penerbit berada di bagian bawah kover', 'Nama BPS penerbit tidak terdeteksi di bagian bawah kover'),
            'P-034' => $not('cover', self::BPS_EN_SALAH, 'u', $P,
                'Nama BPS bahasa Inggris sesuai (atau publikasi satu bahasa)', 'Nama BPS bahasa Inggris tidak sesuai format "BPS-STATISTICS XX PROVINCE/REGENCY/MUNICIPALITY"'),
            'P-035' => $re('cover', self::BPS_DAERAH, 'u', $P,
                'Nama BPS daerah sesuai format', 'Tidak ditemukan "BADAN PUSAT STATISTIK" + "PROVINSI/KABUPATEN/KOTA XX" kapital (abaikan untuk publikasi BPS pusat)'),
            'P-036' => $re('cover', 'BADAN PUSAT STATISTIK', 'u', $P,
                'Nama "BADAN PUSAT STATISTIK" ditemukan', 'Nama "BADAN PUSAT STATISTIK" (huruf kapital) tidak ditemukan di kover'),
            'P-042' => $blank(30, 'Halaman setelah kover depan kosong', 'Halaman setelah kover depan tidak kosong'),
            'P-043' => $blank(0, 'Halaman kosong tidak memuat teks', 'Halaman kosong masih memuat teks (running title/nomor halaman?)'),

            // ── 3. Halaman Katalog ────────────────────────────────
            'P-056' => $re('front', 'Nomor\s+Publikasi', 'iu', $T, '"Nomor Publikasi" ditemukan', '"Nomor Publikasi" tidak ditemukan (tidak boleh disingkat)'),
            'P-057' => $re('front', '\b(?:ISSN|ISBN)\b[^\n:]{0,10}:\s*[\dX]', 'iu', $P, 'ISSN/ISBN pada halaman katalog memakai titik dua', 'ISSN/ISBN dengan titik dua tidak ditemukan di halaman katalog'),
            'P-058' => $re('front', 'Ukuran\s+Buku(?:\s*/\s*Book\s+Size)?\s*:\s*[\d,.]+\s*(?:cm\s*)?[x×]\s*[\d,.]+\s*cm', 'iu', $P,
                'Ukuran buku sesuai format cm', 'Ukuran buku tidak ditemukan atau tidak berformat "lebar x tinggi cm"'),
            'P-068' => $re('front', 'Jumlah\s+Halaman(?:\s*/\s*Number\s+of\s+Pages)?\s*:', 'iu', $P, 'Istilah Jumlah Halaman ditemukan', 'Istilah "Jumlah Halaman" / "Jumlah Halaman/Number of Pages" tidak ditemukan'),
            'P-072' => $re('front', '\b[ivxlcdm]+\+\d+\s+(?:halaman|pages)\b', 'iu', $P, 'Format jumlah halaman sesuai', 'Format jumlah halaman bukan "xii+90 halaman" (tanpa spasi di sekitar +)'),
            'P-074' => $re('front', 'Penyusun\s+Naskah(?:\s*/\s*Manuscript\s+Drafter)?\s*:', 'iu', $P, 'Istilah Penyusun Naskah ditemukan', 'Istilah "Penyusun Naskah" / "Penyusun Naskah/Manuscript Drafter" tidak ditemukan'),
            'P-079' => $re('front', 'Penyunting(?:\s*/\s*Editor)?\s*:', 'iu', $P, 'Istilah Penyunting ditemukan', 'Istilah "Penyunting" / "Penyunting/Editor" tidak ditemukan'),
            'P-082' => $re('front', 'Pembuat\s+Kover(?:\s*/\s*Cover\s+Designer)?\s*:', 'iu', $P, 'Istilah Pembuat Kover ditemukan', 'Istilah "Pembuat Kover" / "Pembuat Kover/Cover Designer" tidak ditemukan'),
            'P-087' => $re('front', 'Penerbit(?:\s*/\s*Publisher)?\s*:', 'iu', $P, 'Istilah Penerbit ditemukan', 'Istilah "Penerbit" / "Penerbit/Publisher" tidak ditemukan'),
            'P-089' => $re('front', '©\S', 'u', $P, 'Simbol © ditemukan tanpa spasi setelahnya', 'Simbol © tidak ditemukan atau diikuti spasi'),
            'P-091' => $re('front', 'Dicetak\s+oleh(?:\s*/\s*Printed\s+by)?\s*:', 'iu', $P, 'Istilah Dicetak oleh ditemukan', 'Istilah "Dicetak oleh" / "Dicetak oleh/Printed by" tidak ditemukan (abaikan jika komponen memang dihapus)'),
            'P-097' => $re('front', 'Sumber\s+Ilustrasi(?:\s*/\s*Illustration\s+Source)?\s*:', 'iu', $P, 'Istilah Sumber Ilustrasi ditemukan', 'Istilah "Sumber Ilustrasi" / "Sumber Ilustrasi/Illustration Source" tidak ditemukan'),
            'P-101' => $re('front', 'Dilarang\s+mereproduksi\s+dan\s*/\s*atau\s+menggandakan\s+sebagian\s+atau\s+seluruh\s+isi\s+buku\s+ini\s+untuk\s+tujuan\s+komersial\s+tanpa\s+izin\s+tertulis\s+dari', 'iu', $P,
                'Pernyataan hak cipta sesuai', 'Pernyataan hak cipta bahasa Indonesia tidak sesuai bunyi baku'),

            // ── 4. Tim Penyusun ───────────────────────────────────
            'P-104' => $re('front', 'Tim\s+Penyusun', 'iu', $P, 'Judul "Tim Penyusun" ditemukan', 'Judul "Tim Penyusun" tidak ditemukan'),
            'P-110' => $re('front', '^(?=[\s\S]*Pengarah)(?=[\s\S]*Penanggung\s+Jawab)(?=[\s\S]*Penyunting)(?=[\s\S]*Penulis\s+Naskah)(?=[\s\S]*Penata\s+Letak)', 'iu', $P,
                'Komponen wajib tim penyusun lengkap', 'Komponen wajib belum lengkap (Pengarah, Penanggung Jawab, Penyunting, Penulis Naskah, Penata Letak)'),

            // ── 6. Kata Pengantar ─────────────────────────────────
            'P-128' => $re('front', 'Kata\s+Pengantar|\bPengantar\b|\bPreface\b|\bForeword\b', 'iu', $T, 'Kata Pengantar ditemukan', 'Halaman Kata Pengantar tidak ditemukan'),
            'P-134' => $re('front', 'Kepala\s+(?:Badan\s+Pusat\s+Statistik|BPS)\b|Head\s+of\s+(?:BPS|Statistics)', 'iu', $P, 'Jabatan penandatangan ditemukan', 'Jabatan penandatangan "Kepala BPS ..." tidak ditemukan'),

            // ── 8. Daftar Isi / 9. Daftar Tabel ───────────────────
            'P-149' => $re('front', 'Daftar\s+Isi|Table\s+of\s+Contents', 'iu', $T, 'Daftar Isi ditemukan', 'Halaman Daftar Isi tidak ditemukan'),
            'P-162' => $not('front', '^\s*(?:Tabel|Table)\s+\d+(?:\.\d+)*\.(?=\s)', 'mu', $P, 'Nomor tabel di daftar tabel tanpa titik akhir', 'Nomor tabel di daftar tabel diakhiri titik'),

            // ── 16. Narasi ────────────────────────────────────────
            'P-221' => $not('all', '^\s*\d+\.\d+(?:\.\d+)*\.\s+[A-Z][a-z]', 'mu', $P, 'Nomor subbab tanpa titik akhir', 'Nomor subbab diakhiri titik (seharusnya "1.1 Latar Belakang")'),

            // ── 18. Tabel ─────────────────────────────────────────
            'P-248' => $not('all', '^\s*(?:Tabel|Table)\s+\d+(?:\.\d+)*\.(?=\s)', 'mu', $P, 'Nomor tabel tanpa titik akhir', 'Nomor tabel diakhiri titik'),
            'P-255' => $not('all', '^\s*Tabel\s+\d+(?:\.\d+)*\s+[^\n,]*[A-Za-z)]\s+(?:19|20)\d{2}(?:–(?:19|20)\d{2})?\s*$', 'mu', $P, 'Judul tabel memakai koma sebelum keterangan waktu', 'Judul tabel tanpa koma sebelum keterangan waktu'),
            'P-257' => $not('all', '^\s*Tabel\s+\d+(?:\.\d+)*\s+[^\n]*\bTahun\s+(?:19|20)\d{2}', 'mu', $P, 'Judul tabel tidak memuat kata "Tahun"', 'Judul tabel memuat kata "Tahun"'),
            'P-258' => $not('all', '^\s*Tabel\s+\d+(?:\.\d+)*\s+[^\n,]*[A-Za-z)]\s+(?:19|20)\d{2}(?:–(?:19|20)\d{2})?\s*$', 'mu', $P, 'Judul tabel memakai koma sebelum keterangan waktu', 'Judul tabel tanpa koma sebelum keterangan waktu'),
            'P-269' => $not('all', '(?<![\d.,])0,0+(?!\d)', 'u', $P, 'Tidak ada nilai nol ditulis 0,0 / 0,00', 'Nilai nol ditulis 0,0 / 0,00 (seharusnya "–" atau "~0")'),

            // ── 19. Gambar ────────────────────────────────────────
            'P-279' => $not('all', '^\s*(?:Gambar|Figure)\s+\d+(?:\.\d+)*\.(?=\s)', 'mu', $P, 'Nomor gambar tanpa titik akhir', 'Nomor gambar diakhiri titik'),
            'P-285' => $not('all', '^\s*Gambar\s+\d+(?:\.\d+)*\s+[^\n,]*[A-Za-z)]\s+(?:19|20)\d{2}(?:–(?:19|20)\d{2})?\s*$', 'mu', $P, 'Judul gambar memakai koma sebelum keterangan waktu', 'Judul gambar tanpa koma sebelum keterangan waktu'),

            // ── 20. Daftar Pustaka ────────────────────────────────
            'P-299' => $re('all', 'Daftar\s+Pustaka|\bBibliography\b|\bReferences\b', 'iu', $P, 'Daftar Pustaka ditemukan', 'Daftar Pustaka tidak ditemukan (wajib untuk publikasi hasil kegiatan/kajian statistik)'),

            // ── 24. Kover Belakang ────────────────────────────────
            'P-339' => $re('last', 'Mencerdaskan\s+Bangsa', 'iu', $P, 'Slogan "Data Mencerdaskan Bangsa" ditemukan di kover belakang', 'Slogan "Data Mencerdaskan Bangsa" tidak ditemukan di kover belakang'),
            'P-345' => $re('last', 'BADAN PUSAT STATISTIK', 'u', $P, 'Nama "BADAN PUSAT STATISTIK" ditemukan', 'Nama "BADAN PUSAT STATISTIK" (huruf kapital) tidak ditemukan di kover belakang'),
            'P-346' => $re('last', '^(?=[\s\S]*\b\d{5}\b)(?=[\s\S]*\b(?:Telp|Telepon|Tel|Phone)\b)(?=[\s\S]*\b(?:Fax|Faks|Faksimile)\b)(?=[\s\S]*(?:https?://|www\.|bps\.go\.id))(?=[\s\S]*@)', 'iu', $P,
                'Alamat BPS penerbit lengkap', 'Alamat belum lengkap (kode pos, telepon, faks, homepage, email)'),
            'P-348' => $re('last', '\b(?:ISSN|ISBN)\b\s*:?\s*[\dX][\dX-]{8,}', 'iu', $P, 'Label ISSN/ISBN ditemukan di kover belakang', 'Label ISSN/ISBN beserta nomornya tidak ditemukan di kover belakang'),
            'P-352' => $re('last', self::BPS_DAERAH, 'u', $P,
                'Nama BPS daerah sesuai format', 'Tidak ditemukan "BADAN PUSAT STATISTIK" + "PROVINSI/KABUPATEN/KOTA XX" kapital (abaikan untuk publikasi BPS pusat)'),
            'P-353' => $not('last', self::BPS_EN_SALAH, 'u', $P,
                'Nama BPS bahasa Inggris sesuai (atau publikasi satu bahasa)', 'Nama BPS bahasa Inggris tidak sesuai format "BPS-STATISTICS XX PROVINCE/REGENCY/MUNICIPALITY"'),

            // ── 25. Umum ──────────────────────────────────────────
            'P-355' => $not('all', '[A-Za-z]\s+/\s*[A-Za-z]|[A-Za-z]\s*/\s+[A-Za-z]', 'u', $P, 'Tidak ada spasi di sekitar garis miring', 'Ada spasi sebelum/sesudah garis miring'),
            'P-357' => $not('all', '[A-Za-z]\s+[,;!?](?=\s|$)', 'mu', $P, 'Tidak ada spasi sebelum tanda baca', 'Ada spasi sebelum tanda baca'),
            'P-358' => $not('all', '\bRp\.\s*\d|\bRp\s+\d', 'u', $P, 'Penulisan Rupiah sesuai', 'Penulisan Rupiah salah (seharusnya "Rp10.000", tanpa titik/spasi)'),
        ];
    }

    public function up(): void
    {
        // Kolom berupa ENUM — tambahkan tipe 'not_regex' dan target 'front'/'last'
        DB::statement("ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM('regex','not_regex','contains','not_contains','posisi_area','min_pages','manual') NOT NULL DEFAULT 'manual'");
        DB::statement("ALTER TABLE kriteria_pemeriksaan MODIFY target ENUM('cover','page2','front','last','all') NOT NULL DEFAULT 'cover'");

        foreach ($this->rules() as $kode => $rule) {
            $rule['parameter'] = json_encode($rule['parameter'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            DB::table('kriteria_pemeriksaan')->where('kode', $kode)->update($rule + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->rules()) as $kode) {
            $urutan = (int) substr($kode, 2);
            DB::table('kriteria_pemeriksaan')->where('kode', $kode)->update([
                'tipe_cek'     => 'manual',
                'target'       => $urutan <= 45 ? 'cover' : 'all',
                'parameter'    => null,
                'status_gagal' => 'TIDAK ADA',
                'pesan_ok'     => null,
                'pesan_gagal'  => self::MANUAL_MSG,
                'updated_at'   => now(),
            ]);
        }

        DB::statement("ALTER TABLE kriteria_pemeriksaan MODIFY tipe_cek ENUM('regex','contains','not_contains','posisi_area','min_pages','manual') NOT NULL DEFAULT 'manual'");
        DB::statement("ALTER TABLE kriteria_pemeriksaan MODIFY target ENUM('cover','page2','all') NOT NULL DEFAULT 'cover'");
    }
};
