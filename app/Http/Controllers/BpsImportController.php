<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\HeaderUtils;
use App\Services\BpsApiService;
use App\Services\PdfCheckerService;
use App\Services\ExcelExportService;
use App\Services\SipotretService;
use App\Models\SesiPemeriksaan;
use App\Models\HasilPemeriksaan;
use App\Models\DetailPemeriksaan;
use App\Models\KriteriaPemeriksaan;

class BpsImportController extends Controller
{
    private const DOMAIN_CACHE_KEY   = 'bps_import.domains.6300';
    private const API_DOWN_CACHE_KEY = 'bps_import.api_down';

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
        $apiError   = false;

        if ($configured) {
            // Daftar domain jarang berubah: simpan sehari supaya halaman tidak menunggu API BPS setiap dibuka.
            $domains = Cache::get(self::DOMAIN_CACHE_KEY, []);

            // Baru saja gagal → jangan menunggu timeout lagi setiap halaman dibuka (coba ulang setelah 5 menit)
            if (!$domains && !Cache::has(self::API_DOWN_CACHE_KEY)) {
                try {
                    $resProv    = $this->bps->getDomains('prov');
                    $resKab     = $this->bps->getDomains('kabbyprov', '63');
                    $domainProv = collect($resProv['data'][1] ?? [])->where('domain_id', '6300')->values()->all();
                    $domainKab  = $resKab['data'][1] ?? [];
                    $domains    = array_merge($domainProv, $domainKab);
                } catch (\Throwable $e) {
                    $domains = [];
                }

                if ($domains) {
                    Cache::put(self::DOMAIN_CACHE_KEY, $domains, now()->addDay());
                } else {
                    Cache::put(self::API_DOWN_CACHE_KEY, true, now()->addMinutes(5));
                }
            }

            // API BPS tidak bisa dihubungi: tetap sediakan domain bawaan agar pencarian bisa dicoba lagi
            if (!$domains) {
                $apiError = true;
                $domains  = [['domain_id' => $domain, 'domain_name' => 'Provinsi Kalimantan Selatan']];
            }
        }

