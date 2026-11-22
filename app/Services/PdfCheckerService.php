<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Illuminate\Http\UploadedFile;
use App\Models\KriteriaPemeriksaan;
use App\Models\HasilPemeriksaan;

/**
 * PdfCheckerService — Database-driven + Ghostscript fallback
 *
 * Tiga mode operasi:
 * 1. check(UploadedFile)      → array hasil lengkap (untuk upload manual)
 * 2. checkFromPath(string)    → array hasil lengkap (untuk BPS import via path)
 * 3. streamCheck(string)      → Generator, yield per event:
 *      - parse_start  : { total_pages }
 *      - parse_page   : { current, total }
 *      - parse_done   : { total_pages, method }
 *      - check        : { id, kategori, deskripsi, status, catatan }
 *      - done         : { result }
 *      - error        : { message }
 */
class PdfCheckerService
{
    // ── State internal ─────────────────────────────────────
    private array  $checks    = [];
    private int    $pages     = 0;
    private string $method    = 'digital';
    private string $errorMsg  = '';

    private string $coverText  = '';
    private array  $coverWords = [];
    /** Posisi kata "ISSN" di halaman awal: [{hal, text, x, y, pw, ph}] — untuk cek posisi selain kover. */
    private array  $frontWords = [];
    /** Halaman khusus yang dikenali dari isinya (cache): nama → [indeks awal, jumlah] atau null. */
    private array $halamanCache = [];

    /** Target halaman khusus → label untuk pesan. */
    private const HALAMAN_KHUSUS = [
        'katalog'         => 'Katalog',
        'tim_penyusun'    => 'Tim Penyusun',
        'kata_sambutan'   => 'Kata Sambutan',
        'kata_pengantar'  => 'Kata Pengantar',
        'abstraksi'       => 'Abstraksi',
        'daftar_isi'      => 'Daftar Isi',
        'daftar_tabel'    => 'Daftar Tabel',
        'daftar_gambar'   => 'Daftar Gambar',
        'daftar_lampiran' => 'Daftar Lampiran',
        'daftar_pustaka'  => 'Daftar Pustaka',
        'glosarium'       => 'Glosarium',
        'indeks'          => 'Indeks',
        'lampiran'        => 'Lampiran',
    ];

    /** Bagian belakang: judul di baris atas halaman (setelah duplikat teks dibersihkan). */
    private const JUDUL_BELAKANG = [
        'daftar_pustaka' => '/^(?:DAFTAR\s+PUSTAKA|DAFTAR\s+REFERENSI|REFERENSI|REFERENCES|BIBLIOGRAPHY)\b/iu',
        'glosarium'      => '/^(?:GLOSARIUM|DAFTAR\s+ISTILAH|GLOSSARY)\b/iu',
        'indeks'         => '/^(?:INDEKS|INDEX)\s*$/iu',
        'lampiran'       => '/^(?:LAMPIRAN|APPENDI(?:X|CES))(?:\s*\/\s*APPENDI(?:X|CES))?\s*$/iu',
    ];

    /** Daftar Tabel/Gambar/Lampiran: kata awal entrinya (dipakai untuk mengenali halamannya). */
    private const ENTRI_DAFTAR = [
        'daftar_tabel'    => 'Tabel|Table',
        'daftar_gambar'   => 'Gambar|Figure',
        'daftar_lampiran' => 'Lampiran|Appendix',
    ];

    /** Tanggal rilis publikasi (Y-m-d), bila diketahui — untuk cek bulan penandatanganan. */
    private ?string $tanggalRilis = null;
    /** Isian Web API BPS (issn, katalog, nomor_publikasi) — untuk cek sama_api; null = bukan dari Import API BPS. */
    private ?array $dataApi = null;
    /** Sumber data pembanding cek sama_api: 'Web API BPS' (Import API) atau 'SIPOTRET' (draf upload PJK). */
    private string $sumberApi = 'Web API BPS';
    private float  $pageW      = 595.0;
    private float  $pageH      = 842.0;
    private string $page2Text  = '';
    private array  $pageTexts  = [];

    /** Jumlah halaman awal yang dipakai target 'front' (katalog, kata pengantar, daftar isi). */
    private const FRONT_PAGES = 20;

    /** Batas data teks dari browser (checkFromText). */
    private const MAX_PAGES      = 3000;
    private const MAX_PAGE_CHARS = 200000;

    /** Lokasi temuan kriteria yang sedang dievaluasi: [{hal, teks}] (lihat addLokasi). */
    private const MAX_LOKASI = 5;
    private array  $lokasi        = [];
    private string $currentTarget = 'cover';

    /** Nomor halaman (1-based) yang teksnya hasil OCR / berupa gambar tanpa teks. */
    private array $ocrPages   = [];
    private array $imagePages = [];
    private int $ocrTerlewat  = 0; // halaman tanpa lapisan teks yang tidak di-OCR karena batas (PdfExtract ocr_terlewat)
    /**
     * Perataan kolom angka tabel yang diukur di browser (PdfExtract ukurTabel): [{hal, kol, n, rata, tengah, geser, contoh}].
     * null = tidak diukur (dibaca di server / versi PdfExtract lama).
     */
    private ?array $tabelAngka = null;
    /**
     * Teks di pita atas/bawah tiap halaman dari browser (PdfExtract tepiHalaman), indeks 0-based:
     * [['w' => lebar, 'h' => tinggi, 'it' => [['t', 'x', 'y'], …]] | null]. Kosong = tidak dikirim (versi lama / server).
     */
    private array $tepi = [];
    /** Nama font teks halaman awal & terakhir (PdfExtract fontHalaman): [nomor halaman => [['t', 'f', 'x', 'y'], …]]. */
    private array $fontHal = [];
    /** Cache nomorHalaman(). */
    private ?array $nomorCache = null;

    // ═══════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════

    /**
     * Mode 1 — Upload manual (UploadedFile).
     */
    public function check(UploadedFile $file): array
    {
        set_time_limit(300);
        $this->reset();

        try {
            $this->extractFromPath($file->getPathname());
        } catch (\Throwable $e) {
            $this->errorMsg = $e->getMessage();
            return $this->buildResult($file->getClientOriginalName(), $file->getSize());
        }

        $this->runAllRules();
        return $this->buildResult($file->getClientOriginalName(), $file->getSize());
    }

    /**
     * Mode 2 — Dari path file (BPS import, non-streaming).
     */
    public function checkFromPath(string $path, string $filename, int $fileSize): array
    {
        set_time_limit(600);
        $this->reset();

        try {
            $this->extractFromPath($path);
        } catch (\Throwable $e) {
            $this->errorMsg = $e->getMessage();
            return $this->buildResult($filename, $fileSize);
        }

        $this->runAllRules();
        return $this->buildResult($filename, $fileSize);
    }

    /**
     * Mode 4 — Teks sudah diekstrak di browser (pdf.js + OCR Tesseract.js, lihat js/pdf-extract.js).
     * Dipakai di hosting yang tidak mengizinkan exec() sehingga Ghostscript/Tesseract tidak tersedia.
     *
     * @param array $x { pages: string[], cover_words: {text,x,y}[], page_w, page_h, method, ocr_pages: int[], image_pages: int[] }
     */
    public function checkFromText(array $x, string $filename, int $fileSize): array
    {
        // publikasi tebal (±900 halaman) butuh lebih dari batas bawaan 30–60 detik; diabaikan bila hosting melarang
        if (function_exists('set_time_limit')) @set_time_limit(300);
        $this->reset();

        $pages = array_values(array_filter($x['pages'] ?? [], 'is_string'));
        if (!$pages) {
            $this->errorMsg = 'PDF tidak memiliki halaman yang dapat dibaca.';
            return $this->buildResult($filename, $fileSize);
        }
        foreach (array_slice($pages, 0, self::MAX_PAGES) as $i => $text) {
            $this->storePageText($i, mb_substr($text, 0, self::MAX_PAGE_CHARS));
        }

        $this->pageW = is_numeric($x['page_w'] ?? null) && $x['page_w'] > 0 ? (float) $x['page_w'] : 595.0;
        $this->pageH = is_numeric($x['page_h'] ?? null) && $x['page_h'] > 0 ? (float) $x['page_h'] : 842.0;
        foreach (array_slice($x['cover_words'] ?? [], 0, 5000) as $w) {
            if (isset($w['text'], $w['x'], $w['y']) && is_string($w['text']) && is_numeric($w['x']) && is_numeric($w['y'])) {
                $this->coverWords[] = ['text' => $w['text'], 'x' => (float) $w['x'], 'y' => (float) $w['y']];
            }
        }
        foreach (array_slice($x['front_words'] ?? [], 0, 500) as $w) {
            if (isset($w['hal'], $w['text'], $w['x'], $w['y']) && is_string($w['text']) && is_numeric($w['x']) && is_numeric($w['y'])) {
                $this->frontWords[] = [
                    'hal' => (int) $w['hal'], 'text' => $w['text'], 'x' => (float) $w['x'], 'y' => (float) $w['y'],
                    'pw'  => is_numeric($w['pw'] ?? null) ? (float) $w['pw'] : 0.0,
                    'ph'  => is_numeric($w['ph'] ?? null) ? (float) $w['ph'] : 0.0,
                ];
            }
        }

        $toInts = fn($v) => array_values(array_filter(array_map('intval', (array) ($v ?? [])), fn($n) => $n > 0));
        $this->ocrPages   = $toInts($x['ocr_pages'] ?? []);
        $this->imagePages = $toInts($x['image_pages'] ?? []);
        $this->ocrTerlewat = max(0, (int) ($x['ocr_terlewat'] ?? 0));
        if (isset($x['tabel_angka']) && is_array($x['tabel_angka'])) {
            $this->tabelAngka = [];
            foreach (array_slice($x['tabel_angka'], 0, 3000) as $t) {
                if (!is_array($t) || !is_numeric($t['hal'] ?? null) || !is_numeric($t['kol'] ?? null)) continue;
                $this->tabelAngka[] = [
                    'hal'    => (int) $t['hal'],
                    'kol'    => (int) $t['kol'],
                    'n'      => (int) ($t['n'] ?? 0),
                    'rata'   => in_array($t['rata'] ?? '', ['kanan', 'kiri', 'tengah', 'tidak'], true) ? $t['rata'] : 'tidak',
                    'tengah' => (bool) ($t['tengah'] ?? false),
                    'geser'  => is_numeric($t['geser'] ?? null) ? (float) $t['geser'] : 0.0,
                    'contoh' => mb_substr((string) ($t['contoh'] ?? ''), 0, 40),
                ];
            }
        }
        $this->method    = in_array($x['method'] ?? '', ['pdfjs', 'pdfjs+ocr'], true) ? $x['method'] : 'pdfjs';

        // tepi halaman (nomor halaman & running title) dan nama font halaman awal/terakhir
        $item = fn($w, bool $font) => is_array($w) && is_string($w['t'] ?? null) && is_numeric($w['x'] ?? null) && is_numeric($w['y'] ?? null)
            ? ['t' => mb_substr($w['t'], 0, 120), 'x' => (float) $w['x'], 'y' => (float) $w['y']] + ($font ? ['f' => mb_substr((string) ($w['f'] ?? ''), 0, 80)] : [])
            : null;
        foreach (array_slice((array) ($x['tepi'] ?? []), 0, self::MAX_PAGES) as $i => $pg) {
            $this->tepi[$i] = is_array($pg) && is_numeric($pg['w'] ?? null) && is_numeric($pg['h'] ?? null) && $pg['h'] > 0
                ? ['w' => (float) $pg['w'], 'h' => (float) $pg['h'],
                   'it' => array_values(array_filter(array_map(fn($w) => $item($w, false), array_slice((array) ($pg['it'] ?? []), 0, 80))))]
                : null;
        }
        foreach ((array) ($x['fonts'] ?? []) as $hal => $daftar) {
            if (!is_numeric($hal) || (int) $hal < 1 || count($this->fontHal) >= 10) continue;
            $this->fontHal[(int) $hal] = array_values(array_filter(array_map(fn($w) => $item($w, true), array_slice((array) $daftar, 0, 400))));
        }

        $this->runAllRules();
        return $this->buildResult($filename, $fileSize);
    }

    /**
     * Ambil baris OCR yang valid dari hasil ekstraksi browser ({ halaman: [{s,x,y,w,h}] }),
     * untuk disimpan dan disisipkan kembali ke viewer saat tinjauan dilanjutkan.
     */
    /**
     * Hasil ekstraksi teks di browser dari request: `extracted_b64` (base64 JSON, lihat PdfExtract.kemas — dipakai
     * supaya firewall hosting tidak salah membaca teks publikasi sebagai serangan) atau `extracted` (JSON biasa).
     */
    public static function extractedDari(\Illuminate\Http\Request $request): ?array
    {
        $json = $request->filled('extracted_b64')
            ? base64_decode((string) $request->input('extracted_b64'), true)
            : (string) $request->input('extracted');
        // dikompres gzip di browser (PdfExtract.kemas) → 2 byte awal 1F 8B
        if (is_string($json) && str_starts_with($json, "\x1f\x8b") && function_exists('gzdecode')) {
            $json = @gzdecode($json, 200 * 1024 * 1024) ?: '';
        }
        $data = $json ? json_decode($json, true) : null;
        return is_array($data) ? $data : null;
    }

    public static function ocrLinesFrom(?array $extracted): ?array
    {
        $out = [];
        // semua halaman hasil OCR (PDF secured bisa ratusan halaman); batas hanya pengaman ukuran
        foreach (array_slice((array) ($extracted['ocr_lines'] ?? []), 0, self::MAX_PAGES, true) as $page => $lines) {
            if (!is_numeric($page) || (int) $page < 1 || !is_array($lines)) continue;
            foreach (array_slice($lines, 0, 500) as $l) {
                if (!isset($l['s'], $l['x'], $l['y'], $l['w'], $l['h']) || !is_string($l['s'])) continue;
                if (!is_numeric($l['x']) || !is_numeric($l['y']) || !is_numeric($l['w']) || !is_numeric($l['h'])) continue;
                $out[(int) $page][] = [
                    's' => mb_substr($l['s'], 0, 500),
                    'x' => (float) $l['x'], 'y' => (float) $l['y'], 'w' => (float) $l['w'], 'h' => (float) $l['h'],
                ];
            }
        }
        return $out ?: null;
    }

    /**
     * Mode 3 — Streaming Generator untuk SSE.
     *
     * Yield event satu per satu sehingga controller bisa
     * langsung flush ke browser tanpa menunggu semua selesai.
     */
    public function streamCheck(string $path, string $filename, int $fileSize): \Generator
    {
        set_time_limit(600);
        $this->reset();

        // ── Fase 1: Parse per halaman ──────────────────────
        $decrypted = null;
        try {
            $parser = $this->makeParser();
            $pdf    = $parser->parseFile($path);
            $allPages = $pdf->getPages();
            $total    = count($allPages);

            if ($total === 0) {
                yield ['type' => 'error', 'message' => 'PDF tidak memiliki halaman yang dapat dibaca.'];
                return;
            }

            yield ['type' => 'parse_start', 'total_pages' => $total];

            foreach ($allPages as $i => $page) {
                // Kumpulkan teks yang dibutuhkan untuk evaluasi
                $this->collectPage($i, $page);

                yield ['type' => 'parse_page', 'current' => $i + 1, 'total' => $total];
            }

            $this->assertHasText();

        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());

            if (str_contains($msg, 'secured') ||
                str_contains($msg, 'encrypted') ||
                str_contains($msg, 'not supported')) {

                // Coba decrypt via GS
                yield ['type' => 'parse_start', 'total_pages' => 0];
                yield ['type' => 'heartbeat', 'msg' => 'PDF terenkripsi, mencoba decrypt...'];

                $decrypted = $this->decryptWithGs($path);

                if ($decrypted && file_exists($decrypted)) {
                    try {
                        $parser   = $this->makeParser();
                        $pdf      = $parser->parseFile($decrypted);
                        $allPages = $pdf->getPages();
                        $total    = count($allPages);
                        $this->method = 'gs_decrypt';
                        $this->pageTexts = [];

                        yield ['type' => 'parse_start', 'total_pages' => $total];

                        foreach ($allPages as $i => $page) {
                            $this->collectPage($i, $page);

                            yield ['type' => 'parse_page', 'current' => $i + 1, 'total' => $total];
                        }

                    } catch (\Throwable $e2) {
                        yield ['type' => 'error', 'message' => 'PDF tidak bisa dibaca setelah decrypt: ' . $e2->getMessage()];
                        return;
                    }
                } else {
                    // GS tidak tersedia — tandai secured_unreadable
                    $this->method   = 'secured_unreadable';
                    $this->errorMsg = 'PDF terenkripsi, GS tidak tersedia.';
                    yield ['type' => 'parse_done', 'total_pages' => 0, 'method' => $this->method];
                }
            } else {
                yield ['type' => 'error', 'message' => $e->getMessage()];
                return;
            }
        } finally {
            if ($decrypted && file_exists($decrypted)) @unlink($decrypted);
        }

