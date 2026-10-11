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
        $this->method     = in_array($x['method'] ?? '', ['pdfjs', 'pdfjs+ocr'], true) ? $x['method'] : 'pdfjs';

        $this->runAllRules();
        return $this->buildResult($filename, $fileSize);
    }

    /**
     * Ambil baris OCR yang valid dari hasil ekstraksi browser ({ halaman: [{s,x,y,w,h}] }),
     * untuk disimpan dan disisipkan kembali ke viewer saat tinjauan dilanjutkan.
     */
    public static function ocrLinesFrom(?array $extracted): ?array
    {
        $out = [];
        foreach (array_slice((array) ($extracted['ocr_lines'] ?? []), 0, 50, true) as $page => $lines) {
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
                    $this->errorMsg = 'PDF terenkripsi — GS tidak tersedia.';
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
                    $this->errorMsg = 'PDF terenkripsi — GS tidak tersedia.';
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
        // Watermark unduhan website BPS ("https:// kalsel.bps.go.id") ada di tiap halaman — bukan isi publikasi
        $text = preg_replace('~https?://\s+[\w.-]+\.bps\.go\.id/?~iu', '', $text) ?? $text;

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

    private function decryptWithGs(string $inputPath): ?string
    {
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
        return trim(shell_exec('which gs 2>/dev/null') ?? '') ?: 'gs';
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
                $rule->target, $this->areaLabel($rule), $this->lokasi);
        }
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

        if (array_intersect($pages, $this->ocrPages)) {
            return ' [teks hasil OCR — cek ulang]';
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
            return ['TIDAK DIPERIKSA', 'Halaman ' . self::HALAMAN_KHUSUS[$rule->target] . ' tidak dikenali otomatis (mungkin berupa gambar) — cek manual'];
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
            'manual'       => ['PERLU DICEK', $msgErr],
            default        => ['TIDAK DIPERIKSA', 'Tipe cek tidak dikenal: ' . $rule->tipe_cek],
        };
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
            $detail = trim($hit) !== '' ? ' — ditemukan: ' . mb_substr(trim($hit), 0, 80) : '';
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
        return [$gagal, sprintf('%s — %d temuan, contoh: "%s"', $err, $count, mb_substr($sample, 0, 80))];
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
        return [$gagal, "{$err} — seharusnya {$diharapkan}, di halaman: " . implode(' → ', array_column($nyata, 'nama'))];
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
            : [$gagal, "{$err} — di halaman ini: {$nilai}, di kover: {$kover}"];
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

        $catatan = "{$err} — {$label} terdeteksi mulai hal. {$hal} PDF ({$nyata})";
        // judul Daftar Isi/Kata Pengantar sering berupa gambar: halaman sebelumnya tanpa teks bisa jadi awalnya
        if (in_array($this->currentTarget, ['daftar_isi', 'kata_pengantar'], true)
            && $start > 0 && trim($this->pageTexts[$start - 1] ?? '') === '') {
            $catatan .= "; hal. {$start} tanpa teks (gambar/kosong) — bila itu awal {$label}, posisinya bisa jadi sudah benar";
        }
        return [$gagal, $catatan];
    }

    /** Jumlah halaman romawi di halaman Katalog (mis. "x+52 halaman") harus genap. */
    private function evalRomawiGenap(string $text, string $gagal, string $ok, string $err): array
    {
        if (!preg_match('/\b([ivxlcdm]+)\s*\+\s*(\d+)\s*(?:halaman|hlm\.?|pages?)\b/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
            return ['TIDAK DIPERIKSA', 'Format jumlah halaman (romawi + arab) tidak ditemukan'];
        }
        $romawi = $m[1][0];
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
            return [$gagal, "{$err} — format penomoran tidak konsisten (" . implode(' dan ', array_slice(array_unique($nomor), 0, 2)) . ')'];
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
                return [$gagal, "{$err} — {$nomor[$i - 1]} lalu {$nomor[$i]}"];
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
        if (!$bab) return ['TIDAK DIPERIKSA', 'Judul bab ("BAB n") tidak dikenali — cek manual'];

        if (($p['cek'] ?? 'ganjil') === 'berurutan') {
            foreach ($bab as $i => $b) {
                $harap = $i === 0 ? 1 : $bab[$i - 1]['nomor'] + 1;
                if ($b['nomor'] !== $harap) {
                    $this->lokasi[] = ['hal' => $b['hal'], 'teks' => $b['label']];
                    return [$gagal, "{$err} — " . ($i === 0 ? "bab pertama bernomor {$b['nomor']}" : "{$bab[$i - 1]['label']} lalu {$b['label']}")];
                }
            }
            return ['OK', "{$ok} (" . count($bab) . ' bab)'];
        }

        $genap = array_filter($bab, fn($b) => $b['hal'] % 2 === 0);
        foreach (array_slice($genap, 0, self::MAX_LOKASI) as $b) $this->lokasi[] = ['hal' => $b['hal'], 'teks' => $b['label']];
        if (!$genap) return ['OK', "{$ok} (" . count($bab) . ' bab)'];
        return [$gagal, "{$err} — " . implode(', ', array_map(fn($b) => "{$b['label']} (hal. {$b['hal']} PDF)", $genap))];
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
                return [$gagal, "{$err} — \"{$pendek($m[1][$i - 1][0])}\" lalu \"{$pendek($m[1][$i][0])}\""];
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
            '/BADAN\s+PUSAT\s+STATISTIK|BPS[-\s]+STATISTICS[^\n]*/iu',
            '/(?:Volume|Vol\.?|Edisi|Nomor|No\.)\s*\d+[^\n]{0,15}?(?:19|20)\d{2}/iu',
            '/(?:Katalog|Catalogue|IS[SB]N|Nomor\s+Publikasi|Publication\s+Number)[^\n]*/iu',
            '/https?:\/\/\S+|\S+\.go\.id\S*/iu',
        ], ' ', $this->bersihkanGanda($s));
        $kover = preg_replace('/(\p{Ll})(\p{Lu})/u', '$1 $2', $buang($this->coverText)); // "SelatanProvinsi" → "Selatan Provinsi"

        $tahun = preg_match('/(?<![\d.,\/-])((?:19|20)\d{2})/u', $kover, $y) ? $y[1] : null;
        $henti = ['DAN', 'YANG', 'DARI', 'UNTUK', 'THE', 'AND', 'FOR'];
        $kata  = array_values(array_unique(array_filter(
            preg_split('/[^\p{L}]+/u', mb_strtoupper($kover)),
            fn($w) => mb_strlen($w) >= 3 && !in_array($w, $henti, true)
        )));
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

    /** Tanggal rilis publikasi (Y-m-d) dari API BPS — dipakai cek bulan penandatanganan. */
    public function setTanggalRilis(?string $tanggal): void
    {
        $ts = $tanggal ? strtotime($tanggal) : false;
        $this->tanggalRilis = $ts ? date('Y-m-d', $ts) : null;
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
            return [$gagal, "Kata '$word' ada di halaman {$label} (hal. " . ($idx + 1) . "), tapi posisinya tidak terbaca — cek manual"];
        }
        if (!$w) {
            // ISSN di halaman ini hanya "jika ada": wajar kosong bila publikasi memang tanpa ISSN
            $adaDiTempatLain = preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $this->coverText . "\n" . $this->targetText('front'));
            return $adaDiTempatLain
                ? [$gagal, "Kata '$word' tidak ditemukan di halaman {$label} (hal. " . ($idx + 1) . "), padahal publikasi punya $word — cek apakah perlu dicantumkan"]
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
            $chars = mb_strlen(trim($this->page2Text));
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
        $this->pageW      = 595.0;
        $this->pageH      = 842.0;
    }
}