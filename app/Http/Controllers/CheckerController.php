<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\PdfCheckerService;
use App\Services\ExcelExportService;
use App\Models\SesiPemeriksaan;
use App\Models\HasilPemeriksaan;
use App\Models\DetailPemeriksaan;

class CheckerController extends Controller
{
    public function __construct(
        private PdfCheckerService  $checker,
        private ExcelExportService $exporter,
    ) {}

    // ── GET /checker ──────────────────────────────────────────
    public function index()
    {
        return view('checker.checker');
    }

    // ── POST /checker/check ───────────────────────────────────
    public function check(Request $request): JsonResponse
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        try {
            $request->validate([
                'files'   => 'required|array|min:1|max:20',
                'files.*' => 'required|file|mimes:pdf|max:51200',
                'sesi_id' => 'nullable|integer|exists:sesi_pemeriksaan,id',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Validasi gagal: ' . $e->getMessage(),
                'results' => [],
            ], 200);
        }

        $sesiId = $request->input('sesi_id');
        $sesi   = $sesiId
            ? SesiPemeriksaan::findOrFail($sesiId)
            : SesiPemeriksaan::create([
                'dibuat_oleh' => auth()->user()?->name ?? session('user_name', 'guest'),
            ]);

        $results = [];

        DB::beginTransaction();
        try {
            foreach ($request->file('files') as $file) {
                try {
                    $result = $this->checker->check($file);
                    $hasil  = $this->saveResult($sesi->id, $result);
                    $result['hasil_id'] = $hasil->id;
                    $results[] = $result;
                } catch (\Throwable $e) {
                    \Log::error('Check file error: ' . $e->getMessage() . ' | File: ' . $file->getClientOriginalName());
                    $results[] = [
                        'filename'           => $file->getClientOriginalName(),
                        'ukuran_file'        => $file->getSize(),
                        'error'              => 'Gagal memproses: ' . $e->getMessage(),
                        'checks'             => [],
                        'summary'            => [
                            'ok'                => 0,
                            'perlu_dicek'       => 0,
                            'tidak_ada'         => 0,
                            'tidak_diperiksa'   => 0,
                            'status_akhir'      => 'ERROR'
                        ],
                        'total_pages'        => null,
                        'extraction_method'  => null,
                    ];
                }
            }

            $sesi->recalcSummary();
            DB::commit();

            return response()->json([
                'sesi_id' => $sesi->id,
                'results' => $results
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Transaction error: ' . $e->getMessage());
            
            return response()->json([
                'error'   => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
                'results' => [],
            ], 200);
        }
    }

    // ── GET /checker/bps/stream ───────────────────────────────
    public function stream(Request $request): StreamedResponse
    {
        $pdfUrl = $request->query('pdf_url');
        $title  = $request->query('title', 'Publikasi');

        return response()->stream(
            function () use ($pdfUrl, $title) {
                while (ob_get_level() > 0) ob_end_clean();

                set_time_limit(600);
                ignore_user_abort(false);

                $tmpPath = null;

                try {
                    $tmpPath = $this->downloadPdf($pdfUrl);

                    if (!$tmpPath) {
                        $this->emit('error', ['message' => 'Gagal mengunduh PDF dari server BPS.']);
                        return;
                    }

                    $fileSize = filesize($tmpPath);

                    
                    foreach ($this->checker->streamCheck($tmpPath, $title, $fileSize) as $event) {
                        $type = $event['type'];

                        switch ($type) {
                            case 'heartbeat':
                                $this->emit('heartbeat', ['msg' => $event['msg'] ?? '...']);
                                break;

                            case 'parse_start':
                                $this->emit('parse_start', ['total_pages' => $event['total_pages']]);
                                break;

                            case 'parse_page':
                                if ($event['current'] % 3 === 0 || $event['current'] === $event['total']) {
                                    $this->emit('parse_page', [
                                        'current' => $event['current'],
                                        'total'   => $event['total'],
                                    ]);
                                }
                                break;

                            case 'parse_done':
                                $this->emit('parse_done', [
                                    'total_pages' => $event['total_pages'],
                                    'method'      => $event['method'],
                                ]);
                                break;

                            case 'check':
                                $this->emit('check', [
                                    'id'        => $event['id'],
                                    'kategori'  => $event['kategori'],
                                    'deskripsi' => $event['deskripsi'],
                                    'status'    => $event['status'],
                                    'catatan'   => $event['catatan'],
                                ]);
                                break;

                            case 'done':
                                try {
                                    $result = $event['result'];
                                    $sesi   = SesiPemeriksaan::create([
                                        'dibuat_oleh' => auth()->user()?->name ?? 'bps-import',
                                    ]);
                                    $hasil  = $this->saveResult($sesi->id, $result);
                                    $sesi->recalcSummary();
                                    $result['hasil_id'] = $hasil->id;
                                    $result['sesi_id']  = $sesi->id;
                                } catch (\Throwable $dbErr) {
                                    \Log::error('[Stream] DB save error: ' . $dbErr->getMessage());
                                }

                                $this->emit('done', ['result' => $result]);
                                break;

                            case 'error':
                                $this->emit('error', ['message' => $event['message']]);
                                return;
                        }

                        if (ob_get_level()) ob_flush();
                        flush();
                    }

                } catch (\Throwable $e) {
                    \Log::error('[Stream] Fatal: ' . $e->getMessage());
                    $this->emit('error', ['message' => $e->getMessage()]);
                    if (ob_get_level()) ob_flush();
                    flush();
                } finally {
                    if ($tmpPath && file_exists($tmpPath)) @unlink($tmpPath);
                }
            },
            200,
            [
                'Content-Type'      => 'text/event-stream',
                'Cache-Control'     => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no',
                'Connection'        => 'keep-alive',
            ]
        );
    }

    // ── POST /checker/export ──────────────────────────────────
    public function export(Request $request)
    {
        $request->validate(['results' => 'required|array|min:1']);

        $ss       = $this->exporter->exportFromArray($request->input('results'));
        $filename = 'rekap_pemeriksaan_bps_' . date('Ymd_His') . '.xlsx';

        return $this->exporter->streamDownload($ss, $filename);
    }

    // ── PATCH /checker/hasil/{hasil}/review ───────────────────
    public function reviewDetail(Request $request, HasilPemeriksaan $hasil): JsonResponse
    {
        $data = $request->validate([
            'kriteria_id' => 'required|string',
            'status'      => 'required|in:OK,PERLU DICEK,TIDAK ADA,TIDAK DIPERIKSA',
            'catatan'     => 'nullable|string',
        ]);

        $detail = $hasil->detail()->where('kriteria_id', $data['kriteria_id'])->first();
        if (!$detail) {
            return response()->json(['error' => 'Detail kriteria tidak ditemukan.'], 404);
        }

        $detail->update([
            'status'  => $data['status'],
            'catatan' => $data['catatan'] ?: null,
        ]);

        $hasil->recalcFromDetail();
        $hasil->sesi->recalcSummary();

        return response()->json([
            'ok'      => true,
            'summary' => [
                'ok'              => $hasil->total_ok,
                'perlu_dicek'     => $hasil->total_perlu_dicek,
                'tidak_ada'       => $hasil->total_tidak_ada,
                'tidak_diperiksa' => $hasil->total_tdk_diperiksa,
                'status_akhir'    => $hasil->status_akhir,
            ],
        ]);
    }

    // ── GET /checker/riwayat ──────────────────────────────────
    public function riwayat(Request $request)
    {
        $sesiList = SesiPemeriksaan::orderByDesc('created_at')->paginate(15);
        return view('checker.riwayat', compact('sesiList'));
    }

    // ── GET /checker/riwayat/{sesi} ───────────────────────────
    public function riwayatDetail(SesiPemeriksaan $sesi)
    {
        $sesi->load('hasilPemeriksaan.detail');
        return view('checker.riwayat-detail', compact('sesi'));
    }

    // ── GET /checker/riwayat/{sesi}/export ───────────────────
    public function exportSesi(SesiPemeriksaan $sesi)
    {
        $sesi->load('hasilPemeriksaan.detail');
        $ss       = $this->exporter->exportSesi($sesi);
        $filename = 'rekap_sesi_' . $sesi->id . '_' . date('Ymd') . '.xlsx';
        return $this->exporter->streamDownload($ss, $filename);
    }

    // ── DELETE /checker/riwayat/{sesi} ───────────────────────
    public function deleteSesi(SesiPemeriksaan $sesi)
    {
        $sesi->delete();
        return redirect()->route('checker.riwayat')
                         ->with('success', 'Sesi pemeriksaan berhasil dihapus.');
    }

    // ═══════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════

    private function downloadPdf(string $url): ?string
    {
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bps_pdf_' . uniqid() . '.pdf';

        $ctx = stream_context_create([
            'http' => [
                'timeout'         => 300,
                'follow_location' => true,
                'user_agent'      => 'Mozilla/5.0 (compatible; BPS-Checker/1.0)',
            ],
            'ssl'  => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $src = @fopen($url, 'r', false, $ctx);
        if (!$src) return null;

        $fp       = fopen($tmpPath, 'wb');
        $bytes    = 0;
        $lastPing = time();

        $this->emit('heartbeat', ['msg' => 'Mengunduh PDF...']);
        if (ob_get_level()) ob_flush();
        flush();

        while (!feof($src)) {
            $chunk = fread($src, 65536);
            if ($chunk === false) break;
            fwrite($fp, $chunk);
            $bytes += strlen($chunk);

            if (time() - $lastPing >= 2) {
                $mb = number_format($bytes / 1048576, 1);
                $this->emit('heartbeat', ['msg' => "Mengunduh PDF... {$mb} MB"]);
                if (ob_get_level()) ob_flush();
                flush();
                $lastPing = time();
            }
        }

        fclose($src);
        fclose($fp);

        if ($bytes < 1024) {
            @unlink($tmpPath);
            return null;
        }

        return $tmpPath;
    }

    private function emit(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    private function saveResult(int $sesiId, array $result): HasilPemeriksaan
    {
        $hasil = HasilPemeriksaan::create([
            'sesi_id'             => $sesiId,
            'nama_file'           => $result['filename'],
            'ukuran_file'         => $result['ukuran_file'],
            'total_halaman'       => $result['total_pages'],
            'metode_ekstraksi'    => $result['extraction_method'],
            'total_ok'            => $result['summary']['ok'],
            'total_perlu_dicek'   => $result['summary']['perlu_dicek'],
            'total_tidak_ada'     => $result['summary']['tidak_ada'],
            'total_tdk_diperiksa' => $result['summary']['tidak_diperiksa'],
            'status_akhir'        => $result['summary']['status_akhir'],
            'error_msg'           => $result['error'] ?: null,
            'created_at'          => now(),
        ]);

        $detailRows = array_map(fn($c) => [
            'hasil_id'    => $hasil->id,
            'kriteria_id' => $c['id']        ?? 'UNKNOWN',
            'kategori'    => $c['kategori']  ?? 'Umum',
            'deskripsi'   => $c['deskripsi'] ?? 'Tidak ada deskripsi',
            'status'      => $this->normalizeStatus($c['status'] ?? null),
            'catatan'     => $c['catatan']   ?: null,
        ], $result['checks']);

        DetailPemeriksaan::insert($detailRows);

        return $hasil;
    }

    private function normalizeStatus(?string $status): string
    {
        if (empty(trim($status ?? ''))) return 'TIDAK DIPERIKSA';
        $status  = strtoupper(trim($status));
        $allowed = ['OK', 'PERLU DICEK', 'TIDAK ADA', 'TIDAK DIPERIKSA'];
        return in_array($status, $allowed) ? $status : 'TIDAK DIPERIKSA';
    }
}