        yield ['type' => 'parse_done', 'total_pages' => $this->pages, 'method' => $this->method];

        // ── Fase 2: Evaluasi kriteria satu per satu ────────
        $rules = KriteriaPemeriksaan::aktif()->get();

        foreach ($rules as $rule) {
            $this->lokasi = [];
            if ($this->method === 'secured_unreadable') {
                $status  = 'PERLU DICEK';
                $catatan = 'PDF terenkripsi, tidak dapat diperiksa otomatis';
            } else {
                [$status, $catatan] = $this->evalRule($rule);
            }

            $area = $this->areaLabel($rule);
            $this->add($rule->kode, $rule->kategori, $rule->deskripsi, $status, $catatan, $rule->target, $area, $this->lokasi);

            yield [
                'type'      => 'check',
                'id'        => $rule->kode,
                'kategori'  => $rule->kategori,
                'deskripsi' => $rule->deskripsi,
                'status'    => $status,
                'catatan'   => $catatan,
                'target'    => $rule->target,
                'area'      => $area,
            ];
        }

        yield ['type' => 'done', 'result' => $this->buildResult($filename, $fileSize)];
    }

    // ═══════════════════════════════════════════════════════
    // INTERNAL — EKSTRAKSI
    // ═══════════════════════════════════════════════════════

    private function extractFromPath(string $path): void
    {
        $decrypted = null;

        try {
            $this->parseAllPages($path);
            $this->assertHasText();
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());

            if (str_contains($msg, 'secured') ||
                str_contains($msg, 'encrypted') ||
                str_contains($msg, 'not supported')) {

                $decrypted = $this->decryptWithGs($path);

                if ($decrypted && file_exists($decrypted)) {
                    try {
                        $this->parseAllPages($decrypted);
                        $this->method = 'gs_decrypt';
                    } catch (\Throwable $e2) {
                        throw new \RuntimeException('PDF tidak bisa dibaca setelah decrypt: ' . $e2->getMessage());
                    }
                } else {
                    $this->method   = 'secured_unreadable';
                    $this->errorMsg = 'PDF terenkripsi, GS tidak tersedia.';
                }
            } else {
                throw $e;
            }
        } finally {
            if ($decrypted && file_exists($decrypted)) @unlink($decrypted);
        }
    }

    private function parseAllPages(string $path): void
    {
        $parser   = $this->makeParser();
        $pdf      = $parser->parseFile($path);
        $pages    = $pdf->getPages();
        $this->pages = count($pages);
        $this->pageTexts = [];

        if ($this->pages === 0) {
            throw new \RuntimeException('PDF tidak memiliki halaman yang dapat dibaca.');
        }

        foreach ($pages as $i => $page) {
            $this->collectPage($i, $page);
        }
    }

    /**
     * Simpan teks satu halaman. Halaman 1 juga diambil posisi kata-katanya
     * untuk cek posisi_area.
     */
    private function collectPage(int $i, \Smalot\PdfParser\Page $page): void
    {
        try {
            $text = $page->getText() ?? '';
        } catch (\Throwable) {
            $text = '';
        }

        $this->storePageText($i, $text);

        if ($i === 0) {
            $this->coverWords = $this->extractWords($page);
        } elseif ($i < self::FRONT_PAGES && stripos($text, 'ISSN') !== false) {
            // posisi kata ISSN di halaman awal (cek posisi di halaman Tim Penyusun)
            foreach ($this->extractWords($page) as $w) {
                // hanya potongan yang diawali ISSN: Smalot kadang mengembalikan satu blok panjang
                // yang posisinya adalah awal blok, bukan posisi kata ISSN
                if (preg_match('/^ISSN\b/i', $w['text'])) {
                    $this->frontWords[] = $w + ['hal' => $i + 1, 'pw' => $this->pageW, 'ph' => $this->pageH];
                }
            }
        }
    }

    private function storePageText(int $i, string $text): void
    {
        // NBSP → spasi biasa supaya \s di regex tetap cocok
        $text = str_replace("\xC2\xA0", ' ', $text);
        // Watermark unduhan website BPS ("https:// kalsel.bps.go.id") ada di tiap halaman — bukan isi publikasi.
        // Nama situsnya bisa terbaca terpotong spasi ("https:// banj arkab.bps.go.id"). Ciri watermark: ada spasi
        // sesudah "//" — alamat homepage asli (kover belakang, cek alamat) ditulis tanpa spasi dan tidak ikut dibuang.
        $text = preg_replace('~https?://\s+[\w.\- ]{1,60}?\.bps\.go\.id/?~iu', '', $text) ?? $text;

        $this->pageTexts[$i] = $text;
        $this->pages         = $i + 1;

        if ($i === 0) {
            $this->coverText = $text;
        } elseif ($i === 1) {
            $this->page2Text = $text;
        }
    }

    /**
     * PDF terenkripsi tetap bisa di-parse karena setIgnoreEncryption(true), tetapi
     * teksnya kosong. Lempar error 'secured' supaya jalur decrypt Ghostscript dipakai.
     */
    private function assertHasText(): void
    {
        foreach ($this->pageTexts as $t) {
            if (trim($t) !== '') return;
        }
        throw new \RuntimeException('PDF secured: tidak ada teks yang terbaca');
    }

    private function makeParser(): Parser
    {
        $config = new \Smalot\PdfParser\Config();
        $config->setIgnoreEncryption(true);
        return new Parser([], $config);
    }

    private function extractWords(\Smalot\PdfParser\Page $page): array
    {
        $words = [];
        try {
            foreach ($page->getDataTm() as $item) {
                if (!isset($item[0], $item[1])) continue;
                $text = trim($item[1]);
                if ($text === '') continue;
                $matrix  = $item[0];
                $x       = isset($matrix[4]) ? (float)$matrix[4] : 0.0;
                $y       = isset($matrix[5]) ? (float)$matrix[5] : 0.0;
                $words[] = ['text' => $text, 'x' => $x, 'y' => $this->pageH - $y];
            }
        } catch (\Throwable) {
            // getDataTm tidak tersedia di versi ini
        }
        return $words;
    }

    // ═══════════════════════════════════════════════════════
    // INTERNAL — GHOSTSCRIPT
    // ═══════════════════════════════════════════════════════

    /** exec()/shell_exec() bisa dimatikan hosting (disable_functions): Ghostscript lalu dianggap tidak tersedia. */
    private static function bisaJalankan(string $fungsi): bool
    {
        $mati = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));
        return function_exists($fungsi) && !in_array($fungsi, $mati, true);
    }

    private function decryptWithGs(string $inputPath): ?string
    {
        if (!self::bisaJalankan('exec')) return null; // → secured_unreadable, bukan fatal error
        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bps_dec_' . uniqid() . '.pdf';
        $gs         = $this->getGsBinary();
        $null       = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        $gsQ  = '"' . str_replace('"', '', $gs)         . '"';
        $outQ = '"' . str_replace('"', '', $outputPath) . '"';
        $inQ  = '"' . str_replace('"', '', $inputPath)  . '"';

        // Percobaan 1
        $cmd = "{$gsQ} -dBATCH -dNOPAUSE -dQUIET -sDEVICE=pdfwrite "
            . "-dCompatibilityLevel=1.4 -sOutputFile={$outQ} {$inQ} 2>{$null}";
        exec($cmd, $out, $code);

        if ($code === 0 && file_exists($outputPath) && filesize($outputPath) > 1024) {
            return $outputPath;
        }

        // Percobaan 2 — dengan password kosong
        if (file_exists($outputPath)) @unlink($outputPath);
        $cmd2 = "{$gsQ} -dBATCH -dNOPAUSE -dQUIET -sDEVICE=pdfwrite "
            . "-sPDFPassword= -sOutputFile={$outQ} {$inQ} 2>{$null}";
        exec($cmd2, $out2, $code2);

        if ($code2 === 0 && file_exists($outputPath) && filesize($outputPath) > 1024) {
            return $outputPath;
        }

        if (file_exists($outputPath)) @unlink($outputPath);
        return null;
    }

    private function getGsBinary(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $baseDir = 'C:\\Program Files\\gs';
            if (is_dir($baseDir)) {
                $dirs = glob($baseDir . '\\gs*', GLOB_ONLYDIR);
                if ($dirs) {
                    rsort($dirs);
                    foreach ($dirs as $dir) {
                        foreach (['gswin64c.exe', 'gswin32c.exe', 'gs.exe'] as $bin) {
                            $p = $dir . '\\bin\\' . $bin;
                            if (file_exists($p)) return $p;
                        }
                    }
                }
            }
            return 'gswin64c';
        }

        foreach (['/bin/gs', '/usr/bin/gs', '/usr/local/bin/gs'] as $p) {
            if (file_exists($p)) return $p;
        }
        return (self::bisaJalankan('shell_exec') ? trim(shell_exec('which gs 2>/dev/null') ?? '') : '') ?: 'gs';
    }

    // ═══════════════════════════════════════════════════════
    // INTERNAL — EVALUATOR
    // ═══════════════════════════════════════════════════════

    private function runAllRules(): void
    {
        $rules = KriteriaPemeriksaan::aktif()->get();

        if ($this->method === 'secured_unreadable') {
            foreach ($rules as $rule) {
                $this->add($rule->kode, $rule->kategori, $rule->deskripsi,
                    'PERLU DICEK', 'PDF terenkripsi, tidak dapat diperiksa otomatis',
                    $rule->target, $this->areaLabel($rule));
            }
            return;
        }

        foreach ($rules as $rule) {
            [$status, $catatan] = $this->evalRule($rule);
            $catatan .= $this->imageNote($rule);
            $this->add($rule->kode, $rule->kategori, $rule->deskripsi, $status, $catatan,
                $rule->target, $this->areaLabel($rule), $this->lokasi ?: $this->lokasiPerkiraan($rule->target));
        }
    }

    /**
     * Tanpa temuan berposisi: perkiraan halaman kriteria dari target-nya (kover → 1, halaman khusus
     * seperti Katalog/Daftar Isi → halaman yang dikenali, kover belakang → terakhir), supaya petugas
     * bisa langsung membuka halamannya di panel tinjauan. teks kosong = hanya pindah halaman.
     * 'all'/'front' terlalu luas untuk ditebak; halaman khusus yang tidak dikenali tidak diberi perkiraan.
     */
    private function lokasiPerkiraan(string $target): array
    {
        if (!$this->pageTexts || in_array($target, ['all', 'front'], true)) return [];
        [$start, $count] = $this->targetRange($target);
        return $count > 0 ? [['hal' => $start + 1, 'teks' => '', 'perkiraan' => true]] : [];
    }

    /**
     * Keterangan jika halaman yang diperiksa kriteria ini berupa gambar:
     * hasil OCR bisa salah baca, dan halaman gambar yang tidak di-OCR tidak terbaca sama sekali.
     */
    private function imageNote(KriteriaPemeriksaan $rule): string
    {
        if ($rule->tipe_cek === 'manual' || (!$this->ocrPages && !$this->imagePages)) return '';

        $pages = match ($rule->target) {
            'cover' => [1],
            'page2' => [2],
            'front' => range(1, min(self::FRONT_PAGES, max(1, $this->pages))),
            'last'  => [$this->pages],
            default => [], // 'all': halaman foto/pembatas bab terlalu umum, catatan jadi tidak bermakna
        };

        // PDF "secured" (teks berbentuk kurva): halaman tanpa teks di luar batas OCR tidak terbaca sama sekali
        if (!$pages && $this->ocrTerlewat > 0) {
            return " [{$this->ocrTerlewat} halaman tanpa lapisan teks tidak di-OCR, cek manual]";
        }

        if (array_intersect($pages, $this->ocrPages)) {
            return ' [teks hasil OCR, cek ulang]';
        }
        if (array_intersect($pages, $this->imagePages)) {
            return ' [halaman berupa gambar, tidak di-OCR]';
        }
        return '';
    }

    private function evalRule(KriteriaPemeriksaan $rule): array
    {
        $this->lokasi        = [];
        $this->currentTarget = $rule->target;

        $param = $rule->parameter ?? [];
        // target halaman khusus (Katalog, Tim Penyusun, Daftar Isi, Kata Pengantar) dikenali dari isinya
        if (isset(self::HALAMAN_KHUSUS[$rule->target]) && $this->halamanKhusus($rule->target) === null && $rule->tipe_cek !== 'manual') {
            return ['TIDAK DIPERIKSA', 'Halaman ' . self::HALAMAN_KHUSUS[$rule->target] . ' tidak dikenali otomatis (mungkin berupa gambar), cek manual'];
        }
        $text  = $this->targetText($rule->target);
        $gagal = $rule->status_gagal;
        $msgOk = $rule->pesan_ok    ?? 'OK';
        $msgErr= $rule->pesan_gagal ?? 'Kriteria tidak terpenuhi';

        return match($rule->tipe_cek) {
            'regex'        => $this->evalRegex($param, $text, $gagal, $msgOk, $msgErr),
            'not_regex'    => $this->evalNotRegex($param, $text, $gagal, $msgOk, $msgErr),
            'contains'     => $this->evalContains($param, $text, false, $gagal, $msgOk, $msgErr),
            'not_contains' => $this->evalContains($param, $text, true,  $gagal, $msgOk, $msgErr),
            'posisi_area'  => $this->evalPosisi($param, $gagal, $msgOk, $msgErr),
            'min_pages'    => $this->evalPages($param, $gagal, $msgOk, $msgErr),
            'urutan_teks'  => $this->evalUrutanTeks($param, $text, $gagal, $msgOk, $msgErr),
            'syarat_min'   => $this->evalSyaratMin($param, $text, $gagal, $msgOk, $msgErr),
            'urutan'       => $this->evalUrutan($param, $text, $gagal, $msgOk, $msgErr),
            'sama_kover'   => $this->evalSamaKover($param, $text, $gagal, $msgOk, $msgErr),
            'paritas'      => $this->evalParitas($param, $gagal, $msgOk, $msgErr),
            'romawi_genap' => $this->evalRomawiGenap($text, $gagal, $msgOk, $msgErr),
            'jumlah_min'   => $this->evalJumlahMin($param, $text, $gagal, $msgOk, $msgErr),
            'nomor_urut'   => $this->evalNomorUrut($param, $text, $gagal, $msgOk, $msgErr),
            'bab'          => $this->evalBab($param, $gagal, $msgOk, $msgErr),
            'bulan_rilis'  => $this->evalBulanRilis($text, $gagal, $msgOk, $msgErr),
            'abjad'        => $this->evalAbjad($param, $text, $gagal, $msgOk, $msgErr),
            'judul_kover'  => $this->evalJudulKover($text, $gagal, $msgOk, $msgErr),
            'sama_api'     => $this->evalSamaApi($param, $text, $gagal, $msgOk, $msgErr),
            'rata_angka'   => $this->evalRataAngka($gagal, $msgOk, $msgErr),
            'nomor_halaman'  => $this->evalNomorHalaman($gagal, $msgOk, $msgErr),
            'running_title'  => $this->evalRunningTitle($param, $gagal, $msgOk, $msgErr),
            'daftar_halaman' => $this->evalDaftarHalaman($param, $text, $gagal, $msgOk, $msgErr),
            'font_bps'       => $this->evalFontBps($param, $gagal, $msgOk, $msgErr),
            'manual'      => ['PERLU DICEK', $msgErr],
            default        => ['TIDAK DIPERIKSA', 'Tipe cek tidak dikenal: ' . $rule->tipe_cek],
        };
    }

    /**
     * Cek urutan: kemunculan pertama pola 'before' harus ada sebelum kemunculan pertama pola
     * 'after' di target text. Dipakai untuk kriteria "Posisi X diletakkan setelah Y".
     */
    /** urutan_teks: pola `before` harus muncul sebelum pola `after`. */
    private function evalUrutanTeks(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        $before = $p['before'] ?? null;
        $after  = $p['after']  ?? null;
        if (!$before || !$after) return ['TIDAK DIPERIKSA', 'Parameter before/after kosong'];

        $flags = $p['flags'] ?? 'iu';
        try {
            $foundBefore = preg_match($this->buildRegex(['pattern' => $before, 'flags' => $flags]), $text, $mb, PREG_OFFSET_CAPTURE);
            $foundAfter  = preg_match($this->buildRegex(['pattern' => $after,  'flags' => $flags]), $text, $ma, PREG_OFFSET_CAPTURE);
        } catch (\Throwable) {
            return ['TIDAK DIPERIKSA', 'Regex before/after tidak valid'];
        }
        if ($foundBefore === false || $foundAfter === false) {
            return ['TIDAK DIPERIKSA', 'Regex before/after tidak valid'];
        }
        if (!$foundBefore || !$foundAfter) {
            return ['TIDAK DIPERIKSA', 'Salah satu komponen tidak ditemukan, urutan tidak bisa diperiksa'];
        }

        [$hitBefore, $offBefore] = $mb[0];
        [$hitAfter,  $offAfter]  = $ma[0];
        $this->addLokasi($offBefore, $hitBefore);
        $this->addLokasi($offAfter,  $hitAfter);

        return $offBefore < $offAfter ? ['OK', $ok] : [$gagal, $err];
    }

    /**
     * Syarat bersyarat: hitung kemunculan 'item_pattern' di SELURUH dokumen; kalau jumlahnya
     * sudah mencapai 'min', baru 'require_pattern' wajib ditemukan di target text rule ini.
     * Dipakai untuk "Daftar X dibuat jika minimal N buah" (daftar tabel/gambar/lampiran).
     */
    private function evalSyaratMin(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        $itemPattern    = $p['item_pattern']    ?? null;
        $requirePattern = $p['require_pattern'] ?? null;
        $min            = (int) ($p['min'] ?? 3);
        if (!$itemPattern || !$requirePattern) return ['TIDAK DIPERIKSA', 'Parameter item_pattern/require_pattern kosong'];

        $flags = $p['flags'] ?? 'iu';
        try {
            $count = preg_match_all($this->buildRegex(['pattern' => $itemPattern, 'flags' => $flags]), $this->targetText('all'));
        } catch (\Throwable) {
            $count = false;
        }
        if ($count === false) return ['TIDAK DIPERIKSA', 'Regex item_pattern tidak valid'];

        if ($count < $min) {
            return ['OK', "Hanya ditemukan {$count} (< {$min}), daftar tidak wajib dibuat"];
        }

        try {
            $found = preg_match($this->buildRegex(['pattern' => $requirePattern, 'flags' => $flags]), $text, $m, PREG_OFFSET_CAPTURE);
        } catch (\Throwable) {
            return ['TIDAK DIPERIKSA', 'Regex require_pattern tidak valid'];
        }
        if ($found === false) return ['TIDAK DIPERIKSA', 'Regex require_pattern tidak valid'];

        if ($found) {
            [$hit, $offset] = $m[0];
            $this->addLokasi($offset, $hit);
            return ['OK', "{$ok} (ditemukan {$count} item)"];
        }
        return [$gagal, "{$err} (ditemukan {$count} item, tapi daftar tidak ditemukan)"];
    }

    private function evalRegex(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        $pattern = $p['pattern'] ?? null;
        if (!$pattern) return ['TIDAK DIPERIKSA', 'Parameter pattern kosong'];
        $text  = $this->samarkan($p, $text);
        $regex = $this->buildRegex($p);
        try {
            $found = preg_match($regex, $text, $m, PREG_OFFSET_CAPTURE);
        } catch (\Throwable) {
            $found = false;
        }
        if ($found === false) {
            return ['TIDAK DIPERIKSA', 'Regex tidak valid: ' . $regex];
        }
        if ($found) {
            [$hit, $offset] = $m[0];
            $this->addLokasi($offset, $hit);
            $detail = trim($hit) !== '' ? ', ditemukan: ' . mb_substr(trim($hit), 0, 80) : '';
            return ['OK', $ok . $detail];
        }
        return [$gagal, $err];
    }

    /**
     * Kebalikan regex: pola yang ditemukan dianggap pelanggaran.
     * Menampilkan jumlah temuan + contoh pertama agar petugas mudah mengecek.
     */
    private function evalNotRegex(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        if (empty($p['pattern'])) return ['TIDAK DIPERIKSA', 'Parameter pattern kosong'];
        $text  = $this->samarkan($p, $text);
        $regex = $this->buildRegex($p);
        try {
            $count = preg_match_all($regex, $text, $m, PREG_OFFSET_CAPTURE);
        } catch (\Throwable) {
            $count = false;
        }
        if ($count === false) {
            return ['TIDAK DIPERIKSA', 'Regex tidak valid: ' . $regex];
        }
        if ($count === 0) {
            // parameter opsional "syarat": pola yang harus ada dulu agar hasil OK bermakna
            // (mis. format ISSN hanya dinilai bila halaman memang memuat ISSN)
            if (!empty($p['syarat']) && !@preg_match($this->buildRegex(['pattern' => $p['syarat'], 'flags' => $p['flags'] ?? '']), $text)) {
                return ['TIDAK DIPERIKSA', $p['pesan_syarat'] ?? 'Tidak ada yang perlu diperiksa di halaman ini'];
            }
            return ['OK', $ok];
        }

        foreach ($m[0] as [$hit, $offset]) $this->addLokasi($offset, $hit);
        $sample = preg_replace('/\s+/u', ' ', trim($m[0][0][0])) ?? '';
        return [$gagal, sprintf('%s: %d temuan, contoh: "%s"', $err, $count, mb_substr($sample, 0, 80))];
    }

    /**
     * Delimiter '~' supaya pola boleh memuat '/' tanpa di-escape.
     * Pola lama yang sudah memakai '\/' tetap valid.
     */
    private function buildRegex(array $p): string
    {
        $pattern = preg_replace('/(?<!\\\\)~/', '\\~', $p['pattern']);
        return '~' . $pattern . '~' . ($p['flags'] ?? '');
    }

    /**
     * Parameter opsional "abaikan": bagian teks yang cocok dengan pola ini diganti spasi (panjang sama,
     * jadi posisi temuan tetap benar) sebelum dicek — mis. tahun pada edisi "Volume 15, 2026" tidak
     * dihitung sebagai tahun judul.
     */
    private function samarkan(array $p, string $text): string
    {
        if (empty($p['abaikan'])) return $text;
        $regex = $this->buildRegex(['pattern' => $p['abaikan'], 'flags' => $p['flags'] ?? '']);
        return @preg_replace_callback($regex, fn($m) => str_repeat(' ', strlen($m[0])), $text) ?? $text;
    }

    /** Pola regex → label yang mudah dibaca untuk pesan (mis. "Nomor\s+Publikasi" → "Nomor Publikasi"). */
    private function labelPola(string $pola): string
    {
        $alt = explode('|', $pola)[0];
        $alt = preg_replace(['/\\\\s[+*]?\??/', '/\[(.)[^\]]*\]/', '/\\\\b|\(\?:|[()?^$]/'], [' ', '$1', ''], $alt);
        return trim(preg_replace('/\s+/', ' ', $alt));
    }

    /**
     * Urutan komponen: setiap label (regex) harus muncul berurutan di halaman target, mis. komponen
     * halaman Katalog "Nomor Publikasi" → "Jumlah Halaman". Label yang tidak ada → tidak diperiksa
     * (komponen opsional seperti "Dicetak oleh").
     */
    private function evalUrutan(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        $labels = array_values(array_filter((array) ($p['label'] ?? []), fn($l) => trim((string) $l) !== ''));
        if (count($labels) < 2) return ['TIDAK DIPERIKSA', 'Parameter urutan butuh minimal 2 komponen'];

        $posisi = [];
        $hilang = [];
        foreach ($labels as $pola) {
            $regex = $this->buildRegex(['pattern' => $pola, 'flags' => $p['flags'] ?? 'iu']);
            if (@preg_match($regex, $text, $m, PREG_OFFSET_CAPTURE)) {
                $posisi[] = ['offset' => $m[0][1], 'nama' => $this->labelPola($pola), 'teks' => $m[0][0]];
            } else {
                $hilang[] = $this->labelPola($pola);
            }
        }
        if ($hilang) {
            return ['TIDAK DIPERIKSA', 'Komponen tidak ditemukan di halaman: ' . implode(', ', $hilang)];
        }

        $last = end($posisi);
        $this->addLokasi($last['offset'], $last['teks']);
        $diharapkan = implode(' → ', array_column($posisi, 'nama'));
        $sesuai = true;
        for ($i = 1; $i < count($posisi); $i++) {
            if ($posisi[$i]['offset'] <= $posisi[$i - 1]['offset']) { $sesuai = false; break; }
        }
        if ($sesuai) return ['OK', "{$ok} ({$diharapkan})"];

        $nyata = $posisi;
        usort($nyata, fn($a, $b) => $a['offset'] <=> $b['offset']);
        // pesan gagal berupa arahan ("... seharusnya ..."), rinciannya urutan yang benar & yang ada sekarang
        return [$gagal, "{$err}: urutan yang benar {$diharapkan}; saat ini " . implode(' → ', array_column($nyata, 'nama'))];
    }

    /**
     * Isian di halaman target harus sama dengan di kover (mis. nomor ISSN). Pola memakai satu grup
     * tangkap untuk nilai yang dibandingkan; spasi & tanda hubung diabaikan saat membandingkan.
     */
    private function evalSamaKover(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        if (empty($p['pattern'])) return ['TIDAK DIPERIKSA', 'Parameter pattern kosong'];
        $regex = $this->buildRegex($p);
        if (!@preg_match($regex, $text, $m, PREG_OFFSET_CAPTURE)) {
            return ['TIDAK DIPERIKSA', $p['pesan_syarat'] ?? 'Isian tidak ditemukan di halaman ini'];
        }
        if (!@preg_match($regex, $this->coverText, $k)) {
            return ['TIDAK DIPERIKSA', 'Isian tidak ditemukan di kover untuk dibandingkan'];
        }
        $nilai = trim($m[1][0] ?? $m[0][0]);
        $kover = trim($k[1] ?? $k[0]);
        $norm  = fn($s) => mb_strtoupper(preg_replace('/[\s\-\x{2010}-\x{2015}]+/u', '', $s));
        $this->addLokasi($m[0][1], $m[0][0]);

        return $norm($nilai) === $norm($kover)
            ? ['OK', "{$ok} ({$nilai})"]
            : [$gagal, "{$err}: di halaman ini: {$nilai}, di kover: {$kover}"];
    }

    /**
     * Halaman awal bagian harus ganjil/genap. Nomor halaman = urutan halaman di PDF (kover = 1),
     * sehingga hasilnya bergantung pada PDF yang memuat semua halaman termasuk halaman kosong.
     */
    private function evalParitas(array $p, string $gagal, string $ok, string $err): array
    {
        [$start] = $this->targetRange($this->currentTarget);
        $hal    = $start + 1;
        $mau    = ($p['posisi'] ?? 'ganjil') === 'genap' ? 'genap' : 'ganjil';
        $nyata  = $hal % 2 === 1 ? 'ganjil' : 'genap';
        $label  = self::HALAMAN_KHUSUS[$this->currentTarget] ?? 'Bagian';
        $baris  = trim(strtok(trim($this->pageTexts[$start] ?? ''), "\n") ?: '');
        $this->lokasi[] = ['hal' => $hal, 'teks' => mb_substr($baris, 0, 80)];

        if ($mau === $nyata) return ['OK', "{$ok} ({$label} mulai hal. {$hal} PDF)"];

        $catatan = "{$err} (saat ini mulai hal. {$hal} PDF, {$nyata})";
        // judul Daftar Isi/Kata Pengantar sering berupa gambar: halaman sebelumnya tanpa teks bisa jadi awalnya
        if (in_array($this->currentTarget, ['daftar_isi', 'kata_pengantar'], true)
            && $start > 0 && trim($this->pageTexts[$start - 1] ?? '') === '') {
            $catatan .= "; hal. {$start} tanpa teks (gambar/kosong), bila itu awal {$label}, posisinya bisa jadi sudah benar";
        }
        return [$gagal, $catatan];
    }

    /** Jumlah halaman romawi di halaman Katalog (mis. "x+52 halaman") harus genap. */
    private function evalRomawiGenap(string $text, string $gagal, string $ok, string $err): array
    {
        // sama dengan P-069: "x+52 halaman", "xx + 106 hal/ pages", "x+52 pages", …; teks PDF kadang terbaca
        // berjarak per huruf ("x x + 12 2 hal/ pages") → satu spasi di dalam angka diterima lalu dibuang
        if (!preg_match('/\b((?:[ivxlcdm] ?)*[ivxlcdm])\s*\+\s*(\d(?: ?\d)*)\s*(?:halaman|hal\.?|hlm\.?|pages?)(?:\s*\/\s*(?:pages?|halaman|hal\.?))?(?!\p{L})/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
            return ['TIDAK DIPERIKSA', 'Format jumlah halaman (romawi + arab) tidak ditemukan'];
        }
        $romawi = str_replace(' ', '', $m[1][0]);
        $nilai  = $this->romawiKeAngka($romawi);
        if (!$nilai) return ['TIDAK DIPERIKSA', "Angka romawi '{$romawi}' tidak terbaca"];
        $this->addLokasi($m[0][1], $m[0][0]);

        return $nilai % 2 === 0
            ? ['OK', "{$ok} ({$romawi} = {$nilai})"]
            : [$gagal, "{$err} ({$romawi} = {$nilai}, ganjil)"];
    }

    /** Minimal jumlah entri (mis. daftar tabel dibuat bila ada ≥ 3 tabel). */
    private function evalJumlahMin(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        if (empty($p['pattern'])) return ['TIDAK DIPERIKSA', 'Parameter pattern kosong'];
        $min = max(1, (int) ($p['min'] ?? 1));
        $n   = @preg_match_all($this->buildRegex($p), $text);
        if ($n === false) return ['TIDAK DIPERIKSA', 'Regex tidak valid'];
        return $n >= $min ? ['OK', "{$ok} ({$n} entri)"] : [$gagal, "{$err} ({$n} entri, minimal {$min})"];
    }

    /**
     * Nomor berurutan & formatnya konsisten (mis. nomor tabel di Daftar Tabel). Pola memakai satu grup
     * tangkap untuk nomornya: "1", "2.3", dst. Satu jenjang: 1, 2, 3 …; dua jenjang atau lebih: nomor
     * terakhir naik satu dalam bab yang sama, mulai dari 1 lagi saat bab berganti.
     */
    private function evalNomorUrut(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        if (empty($p['pattern'])) return ['TIDAK DIPERIKSA', 'Parameter pattern kosong'];
        if (!@preg_match_all($this->buildRegex($p), $text, $m, PREG_OFFSET_CAPTURE)) {
            return ['TIDAK DIPERIKSA', 'Nomor tidak ditemukan'];
        }
        $nomor = array_map(fn($x) => $x[0], $m[1]);
        if (count($nomor) < 2) return ['TIDAK DIPERIKSA', 'Hanya ' . count($nomor) . ' nomor ditemukan'];
        $this->addLokasi($m[0][0][1], $m[0][0][0]);

        $jenjang = array_unique(array_map(fn($n) => substr_count($n, '.') + 1, $nomor));
        if (count($jenjang) > 1) {
            return [$gagal, "{$err}: format penomoran tidak konsisten (" . implode(' dan ', array_slice(array_unique($nomor), 0, 2)) . ')'];
        }
        for ($i = 1; $i < count($nomor); $i++) {
            $a = array_map('intval', explode('.', $nomor[$i - 1]));
            $b = array_map('intval', explode('.', $nomor[$i]));
            $akhir = count($b) - 1;
            $babSama = array_slice($a, 0, $akhir) === array_slice($b, 0, $akhir);
            $benar = $babSama
                ? $b[$akhir] === $a[$akhir] + 1
                : array_slice($b, 0, $akhir) > array_slice($a, 0, $akhir) && $b[$akhir] === 1;
            if (!$benar) {
                $this->lokasi = [];
                $this->addLokasi($m[0][$i][1], $m[0][$i][0]);
                return [$gagal, "{$err}: {$nomor[$i - 1]} lalu {$nomor[$i]}"];
            }
        }
        return ['OK', "{$ok} (" . count($nomor) . " nomor, {$nomor[0]} s.d. " . end($nomor) . ')'];
    }

    /**
     * Awal setiap bab (baris "BAB n"/"CHAPTER n" di bagian atas halaman, bukan entri daftar isi).
     * @return array<int, array{nomor:int, label:string, hal:int}> urut menurut halaman
     */
    private function awalBab(): array
    {
        $lewati = [];
        foreach (['daftar_isi', 'daftar_tabel', 'daftar_gambar', 'daftar_lampiran'] as $d) {
            if ($r = $this->halamanKhusus($d)) $lewati = array_merge($lewati, range($r[0], $r[0] + $r[1] - 1));
        }

        $bab = [];
        foreach ($this->pageTexts as $i => $t) {
            if ($i === 0 || in_array($i, $lewati, true)) continue;
            $baris = array_slice(array_values(array_filter(array_map('trim', preg_split('/\R/u', $t)))), 0, 8);
            foreach ($baris as $b) {
                if (preg_match('/(?:\.\s?){5,}/u', $b)) continue; // entri daftar isi
                if (!preg_match('/^(?:BAB|Bab|CHAPTER|Chapter)\s+(\d{1,2}|[IVXLC]{1,6})\b/u', $b, $x)) continue;
                $n = ctype_digit($x[1]) ? (int) $x[1] : $this->romawiKeAngka($x[1]);
                if ($n > 0 && !isset($bab[$n])) $bab[$n] = ['nomor' => $n, 'label' => $x[0], 'hal' => $i + 1];
                break;
            }
        }
        usort($bab, fn($a, $b) => $a['hal'] <=> $b['hal']);
        return $bab;
    }

    /** Bab: dimulai di halaman ganjil (cek "ganjil") atau bernomor berurutan 1, 2, 3 … (cek "berurutan"). */
    private function evalBab(array $p, string $gagal, string $ok, string $err): array
    {
        $bab = $this->awalBab();
        if (!$bab) return ['TIDAK DIPERIKSA', 'Judul bab ("BAB n") tidak dikenali, cek manual'];

        if (($p['cek'] ?? 'ganjil') === 'berurutan') {
            foreach ($bab as $i => $b) {
                $harap = $i === 0 ? 1 : $bab[$i - 1]['nomor'] + 1;
                if ($b['nomor'] !== $harap) {
                    $this->lokasi[] = ['hal' => $b['hal'], 'teks' => $b['label']];
                    return [$gagal, "{$err}: " . ($i === 0 ? "bab pertama bernomor {$b['nomor']}" : "{$bab[$i - 1]['label']} lalu {$b['label']}")];
                }
            }
            return ['OK', "{$ok} (" . count($bab) . ' bab)'];
        }

        $genap = array_filter($bab, fn($b) => $b['hal'] % 2 === 0);
        foreach (array_slice($genap, 0, self::MAX_LOKASI) as $b) $this->lokasi[] = ['hal' => $b['hal'], 'teks' => $b['label']];
        if (!$genap) return ['OK', "{$ok} (" . count($bab) . ' bab)'];
        return [$gagal, "{$err}: " . implode(', ', array_map(fn($b) => "{$b['label']} (hal. {$b['hal']} PDF)", $genap))];
    }

    /**
     * Entri tersusun menurut abjad (Daftar Pustaka, Indeks, Glosarium). Pola memakai satu grup tangkap
     * untuk kunci pengurutan (nama penulis / istilah); kunci yang sama boleh berurutan.
     */
    private function evalAbjad(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        if (empty($p['pattern'])) return ['TIDAK DIPERIKSA', 'Parameter pattern kosong'];
        if (!@preg_match_all($this->buildRegex($p), $text, $m, PREG_OFFSET_CAPTURE) || count($m[1]) < 3) {
            return ['TIDAK DIPERIKSA', 'Entri tidak cukup terbaca untuk dicek urutannya (' . count($m[1] ?? []) . ' entri)'];
        }
        $kunci = fn($s) => trim(preg_replace('/[^\p{L}\d ]+/u', '', mb_strtolower(preg_replace('/\s+/u', ' ', $s))));
        for ($i = 1; $i < count($m[1]); $i++) {
            $a = $kunci($m[1][$i - 1][0]);
            $b = $kunci($m[1][$i][0]);
            if (strcmp($a, $b) > 0) {
                $this->addLokasi($m[0][$i][1], $m[0][$i][0]);
                $pendek = fn($s) => mb_strimwidth(trim($s), 0, 40, '…');
                return [$gagal, "{$err}: \"{$pendek($m[1][$i - 1][0])}\" lalu \"{$pendek($m[1][$i][0])}\""];
            }
        }
        return ['OK', "{$ok} (" . count($m[1]) . ' entri)'];
    }

    /**
     * Judul & tahun judul di halaman target sama dengan kover: kata-kata judul kover (tanpa nama penerbit,
     * katalog, ISSN, edisi, alamat web) minimal 70% ada di halaman target, dan tahun judulnya ada.
     */
    private function evalJudulKover(string $text, string $gagal, string $ok, string $err): array
    {
        // buang bagian yang bukan judul (penerbit, edisi, katalog/ISSN, alamat web) — di kover maupun halaman target,
        // supaya tahun pada edisi "Volume 41, 2026" tidak terhitung sebagai tahun judul
        $buang = fn(string $s) => preg_replace([
            // penerbit beserta wilayahnya (mis. "BADAN PUSAT STATISTIK / KABUPATEN BANJAR"): bukan bagian judul,
            // apalagi untuk publikasi kecamatan yang judulnya tidak memuat nama kabupaten
            '/BADAN\s+PUSAT\s+STATISTIK(?:\s+(?:PROVINSI|KABUPATEN|KOTA)\b[^\n]*)?|BPS[-\s]+STATISTICS[^\n]*/iu',
            '/(?:Volume|Vol\.?|Edisi|Nomor|No\.)\s*\d+[^\n]{0,15}?(?:19|20)\d{2}/iu',
            '/(?:Katalog|Catalogue|IS[SB]N|Nomor\s+Publikasi|Publication\s+Number)[^\n]*/iu',
            '/https?:\/\/\S+|\S+\.go\.id\S*/iu',
        ], ' ', $this->bersihkanGanda($s));
        // wilayah penerbit (mis. "BADAN PUSAT STATISTIK KABUPATEN BARITO KUALA" / "BPS-Statistics of Barito Kuala Regency",
        // dalam urutan baris apa pun): bukan bagian judul — publikasi kecamatan tidak memuatnya di judul
        $wilayah = [];
        if (preg_match_all('/BADAN\s+PUSAT\s+STATISTIK\s+(?:PROVINSI|KABUPATEN|KOTA)\s+([\p{L}\- ]+)'
            . '|(?:PROVINSI|KABUPATEN|KOTA)\s+([\p{L}\- ]+?)\s*\R\s*BADAN\s+PUSAT\s+STATISTIK'
            . '|STATISTICS\s+(?:OF\s+)?([\p{L}\- ]+?)\s+(?:REGENCY|MUNICIPALITY|PROVINCE)/iu', $this->bersihkanGanda($this->coverText), $mw, PREG_SET_ORDER)) {
            foreach ($mw as $x) {
                foreach (preg_split('/[^\p{L}]+/u', mb_strtoupper(trim(($x[1] ?? '') . ' ' . ($x[2] ?? '') . ' ' . ($x[3] ?? '')))) as $w) {
                    if ($w !== '') $wilayah[$w] = true;
                }
            }
        }
        $wilayah += array_fill_keys(['KABUPATEN', 'KOTA', 'PROVINSI', 'REGENCY', 'MUNICIPALITY', 'PROVINCE', 'STATISTICS', 'BPS'], true);

        // baris berbahasa Inggris (judul terjemahan "… District in Figures", penerbit "… Regency"): halaman target
        // sering hanya memuat judul bahasa Indonesia
        $inggris = '/\b(?:IN\s+FIGURES|SUBDISTRICT|DISTRICT|REGENCY|MUNICIPALITY|PROVINCE|STATISTICS|OF|THE)\b/iu';
        $koverAsli = preg_replace('/(\p{Ll})(\p{Lu})/u', '$1 $2', $buang($this->coverText)); // "SelatanProvinsi" → "Selatan Provinsi"
        $kover = implode("\n", array_filter(preg_split('/\R/u', $koverAsli), fn($l) => !preg_match($inggris, $l)));

        $tahun = preg_match('/(?<![\d.,\/-])((?:19|20)\d{2})/u', $koverAsli, $y) ? $y[1] : null;
        $henti = ['DAN', 'YANG', 'DARI', 'UNTUK', 'THE', 'AND', 'FOR'];
        $pilihKata = fn(string $s) => array_values(array_unique(array_filter(
            preg_split('/[^\p{L}]+/u', mb_strtoupper($s)),
            fn($w) => mb_strlen($w) >= 3 && !in_array($w, $henti, true) && !isset($wilayah[$w])
        )));
        $kata = $pilihKata($kover);
        if (count($kata) < 2) $kata = $pilihKata($koverAsli); // judul hanya bahasa Inggris / satu baris campuran
        if (count($kata) < 2) return ['TIDAK DIPERIKSA', 'Judul di kover tidak terbaca (mungkin berupa gambar)'];

        $padat  = preg_replace('/[^\p{L}\d]+/u', '', mb_strtoupper($buang($text)));
        $hilang = array_values(array_filter($kata, fn($w) => !str_contains($padat, $w)));
        $porsi  = 1 - count($hilang) / count($kata);
        $adaTahun = !$tahun || str_contains($padat, $tahun);

        $info = sprintf('%d dari %d kata judul kover ditemukan', count($kata) - count($hilang), count($kata))
              . ($tahun ? ", tahun {$tahun} " . ($adaTahun ? 'ada' : 'tidak ada') : '');
        if ($porsi >= 0.7 && $adaTahun) return ['OK', "{$ok} ({$info})"];
        return [$gagal, "{$err} ({$info}" . ($hilang ? '; tidak ditemukan: ' . implode(', ', array_slice($hilang, 0, 6)) : '') . ')'];
    }

    // ═══════════════════════════════════════════════════════
    // NOMOR HALAMAN, RUNNING TITLE, DAFTAR ↔ HALAMAN, FONT NAMA BPS
    // (butuh data tepi/fonts dari browser: PdfExtract tepiHalaman & fontHalaman)
    // ═══════════════════════════════════════════════════════

    /** Batas jumlah temuan yang dirinci di catatan (sisanya diringkas "dan N lainnya"). */
    private const MAKS_RINCI = 6;
    /** Pita atas halaman tempat judul "BAB n" (sama dengan PITA_TEPI di js/pdf-extract.js). */
    private const PITA_ATAS = 0.12;

    /**
     * Nomor halaman tercetak & running title tiap halaman (indeks 0-based → info|null), dari teks pita atas/bawah:
     * nomor = item berisi angka arab / romawi saja yang paling dekat ke tepi halaman dan berada di garis dasar
     * yang paling sering dipakai nomor halaman di dokumen ini (supaya angka sel tabel di dekat tepi tidak ikut);
     * running title = teks lain di baris yang sama dengan nomor itu.
     *
     * @return array<int, ?array{jenis:string, n:int, teks:string, rt:string, x:float, y:float, sisi:string}>
     */
    private function nomorHalaman(): array
    {
        if ($this->nomorCache !== null) return $this->nomorCache;

        // 1. kandidat per halaman
        $kandidat = [];
        foreach ($this->tepi as $i => $pg) {
            if (!$pg) continue;
            $terbaik = null;
            foreach ($pg['it'] as $it) {
                $t = $it['t'];
                if (preg_match('/^\d{1,4}$/', $t)) {
                    $jenis = 'arab';
                } elseif (preg_match('/^(?:[ivxlcdm]{1,7}|[IVXLCDM]{1,7})$/', $t) && $this->romawiKeAngka(strtolower($t)) > 0) {
                    $jenis = 'romawi';
                } else {
                    continue;
                }
                $atas  = $it['y'] < $pg['h'] / 2;
                $jarak = $atas ? $it['y'] : $pg['h'] - $it['y'];
                if (!$terbaik || $jarak < $terbaik['jarak']) {
                    $terbaik = ['jenis' => $jenis, 'teks' => $t, 'x' => $it['x'], 'y' => $it['y'], 'jarak' => $jarak, 'pita' => $atas ? 'atas' : 'bawah'];
                }
            }
            if ($terbaik) $kandidat[$i] = $terbaik;
        }

        // 2. garis dasar nomor halaman per jenis (y dibulatkan 4 pt): dipakai banyak halaman (≥ 3 dan ≥ 8% kandidat
        //    jenis itu). Angka di ujung entri daftar isi/tabel atau sel tabel yang kebetulan dekat tepi tersebar → tersaring.
        $dasar = [];
        foreach ($kandidat as $k) {
            $b = (int) round($k['y'] / 4);
            $dasar[$k['jenis']][$b] = ($dasar[$k['jenis']][$b] ?? 0) + 1;
        }
        $garis = [];
        foreach ($dasar as $jenis => $hitung) {
            $min = max(3, array_sum($hitung) * 0.08);
            $garis[$jenis] = array_flip(array_keys(array_filter($hitung, fn($n) => $n >= $min)));
        }

        // 3. nomor + running title
        $hasil = [];
        foreach ($this->tepi as $i => $pg) {
            $k = $kandidat[$i] ?? null;
            $b = $k ? (int) round($k['y'] / 4) : 0;
            $g = $k ? ($garis[$k['jenis']] ?? []) : [];
            if (!$k || !(isset($g[$b]) || isset($g[$b - 1]) || isset($g[$b + 1]))) {
                $hasil[$i] = null;
                continue;
            }
            $sebaris = array_values(array_filter($pg['it'], fn($it) => abs($it['y'] - $k['y']) <= 3
                && !($it['x'] === $k['x'] && $it['t'] === $k['teks'])));
            usort($sebaris, fn($a, $b) => $a['x'] <=> $b['x']);
            $rt = trim(preg_replace([
                '~(?:https?://)?\S+\.(?:go\.id|bps\.go\.id)\S*~iu', // watermark alamat web
                '/\s+/u',
            ], ' ', implode(' ', array_column($sebaris, 't'))));
            // romawi satu huruf ("BAB V", "Pilar I") di pita bukan nomor halaman
            if ($k['jenis'] === 'romawi' && mb_strlen($k['teks']) === 1 && preg_match('/\b(?:BAB|CHAPTER|PILAR|BAGIAN)\s*$/iu', $rt)) {
                $hasil[$i] = null;
                continue;
            }
            $hasil[$i] = [
                'jenis' => $k['jenis'],
                'n'     => $k['jenis'] === 'arab' ? (int) $k['teks'] : $this->romawiKeAngka(strtolower($k['teks'])),
                'teks'  => $k['teks'],
                'rt'    => preg_match('/\p{L}{3}/u', $rt) ? $rt : '',
                'x'     => $k['x'],
                'y'     => $k['y'],
                'sisi'  => $k['x'] < $pg['w'] * 0.4 ? 'kiri' : ($k['x'] > $pg['w'] * 0.6 ? 'kanan' : 'tengah'),
            ];
        }
        return $this->nomorCache = $hasil;
    }

    /**
     * Selisih indeks halaman PDF (1-based) dengan nomor tercetak untuk satu jenis (arab/romawi): nilai yang paling sering.
     * null bila nomor halaman jenis itu tidak cukup terbaca (< 3 halaman sepakat).
     */
    private function selisihHalaman(string $jenis): ?int
    {
        $hitung = [];
        foreach ($this->nomorHalaman() as $i => $nh) {
            if ($nh && $nh['jenis'] === $jenis) $hitung[$i + 1 - $nh['n']] = ($hitung[$i + 1 - $nh['n']] ?? 0) + 1;
        }
        if (!$hitung) return null;
        arsort($hitung);
        $selisih = array_key_first($hitung);
        return $hitung[$selisih] >= 3 ? (int) $selisih : null;
    }

    /** Nomor tercetak → indeks halaman PDF (0-based), atau null bila tidak bisa dipetakan. */
    private function indeksDariNomor(string $nomor): ?int
    {
        $jenis = ctype_digit($nomor) ? 'arab' : 'romawi';
        $n = $jenis === 'arab' ? (int) $nomor : $this->romawiKeAngka(strtolower($nomor));
        $selisih = $this->selisihHalaman($jenis);
        if ($n < 1 || $selisih === null) return null;
        $i = $n + $selisih - 1;
        return $i >= 0 && $i < count($this->pageTexts) ? $i : null;
    }

    /**
     * Label halaman untuk catatan: "hal. 18 (PDF 47)". Nomornya menurut urutan yang benar (dari selisih yang paling
     * sering), bukan yang tercetak, supaya nomor halaman yang salah cetak tidak membingungkan.
     */
    private function labelHal(int $i): string
    {
        foreach (['arab', 'romawi'] as $jenis) {
            $s = $this->selisihHalaman($jenis);
            if ($s === null || $i + 1 - $s < 1) continue;
            if ($jenis === 'romawi' && ($a = $this->selisihHalaman('arab')) !== null && $i + 1 - $a >= 1) continue;
            $n = $i + 1 - $s;
            return 'hal. ' . ($jenis === 'arab' ? $n : $this->angkaKeRomawi($n)) . ' (PDF ' . ($i + 1) . ')';
        }
        return 'hal. PDF ' . ($i + 1);
    }

    private function angkaKeRomawi(int $n): string
    {
        $peta = ['m' => 1000, 'cm' => 900, 'd' => 500, 'cd' => 400, 'c' => 100, 'xc' => 90, 'l' => 50, 'xl' => 40, 'x' => 10, 'ix' => 9, 'v' => 5, 'iv' => 4, 'i' => 1];
        $s = '';
        foreach ($peta as $r => $v) while ($n >= $v) { $s .= $r; $n -= $v; }
        return $s;
    }

    /** Ringkas daftar temuan: maks. MAKS_RINCI dirinci, sisanya "dan N lainnya". */
    private function rinci(array $temuan): string
    {
        $temuan = array_values(array_unique($temuan));
        $lebih  = count($temuan) - self::MAKS_RINCI;
        return implode('; ', array_slice($temuan, 0, self::MAKS_RINCI)) . ($lebih > 0 ? "; dan {$lebih} lainnya" : '');
    }

    /** Kata (huruf/angka, ≥ 3 karakter) dalam huruf besar, untuk membandingkan judul. */
    private function kataKunci(string $s): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\d]+/u', mb_strtoupper($this->bersihkanGanda($s))),
            fn($w) => mb_strlen($w) >= 3 && !in_array($w, ['DAN', 'YANG', 'THE', 'AND', 'FOR', 'DARI', 'BAB'], true)
        )));
    }

    /** Porsi kata $a yang ada di $b (0–1); $a kosong → 0. */
    private function porsiKata(array $a, array $b): float
    {
        if (!$a) return 0.0;
        $b = array_flip($b);
        return count(array_filter($a, fn($w) => isset($b[$w]))) / count($a);
    }

    /** nomor_halaman: nomor halaman romawi & arab berurutan, tanpa loncat atau ganda. */
    private function evalNomorHalaman(string $gagal, string $ok, string $err): array
    {
        if (!$this->tepi) return ['TIDAK DIPERIKSA', 'Data nomor halaman tidak tersedia (periksa ulang dengan halaman yang sudah dimuat ulang)'];
        $nh = array_filter($this->nomorHalaman());
        if (!$nh) return ['TIDAK DIPERIKSA', 'Nomor halaman tidak terbaca (mungkin berupa gambar/kurva), cek manual'];

        $temuan = [];
        $ringkas = [];
        foreach (['romawi', 'arab'] as $jenis) {
            $selisih = $this->selisihHalaman($jenis);
            $hal = array_filter($nh, fn($x) => $x['jenis'] === $jenis);
            if ($selisih === null || !$hal) continue;
            $nomor = array_column($hal, 'n');
            $ringkas[] = $jenis === 'arab' ? 'arab ' . min($nomor) . '–' . max($nomor) : 'romawi ' . $this->angkaKeRomawi(min($nomor)) . '–' . $this->angkaKeRomawi(max($nomor));
            foreach ($hal as $i => $x) {
                $harap = $i + 1 - $selisih;
                if ($x['n'] === $harap) continue;
                $seharusnya = $jenis === 'arab' ? (string) $harap : $this->angkaKeRomawi(max(1, $harap));
                $temuan[] = 'hal. PDF ' . ($i + 1) . " tertulis {$x['teks']} (seharusnya {$seharusnya})";
                if (count($this->lokasi) < self::MAX_LOKASI) $this->lokasi[] = ['hal' => $i + 1, 'teks' => mb_substr(trim($x['rt'] . ' ' . $x['teks']), 0, 80)];
            }
        }
        if (!$ringkas) return ['TIDAK DIPERIKSA', 'Nomor halaman tidak cukup terbaca untuk dicek urutannya'];
        if (!$temuan) return ['OK', "{$ok} (" . implode(', ', $ringkas) . ')'];
        return [$gagal, "{$err}: " . $this->rinci($temuan)];
    }

    /**
     * running_title (cek: arab | romawi | ganjil | genap):
     *   arab   — halaman bernomor arab (isi s.d. penutup) memakai running title;
     *   romawi — halaman bernomor romawi tidak memakai running title;
     *   ganjil — running title halaman ganjil berisi judul bab;
     *   genap  — running title halaman genap berisi judul buku.
     */
    private function evalRunningTitle(array $p, string $gagal, string $ok, string $err): array
    {
        if (!$this->tepi) return ['TIDAK DIPERIKSA', 'Data header/footer tidak tersedia (periksa ulang dengan halaman yang sudah dimuat ulang)'];
        $nh  = $this->nomorHalaman();
        $cek = $p['cek'] ?? 'arab';
        $temuan = [];
        $lokasi = function (int $i, string $teks) {
            if (count($this->lokasi) < self::MAX_LOKASI) $this->lokasi[] = ['hal' => $i + 1, 'teks' => mb_substr($teks, 0, 80)];
        };

        if ($cek === 'romawi') {
            $romawi = array_filter($nh, fn($x) => $x && $x['jenis'] === 'romawi');
            if (!$romawi) return ['TIDAK DIPERIKSA', 'Halaman bernomor romawi tidak terbaca'];
            foreach ($romawi as $i => $x) {
                if ($x['rt'] === '') continue;
                $temuan[] = "hal. {$x['teks']} (PDF " . ($i + 1) . "): \"" . mb_strimwidth($x['rt'], 0, 50, '…') . '"';
                $lokasi($i, $x['rt']);
            }
            return $temuan ? [$gagal, "{$err}: " . $this->rinci($temuan)] : ['OK', "{$ok} (" . count($romawi) . ' halaman romawi)'];
        }

        $arab = array_filter($nh, fn($x) => $x && $x['jenis'] === 'arab');
        if (count($arab) < 3) return ['TIDAK DIPERIKSA', 'Halaman bernomor arab tidak cukup terbaca, cek manual'];

        if ($cek === 'arab') {
            [$awal, $akhir] = [min(array_keys($arab)), max(array_keys($arab))];
            for ($i = $awal; $i <= $akhir; $i++) {
                $pg = $this->tepi[$i] ?? null;
                // halaman landscape (tabel diputar), kosong, atau berupa gambar (pembatas bab) dilewati
                if (!$pg || $pg['w'] > $pg['h'] || mb_strlen(trim($this->pageTexts[$i] ?? '')) < 150) continue;
                if (!empty($nh[$i]['rt'])) continue;
                $temuan[] = ($nh[$i] ? $this->labelHal($i) : 'hal. PDF ' . ($i + 1) . ' (nomor halaman juga tidak terbaca)');
                $lokasi($i, '');
            }
            return $temuan ? [$gagal, "{$err}: tanpa running title di " . $this->rinci($temuan)]
                           : ['OK', "{$ok} (" . count($arab) . ' halaman arab)'];
        }

        // ganjil (judul bab) / genap (judul buku): running title yang sama dikelompokkan
        $mauGanjil = $cek === 'ganjil';
        $judulBuku = $this->kataKunci(preg_replace('/(?:Katalog|Catalogue|IS[SB]N)[^\n]*|https?:\/\/\S+|\S+\.go\.id\S*/iu', ' ', $this->coverText));
        $bab = $mauGanjil ? $this->judulBab() : [];
        if ($mauGanjil && !$bab) return ['TIDAK DIPERIKSA', 'Judul bab tidak terbaca dari Daftar Isi, cek manual'];
        $babTerakhir = $this->babMulai() ? max($this->babMulai()) : PHP_INT_MAX; // halaman awal bab terakhir
        if (!$mauGanjil && count($judulBuku) < 2) return ['TIDAK DIPERIKSA', 'Judul buku di kover tidak terbaca (mungkin gambar)'];

        $salah = []; // rt → [indeks halaman]
        $diperiksa = 0;
        foreach ($arab as $i => $x) {
            if ($x['rt'] === '' || ($x['n'] % 2 === 1) !== $mauGanjil) continue;
            $diperiksa++;
            $rujukan = $mauGanjil ? $this->babUntuk($bab, $i) : ['judul' => '', 'kata' => $judulBuku];
            if (!$rujukan) continue;
            if ($this->porsiKata($this->kataKunci($x['rt']), $rujukan['kata']) >= 0.7) continue;
            // bagian penutup yang judulnya tidak terbaca sebagai awal bagian (mis. salah ketik di judul halamannya):
            // running title "DAFTAR PUSTAKA"/"LAMPIRAN" sesudah bab terakhir diterima
            if ($mauGanjil && $i > $babTerakhir
                && array_filter(self::JUDUL_BELAKANG, fn($pola) => preg_match($pola, $x['rt']))) continue;
            $salah[$x['rt'] . ($mauGanjil ? ' ⟂ ' . $rujukan['judul'] : '')][] = $i;
        }
        if (!$diperiksa) return ['TIDAK DIPERIKSA', 'Running title halaman ' . ($mauGanjil ? 'ganjil' : 'genap') . ' tidak terbaca'];
        foreach ($salah as $kunci => $hal) {
            [$rt, $judul] = array_pad(explode(' ⟂ ', $kunci, 2), 2, '');
            $temuan[] = '"' . mb_strimwidth($rt, 0, 45, '…') . '"' . ($judul ? ' (bab: "' . mb_strimwidth($judul, 0, 40, '…') . '")' : '')
                . ' di ' . implode(', ', array_map(fn($i) => $this->labelHal($i), array_slice($hal, 0, 3))) . (count($hal) > 3 ? ' +' . (count($hal) - 3) : '');
            $lokasi($hal[0], $rt);
        }
        return $temuan ? [$gagal, "{$err}: " . $this->rinci($temuan)] : ['OK', "{$ok} ({$diperiksa} halaman " . ($mauGanjil ? 'ganjil' : 'genap') . ')'];
    }

    /**
     * Halaman awal bab: [nomor bab => indeks halaman], dari judul "BAB n" di pita atas halaman (data tepi), atau
     * awalBab() (baris atas teks halaman) bila data tepi tidak memuatnya.
     */
    private function babMulai(): array
    {
        $hasil = [];
        foreach ($this->tepi as $i => $pg) {
            if (!$pg) continue;
            $baris = [];
            foreach ($pg['it'] as $it) if ($it['y'] < $pg['h'] * self::PITA_ATAS) $baris[(int) round($it['y'] / 3)][] = $it;
            foreach ($baris as $b) {
                usort($b, fn($a, $c) => $a['x'] <=> $c['x']);
                if (!preg_match('/^(?:BAB|CHAPTER)\s+(\d{1,2}|[IVXLC]{1,6})$/u', trim(implode(' ', array_column($b, 't'))), $m)) continue;
                $n = ctype_digit($m[1]) ? (int) $m[1] : $this->romawiKeAngka(strtolower($m[1]));
                if ($n > 0) $hasil[$n] ??= $i;
            }
        }
        if (!$hasil) foreach ($this->awalBab() as $b) $hasil[$b['nomor']] ??= $b['hal'] - 1;
        ksort($hasil);
        return $hasil;
    }

    /**
     * Bagian buku untuk running title halaman ganjil, menurut letaknya di dokumen (bukan nomor di Daftar Isi, yang
     * bisa salah): tiap bab (judul dari entri Daftar Isi "BAB n …", atau baris setelah "BAB n" di halamannya) dan
     * bagian penutup (Daftar Pustaka, Glosarium, Indeks, Lampiran).
     * @return array<int, array{idx:int, judul:string, kata:array}> urut indeks halaman
     */
    private function judulBab(): array
    {
        $judulToc = [];
        if ($r = $this->halamanKhusus('daftar_isi')) {
            foreach ($this->entriDaftar(implode("\n", array_slice($this->pageTexts, $r[0], $r[1]))) as $e) {
                if (!preg_match('/^(?:BAB|CHAPTER)\s+(\d{1,2}|[IVXLC]{1,6})\b[\s.:]*(.+)$/iu', $e['judul'], $m)) continue;
                $judulToc[ctype_digit($m[1]) ? (int) $m[1] : $this->romawiKeAngka(strtolower($m[1]))] = trim($m[2]);
            }
        }
        $bagian = [];
        foreach ($this->babMulai() as $no => $i) {
            $judul = $judulToc[$no] ?? '';
            if ($judul === '') {
                // baris setelah "BAB n" di halaman awal bab
                $baris = array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->pageTexts[$i] ?? ''))));
                foreach ($baris as $k => $l) {
                    if (preg_match('/^(?:BAB|CHAPTER)\s+(?:\d{1,2}|[IVXLC]{1,6})$/u', $l) && isset($baris[$k + 1])) { $judul = $baris[$k + 1]; break; }
                }
            }
            if ($judul !== '') $bagian[] = ['idx' => $i, 'judul' => $judul, 'kata' => $this->kataKunci($judul)];
        }
        foreach (['daftar_pustaka', 'glosarium', 'indeks', 'lampiran'] as $nama) {
            if ($r = $this->halamanKhusus($nama)) {
                $bagian[] = ['idx' => $r[0], 'judul' => self::HALAMAN_KHUSUS[$nama], 'kata' => $this->kataKunci(self::HALAMAN_KHUSUS[$nama])];
            }
        }
        usort($bagian, fn($a, $b) => $a['idx'] <=> $b['idx']);
        return $bagian;
    }

    /** Bagian buku yang memuat halaman berindeks $i. */
    private function babUntuk(array $bab, int $i): ?array
    {
        $kini = null;
        foreach ($bab as $b) if ($b['idx'] <= $i) $kini = $b;
        return $kini;
    }

    /**
     * Entri daftar (isi/tabel/gambar): baris "Judul …… 12" atau "Judul ……" lalu baris "12". Baris tanpa nomor
     * sebelum entri (judul yang terpotong ke baris berikutnya, "BAB I" di atas judul bab) digabung ke judul entri.
     * @return array<int, array{judul:string, nomor:string, jenis:string}>
     */
    private function entriDaftar(string $teks): array
    {
        $entri = [];
        $buf = [];
        $pengarah = '(?:(?:\.\s?){3,}|…{2,}|·{3,}|_{3,})';
        foreach (preg_split('/\R/u', $teks) as $baris) {
            $l = trim(preg_replace('/\s+/u', ' ', $this->bersihkanGanda($baris)));
            if ($l === '') continue;
            if (preg_match('/^(?:DAFTAR\s+(?:ISI|TABEL|GAMBAR|LAMPIRAN)|(?:TABLE\s+OF\s+)?CONTENTS|LIST\s+OF\s+(?:TABLES|FIGURES|APPENDI\w+)|ISSN\b|Halaman|Page)\b/iu', $l)
                && !preg_match('/' . $pengarah . '\s*(?:[ivxlcdm]{1,7}|\d{1,4})$/iu', $l)) {
                $buf = [];
                continue;
            }
            if (preg_match('/^(.*?)\s*' . $pengarah . '\s*([ivxlcdm]{1,7}|\d{1,4})$/iu', $l, $m)) {
                // baris yang diawali "BAB n" / "3.1" adalah entri utuh: baris tanpa nomor sebelumnya bukan judulnya
                $awalEntri = preg_match('/^(?:BAB|CHAPTER)\s+(?:\d{1,2}|[IVXLC]{1,6})\b|^\d+(?:\.\d+)+\s/u', $l);
                $judul = trim(($awalEntri ? '' : implode(' ', $buf)) . ' ' . $m[1]);
                $nomor = $m[2];
            } elseif ($buf && preg_match('/^' . $pengarah . '?\s*([ivxlcdm]{1,7}|\d{1,4})$/iu', $l, $m) && preg_match('/' . $pengarah . '\s*$/u', end($buf))) {
                $judul = implode(' ', $buf);
                $nomor = $m[1];
            } else {
                // baris awal entri ("BAB III", "3.1 …") memulai entri baru: baris sebelumnya (mis. kepala halaman
                // lanjutan daftar isi berisi judul buku) bukan bagian judulnya
                if (preg_match('/^(?:BAB|CHAPTER)\s+(?:\d{1,2}|[IVXLC]{1,6})\b|^\d+(?:\.\d+)+\s/u', $l)) $buf = [];
                $buf[] = $l;
                if (count($buf) > 3) array_shift($buf);
                continue;
            }
            $buf = [];
            $judul = trim(preg_replace('/[\s.…·_]+$/u', '', $judul));
            if (mb_strlen($judul) < 2) continue;
            $entri[] = ['judul' => $judul, 'nomor' => $nomor, 'jenis' => ctype_digit($nomor) ? 'arab' : 'romawi'];
        }
        return $entri;
    }

    /**
     * Entri Daftar Tabel/Gambar per blok: blok dimulai baris "Tabel 2.1 …" / "2.1 …" dan berakhir sebelum entri berikutnya.
     * Nomor halaman = angka setelah pengarah titik, atau angka di baris sendiri di akhir blok (bukan tahun 19xx/20xx).
     * @return array<int, array{nomor_item:string, judul:string, nomor:?string}> nomor null = nomor halaman tidak dicantumkan
     */
    private function entriDaftarBernomor(string $teks, string $jenis): array
    {
        $kata = $jenis === 'tabel' ? 'Tabel|Table' : 'Gambar|Figure|Grafik';
        $blok = [];
        foreach (preg_split('/\R/u', $teks) as $baris) {
            $l = trim(preg_replace('/\s+/u', ' ', $this->bersihkanGanda($baris)));
            if ($l === '' || preg_match('/^(?:DAFTAR\s+(?:TABEL|GAMBAR)|LIST\s+OF\s+(?:TABLES|FIGURES)|Halaman|Page)\b/iu', $l)) continue;
            if (preg_match('/^(?:(?:' . $kata . ')\s+)?(\d+(?:\.\d+)+|(?:' . $kata . ')\s+\d+)(?![\d]|\.\d)\.?\s*(.*)$/iu', $l, $m)) {
                $no = preg_replace('/^\D+/', '', $m[1]);
                $blok[] = ['nomor_item' => $no, 'baris' => $m[2] !== '' ? [$m[2]] : []];
            } elseif ($blok) {
                $blok[array_key_last($blok)]['baris'][] = $l;
            }
        }
        $hasil = [];
        foreach ($blok as $b) {
            $baris = $b['baris'];
            $nomor = null;
            $akhir = end($baris) ?: '';
            if (preg_match('/^(?:(?:\.\s?){3,}|…{2,})?\s*(\d{1,4}|[ivxlcdm]{1,7})$/iu', $akhir, $m) && !preg_match('/^(?:19|20)\d{2}$/', $m[1])) {
                $nomor = $m[1];
                array_pop($baris);
            } elseif (preg_match('/(?:(?:\.\s?){3,}|…{2,})\s*(\d{1,4}|[ivxlcdm]{1,7})$/iu', $akhir, $m)) {
                $nomor = $m[1];
            }
            $judul = trim(preg_replace(['/(?:(?:\.\s?){3,}|…{2,})\s*(?:\d{1,4}|[ivxlcdm]{1,7})?$/iu', '/\s+/u'], ['', ' '], implode(' ', $baris)));
            $hasil[] = ['nomor_item' => $b['nomor_item'], 'judul' => $judul, 'nomor' => $nomor];
        }
        return $hasil;
    }

    /** Teks halaman tanpa baris running title (judul bab di running title halaman lain tidak boleh dianggap judul). */
    private function teksIsi(int $i): string
    {
        $t  = $this->pageTexts[$i] ?? '';
        $nh = $this->nomorHalaman()[$i] ?? null;
        if (!$nh || $nh['rt'] === '' || mb_substr_count($t, $nh['rt']) < 2) return $t;
        // hanya satu kemunculan yang dibuang (footer: yang terakhir, header: yang pertama) — judul halaman
        // yang kebetulan sama dengan running title-nya (mis. "DAFTAR PUSTAKA") tetap ada
        $diBawah = $nh['y'] > ($this->tepi[$i]['h'] ?? $this->pageH) / 2;
        $pos = $diBawah ? mb_strrpos($t, $nh['rt']) : mb_strpos($t, $nh['rt']);
        return $pos === false ? $t : mb_substr($t, 0, $pos) . ' ' . mb_substr($t, $pos + mb_strlen($nh['rt']));
    }

    /** Judul (dan nomor bab/subbab-nya, bila ada) tercantum di halaman $i. */
    private function memuatJudul(int $i, string $judul): bool
    {
        $isi = $this->teksIsi($i);
        $padat = fn($s) => preg_replace('/[^\p{L}\d]+/u', '', mb_strtoupper($this->bersihkanGanda($s)));
        if (mb_strlen($padat($judul)) >= 6 && str_contains($padat($isi), $padat($judul))) return true;
        if (preg_match('/^((?:\d+\.)+\d+|\d+)\s/u', $judul, $no) && !preg_match('/(?<![\d.])' . preg_quote($no[1], '/') . '(?![\d])/u', $isi)) return false;
        $kata = array_slice($this->kataKunci($judul), 0, 8);
        return count($kata) >= 1 && $this->porsiKata($kata, $this->kataKunci($isi)) >= 0.85;
    }

    /** Judul tabel/gambar bernomor $nomor di halaman $i ("Tabel 2.1 …" + 2 baris berikutnya), atau null. */
    private function captionDi(int $i, string $jenis, string $nomor): ?string
    {
        $kata = $jenis === 'tabel' ? 'Tabel|Table' : 'Gambar|Figure|Grafik';
        $baris = array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->teksIsi($i)))));
        foreach ($baris as $k => $l) {
            if (preg_match('/^(?:' . $kata . ')\s+' . preg_quote($nomor, '/') . '(?![\d]|\.\d)\.?\s*(.*)$/u', $l, $m)
                && !preg_match('/(?:\.\s?){4,}/u', $l)) {
                return trim($m[1] . ' ' . ($baris[$k + 1] ?? '') . ' ' . ($baris[$k + 2] ?? ''));
            }
        }
        return null;
    }

    /**
     * daftar_halaman (daftar: isi | tabel | gambar): setiap entri daftar ada di halaman yang disebutnya.
     * Tabel/gambar juga: judulnya sama dengan judul di halaman isi, dan tabel/gambar di isi tercantum di daftarnya.
     * Entri yang tidak ditemukan di mana pun (mis. halaman berupa gambar) → PERLU DICEK, bukan langsung tidak sesuai.
     */
    private function evalDaftarHalaman(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        if (!$this->tepi) return ['TIDAK DIPERIKSA', 'Data nomor halaman tidak tersedia (periksa ulang dengan halaman yang sudah dimuat ulang)'];
        $jenis = in_array($p['daftar'] ?? 'isi', ['isi', 'tabel', 'gambar'], true) ? $p['daftar'] : 'isi';
        $entri = $jenis === 'isi' ? $this->entriDaftar($text) : $this->entriDaftarBernomor($text, $jenis);
        if (count($entri) < 2) return ['TIDAK DIPERIKSA', 'Entri daftar tidak terbaca, cek manual'];
        if ($this->selisihHalaman('arab') === null) return ['TIDAK DIPERIKSA', 'Nomor halaman isi tidak terbaca, tidak bisa dicocokkan'];

        // halaman daftar (isi/tabel/gambar/lampiran) tidak ikut dicari
        $lewati = [];
        foreach (['daftar_isi', 'daftar_tabel', 'daftar_gambar', 'daftar_lampiran'] as $d) {
            if ($r = $this->halamanKhusus($d)) $lewati += array_fill_keys(range($r[0], $r[0] + $r[1] - 1), true);
        }
        $n = count($this->pageTexts);
        $temuan = [];
        $hilang = [];
        $cocok = 0;
        $nomorDaftar = [];
        $kataItem = $jenis === 'tabel' ? 'Tabel' : 'Gambar';
        // indeks judul tabel/gambar di isi (nomor → halaman pertama), dibuat sekali: publikasi tebal ("Dalam Angka"
        // ±900 halaman, ratusan tabel) kehabisan waktu bila tiap entri memindai ulang semua halaman
        $indeks = [];
        if ($jenis !== 'isi') {
            $pola = $jenis === 'tabel' ? 'Tabel|Table' : 'Gambar|Figure|Grafik';
            $mulai = $lewati ? max(array_keys($lewati)) + 1 : 1;
            for ($j = $mulai; $j < $n; $j++) {
                if (!preg_match_all('/^\s*(?:' . $pola . ')\s+(\d+(?:\.\d+)*)(?![\d]|\.\d)\.?\s+[\p{Lu}\d]/mu', $this->teksIsi($j), $mm)) continue;
                foreach ($mm[1] as $no) $indeks[$no] ??= $j;
            }
        }
        // awal bab yang dikenali ("BAB n" di bagian atas halaman): nomor bab → indeks halaman
        $awalBab = $this->babMulai();

        foreach ($entri as $e) {
            if ($jenis !== 'isi') {
                $nomorDaftar[$e['nomor_item']] = true;
                if ($e['nomor'] === null) {
                    $temuan[] = "{$kataItem} {$e['nomor_item']}: nomor halaman tidak dicantumkan di daftar";
                    continue;
                }
            }
            $i = $this->indeksDariNomor($e['nomor']);
            if ($i === null) continue; // nomor romawi tanpa nomor romawi terbaca, dsb.
            $label = mb_strimwidth($e['judul'], 0, 45, '…');

            if ($jenis === 'isi') {
                // entri bab: dicocokkan dengan halaman awal bab ("BAB n" di bagian atas halaman)
                if (preg_match('/^(?:BAB|CHAPTER)\s+(\d{1,2}|[IVXLC]{1,6})\b/iu', $e['judul'], $bm)) {
                    $nb = ctype_digit($bm[1]) ? (int) $bm[1] : $this->romawiKeAngka(strtolower($bm[1]));
                    if (isset($awalBab[$nb])) {
                        if ($awalBab[$nb] === $i) { $cocok++; continue; }
                        $temuan[] = "\"{$label}\" tertulis hal. {$e['nomor']}, ada di " . $this->labelHal($awalBab[$nb]);
                        if (count($this->lokasi) < self::MAX_LOKASI) $this->lokasi[] = ['hal' => $awalBab[$nb] + 1, 'teks' => $bm[0]];
                        continue;
                    }
                }
                if ($this->memuatJudul($i, $e['judul'])) { $cocok++; continue; }
                $alt = null;
                foreach ([1, -1, 2, -2, 3, -3, 4, -4] as $d) {
                    $j = $i + $d;
                    if ($j > 0 && $j < $n && !isset($lewati[$j]) && $this->memuatJudul($j, $e['judul'])) { $alt = $j; break; }
                }
                if ($alt !== null) {
                    $temuan[] = "\"{$label}\" tertulis hal. {$e['nomor']}, ada di " . $this->labelHal($alt);
                    if (count($this->lokasi) < self::MAX_LOKASI) $this->lokasi[] = ['hal' => $alt + 1, 'teks' => mb_substr($e['judul'], 0, 80)];
                } else {
                    $hilang[] = "\"{$label}\" tidak ditemukan di hal. {$e['nomor']}";
                }
                continue;
            }

            // tabel / gambar
            [$nomor, $judul, $kata] = [$e['nomor_item'], $e['judul'], $kataItem];
            $cap = $this->captionDi($i, $jenis, $nomor);
            if ($cap === null) {
                $ada = $indeks[$nomor] ?? null; // satu indeks untuk seluruh isi (lihat di atas), bukan pindai ulang per entri
                if ($ada !== null && $ada !== $i) {
                    $temuan[] = "{$kata} {$nomor} tertulis hal. {$e['nomor']}, ada di " . $this->labelHal($ada);
                    if (count($this->lokasi) < self::MAX_LOKASI) $this->lokasi[] = ['hal' => $ada + 1, 'teks' => "{$kata} {$nomor}"];
                } else {
                    $hilang[] = "{$kata} {$nomor} tidak ditemukan di isi";
                }
                continue;
            }
            if ($this->porsiKata(array_slice($this->kataKunci($judul), 0, 10), $this->kataKunci($cap)) < 0.75) {
                $temuan[] = "judul {$kata} {$nomor} beda: daftar \"" . mb_strimwidth($judul, 0, 40, '…') . '", isi "' . mb_strimwidth($cap, 0, 40, '…') . '"';
                if (count($this->lokasi) < self::MAX_LOKASI) $this->lokasi[] = ['hal' => $i + 1, 'teks' => "{$kata} {$nomor}"];
                continue;
            }
            $cocok++;
        }

        // tabel/gambar di isi yang tidak tercantum di daftarnya
        if ($jenis !== 'isi' && $nomorDaftar) {
            foreach ($indeks as $nomor => $j) {
                if (!isset($nomorDaftar[$nomor])) $temuan[] = "{$kataItem} {$nomor} di " . $this->labelHal($j) . ' tidak ada di daftar';
            }
        }

        if (!$temuan && !$hilang) return ['OK', "{$ok} ({$cocok} entri cocok)"];
        if (!$temuan) return ['PERLU DICEK', 'Sebagian entri tidak ditemukan otomatis (judul mungkin berupa gambar / terbaca beda): ' . $this->rinci($hilang)];
        return [$gagal, "{$err}: " . $this->rinci(array_merge($temuan, $hilang))];
    }

    /**
     * font_bps (halaman: kover | judul | belakang): nama BPS penerbit ("BADAN PUSAT STATISTIK" + baris wilayahnya)
     * ditulis Arial Bold Italic, huruf kapital semua.
     */
    private function evalFontBps(array $p, string $gagal, string $ok, string $err): array
    {
        if (!$this->fontHal) return ['TIDAK DIPERIKSA', 'Data huruf tidak tersedia (periksa ulang dengan halaman yang sudah dimuat ulang)'];
        $namaBps = '/BADAN\s+PUSAT\s+STATISTIK|BPS\s*[-–]?\s*STATISTICS/iu';
        $hal = match ($p['halaman'] ?? 'kover') {
            'belakang' => count($this->pageTexts),
            'judul'    => (function () use ($namaBps) {
                // halaman judul utama: halaman 2–5 pertama (bukan katalog) yang memuat nama BPS sebagai teks
                $katalog = ($this->halamanKhusus('katalog')[0] ?? -1) + 1;
                foreach ($this->fontHal as $h => $its) {
                    if ($h >= 2 && $h <= 5 && $h !== $katalog && preg_match($namaBps, implode(' ', array_column($its, 't')))) return $h;
                }
                return null;
            })(),
            default    => 1,
        };
        $label = ['kover' => 'kover depan', 'judul' => 'halaman judul utama', 'belakang' => 'kover belakang'][$p['halaman'] ?? 'kover'] ?? 'halaman';
        if (!$hal || empty($this->fontHal[$hal])) return ['TIDAK DIPERIKSA', "Nama BPS penerbit di {$label} tidak terbaca sebagai teks (mungkin gambar/kurva), cek manual"];

        // kelompokkan per baris (y), cari baris nama BPS + baris wilayah tepat di bawahnya (≤ 2 baris, ≤ 40 pt)
        $its = $this->fontHal[$hal];
        usort($its, fn($a, $b) => [$a['y'], $a['x']] <=> [$b['y'], $b['x']]);
        $baris = [];
        foreach ($its as $it) {
            $k = array_key_last($baris);
            if ($k !== null && abs($baris[$k][0]['y'] - $it['y']) <= 2) $baris[$k][] = $it; else $baris[] = [$it];
        }
        $pilih = [];
        foreach ($baris as $k => $b) {
            if (!preg_match($namaBps, implode(' ', array_column($b, 't')))) continue;
            $pilih = array_merge($pilih, $b);
            for ($d = 1; $d <= 2 && isset($baris[$k + $d]); $d++) {
                $bawah = $baris[$k + $d];
                if ($bawah[0]['y'] - $b[0]['y'] > 40 * $d) break;
                if (preg_match('/\b(?:PROVINSI|KABUPATEN|KOTA|PROVINCE|REGENCY|MUNICIPALITY)\b/iu', implode(' ', array_column($bawah, 't')))) $pilih = array_merge($pilih, $bawah);
            }
            break;
        }
        if (!$pilih) return ['TIDAK DIPERIKSA', "Nama BPS penerbit di {$label} tidak terbaca sebagai teks (mungkin gambar/kurva), cek manual"];

        $teks = trim(implode(' ', array_column($pilih, 't')));
        $this->lokasi[] = ['hal' => $hal, 'teks' => mb_substr($pilih[0]['t'], 0, 80)];
        $fontSalah = [];
        $kecil = [];
        foreach ($pilih as $it) {
            $f = mb_strtolower(preg_replace('/^[A-Z]{6}\+/', '', $it['f']));
            if ($f === '' || !(str_contains($f, 'arial') && str_contains($f, 'bold') && (str_contains($f, 'italic') || str_contains($f, 'oblique')))) {
                $fontSalah[preg_replace('/^[A-Z]{6}\+/', '', $it['f']) ?: '(tidak diketahui)'][] = $it['t'];
            }
            if (mb_strtoupper($it['t']) !== $it['t']) $kecil[] = $it['t'];
        }
        $temuan = [];
        foreach ($fontSalah as $f => $t) $temuan[] = "huruf {$f} pada \"" . mb_strimwidth(implode(' ', $t), 0, 40, '…') . '"';
        if ($kecil) $temuan[] = 'belum kapital semua: "' . mb_strimwidth(implode(' ', $kecil), 0, 50, '…') . '"';
        $contoh = mb_strimwidth($teks, 0, 50, '…');
        return $temuan ? [$gagal, "{$err} di {$label}: " . implode('; ', $temuan)] : ['OK', "{$ok} (\"{$contoh}\")"];
    }

    /** Tanggal rilis publikasi (Y-m-d) dari API BPS — dipakai cek bulan penandatanganan. */
    public function setTanggalRilis(?string $tanggal): void
    {
        $ts = $tanggal ? strtotime($tanggal) : false;
        $this->tanggalRilis = $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * Isian publikasi pembanding: ['issn' => ..., 'katalog' => ..., 'nomor_publikasi' => ...]; null = tidak ada.
     * $sumber: asal data, ditulis di catatan hasil cek (Web API BPS untuk Import API, SIPOTRET untuk draf PJK).
     */
    public function setDataApi(?array $data, string $sumber = 'Web API BPS'): void
    {
        $this->dataApi   = $data;
        $this->sumberApi = $sumber;
    }

    /** Label & pola nilai per isian yang dibandingkan dengan Web API (nilai = grup 1). */
    private const ISIAN_API = [
        'issn' => [
            'label' => 'ISSN/ISBN',
            'pola'  => '/\b(?:[pe]-)?(?:ISSN|ISBN)\b(?:\s*\/\s*(?:ISSN|ISBN)\b)?\s*:?\s*((?:97[89][\-\x{2013}\h]?)?\d[\dXx\-\x{2013}\h]{6,16}[\dXx])/u',
        ],
        'katalog' => [
            'label' => 'Nomor Katalog',
            'pola'  => '/\b(?:Katalog|Catalogue)(?:\s*\/\s*Catalogue)?(?:\s+BPS)?\s*:?\s*(\d[\d.]{3,20}\d)/iu',
        ],
        'nomor_publikasi' => [
            'label' => 'Nomor Publikasi',
            'pola'  => '/\bNomor\s+Publikasi(?:\s*\/\s*Publication\s+Number)?\s*:?\s*(\d[\d.\-\/]{3,30}\d)/iu',
        ],
    ];

    /**
     * Isian ISSN / nomor katalog / nomor publikasi di halaman depan sama dengan Web API BPS.
     * Dibandingkan tanpa spasi, titik & tanda hubung; setiap kemunculan dicek (kover, katalog, tim penyusun, …).
     */
    private function evalSamaApi(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        $isian = self::ISIAN_API[$p['isian'] ?? ''] ?? null;
        if (!$isian) return ['TIDAK DIPERIKSA', 'Parameter isian harus issn, katalog, atau nomor_publikasi'];
        if ($this->dataApi === null) {
            return ['TIDAK DIPERIKSA', 'Data pembanding tidak tersedia (hanya untuk pemeriksaan lewat Import API BPS atau draf SIPOTRET)'];
        }
        $sumber  = $this->sumberApi;
        $singkat = $sumber === 'Web API BPS' ? 'Web API' : $sumber;
        // pesan kriteria ditulis untuk Web API BPS; untuk draf SIPOTRET sumbernya disesuaikan
        [$ok, $err] = array_map(fn($m) => str_replace(['Web API BPS', 'Web API'], [$sumber, $singkat], $m), [$ok, $err]);
        $api = trim((string) ($this->dataApi[$p['isian']] ?? ''));
        if ($api === '' || $api === '-') {
            return ['TIDAK DIPERIKSA', "{$isian['label']} tidak tercantum di {$sumber}"];
        }
        $norm = fn(string $v) => strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $v));

        if (!preg_match_all($isian['pola'], $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return ['PERLU DICEK', "{$isian['label']} tidak ditemukan di halaman depan ({$singkat}: {$api}), cek manual"];
        }
        $beda = [];
        foreach ($m as $x) {
            $nilai = trim(preg_replace('/\h+/u', ' ', $x[1][0]));
            $this->addLokasi($x[0][1], $x[0][0]);
            if ($norm($nilai) !== $norm($api)) $beda[] = $nilai;
        }
        $n = count($m);
        if (!$beda) return ['OK', "{$ok} ({$api}, {$n} kali ditemukan)"];
        $beda = array_values(array_unique($beda));
        return [$gagal, sprintf('%s (%s: %s; di publikasi: %s)', $err, $singkat, $api, implode(', ', array_slice($beda, 0, 4)))];
    }

    /**
     * rata_angka: angka dalam tabel rata kanan & blok angkanya di tengah kolom. Diukur di browser dari posisi teks
     * (PdfExtract ukurTabel), memakai baris nomor kolom "(1) (2) …" sebagai pusat kolom. Halaman hasil OCR tidak diukur.
     */
    private function evalRataAngka(string $gagal, string $ok, string $err): array
    {
        if ($this->tabelAngka === null) {
            return ['TIDAK DIPERIKSA', 'Posisi angka tabel hanya diukur saat PDF dibaca di browser, cek manual'];
        }
        if (!$this->tabelAngka) {
            return ['TIDAK DIPERIKSA', 'Tidak ada tabel dengan baris nomor kolom (1), (2), … yang terbaca di lapisan teks PDF, cek manual'];
        }

        $kolom = count($this->tabelAngka);
        $hal   = count(array_unique(array_column($this->tabelAngka, 'hal')));
        $salah = array_values(array_filter($this->tabelAngka, fn($t) => $t['rata'] !== 'kanan' || !$t['tengah']));
        if (!$salah) {
            return ['OK', "{$ok} ({$kolom} kolom angka di {$hal} halaman tabel diukur)"];
        }

        $label = ['kiri' => 'rata kiri', 'tengah' => 'rata tengah', 'tidak' => 'tidak rata'];
        $uraian = function (array $t) use ($label): string {
            $x = [];
            if ($t['rata'] !== 'kanan') $x[] = $label[$t['rata']];
            if (!$t['tengah']) $x[] = sprintf('bergeser %s pt ke %s', abs($t['geser']), $t['geser'] > 0 ? 'kanan' : 'kiri');
            return "hal. {$t['hal']} kolom ({$t['kol']}) " . implode(', ', $x);
        };
        foreach (array_slice($salah, 0, self::MAX_LOKASI) as $t) {
            $this->lokasi[] = ['hal' => $t['hal'], 'teks' => $t['contoh']];
        }
        $halSalah = array_values(array_unique(array_column($salah, 'hal')));
        $contoh = implode('; ', array_map($uraian, array_slice($salah, 0, 4)));
        return [$gagal, sprintf('%s: %d dari %d kolom angka, di %d halaman (%s%s)', $err, count($salah), $kolom,
            count($halSalah), $contoh, count($salah) > 4 ? '; …' : '')];
    }

    /** Bulan penandatanganan (mis. "Banjarbaru, Februari 2026") paling lama satu bulan sebelum rilis. */
    private function evalBulanRilis(string $text, string $gagal, string $ok, string $err): array
    {
        if (!$this->tanggalRilis) {
            return ['TIDAK DIPERIKSA', 'Tanggal rilis tidak diketahui (hanya tersedia untuk pemeriksaan lewat Import API BPS)'];
        }
        $bulan = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7,
                  'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
                  'january' => 1, 'february' => 2, 'march' => 3, 'may' => 5, 'june' => 6, 'july' => 7,
                  'august' => 8, 'october' => 10, 'december' => 12];
        $pola = '/\b\p{Lu}[\p{L}.\- ]{2,30},\s*(?:\d{1,2}\s+)?(' . implode('|', array_keys($bulan)) . ')\s+((?:19|20)\d{2})\b/iu';
        if (!preg_match_all($pola, $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return ['TIDAK DIPERIKSA', 'Tempat & tanggal penandatanganan tidak ditemukan'];
        }
        $akhir = end($m); // tanda tangan biasanya di bagian akhir
        $this->addLokasi($akhir[0][1], $akhir[0][0]);

        $ttd   = (int) $akhir[2][0] * 12 + $bulan[mb_strtolower($akhir[1][0])];
        $rilis = (int) substr($this->tanggalRilis, 0, 4) * 12 + (int) substr($this->tanggalRilis, 5, 2);
        $beda  = $rilis - $ttd;
        $info  = sprintf('ditandatangani %s %s, rilis %s', ucfirst(mb_strtolower($akhir[1][0])), $akhir[2][0], date('m/Y', strtotime($this->tanggalRilis)));
        return $beda >= 0 && $beda <= 1 ? ['OK', "{$ok} ({$info})"] : [$gagal, "{$err} ({$info})"];
    }

    private function romawiKeAngka(string $r): int
    {
        $nilai = ['i' => 1, 'v' => 5, 'x' => 10, 'l' => 50, 'c' => 100, 'd' => 500, 'm' => 1000];
        $r = strtolower($r);
        $total = 0;
        for ($i = 0, $n = strlen($r); $i < $n; $i++) {
            $v = $nilai[$r[$i]] ?? 0;
            $berikut = $i + 1 < $n ? ($nilai[$r[$i + 1]] ?? 0) : 0;
            $total += $v < $berikut ? -$v : $v;
        }
        return $total;
    }

    private function evalContains(array $p, string $text, bool $invert, string $gagal, string $ok, string $err): array
    {
        $needle = $p['text'] ?? null;
        if ($needle === null) return ['TIDAK DIPERIKSA', 'Parameter text kosong'];
        $cs       = $p['case'] ?? false;
        $haystack = $cs ? $text   : mb_strtolower($text);
        $needle   = $cs ? $needle : mb_strtolower($needle);
        $at       = strpos($haystack, $needle);
        $found    = $at !== false;
        $pass     = $invert ? !$found : $found;
        // mb_strtolower bisa mengubah panjang byte huruf non-ASCII — posisi dihitung ulang dari teks asli
        if ($found) $this->addLokasi($cs ? $at : strlen(mb_substr($text, 0, mb_strlen(substr($haystack, 0, $at)))), $p['text']);
        return $pass ? ['OK', $ok] : [$gagal, $err];
    }

    private function evalPosisi(array $p, string $gagal, string $ok, string $err): array
    {
        $word = $p['word'] ?? null;
        $area = $p['area'] ?? null;
        if (!$word || !$area) return ['TIDAK DIPERIKSA', 'Parameter word/area kosong'];

        if (isset(self::HALAMAN_KHUSUS[$this->currentTarget])) {
            return $this->evalPosisiHalaman($word, $area, $gagal, $ok, $err);
        }

        $w = $this->findWord($word);
        if (!$w) return ['TIDAK DIPERIKSA', "Kata '$word' tidak ditemukan di kover"];
        $this->lokasi[] = ['hal' => 1, 'teks' => mb_substr($w['text'], 0, 80)];
        $pass = match($area) {
            'top'           => $this->isTopArea($w['y']),
            'top_right'     => $this->isTopRight($w['x'], $w['y']),
            'bottom'        => $this->isBottomArea($w['y']),
            'bottom_left'   => $this->isBottomLeft($w['x'], $w['y']),
            'below_katalog' => $this->isBelowKatalog($w),
            default         => false,
        };
        $detail = sprintf(' (x=%.0f, y=%.0f)', $w['x'], $w['y']);
        return $pass ? ['OK', $ok . $detail] : [$gagal, $err . $detail];
    }

    /**
     * Posisi kata di halaman khusus (mis. ISSN di pojok kanan atas halaman Tim Penyusun/Daftar Isi).
     * Ukuran halaman diambil dari kata itu sendiri — halaman awal tidak selalu seukuran kover.
     */
    private function evalPosisiHalaman(string $word, string $area, string $gagal, string $ok, string $err): array
    {
        [$idx]  = $this->halamanKhusus($this->currentTarget);
        $label  = self::HALAMAN_KHUSUS[$this->currentTarget];
        $w      = $this->findWord($word, $idx);
        if (!$w && preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $this->pageTexts[$idx] ?? '')) {
            return [$gagal, "Kata '$word' ada di halaman {$label} (hal. " . ($idx + 1) . "), tapi posisinya tidak terbaca, cek manual"];
        }
        if (!$w) {
            // ISSN di halaman ini hanya "jika ada": wajar kosong bila publikasi memang tanpa ISSN
            $adaDiTempatLain = preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $this->coverText . "\n" . $this->targetText('front'));
            return $adaDiTempatLain
                ? [$gagal, "Kata '$word' tidak ditemukan di halaman {$label} (hal. " . ($idx + 1) . "), padahal publikasi punya $word, cek apakah perlu dicantumkan"]
                : ['TIDAK DIPERIKSA', "Publikasi tanpa $word"];
        }
        $this->lokasi[] = ['hal' => $idx + 1, 'teks' => mb_substr($w['text'], 0, 80)];

        $pw = $w['pw'] ?: $this->pageW;
        $ph = $w['ph'] ?: $this->pageH;
        $pass = match ($area) {
            'top'         => $w['y'] < $ph * 0.30,
            'top_right'   => $w['x'] > $pw * 0.5 && $w['y'] < $ph * 0.30,
            'bottom'      => $w['y'] > $ph * 0.60,
            'bottom_left' => $w['x'] < $pw * 0.35 && $w['y'] > $ph * 0.60,
            default       => false,
        };
        $detail = sprintf(' (hal. %d, x=%.0f, y=%.0f)', $idx + 1, $w['x'], $w['y']);
        return $pass ? ['OK', $ok . $detail] : [$gagal, $err . $detail];
    }

    private function evalPages(array $p, string $gagal, string $ok, string $err): array
    {
        $min = (int)($p['min'] ?? 1);
        if ($this->pages < $min) {
            return ['TIDAK DIPERIKSA', "PDF hanya {$this->pages} halaman (butuh min. {$min})"];
        }
        if (isset($p['max_chars_page2'])) {
            // alamat website BPS (watermark unduhan, panjangnya beda tiap kab/kota; "https://", tanpa skema,
            // atau terpotong spasi) bukan isi halaman — dibuang sebelum dihitung
            $isi   = preg_replace('~(?:https?://\s*)?[\w-]+(?:\s[\w-]+)?\.bps\.go\.id\S*~iu', '', $this->page2Text) ?? $this->page2Text;
            $chars = mb_strlen(trim(preg_replace('/\s+/u', ' ', $isi)));
            $max   = (int)$p['max_chars_page2'];
            if ($chars > $max) {
                $this->currentTarget = 'page2';
                $this->addLokasi(0, trim($this->page2Text));
                return [$gagal, $err . " ({$chars} karakter, maks {$max})"];
            }
        }
        return ['OK', $ok];
    }

    // ═══════════════════════════════════════════════════════
    // INTERNAL — HELPERS
    // ═══════════════════════════════════════════════════════

    /** Halaman yang dibaca sebuah target: [indeks awal (0-based), jumlah halaman]. */
    private function targetRange(string $target): array
    {
        $n = count($this->pageTexts);
        return match($target) {
            'page2' => [1, 1],
            'katalog', 'tim_penyusun', 'daftar_isi', 'kata_pengantar', 'kata_sambutan', 'abstraksi',
            'daftar_tabel', 'daftar_gambar', 'daftar_lampiran',
            'daftar_pustaka', 'glosarium', 'indeks', 'lampiran' => $this->halamanKhusus($target) ?? [0, 0],
            'front' => [0, self::FRONT_PAGES],
            'last'  => [max(0, $n - 1), 1],
            'all'   => [0, $n],
            default => [0, 1],
        };
    }

    private function targetText(string $target): string
    {
        [$start, $count] = $this->targetRange($target);
        return implode("\n", array_slice($this->pageTexts, $start, $count));
    }

    /**
     * Catat lokasi temuan (nomor halaman + potongan teks) supaya petugas bisa langsung
     * melompat ke halaman tersebut dari panel tinjauan. $byteOffset = posisi di targetText().
     */
    private function addLokasi(int $byteOffset, string $match): void
    {
        if (count($this->lokasi) >= self::MAX_LOKASI) return;

        // Potongan teks untuk dicari di viewer: baris pertama yang berisi (pencarian per baris lebih andal)
        $line = '';
        foreach (preg_split('/\R/u', $match) as $l) {
            if (($l = trim(preg_replace('/\s+/u', ' ', $l))) !== '') { $line = $l; break; }
        }
        if (mb_strlen($line) < 2) return;

        [$start, $count] = $this->targetRange($this->currentTarget);
        $pos = 0;
        $hal = $start + 1;
        foreach (array_slice($this->pageTexts, $start, $count) as $i => $text) {
            $len = strlen($text) + 1; // + "\n" pemisah halaman
            if ($byteOffset < $pos + $len) { $hal = $start + $i + 1; break; }
            $pos += $len;
        }

        $this->lokasi[] = ['hal' => $hal, 'teks' => mb_substr($line, 0, 80)];
    }

    /**
     * Halaman khusus di bagian awal yang dikenali dari isinya (sering tanpa judul):
     * [indeks awal (0-based), jumlah halaman], atau null bila tidak ditemukan (mis. halaman berupa gambar).
     *   katalog        — memuat "Nomor Publikasi" + "Ukuran Buku"/"Jumlah Halaman"/"Katalog"
     *   tim_penyusun   — "Tim Penyusun"/"Pengarah"/"Penanggung Jawab", bukan halaman Katalog
     *   daftar_isi     — ≥ 3 baris berpengarah titik (….. 12) berisi bab/bagian, bukan daftar tabel/gambar;
     *                    halaman berikutnya yang serupa ikut dihitung (maks. 4)
     *   kata_pengantar — judul "Kata Pengantar"/"Preface"/"Foreword", bukan halaman daftar isi
     */
    private function halamanKhusus(string $nama): ?array
    {
        if (array_key_exists($nama, $this->halamanCache)) return $this->halamanCache[$nama];
        if (isset(self::JUDUL_BELAKANG[$nama])) return $this->halamanCache[$nama] = $this->halamanBelakang($nama);

        $awal = array_slice($this->pageTexts, 1, self::FRONT_PAGES - 1, true);
        $isKatalog = fn($t) => preg_match('/Nomor\s+Publikasi|Publication\s+Number/iu', $t)
            && preg_match('/Ukuran\s+Buku|Jumlah\s+Halaman|Book\s+Size|\bKatalog\b|\bCatalog/iu', $t);
        $isDaftarIsi = function ($t) {
            $pengarah = preg_match_all('/(?:\.\s?){6,}|…{3,}/u', $t);
            if ($pengarah < 3) return false;
            $daftarLain = preg_match_all('/^\s*(?:Tabel|Table|Gambar|Figure|Lampiran|Appendix)\s+\d/imu', $t);
            return $daftarLain < $pengarah / 2
                && preg_match('/\bBAB\b|\bCHAPTER\b|DAFTAR\s+ISI|KATA\s+PENGANTAR|PENDAHULUAN|CONTENTS/iu', $t);
        };

        // Daftar Tabel/Gambar/Lampiran: ≥ 2 baris berpengarah titik dan entri jenis ini paling banyak
        $isDaftar = function ($t, string $jenis) {
            if (preg_match_all('/(?:\.\s?){6,}|…{3,}/u', $t) < 2) return false;
            $hitung = [];
            foreach (self::ENTRI_DAFTAR as $j => $kata) {
                $hitung[$j] = preg_match_all('/^\s*(?:' . $kata . ')\s+\d/imu', $t);
            }
            return $hitung[$jenis] >= 2 && $hitung[$jenis] === max($hitung);
        };
        // halaman yang bukan daftar dan berjudul tertentu (Kata Sambutan, Kata Pengantar, Abstraksi)
        $berjudul = fn($t, string $judul) => !$isDaftarIsi($t) && preg_match_all('/(?:\.\s?){6,}/u', $t) < 2
            && preg_match($judul, $t) && mb_strlen(trim($t)) > 200;

        // daftar tabel/gambar/lampiran bisa panjang, jadi dicari sedikit lebih jauh
        if (isset(self::ENTRI_DAFTAR[$nama])) {
            $awal = array_slice($this->pageTexts, 1, self::FRONT_PAGES + 9, true);
        }

        $hasil = null;
        foreach ($awal as $i => $t) {
            $cocok = match ($nama) {
                'katalog'         => $isKatalog($t),
                'tim_penyusun'    => !$isKatalog($t) && preg_match('/\bTim\s+Penyusun\b|\bPengarah\b|\bPenanggung\s*Jawab\b|\bEditorial\s+Team\b/iu', $t),
                'daftar_isi'      => $isDaftarIsi($t),
                'kata_pengantar'  => $berjudul($t, '/KATA\s+PENGANTAR|\bPREFACE\b|\bFOREWORD\b/iu'),
                'kata_sambutan'   => $berjudul($t, '/KATA\s+SAMBUTAN|\bSAMBUTAN\s+(?:KEPALA|GUBERNUR|BUPATI|WALI\s*KOTA|MENTERI)/iu'),
                'abstraksi'       => $berjudul($t, '/^\s*(?:ABSTRAK(?:SI)?|ABSTRACT)\b/imu'),
                'daftar_tabel', 'daftar_gambar', 'daftar_lampiran' => $isDaftar($t, $nama),
                default           => false,
            };
            if (!$cocok) continue;

            $jumlah = 1;
            if ($nama === 'daftar_isi') {
                while ($jumlah < 4 && isset($this->pageTexts[$i + $jumlah]) && $isDaftarIsi($this->pageTexts[$i + $jumlah])) $jumlah++;
            } elseif (isset(self::ENTRI_DAFTAR[$nama])) {
                while ($jumlah < 8 && isset($this->pageTexts[$i + $jumlah]) && $isDaftar($this->pageTexts[$i + $jumlah], $nama)) $jumlah++;
            }
            $hasil = [$i, $jumlah];
            break;
        }
        return $this->halamanCache[$nama] = $hasil;
    }

    /**
     * Teks hasil ekstraksi kadang menggandakan kata/frasa yang di-outline/berlapis
     * ("STATISTIK DAERAHSTATISTIK DAERAH", "202620262026"). Kata/frasa (≥ 4 karakter) atau tahun yang
     * terulang berdempetan disatukan.
     */
    private function bersihkanGanda(string $t): string
    {
        return preg_replace('/(\p{L}[\p{L}\d ]{3,}?|\d{4})\1+/u', '$1', $t) ?? $t;
    }

    /**
     * Bagian belakang (Daftar Pustaka, Glosarium, Indeks, Lampiran): halaman yang salah satu baris atasnya
     * berupa judul bagian itu (bukan entri daftar isi berpengarah titik). Halaman sesudahnya ikut dihitung
     * sampai bertemu judul bagian lain atau halaman kosong (maks. 6).
     */
    private function halamanBelakang(string $nama): ?array
    {
        $lewati = [];
        foreach (['daftar_isi', 'daftar_tabel', 'daftar_gambar', 'daftar_lampiran'] as $d) {
            if ($r = $this->halamanKhusus($d)) $lewati = array_merge($lewati, range($r[0], $r[0] + $r[1] - 1));
        }
        $judulDi = function (int $i, string $pola) use ($lewati): bool {
            if ($i < 3 || in_array($i, $lewati, true)) return false;
            $baris = array_slice(array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->pageTexts[$i] ?? '')))), 0, 6);
            foreach ($baris as $b) {
                if (preg_match('/(?:\.\s?){5,}/u', $b)) continue;
                if (preg_match($pola, $this->bersihkanGanda($b))) return true;
            }
            return false;
        };

        $n = count($this->pageTexts);
        for ($i = 3; $i < $n; $i++) {
            if (!$judulDi($i, self::JUDUL_BELAKANG[$nama])) continue;
            $jumlah = 1;
            if ($nama !== 'lampiran') {
                while ($jumlah < 6 && $i + $jumlah < $n - 1 && trim($this->pageTexts[$i + $jumlah]) !== '') {
                    foreach (self::JUDUL_BELAKANG as $lain => $pola) {
                        if ($lain !== $nama && $judulDi($i + $jumlah, $pola)) break 2;
                    }
                    $jumlah++;
                }
            }
            return [$i, $jumlah];
        }
        return null;
    }

    /** Indeks halaman Tim Penyusun (dipakai pengecekan posisi kata). */
    private function timPenyusunIndex(): ?int
    {
        return $this->halamanKhusus('tim_penyusun')[0] ?? null;
    }

    /**
     * Kata yang dicari posisinya: di kover, atau di halaman target lain (posisi kata ISSN halaman awal
     * dikirim terpisah sebagai front_words). Mengembalikan kata + ukuran halamannya.
     */
    private function findWord(string $needle, ?int $pageIdx = null): ?array
    {
        $nl = strtolower($needle);
        $words = $pageIdx === null
            ? $this->coverWords
            : array_filter($this->frontWords, fn($w) => $w['hal'] === $pageIdx + 1);
        foreach ($words as $w) {
            if (str_contains(strtolower($w['text']), $nl)) {
                return $w + ['pw' => $this->pageW, 'ph' => $this->pageH];
            }
        }
        return null;
    }

    private function isTopArea(float $y, float $pct = 0.30): bool    { return $y < $this->pageH * $pct; }
    private function isTopRight(float $x, float $y): bool             { return $x > $this->pageW * 0.5 && $this->isTopArea($y); }
    private function isBottomArea(float $y, float $pct = 0.40): bool  { return $y > $this->pageH * (1 - $pct); }
    private function isBottomLeft(float $x, float $y): bool           { return $x < $this->pageW * 0.35 && $this->isBottomArea($y); }

    private function isBelowKatalog(array $issnWord): bool
    {
        $kw = $this->findWord('Katalog');
        return $kw ? $issnWord['y'] > $kw['y'] : false;
    }

    private function add(
        string $id, string $kategori, string $deskripsi, string $status, string $catatan = '',
        string $target = 'cover', ?string $area = null, array $lokasi = []
    ): void {
        $this->checks[] = compact('id', 'kategori', 'deskripsi', 'status', 'catatan', 'target', 'area', 'lokasi');
    }

    private function areaLabel(KriteriaPemeriksaan $rule): ?string
    {
        if ($rule->tipe_cek !== 'posisi_area') return null;
        $key = $rule->parameter['area'] ?? null;
        return $key ? (KriteriaPemeriksaan::areaOptions()[$key] ?? null) : null;
    }

    private function buildResult(string $filename, int $size): array
    {
        $ok   = count(array_filter($this->checks, fn($c) => $c['status'] === 'OK'));
        $warn = count(array_filter($this->checks, fn($c) => $c['status'] === 'PERLU DICEK'));
        $err  = count(array_filter($this->checks, fn($c) => $c['status'] === 'TIDAK ADA'));
        $skip = count(array_filter($this->checks, fn($c) => $c['status'] === 'TIDAK DIPERIKSA'));

        return [
            'filename'          => $filename,
            'ukuran_file'       => $size,
            'total_pages'       => $this->pages,
            'extraction_method' => $this->method,
            'error'             => $this->errorMsg,
            'summary' => [
                'ok'              => $ok,
                'perlu_dicek'     => $warn,
                'tidak_ada'       => $err,
                'tidak_diperiksa' => $skip,
                'status_akhir'    => HasilPemeriksaan::resolveStatus($err, $warn),
            ],
            'checks' => $this->checks,
        ];
    }

    private function reset(): void
    {
        $this->checks     = [];
        $this->pages      = 0;
        $this->method     = 'digital';
        $this->errorMsg   = '';
        $this->coverText  = '';
        $this->coverWords = [];
        $this->frontWords = [];
        $this->halamanCache = [];
        $this->page2Text  = '';
        $this->pageTexts  = [];
        $this->ocrPages   = [];
        $this->imagePages = [];
        $this->ocrTerlewat = 0;
        $this->tabelAngka = null;
        $this->tepi       = [];
        $this->fontHal    = [];
        $this->nomorCache = null;
        $this->pageW      = 595.0;
        $this->pageH      = 842.0;
    }
}