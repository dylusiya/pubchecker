<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\BpsApiService;
use App\Services\PdfCheckerService;
use App\Services\ExcelExportService;
use App\Models\SesiPemeriksaan;
use App\Models\HasilPemeriksaan;
use App\Models\DetailPemeriksaan;

class BpsImportController extends Controller
{
    public function __construct(
        private BpsApiService      $bps,
        private PdfCheckerService  $checker,
        private ExcelExportService $exporter,
    ) {}

    // ── GET /checker/bps-import ───────────────────────────────
    public function index()
    {
        $configured = !empty(config('services.bps_api.key', ''));
        $domain     = config('services.bps_api.domain', '0000');
        $domains    = [];

        if ($configured) {
            try {
                $resProv    = $this->bps->getDomains('prov');
                $resKab     = $this->bps->getDomains('kabbyprov', '63');
                $domainProv = collect($resProv['data'][1] ?? [])->where('domain_id', '6300')->values()->all();
                $domainKab  = $resKab['data'][1] ?? [];
                $domains    = array_merge($domainProv, $domainKab);

            } catch (\Throwable $e) {
                $domains = [];
            }
        }

        return view('checker.bps-import', compact('configured', 'domain', 'domains'));
    }

    // ── POST /checker/bps-import/search ──────────────────────
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'domain'  => 'required|string',
            'keyword' => 'nullable|string|max:100',
            'year'    => 'nullable|integer|min:2000|max:2099',
            'month'   => 'nullable|integer|min:1|max:12',
            'page'    => 'nullable|integer|min:1',
        ]);

        if (empty(config('services.bps_api.key', ''))) {
            return response()->json([
                'error' => 'API Key BPS belum dikonfigurasi. Tambahkan BPS_API_KEY di .env'
            ], 422);
        }

        $result = $this->bps->getPublications(
            domain : $request->input('domain'),
            page   : (int) $request->input('page', 1),
            month  : $request->input('month')   ? (int) $request->input('month')   : null,
            year   : $request->input('year')    ? (int) $request->input('year')    : null,
            keyword: $request->input('keyword') ?: null,
        );

        if (empty($result['items']) && ($result['status'] ?? '') !== 'OK') {
            return response()->json(['error' => 'Gagal mengambil data dari API BPS.'], 500);
        }

        $items = array_map(fn($pub) => array_merge($pub, [
            'has_pdf' => !empty($pub['pdf']),
        ]), $result['items']);

        return response()->json([
            'meta'  => $result['pagination'],
            'items' => $items,
        ]);
    }

    // ── POST /checker/bps-import/run ─────────────────────────
    public function run(Request $request): JsonResponse
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);

        $request->validate([
            'publications'          => 'required|array|min:1|max:20',
            'publications.*.pdf'    => 'required|url',
            'publications.*.title'  => 'required|string',
            'publications.*.pub_id' => 'nullable|string',
        ]);

        if (empty(config('services.bps_api.key', ''))) {
            return response()->json(['error' => 'API Key BPS belum dikonfigurasi.'], 422);
        }

        $sesi = SesiPemeriksaan::create([
            'dibuat_oleh' => auth()->user()?->name ?? 'guest',
        ]);

        $results = [];

        DB::beginTransaction();
        try {
            foreach ($request->input('publications') as $pub) {
                $pdfUrl   = $pub['pdf'];
                $title    = $pub['title'];
                $pubId    = $pub['pub_id'] ?? null;
                $filename = $this->sanitizeFilename($title) . '.pdf';
                $tmpPath  = null;

                try {
                    $tmpPath = $this->bps->downloadPdf($pdfUrl);

                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $tmpPath, $filename, 'application/pdf', null, true
                    );

                    $result = $this->checker->check($uploadedFile);

                } catch (\Throwable $e) {
                    $result = $this->buildErrorResult($filename, $e->getMessage());
                } finally {
                    if ($tmpPath && file_exists($tmpPath)) {
                        @unlink($tmpPath);
                    }
                }

                $hasil = HasilPemeriksaan::create([
                    'sesi_id'             => $sesi->id,
                    'nama_file'           => $filename,
                    'ukuran_file'         => $result['ukuran_file'] ?? 0,
                    'total_halaman'       => $result['total_pages'] ?? 0,
                    'metode_ekstraksi'    => $result['extraction_method'] ?? 'none',
                    'total_ok'            => $result['summary']['ok'],
                    'total_perlu_dicek'   => $result['summary']['perlu_dicek'],
                    'total_tidak_ada'     => $result['summary']['tidak_ada'],
                    'total_tdk_diperiksa' => $result['summary']['tidak_diperiksa'],
                    'status_akhir'        => $result['summary']['status_akhir'],
                    'error_msg'           => $result['error'] ?: null,
                ]);

                if (!empty($result['checks'])) {
                    DetailPemeriksaan::insert(array_map(fn($c) => [
                        'hasil_id'    => $hasil->id,
                        'kriteria_id' => $c['id'],
                        'kategori'    => $c['kategori'],
                        'deskripsi'   => $c['deskripsi'],
                        'status'      => $c['status'],
                        'catatan'     => $c['catatan'] ?: null,
                    ], $result['checks']));
                }

                $result['hasil_id'] = $hasil->id;
                $results[] = $result;
            }

            $sesi->recalcSummary();
            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'sesi_id' => $sesi->id,
            'results' => $results,
        ]);
    }

    // ── GET /checker/bps-import/detail ───────────────────────
    public function show(Request $request)
    {
        $pubId = $request->query('pub_id');
        $domain = $request->query('domain', config('services.bps_api.domain', '6300'));

        return view('checker.bps-detail', [
            'pub_id' => $pubId,
            'domain' => $domain,
        ]);
    }

    // ── POST /checker/bps-import/detail ──────────────────────
    public function detail(Request $request): JsonResponse
    {
        $request->validate([
            'pub_id' => 'required|string',
            'domain' => 'nullable|string',
        ]);

        if (empty(config('services.bps_api.key', ''))) {
            return response()->json([
                'error' => 'API Key BPS belum dikonfigurasi'
            ], 422);
        }

        $pubId = $request->input('pub_id');
        $domain = $request->input('domain') ?? config('services.bps_api.domain', '6300');

        try {
            // Get publication detail from BPS API
            $result = $this->bps->getPublicationDetail($domain, $pubId);
            
            \Log::debug('BPS Detail result', [
                'domain' => $domain,
                'pub_id' => $pubId,
                'status' => $result['status'] ?? 'no status',
                'has_data' => !empty($result['data']),
                'data_availability' => $result['data-availability'] ?? 'unknown',
            ]);

            // Check if request was successful
            if (($result['status'] ?? '') === 'ERROR' || empty($result['data'])) {
                return response()->json([
                    'error' => 'Publikasi tidak ditemukan atau API error'
                ], 404);
            }

            // Data dari response bisa berbeda strukturnya
            $pub = is_array($result['data']) ? $result['data'] : null;
            
            if (!$pub) {
                return response()->json([
                    'error' => 'Data publikasi tidak tersedia'
                ], 404);
            }

            // Get domain URL dari response
            $domainUrl = rtrim($result['request']['domain'] ?? 'https://www.bps.go.id', '/');

            // Parse publication data dengan fallback untuk berbagai field names
            $publication = [
                'pub_id'      => $pub['pub_id']     ?? $pub['id']       ?? $pubId,
                'title'       => $pub['title']      ?? $pub['judul']    ?? '-',
                'issn'        => $pub['issn']       ?? $pub['nomorissn'] ?? $pub['isbn'] ?? null,
                'catalog'     => $pub['kat_no']     ?? $pub['katalog']  ?? null,
                'pub_no'      => $pub['pub_no']     ?? $pub['nopub']    ?? null,
                'abstract'    => $pub['abstract']   ?? $pub['abstrak']  ?? null,
                'sch_date'    => $pub['sch_date']   ?? $pub['schedule_date'] ?? null,
                'rl_date'     => $pub['rl_date']    ?? $pub['rl']       ?? $pub['release_date'] ?? null,
                'updt_date'   => $pub['updt_date']  ?? $pub['update_date'] ?? null,
                'cover'       => $pub['cover']      ?? $pub['thumbnail'] ?? null,
                'pdf'         => $pub['pdf']        ?? $pub['pdffile']  ?? null,
                'has_pdf'     => !empty($pub['pdf'] ?? $pub['pdffile']),
                'size'        => $pub['size']       ?? $this->formatFileSize($pub['filesize'] ?? 0),
                'pages'       => $pub['pages']      ?? $pub['halaman']  ?? null,
                'periode'     => $pub['periode']    ?? $pub['period']   ?? null,
                'bahasa'      => $pub['bahasa']     ?? $pub['language'] ?? 'Indonesia',
                'revisi'      => $pub['revisi']     ?? $pub['revision'] ?? null,
                'subject_csa' => $pub['subject_csa'] ?? $pub['subject'] ?? $pub['subjek'] ?? [],
                'domain'      => $domain,
                'domain_name' => $this->getDomainName($domain),
                'domain_url'  => $domainUrl,
            ];

            return response()->json([
                'success'     => true,
                'publication' => $publication,
            ]);

        } catch (\Throwable $e) {
            \Log::error('BPS Publication Detail Error', [
                'pub_id' => $pubId,
                'domain' => $domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Gagal mengambil detail publikasi: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    private function sanitizeFilename(string $title): string
    {
        $clean = preg_replace('/[^\w\s\-]/u', '', $title);
        $clean = preg_replace('/\s+/', '_', trim($clean));
        return mb_substr($clean, 0, 100);
    }

    private function buildErrorResult(string $filename, string $error): array
    {
        return [
            'filename'          => $filename,
            'ukuran_file'       => 0,
            'total_pages'       => 0,
            'extraction_method' => 'none',
            'error'             => $error,
            'summary' => [
                'ok'              => 0,
                'perlu_dicek'     => 0,
                'tidak_ada'       => 0,
                'tidak_diperiksa' => 0,
                'status_akhir'    => 'error',
            ],
            'checks' => [],
        ];
    }

    private function getDomainName($domainId): string
    {
        $domains = [
            '0000' => 'BPS Pusat',
            '1100' => 'BPS Provinsi Aceh',
            '1200' => 'BPS Provinsi Sumatera Utara',
            '1300' => 'BPS Provinsi Sumatera Barat',
            '1400' => 'BPS Provinsi Riau',
            '1500' => 'BPS Provinsi Jambi',
            '1600' => 'BPS Provinsi Sumatera Selatan',
            '1700' => 'BPS Provinsi Bengkulu',
            '1800' => 'BPS Provinsi Lampung',
            '1900' => 'BPS Provinsi Kepulauan Bangka Belitung',
            '2100' => 'BPS Provinsi Kepulauan Riau',
            '3100' => 'BPS Provinsi DKI Jakarta',
            '3200' => 'BPS Provinsi Jawa Barat',
            '3300' => 'BPS Provinsi Jawa Tengah',
            '3400' => 'BPS Provinsi DI Yogyakarta',
            '3500' => 'BPS Provinsi Jawa Timur',
            '3600' => 'BPS Provinsi Banten',
            '5100' => 'BPS Provinsi Bali',
            '5200' => 'BPS Provinsi Nusa Tenggara Barat',
            '5300' => 'BPS Provinsi Nusa Tenggara Timur',
            '6100' => 'BPS Provinsi Kalimantan Barat',
            '6200' => 'BPS Provinsi Kalimantan Tengah',
            '6300' => 'BPS Provinsi Kalimantan Selatan',
            '6400' => 'BPS Provinsi Kalimantan Timur',
            '6500' => 'BPS Provinsi Kalimantan Utara',
            '7100' => 'BPS Provinsi Sulawesi Utara',
            '7200' => 'BPS Provinsi Sulawesi Tengah',
            '7300' => 'BPS Provinsi Sulawesi Selatan',
            '7400' => 'BPS Provinsi Sulawesi Tenggara',
            '7500' => 'BPS Provinsi Gorontalo',
            '7600' => 'BPS Provinsi Sulawesi Barat',
            '8100' => 'BPS Provinsi Maluku',
            '8200' => 'BPS Provinsi Maluku Utara',
            '9100' => 'BPS Provinsi Papua Barat',
            '9400' => 'BPS Provinsi Papua',
            // Kalsel Kabupaten/Kota
            '6301' => 'BPS Kabupaten Tanah Laut',
            '6302' => 'BPS Kabupaten Kotabaru',
            '6303' => 'BPS Kabupaten Banjar',
            '6304' => 'BPS Kabupaten Barito Kuala',
            '6305' => 'BPS Kabupaten Tapin',
            '6306' => 'BPS Kabupaten Hulu Sungai Selatan',
            '6307' => 'BPS Kabupaten Hulu Sungai Tengah',
            '6308' => 'BPS Kabupaten Hulu Sungai Utara',
            '6309' => 'BPS Kabupaten Tabalong',
            '6310' => 'BPS Kabupaten Tanah Bumbu',
            '6311' => 'BPS Kabupaten Balangan',
            '6371' => 'BPS Kota Banjarmasin',
            '6372' => 'BPS Kota Banjarbaru',
        ];

        return $domains[$domainId] ?? "Domain {$domainId}";
    }

    private function formatFileSize($bytes): string
    {
        if ($bytes == 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function pdfProxy(Request $request)
    {
        $url = $request->query('url');

        if (!$url || !str_contains(parse_url($url, PHP_URL_HOST) ?? '', 'bps.go.id')) {
            abort(403, 'URL tidak diizinkan');
        }

        // Forward Range header jika ada (untuk PDF.js partial loading)
        $headers = ['User-Agent' => 'Mozilla/5.0'];
        if ($request->hasHeader('Range')) {
            $headers['Range'] = $request->header('Range');
        }

        $response = Http::timeout(120)
            ->withHeaders($headers)
            ->withOptions(['stream' => true])
            ->get($url);

        // Forward status (200 atau 206 Partial Content)
        $status = $response->status();

        $responseHeaders = array_filter([
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="publication.pdf"',
            'Cache-Control'       => 'private, max-age=3600',
            'X-Frame-Options'     => 'SAMEORIGIN',
            'Accept-Ranges'       => 'bytes',
            'Content-Length'      => $response->header('Content-Length'),
            'Content-Range'       => $response->header('Content-Range'),
        ]);

        return response()->stream(function () use ($response) {
            $body = $response->toPsrResponse()->getBody();
            while (!$body->eof()) {
                echo $body->read(65536);
                flush();
            }
        }, $status, $responseHeaders);
    }
}