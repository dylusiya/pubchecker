<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\SipotretService;
use Symfony\Component\HttpFoundation\HeaderUtils;

class HomeController extends Controller
{
    public function index(Request $request, SipotretService $sipotret)
    {
        // Pastikan sudah login
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Rekap Pasca Rilis SIPOTRET per kab/kota per bulan — dashboard tetap tampil bila SIPOTRET tak terjangkau
        $tahun = (int) $request->input('tahun', date('Y'));
        $tahun = $tahun >= 2000 && $tahun <= 2100 ? $tahun : (int) date('Y');
        $rekap = null;
        $rekapError = null;
        if (!$sipotret->enabled()) {
            $rekapError = 'Koneksi SIPOTRET belum diatur (SIPOTRET_DB_* di .env).';
        } else {
            try {
                $rekap = $sipotret->rekapWilayah($tahun);
            } catch (\Throwable $e) {
                report($e);
                $rekapError = 'Tidak bisa membaca database SIPOTRET: ' . $e->getMessage();
            }
        }

        return view('home', compact('tahun', 'rekap', 'rekapError'));
    }

    // ── GET /dashboard/sipotret/{wilayah}?tahun=&bulan=&status= — halaman daftar publikasi bermasalah satu kab/kota ──
    public function sipotretMasalah(Request $request, SipotretService $sipotret, int $wilayah)
    {
        $request->validate([
            'tahun'  => 'nullable|integer|between:2000,2100',
            'bulan'  => 'nullable|integer|between:1,12',
            'status' => 'nullable|in:belum,menunggu,approve',
            'tugas'  => 'nullable|in:saya,belum',
        ]);
        // selalu satu bulan; bawaan = bulan lalu (Nov → Okt, Jan → Des tahun sebelumnya).
        // Dari dashboard tahun lalu (tanpa bulan) → Desember tahun itu.
        $lalu  = now()->subMonthNoOverflow();
        $tahun = $request->integer('tahun') ?: $lalu->year;
        $bulan = $request->integer('bulan') ?: null;
        if (!$bulan) {
            [$bulan, $tahun] = $tahun >= (int) date('Y') ? [$lalu->month, $lalu->year] : [12, $tahun];
        }
        $status = $request->input('status');
        $nama   = SipotretService::WILAYAH[$wilayah] ?? "Domain {$wilayah}";

        $daftar = [];
        $isian  = [];
        $error  = null;
        if (!$sipotret->enabled()) {
            $error = 'Koneksi SIPOTRET belum diatur (SIPOTRET_DB_* di .env).';
        } else {
            try {
                $daftar = $sipotret->daftarMasalah($wilayah, $tahun, $bulan);
                // catatan periode yang dipilih di filter (acuan, disimpan di hasil) → isian awal tinjauan saat diperiksa ulang
                $isian  = $sipotret->isianPeriode(array_column($daftar, 'pub_id_api'), $bulan, $tahun);
            } catch (\Throwable $e) {
                report($e);
                $error = 'Tidak bisa membaca database SIPOTRET: ' . $e->getMessage();
            }
        }

        $jumlah = ['semua' => count($daftar)] + array_count_values(array_column($daftar, 'tahap')) + ['belum' => 0, 'menunggu' => 0, 'approve' => 0];
        if ($status) {
            $daftar = array_values(array_filter($daftar, fn($p) => $p['tahap'] === $status));
        }

        // penugasan pemeriksaan per publikasi (pub_id) + filter "Tugas saya" / "Belum ditugaskan"
        $user  = auth()->user();
        $tugas = \App\Models\PenugasanPemeriksaan::untuk(array_column($daftar, 'pub_id_api'))->map->ringkas($user)->all();
        $filterTugas = $request->input('tugas');
        $jumlahTugas = [
            'saya'  => count(array_filter($daftar, fn($p) => ($tugas[$p['pub_id_api']]['saya'] ?? false))),
            'belum' => count(array_filter($daftar, fn($p) => !isset($tugas[$p['pub_id_api']]))),
        ];
        if ($filterTugas) {
            $daftar = array_values(array_filter($daftar, fn($p) => $filterTugas === 'saya'
                ? ($tugas[$p['pub_id_api']]['saya'] ?? false)
                : !isset($tugas[$p['pub_id_api']])));
        }
        $pemeriksa = $user?->isAdmin() ? \App\Http\Controllers\PenugasanController::daftarPemeriksa() : [];
        $bisaApprove = (bool) auth()->user()?->isProvinsi() && auth()->user()->canPeriksa();

        // periode pemeriksaan ulang: sebulan setelah periode SIPOTRET terakhir publikasi-publikasi ini (Sep → Okt)
        $terakhir = collect($daftar)->flatMap(fn($p) => $p['riwayat'])->max(fn($h) => $h['tahun'] * 12 + $h['bulan'] - 1);
        $periodeBaru = $terakhir !== null
            ? ['bulan' => ($terakhir + 1) % 12 + 1, 'tahun' => intdiv($terakhir + 1, 12)]
            : ['bulan' => (int) date('n'), 'tahun' => (int) date('Y')];

        return view('sipotret-masalah', compact('wilayah', 'nama', 'tahun', 'bulan', 'status', 'daftar', 'jumlah', 'error', 'bisaApprove', 'periodeBaru', 'isian',
            'tugas', 'filterTugas', 'jumlahTugas', 'pemeriksa'));
    }

