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
    private float  $pageW      = 595.0;
    private float  $pageH      = 842.0;
    private string $page2Text  = '';

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
                if ($i === 0) {
                    $this->coverText  = $page->getText() ?? '';
                    $this->coverWords = $this->extractWords($page);
                } elseif ($i === 1) {
                    $this->page2Text = $page->getText() ?? '';
                }
                $this->pages = $i + 1;

                yield ['type' => 'parse_page', 'current' => $i + 1, 'total' => $total];
            }

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

                        yield ['type' => 'parse_start', 'total_pages' => $total];

                        foreach ($allPages as $i => $page) {
                            if ($i === 0) {
                                $this->coverText  = $page->getText() ?? '';
                                $this->coverWords = $this->extractWords($page);
                            } elseif ($i === 1) {
                                $this->page2Text = $page->getText() ?? '';
                            }
                            $this->pages = $i + 1;

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
            if ($this->method === 'secured_unreadable') {
                $status  = 'PERLU DICEK';
                $catatan = 'PDF terenkripsi, tidak dapat diperiksa otomatis';
            } else {
                [$status, $catatan] = $this->evalRule($rule);
            }

            $area = $this->areaLabel($rule);
            $this->add($rule->kode, $rule->kategori, $rule->deskripsi, $status, $catatan, $rule->target, $area);

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

        if ($this->pages === 0) {
            throw new \RuntimeException('PDF tidak memiliki halaman yang dapat dibaca.');
        }

        $this->coverText  = $pages[0]->getText() ?? '';
        $this->coverWords = $this->extractWords($pages[0]);

        if ($this->pages >= 2) {
            $this->page2Text = $pages[1]->getText() ?? '';
        }
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
            $this->add($rule->kode, $rule->kategori, $rule->deskripsi, $status, $catatan,
                $rule->target, $this->areaLabel($rule));
        }
    }

    private function evalRule(KriteriaPemeriksaan $rule): array
    {
        $param = $rule->parameter ?? [];
        $text  = $this->targetText($rule->target);
        $gagal = $rule->status_gagal;
        $msgOk = $rule->pesan_ok    ?? 'OK';
        $msgErr= $rule->pesan_gagal ?? 'Kriteria tidak terpenuhi';

        return match($rule->tipe_cek) {
            'regex'        => $this->evalRegex($param, $text, $gagal, $msgOk, $msgErr),
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
        $regex = '/' . $pattern . '/' . ($p['flags'] ?? '');
        try {
            $found = (bool) preg_match($regex, $text, $m);
        } catch (\Throwable) {
            return ['TIDAK DIPERIKSA', 'Regex tidak valid: ' . $regex];
        }
        if ($found) {
            $detail = isset($m[0]) ? ' — ditemukan: ' . mb_substr(trim($m[0]), 0, 80) : '';
            return ['OK', $ok . $detail];
        }
        return [$gagal, $err];
    }

    private function evalContains(array $p, string $text, bool $invert, string $gagal, string $ok, string $err): array
    {
        $needle = $p['text'] ?? null;
        if ($needle === null) return ['TIDAK DIPERIKSA', 'Parameter text kosong'];
        $cs       = $p['case'] ?? false;
        $haystack = $cs ? $text   : mb_strtolower($text);
        $needle   = $cs ? $needle : mb_strtolower($needle);
        $found    = str_contains($haystack, $needle);
        $pass     = $invert ? !$found : $found;
        return $pass ? ['OK', $ok] : [$gagal, $err];
    }

    private function evalPosisi(array $p, string $gagal, string $ok, string $err): array
    {
        $word = $p['word'] ?? null;
        $area = $p['area'] ?? null;
        if (!$word || !$area) return ['TIDAK DIPERIKSA', 'Parameter word/area kosong'];
        $w = $this->findWord($word);
        if (!$w) return ['TIDAK DIPERIKSA', "Kata '$word' tidak ditemukan di kover"];
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
                return [$gagal, $err . " ({$chars} karakter, maks {$max})"];
            }
        }
        return ['OK', $ok];
    }

    // ═══════════════════════════════════════════════════════
    // INTERNAL — HELPERS
    // ═══════════════════════════════════════════════════════

    private function targetText(string $target): string
    {
        return match($target) {
            'page2' => $this->page2Text,
            'all'   => $this->coverText . "\n" . $this->page2Text,
            default => $this->coverText,
        };
    }

    private function findWord(string $needle): ?array
    {
        $nl = strtolower($needle);
        foreach ($this->coverWords as $w) {
            if (str_contains(strtolower($w['text']), $nl)) return $w;
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
        string $target = 'cover', ?string $area = null
    ): void {
        $this->checks[] = compact('id', 'kategori', 'deskripsi', 'status', 'catatan', 'target', 'area');
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
        $this->page2Text  = '';
    }
}