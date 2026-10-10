<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\PdfCheckerService;
use App\Services\ExcelExportService;
use App\Models\SesiPemeriksaan;
use App\Models\HasilPemeriksaan;
use App\Models\DetailPemeriksaan;
use App\Models\KriteriaPemeriksaan;

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

    // ── POST /checker/sesi ────────────────────────────────────
    // Buat sesi lebih dulu supaya beberapa publikasi yang diperiksa paralel masuk ke sesi yang sama.
    public function buatSesi(): JsonResponse
    {
        $sesi = SesiPemeriksaan::create([
            'dibuat_oleh' => auth()->user()?->name ?? session('user_name', 'guest'),
        ]);

        return response()->json(['sesi_id' => $sesi->id]);
    }

    // ── POST /checker/check ───────────────────────────────────
    public function check(Request $request): JsonResponse
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        try {
            $request->validate([
                'files'   => 'required|array|size:1', // satu publikasi per pemeriksaan
                'files.*' => 'required|file|mimes:pdf|max:51200',
                'sesi_id' => 'nullable|integer|exists:sesi_pemeriksaan,id',
                // teks hasil ekstraksi di browser (js/pdf-extract.js), JSON
                'extracted' => 'nullable|string',
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
                    $extracted = json_decode((string) $request->input('extracted'), true);
                    $result    = is_array($extracted)
                        ? $this->checker->checkFromText($extracted, $file->getClientOriginalName(), $file->getSize())
                        : $this->checker->check($file); // fallback: ekstraksi di server (butuh exec untuk PDF terenkripsi)
                    // Simpan PDF supaya tinjauan manual bisa dilanjutkan dari riwayat
                    $hasil  = $this->saveResult($sesi->id, $result, [
                        'pdf_path'  => $file->store('pdf-hasil', HasilPemeriksaan::DISK) ?: null,
                        'ocr_lines' => is_array($extracted) ? PdfCheckerService::ocrLinesFrom($extracted) : null,
                    ]);
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
                                    $hasil  = $this->saveResult($sesi->id, $result, ['pdf_url' => $pdfUrl]);
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
            'status'      => ['required', Rule::in(DetailPemeriksaan::STATUS_VERIFIKASI)],
            'keterangan'  => 'nullable|string|max:2000|required_if:status,' . DetailPemeriksaan::STATUS_TIDAK_SESUAI,
        ], $this->pesanVerifikasi());

        $detail = $hasil->detail()->where('kriteria_id', $data['kriteria_id'])->first();
        if (!$detail) {
            return response()->json(['error' => 'Detail kriteria tidak ditemukan.'], 404);
        }

        $detail->update($this->dataVerifikasi($data));

        $hasil->recalcFromDetail();
        $hasil->sesi->recalcSummary();

        return response()->json(['ok' => true, 'summary' => $this->summaryOf($hasil)]);
    }

    // ── PATCH /checker/hasil/{hasil}/review-kategori ──────────
    // Simpan hasil verifikasi semua kriteria dalam satu kategori sekaligus.
    public function reviewKategori(Request $request, HasilPemeriksaan $hasil): JsonResponse
    {
        $data = $request->validate([
            'items'               => 'required|array|min:1',
            'items.*.kriteria_id' => 'required|string',
            'items.*.status'      => ['required', Rule::in(DetailPemeriksaan::STATUS_VERIFIKASI)],
            'items.*.keterangan'  => 'nullable|string|max:2000|required_if:items.*.status,' . DetailPemeriksaan::STATUS_TIDAK_SESUAI,
        ], $this->pesanVerifikasi('items.*.'));

        DB::transaction(function () use ($hasil, $data) {
            foreach ($data['items'] as $item) {
                $hasil->detail()->where('kriteria_id', $item['kriteria_id'])->update($this->dataVerifikasi($item));
            }
            $hasil->recalcFromDetail();
            $hasil->sesi->recalcSummary();
        });

        return response()->json(['ok' => true, 'summary' => $this->summaryOf($hasil)]);
    }

    // ── GET /checker/hasil/{hasil}/tinjau ─────────────────────
    // Lanjutkan tinjauan manual dari riwayat (mulai di kategori pertama yang belum ditinjau).
    public function tinjau(HasilPemeriksaan $hasil)
    {
        if (!$hasil->hasPdf()) {
            return redirect()->route('checker.riwayat.detail', $hasil->sesi_id)
                ->with('error', 'File PDF untuk hasil ini tidak tersimpan, tinjauan tidak bisa dilanjutkan.');
        }

        // Semua publikasi dalam sesi dibuka sekaligus (tab per publikasi), mulai dari publikasi ini
        return $this->tampilTinjauan($hasil->sesi, $hasil->id);
    }

    // ── GET /checker/riwayat/{sesi}/tinjau ────────────────────
    public function tinjauSesi(SesiPemeriksaan $sesi)
    {
        return $this->tampilTinjauan($sesi, null);
    }

    /** Tinjauan semua publikasi ber-PDF dalam satu sesi, seperti saat pemeriksaan. */
    private function tampilTinjauan(SesiPemeriksaan $sesi, ?int $mulaiId)
    {
        $semua = $sesi->hasilPemeriksaan()->with('detail')->orderBy('id')->get();
        $daftar = $semua->filter(fn($h) => $h->hasPdf() && $h->detail->isNotEmpty())->values();

        if ($daftar->isEmpty()) {
            return redirect()->route('checker.riwayat.detail', $sesi)
                ->with('error', 'Tidak ada PDF tersimpan di sesi ini, tinjauan tidak bisa dilanjutkan.');
        }

        // target/area tidak disimpan di detail — ambil dari master kriteria untuk petunjuk halaman
        $kriteria = KriteriaPemeriksaan::whereIn('kode', $daftar->flatMap(fn($h) => $h->detail->pluck('kriteria_id'))->unique())
            ->get()->keyBy('kode');

        $entries = $daftar->map(fn($hasil) => [
            'filename' => $hasil->judul,
            'hasilId'  => $hasil->id,
            'summary'  => null,
            'url'      => route('checker.hasil.pdf', $hasil),
            'ocrLines' => $hasil->ocr_lines ?: (object) [],
            'checks'   => $hasil->detail->sortBy('id')->map(function ($d) use ($kriteria) {
                $k = $kriteria->get($d->kriteria_id);
                return [
                    'id'        => $d->kriteria_id,
                    'kategori'  => $d->kategori,
                    'deskripsi' => $d->deskripsi,
                    'status'    => $d->status,
                    'catatan'   => $d->catatan,
                    'keterangan'=> $d->keterangan,
                    'target'    => $k->target ?? 'all',
                    'area'      => $k && $k->tipe_cek === 'posisi_area'
                        ? (KriteriaPemeriksaan::areaOptions()[$k->parameter['area'] ?? ''] ?? null)
                        : null,
                    'reviewed'  => $d->ditinjau_at !== null,
                    'lokasi'    => $d->lokasi ?? [],
                ];
            })->values(),
        ])->values();

        $startIndex = $mulaiId ? $daftar->search(fn($h) => $h->id === $mulaiId) : false;

        return view('checker.tinjau', [
            'sesi'       => $sesi,
            'daftar'     => $daftar,
            'tanpaPdf'   => $semua->count() - $daftar->count(),
            'entries'    => $entries,
            'startIndex' => $startIndex === false ? null : $startIndex,
        ]);
    }

    // ── GET /checker/hasil/{hasil}/pdf ────────────────────────
    public function pdf(HasilPemeriksaan $hasil)
    {
        if ($hasil->pdf_path && Storage::disk(HasilPemeriksaan::DISK)->exists($hasil->pdf_path)) {
            return response()->file(Storage::disk(HasilPemeriksaan::DISK)->path($hasil->pdf_path), ['Content-Type' => 'application/pdf']);
        }
        if ($hasil->pdf_url) {
            return redirect()->route('checker.bps.pdf_proxy', ['url' => $hasil->pdf_url]);
        }
        abort(404, 'File PDF tidak tersimpan.');
    }

    /**
     * Kolom yang disimpan dari verifikasi petugas. Catatan hasil cek otomatis (`catatan`) tidak ditimpa;
     * keterangan hanya disimpan untuk status TIDAK SESUAI.
     */
    private function dataVerifikasi(array $item): array
    {
        $keterangan = trim((string) ($item['keterangan'] ?? ''));

        return [
            'status'      => $item['status'],
            'keterangan'  => $item['status'] === DetailPemeriksaan::STATUS_TIDAK_SESUAI && $keterangan !== '' ? $keterangan : null,
            'ditinjau_at' => now(),
        ];
    }

    private function pesanVerifikasi(string $prefix = ''): array
    {
        return [
            "{$prefix}status.in"           => 'Status verifikasi harus Sesuai, Tidak Sesuai, atau Skip.',
            "{$prefix}keterangan.required_if" => 'Keterangan wajib diisi untuk kriteria yang Tidak Sesuai.',
        ];
    }

    private function summaryOf(HasilPemeriksaan $hasil): array
    {
        return [
            'ok'              => $hasil->total_ok,
            'perlu_dicek'     => $hasil->total_perlu_dicek,
            'tidak_ada'       => $hasil->total_tidak_ada,
            'tidak_diperiksa' => $hasil->total_tdk_diperiksa,
            'status_akhir'    => $hasil->status_akhir,
        ];
    }

    // ── GET /checker/riwayat ──────────────────────────────────
    public function riwayat(Request $request)
    {
        $sesiList = SesiPemeriksaan::with(['hasilPemeriksaan' => fn($q) => $q->withCount([
                'detail',
                'detail as ditinjau_count' => fn($d) => $d->whereNotNull('ditinjau_at'),
            ])])
            ->orderByDesc('created_at')
            ->paginate(15);
        return view('checker.riwayat', compact('sesiList'));
    }

    // ── GET /checker/riwayat/{sesi} ───────────────────────────
    public function riwayatDetail(SesiPemeriksaan $sesi)
    {
        $sesi->load('hasilPemeriksaan.detail', 'hasilPemeriksaan.catatanTambahan');
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
        $this->hapusSesi($sesi);
        return redirect()->route('checker.riwayat')
                         ->with('success', 'Sesi pemeriksaan berhasil dihapus.');
    }

    // ── DELETE /checker/riwayat (hapus beberapa sesi sekaligus) ─
    public function deleteSesiBulk(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ], [
            'ids.required' => 'Pilih minimal satu sesi untuk dihapus.',
        ]);

        $sesiList = SesiPemeriksaan::whereIn('id', $data['ids'])->get();
        foreach ($sesiList as $sesi) {
            $this->hapusSesi($sesi);
        }

        return redirect()->route('checker.riwayat', $request->only('page'))
                         ->with('success', $sesiList->count() . ' sesi pemeriksaan berhasil dihapus.');
    }

    /** Hapus sesi beserta hasil, detail (cascade) dan file PDF upload-nya. */
    private function hapusSesi(SesiPemeriksaan $sesi): void
    {
        $paths = $sesi->hasilPemeriksaan()->whereNotNull('pdf_path')->pluck('pdf_path')->all();
        $sesi->delete();
        Storage::disk(HasilPemeriksaan::DISK)->delete($paths);
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

    private function saveResult(int $sesiId, array $result, array $extra = []): HasilPemeriksaan
    {
        $hasil = HasilPemeriksaan::create($extra + [
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
            'lokasi'      => DetailPemeriksaan::encodeLokasi($c['lokasi'] ?? null),
        ], $result['checks']);

        DetailPemeriksaan::insert($detailRows);

        return $hasil;
    }

    private function normalizeStatus(?string $status): string
    {
        if (empty(trim($status ?? ''))) return 'TIDAK DIPERIKSA';
        $status  = strtoupper(trim($status));
        $allowed = ['OK', 'PERLU DICEK', 'TIDAK ADA', 'TIDAK SESUAI', 'TIDAK DIPERIKSA'];
        return in_array($status, $allowed) ? $status : 'TIDAK DIPERIKSA';
    }
}