    // ── GET /dashboard/sipotret/file/{history}?nama= — file perbaikan yang diunggah di SIPOTRET ──
    // Dilayani lewat pubchecker supaya satu origin (viewer pdf.js & pemeriksaan di browser). ?nama= → diunduh.
    public function sipotretFile(Request $request, SipotretService $sipotret, int $history)
    {
        $path = $sipotret->enabled() ? $sipotret->fileRev($history) : null;
        // path relatif dari database SIPOTRET — tolak yang keluar folder, absolut, atau berupa URL
        if (!$path || str_contains($path, '..') || preg_match('#^([a-z][a-z0-9+.-]*:|[\\\\/])#i', $path)) {
            abort(404, 'File perbaikan tidak ada di SIPOTRET.');
        }

        $nama = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', (string) $request->query('nama')));
        $disposisi = $nama !== ''
            ? HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, "{$nama}.pdf",
                (preg_replace('/[^\x20-\x7E]/u', '_', $nama) ?: 'perbaikan') . '.pdf')
            : HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, basename($path),
                preg_replace('/[^\x20-\x7E]/u', '_', basename($path)));

        // SIPOTRET satu server: baca langsung dari foldernya (tidak ketemu → coba lewat URL)
        if ($akar = realpath((string) config('services.sipotret.file_path'))) {
            $file = realpath($akar . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
            if ($file && str_starts_with($file, $akar . DIRECTORY_SEPARATOR) && is_file($file)) {
                return response()->file($file, ['Content-Type' => 'application/pdf', 'Content-Disposition' => $disposisi]);
            }
        }

        // SIPOTRET di server lain: ambil lewat URL akarnya
        if ($url = rtrim((string) config('services.sipotret.file_url'), '/')) {
            $res = Http::timeout(120)->withOptions(['stream' => true])
                ->get($url . '/' . implode('/', array_map('rawurlencode', explode('/', $path))));
            abort_unless($res->successful(), 404, 'File perbaikan tidak bisa diambil dari SIPOTRET.');
            return response()->stream(function () use ($res) {
                $body = $res->toPsrResponse()->getBody();
                while (!$body->eof()) {
                    echo $body->read(65536);
                    flush();
                }
            }, 200, array_filter([
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => $disposisi,
                'Content-Length'      => $res->header('Content-Length'),
                'Cache-Control'       => 'private, max-age=600',
            ]));
        }

        abort(404, 'Lokasi file SIPOTRET belum diatur (SIPOTRET_FILE_PATH atau SIPOTRET_FILE_URL di .env).');
    }

    // ── POST /dashboard/sipotret/approve/{history?} — approve file perbaikan yang sudah diunggah ──
    // satu publikasi lewat {history}, atau beberapa sekaligus lewat history_ids[] (centang di daftar)
    public function sipotretApprove(Request $request, SipotretService $sipotret, ?int $history = null)
    {
        // hanya pengguna provinsi; kab/kota tidak meng-approve perbaikannya sendiri
        abort_unless(auth()->user()?->isProvinsi(), 403, 'Hanya pengguna BPS Provinsi yang dapat meng-approve perbaikan.');
        $request->validate(['history_ids' => 'array', 'history_ids.*' => 'integer']);
        $ids = $history ? [$history] : array_unique(array_map('intval', $request->input('history_ids', [])));
        if (!$ids) {
            return back()->with('warning', 'Tidak ada publikasi yang dipilih untuk di-approve.');
        }
        if (!$sipotret->enabled()) {
            return back()->with('error', 'Koneksi SIPOTRET belum diatur (SIPOTRET_DB_* di .env).');
        }

        try {
            $ok = count(array_filter($ids, fn($id) => $sipotret->approve($id)));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Gagal menulis ke SIPOTRET: ' . $e->getMessage());
        }

        $lewat = count($ids) - $ok;
        if (!$ok) {
            return back()->with('warning', 'Tidak ada yang diubah karena status perbaikannya bukan "uploaded" / "uploaded_kabkota" lagi.');
        }
        return back()->with('success', "{$ok} perbaikan disetujui (approve) di SIPOTRET."
            . ($lewat ? " {$lewat} dilewati karena statusnya sudah bukan menunggu approve." : ''));
    }
}
