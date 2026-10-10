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
    /** Indeks halaman Tim Penyusun (cache): false = belum dicari, null = tidak ditemukan. */
    private int|null|false $timPenyusunIdx = false;
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
        if ($rule->target === 'tim_penyusun' && $this->timPenyusunIndex() === null && $rule->tipe_cek !== 'manual') {
            return ['TIDAK DIPERIKSA', 'Halaman Tim Penyusun tidak dikenali otomatis (tidak ada "Tim Penyusun"/"Pengarah"/"Penanggung Jawab") — cek manual'];
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
            'manual'       => ['PERLU DICEK', $msgErr],
            default        => ['TIDAK DIPERIKSA', 'Tipe cek tidak dikenal: ' . $rule->tipe_cek],
        };
    }

    private function evalRegex(array $p, string $text, string $gagal, string $ok, string $err): array
    {
        $pattern = $p['pattern'] ?? null;
        if (!$pattern) return ['TIDAK DIPERIKSA', 'Parameter pattern kosong'];
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

        if ($this->currentTarget === 'tim_penyusun') {
            return $this->evalPosisiTimPenyusun($word, $area, $gagal, $ok, $err);
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
     * Posisi kata di halaman Tim Penyusun (mis. ISSN di pojok kanan atas). Ukuran halaman diambil dari
     * kata itu sendiri — halaman awal tidak selalu seukuran kover.
     */
    private function evalPosisiTimPenyusun(string $word, string $area, string $gagal, string $ok, string $err): array
    {
        $idx = $this->timPenyusunIndex();
        $w   = $this->findWord($word, $idx);
        if (!$w && preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $this->pageTexts[$idx] ?? '')) {
            return [$gagal, "Kata '$word' ada di halaman Tim Penyusun (hal. " . ($idx + 1) . "), tapi posisinya tidak terbaca — cek manual"];
        }
        if (!$w) {
            // ISSN di halaman ini hanya "jika ada": wajar kosong bila publikasi memang tanpa ISSN
            $adaDiTempatLain = preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $this->coverText . "\n" . $this->targetText('front'));
            return $adaDiTempatLain
                ? [$gagal, "Kata '$word' tidak ditemukan di halaman Tim Penyusun (hal. " . ($idx + 1) . "), padahal publikasi punya $word — cek apakah perlu dicantumkan"]
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
            'tim_penyusun' => ($i = $this->timPenyusunIndex()) === null ? [0, 0] : [$i, 1],
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
     * Indeks (0-based) halaman Tim Penyusun di bagian awal: berisi "Tim Penyusun"/"Pengarah"/
     * "Penanggung Jawab" dan bukan halaman Katalog (yang juga memuat "Penyusun Naskah"/"Penyunting").
     * Halaman ini sering tidak berjudul, jadi dikenali dari isinya.
     */
    private function timPenyusunIndex(): ?int
    {
        if ($this->timPenyusunIdx !== false) return $this->timPenyusunIdx;

        $found = null;
        foreach (array_slice($this->pageTexts, 1, self::FRONT_PAGES - 1, true) as $i => $text) {
            $isKatalog = preg_match('/Nomor\s+Publikasi|Ukuran\s+Buku|Jumlah\s+Halaman|Publication\s+Number|Book\s+Size/iu', $text);
            if (!$isKatalog && preg_match('/\bTim\s+Penyusun\b|\bPengarah\b|\bPenanggung\s*Jawab\b|\bEditorial\s+Team\b/iu', $text)) {
                $found = $i;
                break;
            }
        }
        return $this->timPenyusunIdx = $found;
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
        $this->timPenyusunIdx = false;
        $this->page2Text  = '';
        $this->pageTexts  = [];
        $this->ocrPages   = [];
        $this->imagePages = [];
        $this->pageW      = 595.0;
        $this->pageH      = 842.0;
    }
}