        // admin: daftar pemeriksa untuk penugasan publikasi
        $pemeriksa = auth()->user()?->isAdmin() ? PenugasanController::daftarPemeriksa() : [];
        return view('checker.bps-import', compact('configured', 'domain', 'domains', 'apiError', 'pemeriksa'));
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
            return response()->json([
                'error' => 'Gagal mengambil data dari API BPS (webapi.bps.go.id tidak merespons atau sedang gangguan). Coba lagi beberapa saat lagi.',
            ], 502);
        }

        $tugas = \App\Models\PenugasanPemeriksaan::untuk(array_column($result['items'], 'pub_id'));
        $items = array_map(fn($pub) => array_merge($pub, [
            'has_pdf' => !empty($pub['pdf']),
            'cover'   => $this->coverLewatProxy($pub['cover'] ?? null),
            // penugasan pemeriksaan (null = belum ditugaskan)
            'tugas'   => isset($tugas[(string) ($pub['pub_id'] ?? '')]) ? $tugas[(string) $pub['pub_id']]->ringkas($request->user()) : null,
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
            'publications.*.domain' => 'nullable|string|max:10',
            'publications.*.issn'   => 'nullable|string|max:30',
            'publications.*.rl_date'=> 'nullable|string|max:30',
            // isian Web API (cadangan bila detail API gagal diambil server) untuk cek sama_api
            'publications.*.catalog' => 'nullable|string|max:50',
            'publications.*.pub_no'  => 'nullable|string|max:50',
            // periode catatan SIPOTRET yang dipakai sebagai acuan isian awal tinjauan (disimpan per hasil)
            'publications.*.acuan_bulan' => 'nullable|integer|between:1,12',
            'publications.*.acuan_tahun' => 'nullable|integer|between:2000,2100',
            // periode pemeriksaan SIPOTRET yang dituju (hasilnya dikirim ke periode ini), untuk Tugas Saya
            'publications.*.periode_bulan' => 'nullable|integer|between:1,12',
            'publications.*.periode_tahun' => 'nullable|integer|between:2000,2100',
            // draf yang dibuat lebih dulu (POST draf, ditinjau sambil diproses): hasil cek mengisi draf ini
            'publications.*.hasil_id' => 'nullable|integer|exists:hasil_pemeriksaan,id',
            'domain'                => 'nullable|string|max:10', // domain bersama (halaman detail)
            // teks hasil ekstraksi di browser (js/pdf-extract.js), JSON — hanya untuk satu publikasi
            'extracted'             => 'nullable|string',
            'extracted_b64'         => 'nullable|string', // base64 dari PdfExtract.kemas
            // beberapa publikasi diperiksa paralel ke satu sesi yang dibuat lebih dulu (POST /checker/sesi)
            'sesi_id'               => 'nullable|integer|exists:sesi_pemeriksaan,id',
        ]);
        $extracted = PdfCheckerService::extractedDari($request);

        // publikasi yang ditugaskan ke pemeriksa lain tidak boleh diperiksa (admin selalu boleh)
        $tugas = \App\Models\PenugasanPemeriksaan::untuk(array_column($request->input('publications'), 'pub_id'));
        foreach ($tugas as $pubId => $t) {
            if (!\App\Models\PenugasanPemeriksaan::boleh($request->user(), $pubId, $t)) {
                return response()->json(['error' => $t->judul ? "\"{$t->judul}\": " . $t->pesanTolak() : $t->pesanTolak()], 403);
            }
        }

        if (empty(config('services.bps_api.key', ''))) {
            return response()->json(['error' => 'API Key BPS belum dikonfigurasi.'], 422);
        }

        $sesi = $request->filled('sesi_id')
            ? SesiPemeriksaan::findOrFail($request->input('sesi_id'))
            : SesiPemeriksaan::create(['dibuat_oleh' => auth()->user()?->name ?? 'guest']);

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
                    // tanggal rilis dari API BPS: cek bulan penandatanganan kata pengantar/sambutan
                    $this->checker->setTanggalRilis($pub['rl_date'] ?? null);
                    // ISSN, nomor katalog & nomor publikasi di Web API: dibandingkan dengan isian publikasi (sama_api)
                    $this->checker->setDataApi($this->dataApi($pub, $request->input('domain')));
                    if (is_array($extracted) && count($request->input('publications')) === 1) {
                        // Teks sudah dibaca di browser — tidak perlu unduh & parse PDF di server
                        $result = $this->checker->checkFromText($extracted, $filename, (int) ($extracted['file_size'] ?? 0));
                    } else {
                        $tmpPath = $this->bps->downloadPdf($pdfUrl);

                        $uploadedFile = new \Illuminate\Http\UploadedFile(
                            $tmpPath, $filename, 'application/pdf', null, true
                        );

                        $result = $this->checker->check($uploadedFile);
                    }

                } catch (\Throwable $e) {
                    $result = $this->buildErrorResult($filename, $e->getMessage());
                } finally {
                    if ($tmpPath && file_exists($tmpPath)) {
                        @unlink($tmpPath);
                    }
                }

                $draf = !empty($pub['hasil_id'])
                    ? HasilPemeriksaan::where('sesi_id', $sesi->id)->find($pub['hasil_id'])
                    : null;
                $isi = [
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
                    'pdf_url'             => $pdfUrl,
                    'ocr_lines'           => is_array($extracted) ? PdfCheckerService::ocrLinesFrom($extracted) : null,
                    // identitas publikasi BPS — untuk dikirim ke SIPOTRET (pasca_rilis.pub_id_api)
                    'pub_id_api'          => $pubId ?: null,
                    'pub_domain'          => ($pub['domain'] ?? null) ?: $request->input('domain'),
                    'pub_issn'            => ($pub['issn'] ?? null) ?: null,
                    'pub_tanggal_rilis'   => $this->tanggal($pub['rl_date'] ?? null),
                    'sipotret_acuan_bulan' => !empty($pub['acuan_bulan']) && !empty($pub['acuan_tahun']) ? (int) $pub['acuan_bulan'] : null,
                    'sipotret_acuan_tahun' => !empty($pub['acuan_bulan']) && !empty($pub['acuan_tahun']) ? (int) $pub['acuan_tahun'] : null,
                ] + (!HasilPemeriksaan::punyaKolomPeriode() ? [] : [
                    // kolom baru (migrasi 2026_11_10_110000); dilewati bila SQL-nya belum dijalankan
                    'periode_bulan'        => !empty($pub['periode_bulan']) && !empty($pub['periode_tahun']) ? (int) $pub['periode_bulan'] : null,
                    'periode_tahun'        => !empty($pub['periode_bulan']) && !empty($pub['periode_tahun']) ? (int) $pub['periode_tahun'] : null,
                ]);

                if ($draf) {
                    // acuan bisa sudah diganti petugas selama meninjau draf → jangan ditimpa
                    $draf->update(array_diff_key($isi, ['sipotret_acuan_bulan' => 1, 'sipotret_acuan_tahun' => 1]));
                    $this->isiDraf($draf, $result['checks'] ?? []);
                    $draf->recalcFromDetail(); // ringkasan ikut keputusan petugas yang sudah tersimpan selama menunggu
                    $hasil = $draf;
                } else {
                    $hasil = HasilPemeriksaan::create($isi);
                }

                if (!$draf && !empty($result['checks'])) {
                    DetailPemeriksaan::insert(array_map(fn($c) => [
                        'hasil_id'    => $hasil->id,
                        'kriteria_id' => $c['id'],
                        'kategori'    => $c['kategori'],
                        'deskripsi'   => $c['deskripsi'],
                        'status'      => $c['status'],
                        'catatan'     => $c['catatan'] ?: null,
                        'lokasi'      => DetailPemeriksaan::encodeLokasi($c['lokasi'] ?? null),
                    ], $result['checks']));
                }

                $result['hasil_id'] = $hasil->id;
                $results[] = $result;
            }

            $sesi->recalcSummary();
            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['error' => 'Gagal: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'sesi_id' => $sesi->id,
            'results' => $results,
        ]);
    }

    /**
     * Isian Web API BPS publikasi ini untuk cek sama_api: detail API diambil server (cache 10 menit);
     * bila gagal, pakai isian yang dikirim halaman. null bila bukan publikasi dari API (tanpa pub_id).
     */
    private function dataApi(array $pub, ?string $domain): ?array
    {
        $pubId = $pub['pub_id'] ?? null;
        if (!$pubId) return null;
        $domain = ($pub['domain'] ?? null) ?: $domain ?: config('services.bps_api.domain', '6300');
        try {
            $d = Cache::remember("bps_detail_api.{$domain}.{$pubId}", now()->addMinutes(10),
                fn() => $this->bps->getPublicationDetail($domain, (string) $pubId)['data'] ?? []);
        } catch (\Throwable $e) {
            report($e);
            $d = [];
        }
        $isi = fn(...$k) => collect($k)->map(fn($x) => $d[$x] ?? null)->first(fn($v) => is_scalar($v) && trim((string) $v) !== '');
        if (!$d) {
            // detail API tidak terbaca: isian dari halaman (bisa kosong) — kriteria yang tak ada datanya jadi Skip
            return ['issn' => $pub['issn'] ?? null, 'katalog' => $pub['catalog'] ?? null, 'nomor_publikasi' => $pub['pub_no'] ?? null];
        }
        return [
            'issn'            => $isi('issn', 'nomorissn', 'isbn'),
            'katalog'         => $isi('kat_no', 'katalog'),
            'nomor_publikasi' => $isi('pub_no', 'nopub'),
        ];
    }

    /**
     * POST /checker/bps-import/draf — buat hasil pemeriksaan lebih dulu (semua kriteria berstatus MENUNGGU)
     * supaya petugas bisa langsung meninjau & menambah ketidaksesuaian selama PDF diunduh/OCR/dicek.
     * Hasil cek otomatis menyusul lewat run() dengan hasil_id ini (isiDraf).
     */
    public function draf(Request $request): JsonResponse
    {
        $request->validate([
            'title'         => 'required|string',
            'pdf'           => 'required|url',
            'pub_id'        => 'nullable|string',
            'domain'        => 'nullable|string|max:10',
            'issn'          => 'nullable|string|max:30',
            'rl_date'       => 'nullable|string|max:30',
            'acuan_bulan'   => 'nullable|integer|between:1,12',
            'acuan_tahun'   => 'nullable|integer|between:2000,2100',
            'periode_bulan' => 'nullable|integer|between:1,12',
            'periode_tahun' => 'nullable|integer|between:2000,2100',
            'sesi_id'       => 'required|integer|exists:sesi_pemeriksaan,id',
        ]);

        $pubId = $request->input('pub_id');
        if ($pubId && ($t = \App\Models\PenugasanPemeriksaan::untuk([$pubId])[$pubId] ?? null)
            && !\App\Models\PenugasanPemeriksaan::boleh($request->user(), $pubId, $t)) {
            return response()->json(['error' => $t->pesanTolak()], 403);
        }

        $sesi     = SesiPemeriksaan::findOrFail($request->input('sesi_id'));
        $filename = $this->sanitizeFilename($request->input('title')) . '.pdf';
        $acuan    = $request->filled('acuan_bulan') && $request->filled('acuan_tahun');
        $periode  = $request->filled('periode_bulan') && $request->filled('periode_tahun');

        $checks = KriteriaPemeriksaan::aktif()->get()->map(fn($k) => [
            'id'        => $k->kode,
            'kategori'  => $k->kategori,
            'deskripsi' => $k->deskripsi,
            'status'    => DetailPemeriksaan::STATUS_MENUNGGU,
            'catatan'   => 'Menunggu pemeriksaan otomatis.',
            'target'    => $k->target,
            'area'      => $k->tipe_cek === 'posisi_area' ? (KriteriaPemeriksaan::areaOptions()[$k->parameter['area'] ?? ''] ?? null) : null,
            'lokasi'    => [],
        ])->all();

        $hasil = DB::transaction(function () use ($request, $sesi, $filename, $acuan, $periode, $pubId, $checks) {
            $hasil = HasilPemeriksaan::create([
                'sesi_id'           => $sesi->id,
                'nama_file'         => $filename,
                'ukuran_file'       => 0,
                'total_halaman'     => 0,
                'metode_ekstraksi'  => 'menunggu',
                'total_ok'          => 0,
                'total_perlu_dicek' => 0,
                'total_tidak_ada'   => 0,
                'total_tdk_diperiksa' => 0,
                'status_akhir'      => 'perlu_dicek',
                // terhapus saat hasil cek otomatis masuk; tetap ada bila tab ditutup sebelum selesai
                'error_msg'         => 'Pemeriksaan otomatis belum selesai (halaman ditutup sebelum unduh/OCR/cek selesai?). Periksa ulang publikasi ini untuk melengkapinya.',
                'pdf_url'           => $request->input('pdf'),
                'pub_id_api'        => $pubId ?: null,
                'pub_domain'        => $request->input('domain') ?: null,
                'pub_issn'          => $request->input('issn') ?: null,
                'pub_tanggal_rilis' => $this->tanggal($request->input('rl_date')),
                'sipotret_acuan_bulan' => $acuan ? (int) $request->input('acuan_bulan') : null,
                'sipotret_acuan_tahun' => $acuan ? (int) $request->input('acuan_tahun') : null,
            ] + (!HasilPemeriksaan::punyaKolomPeriode() ? [] : [
                'periode_bulan' => $periode ? (int) $request->input('periode_bulan') : null,
                'periode_tahun' => $periode ? (int) $request->input('periode_tahun') : null,
            ]));
            DetailPemeriksaan::insert(array_map(fn($c) => [
                'hasil_id'    => $hasil->id,
                'kriteria_id' => $c['id'],
                'kategori'    => $c['kategori'],
                'deskripsi'   => $c['deskripsi'],
                'status'      => $c['status'],
                'catatan'     => $c['catatan'],
                'lokasi'      => null,
            ], $checks));
            $sesi->recalcSummary();
            return $hasil;
        });

        return response()->json([
            'sesi_id' => $sesi->id,
            'result'  => [
                'filename'          => $filename,
                'ukuran_file'       => 0,
                'total_pages'       => 0,
                'extraction_method' => 'menunggu',
                'error'             => '',
                'summary'           => ['ok' => 0, 'perlu_dicek' => 0, 'tidak_ada' => 0, 'tidak_diperiksa' => 0, 'status_akhir' => 'perlu_dicek'],
                'checks'            => $checks,
                'hasil_id'          => $hasil->id,
                'menunggu'          => true,
            ],
        ]);
    }

    /**
     * POST /checker/hasil/{hasil}/scan-ulang — jalankan lagi cek otomatis (kriteria terbaru) untuk hasil yang sedang
     * ditinjau. Kriteria yang sudah dinilai petugas tetap penilaian petugas (isiDraf); yang belum, mendapat status
     * otomatis baru. Teks PDF dikirim dari browser (extracted_b64); tanpa itu dibaca di server.
     */
    public function scanUlang(Request $request, HasilPemeriksaan $hasil): JsonResponse
    {
        set_time_limit(300);
        $request->validate(['extracted' => 'nullable|string', 'extracted_b64' => 'nullable|string']);
        $extracted = PdfCheckerService::extractedDari($request);

        $this->checker->setTanggalRilis($hasil->pub_tanggal_rilis?->format('Y-m-d'));
        $this->checker->setDataApi($this->dataApi(
            ['pub_id' => $hasil->pub_id_api, 'domain' => $hasil->pub_domain, 'issn' => $hasil->pub_issn], null));

        $tmpPath = null;
        try {
            if (is_array($extracted)) {
                $result = $this->checker->checkFromText($extracted, $hasil->nama_file, (int) ($extracted['file_size'] ?? 0));
            } else {
                $lokal = $hasil->pdf_path && \Illuminate\Support\Facades\Storage::disk(HasilPemeriksaan::DISK)->exists($hasil->pdf_path)
                    ? \Illuminate\Support\Facades\Storage::disk(HasilPemeriksaan::DISK)->path($hasil->pdf_path) : null;
                if (!$lokal && !$hasil->pdf_url) {
                    return response()->json(['error' => 'PDF publikasi ini tidak tersimpan, tidak bisa diperiksa ulang.'], 422);
                }
                $path = $lokal ?: ($tmpPath = $this->bps->downloadPdf($hasil->pdf_url));
                $result = $this->checker->check(new \Illuminate\Http\UploadedFile($path, $hasil->nama_file, 'application/pdf', null, true));
            }
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Pemeriksaan ulang gagal: ' . $e->getMessage()], 500);
        } finally {
            if ($tmpPath && file_exists($tmpPath)) @unlink($tmpPath);
        }
        if (empty($result['checks'])) {
            return response()->json(['error' => 'Pemeriksaan ulang gagal: ' . ($result['error'] ?: 'tidak ada hasil cek')], 422);
        }
        // PDF tidak terbaca (terenkripsi tanpa Ghostscript di server, atau tanpa teks): hasil seperti itu jangan
        // menimpa hasil cek otomatis sebelumnya yang sudah benar
        if (($result['extraction_method'] ?? '') === 'secured_unreadable' || (int) ($result['total_pages'] ?? 0) === 0) {
            return response()->json(['error' => 'PDF tidak bisa dibaca di server (terenkripsi). Hasil sebelumnya tidak diubah; '
                . 'scan ulang perlu membaca PDF di browser, coba lagi setelah PDF di panel termuat.'], 422);
        }

        DB::transaction(function () use ($hasil, $result, $extracted) {
            $hasil->update(array_filter([
                'ukuran_file'      => $result['ukuran_file'] ?: null,
                'total_halaman'    => $result['total_pages'] ?: null,
                'metode_ekstraksi' => $result['extraction_method'] ?? null,
                'ocr_lines'        => is_array($extracted) ? PdfCheckerService::ocrLinesFrom($extracted) : null,
            ], fn($v) => $v !== null) + ['error_msg' => $result['error'] ?: null]);
            $this->isiDraf($hasil, $result['checks']);
            $hasil->recalcFromDetail();
            $hasil->sesi?->recalcSummary();
        });

        $result['hasil_id'] = $hasil->id;
        return response()->json(['result' => $result]);
    }

    /**
     * Hasil cek otomatis mengisi draf: kriteria yang belum ditinjau petugas mendapat status otomatis;
     * yang sudah ditinjau tetap memakai penilaian petugas (hanya catatan & lokasi otomatis diperbarui).
     */
    private function isiDraf(HasilPemeriksaan $hasil, array $checks): void
    {
        $ada = $hasil->detail()->get()->keyBy('kriteria_id');
        $baru = [];
        foreach ($checks as $c) {
            $d = $ada[$c['id']] ?? null;
            // update() lewat mutator lokasi (array → JSON); insert() massal perlu JSON sendiri
            $otomatis = ['catatan' => $c['catatan'] ?: null, 'lokasi' => ($c['lokasi'] ?? null) ?: null];
            if (!$d) {
                $baru[] = ['hasil_id' => $hasil->id, 'kriteria_id' => $c['id'], 'kategori' => $c['kategori'],
                    'deskripsi' => $c['deskripsi'], 'status' => $c['status'],
                    'catatan' => $otomatis['catatan'], 'lokasi' => DetailPemeriksaan::encodeLokasi($otomatis['lokasi'])];
            } elseif ($d->ditinjau_at) {
                $d->update($otomatis);
            } else {
                $d->update(['status' => $c['status']] + $otomatis);
            }
        }
        if ($baru) DetailPemeriksaan::insert($baru);
        // pemeriksaan gagal (checks kosong): kriteria yang belum ditinjau tidak lagi "menunggu"
        if (!$checks) {
            $hasil->detail()->whereNull('ditinjau_at')->where('status', DetailPemeriksaan::STATUS_MENUNGGU)
                ->update(['status' => DetailPemeriksaan::STATUS_PERLU_DICEK, 'catatan' => 'Pemeriksaan otomatis gagal, periksa manual.']);
        }
    }

    // ── GET /checker/bps-import/detail ───────────────────────
    public function show(Request $request)
    {
        $pubId = $request->query('pub_id');
        $domain = $request->query('domain', config('services.bps_api.domain', '6300'));

        return view('checker.bps-detail', [
            'pub_id' => $pubId,
            'domain' => $domain,
            // admin: daftar pemeriksa untuk penugasan publikasi ini
            'pemeriksa' => auth()->user()?->isAdmin() ? PenugasanController::daftarPemeriksa() : [],
        ]);
    }

    /**
     * [riwayat per bulan, isian periode terakhir] publikasi ini di SIPOTRET untuk halaman detail.
     * Riwayat null = SIPOTRET belum diatur / tidak bisa dihubungi (bagian riwayat disembunyikan);
     * isian null = tidak ada catatan untuk dilanjutkan. Gagalnya tidak boleh menggagalkan detail publikasi.
     *
     * @return array{0: ?array, 1: ?array}
     */
    private function riwayatSipotret(string $pubId): array
    {
        $sipotret = app(SipotretService::class);
        if (!$sipotret->enabled()) {
            return [null, null];
        }

        try {
            // isian: catatan periode terakhir → isian awal bila petugas memilih "lanjutkan" saat memeriksa
            return [$sipotret->riwayatPublikasi($pubId), $sipotret->isianTerakhir([$pubId])[$pubId] ?? null];
        } catch (\Throwable $e) {
            \Log::warning('Riwayat SIPOTRET gagal dimuat', ['pub_id' => $pubId, 'error' => $e->getMessage()]);
            return [null, null];
        }
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
            if (($result['reason'] ?? '') === 'koneksi') {
                return response()->json([
                    'error' => 'Server BPS (webapi.bps.go.id) tidak bisa dihubungi / koneksi terputus. Coba lagi beberapa saat lagi.'
                ], 502);
            }
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
                'cover'       => $this->coverLewatProxy($pub['cover'] ?? $pub['thumbnail'] ?? null),
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

            [$riwayat, $isian] = $this->riwayatSipotret((string) $publication['pub_id']);

            $tugas = \App\Models\PenugasanPemeriksaan::untuk([$publication['pub_id']])->first();

            return response()->json([
                'success'        => true,
                'publication'    => $publication,
                'sipotret'       => $riwayat,
                'sipotret_isian' => $isian,
                // penugasan pemeriksaan: null = belum ditugaskan (siapa pun pemeriksa boleh)
                'tugas'          => $tugas?->ringkas($request->user()),
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

    /** Tanggal rilis dari API (mis. "2026-02-28") → Y-m-d, atau null bila tidak terbaca. */
    private function tanggal(?string $value): ?string
    {
        $ts = $value ? strtotime($value) : false;
        return $ts ? date('Y-m-d', $ts) : null;
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
                // kolom status_akhir hanya enum ok/perlu_dicek/masalah; kegagalannya tercatat di error_msg
                'status_akhir'    => 'masalah',
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

    /** URL sampul BPS → lewat coverProxy, supaya semua halaman (import, detail, batch) memuat sampul dari server sendiri. */
    private function coverLewatProxy(?string $cover): ?string
    {
        return $cover ? route('checker.bps.cover_proxy', ['url' => $cover]) : null;
    }

    /** Sampul publikasi lewat server — browser sering gagal memuat langsung dari webapi.bps.go.id. */
    public function coverProxy(Request $request)
    {
        $url  = (string) $request->query('url');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host !== 'bps.go.id' && !str_ends_with($host, '.bps.go.id')) {
            abort(403, 'URL tidak diizinkan');
        }

        $gambar = Cache::remember('bps_cover.' . md5($url), now()->addDay(), function () use ($url) {
            try {
                $res = Http::timeout(20)->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);
            } catch (\Throwable $e) {
                return null; // webapi tidak terjangkau
            }
            $type = strtok((string) $res->header('Content-Type'), ';');
            return $res->successful() && str_starts_with($type, 'image/')
                ? ['type' => $type, 'body' => base64_encode($res->body())]
                : null;
        });
        if (!$gambar) {
            Cache::forget('bps_cover.' . md5($url)); // jangan simpan kegagalan sehari penuh
            abort(404);
        }

        return response(base64_decode($gambar['body']), 200, [
            'Content-Type'  => $gambar['type'],
            'Cache-Control' => 'private, max-age=86400',
        ]);
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

        // Koneksi ke webapi.bps.go.id kadang di-reset ("cURL error 56: Connection reset by peer")
        // → coba ulang beberapa kali, dan bila tetap gagal balas 502 (bukan 500 tanpa pesan).
        try {
            $response = Http::timeout(120)
                ->connectTimeout(15)
                ->retry(3, 1000, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException
                    || $e instanceof \GuzzleHttp\Exception\RequestException, throw: false)
                ->withHeaders($headers)
                ->withOptions(['stream' => true])
                ->get($url);
        } catch (\Throwable $e) {
            \Log::warning('BPS pdfProxy gagal terhubung: ' . $e->getMessage());
            return response('Gagal mengambil PDF dari server BPS (koneksi terputus). Silakan coba lagi.', 502)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // Forward status (200 atau 206 Partial Content)
        $status = $response->status();

        // Upstream menolak (mis. 400 "Invalid Token" bila link unduh sudah kedaluwarsa) → jangan dikirim sebagai PDF
        if ($status !== 200 && $status !== 206) {
            $pesan = mb_substr(trim((string) $response->body()), 0, 300);
            \Log::warning("BPS pdfProxy upstream {$status}: {$pesan}");
            return response("Server BPS menolak permintaan PDF (HTTP {$status}). {$pesan}", $status >= 400 ? $status : 502)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // ?nama=<judul> → diunduh sebagai "<judul>.pdf"; tanpa nama → tampil di viewer (inline)
        $nama = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', (string) $request->query('nama')));
        $nama = trim(preg_replace('/\s+/u', ' ', mb_substr($nama, 0, 150)), ' .');
        $disposition = $nama !== ''
            ? HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, "{$nama}.pdf",
                (preg_replace('/[^\x20-\x7E]/u', '_', $nama) ?: 'publikasi') . '.pdf')
            : 'inline; filename="publication.pdf"';

        $responseHeaders = array_filter([
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition,
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