<?php

namespace App\Http\Controllers;

use App\Models\HasilPemeriksaan;
use App\Models\PenugasanPemeriksaan;
use App\Models\User;
use App\Services\SipotretService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Penugasan pemeriksaan per publikasi BPS (khusus admin). */
class PenugasanController extends Controller
{
    /** Pemeriksa yang bisa ditugaskan (admin & pemeriksa), untuk pilihan di halaman. */
    public static function daftarPemeriksa(): array
    {
        return User::whereIn('role', ['admin', 'pemeriksa'])->orderBy('name')->get()
            ->map(fn($u) => ['id' => $u->id, 'nama' => $u->full_name, 'role' => $u->role])->all();
    }

    /** Status pengerjaan tugas pada bulan terpilih → [label, warna badge, ikon]. */
    public const STATUS = [
        'belum'    => ['Belum diperiksa',            'bg-red-lt',    'circle-dashed'],
        'tinjau'   => ['Tinjauan belum selesai',     'bg-yellow-lt', 'clipboard'],
        'selesai'  => ['Tinjauan selesai, belum kirim', 'bg-azure-lt', 'clipboard-check'],
        'terkirim' => ['Terkirim ke SIPOTRET',       'bg-green-lt',  'send'],
        // diperiksa & dicatat langsung di aplikasi SIPOTRET (tanpa hasil pemeriksaan pubchecker)
        'sipotret' => ['Tercatat di SIPOTRET',       'bg-green-lt',  'circle-check'],
        // tidak bermasalah lagi: periode tepat sebelumnya tanpa catatan tidak sesuai (baris tanpa catatan / sudah tidak
        // ada di SIPOTRET), atau diperiksa periode ini & semua kriteria Sesuai ("Tandai semua sesuai");
        // perbaikan yang sudah di-approve belum tentu sesuai, jadi tidak dihitung
        'ok'       => ['Sudah sesuai',               'bg-green-lt',  'rosette-discount-check'],
    ];

    // ── GET /checker/tugas?bulan=&tahun=&status=&pemeriksa= — Tugas Saya per bulan ──
    public function index(Request $request, SipotretService $sipotret)
    {
        $request->validate([
            'bulan'     => 'nullable|integer|between:1,12',
            'tahun'     => 'nullable|integer|between:2000,2100',
            'status'    => 'nullable|in:' . implode(',', array_keys(self::STATUS)),
            'pemeriksa' => 'nullable|integer',
            'wilayah'   => 'nullable|string|max:10',
        ]);
        $user  = $request->user();
        // bulan/tahun = PERIODE pemeriksaan (seperti di SIPOTRET); periode Oktober diperiksa di bulan November.
        // Bawaan: periode bulan lalu (sekarang November → periode Oktober).
        $lalu  = now()->subMonthNoOverflow();
        $bulan = $request->integer('bulan') ?: $lalu->month;
        $tahun = $request->integer('tahun') ?: $lalu->year;
        $waktuPeriksa = \Illuminate\Support\Carbon::create($tahun, $bulan, 1)->addMonthNoOverflow(); // bulan pemeriksaan dilakukan
        // admin bisa melihat tugas semua pemeriksa (kosong = semua); pemeriksa hanya tugasnya sendiri
        $pemeriksaId = $user->isAdmin() ? ($request->integer('pemeriksa') ?: null) : $user->id;

        $tugas = PenugasanPemeriksaan::with('user:id,name,first_name,last_name', 'penugas:id,name,first_name,last_name')
            ->when($pemeriksaId, fn($q) => $q->where('user_id', $pemeriksaId))
            ->orderBy('judul')->get();
        $pubIds = $tugas->pluck('pub_id_api')->all();

        // hasil pemeriksaan publikasi-publikasi ini (terbaru dulu) + progres tinjauan
        $hasil = HasilPemeriksaan::whereIn('pub_id_api', $pubIds)
            ->withCount(['detail', 'detail as ditinjau_count' => fn($q) => $q->whereNotNull('ditinjau_at'),
                // "Tandai semua sesuai": tinjauan selesai tanpa Tidak Sesuai & catatan tambahan → Sudah sesuai
                'detail as ts_count' => fn($q) => $q->where('status', \App\Models\DetailPemeriksaan::STATUS_TIDAK_SESUAI),
                'catatanTambahan'])
            ->orderByDesc('created_at')
            ->get(array_merge(['id', 'sesi_id', 'pub_id_api', 'status_akhir', 'sipotret_terkirim_at', 'sipotret_history_id', 'created_at'],
                HasilPemeriksaan::punyaKolomPeriode() ? ['periode_bulan', 'periode_tahun'] : []))
            ->groupBy('pub_id_api');
        $periodeKirim = []; // history_id SIPOTRET → periode tempat hasil itu dikirim

        // status di SIPOTRET pada periode yang sama (catatan & tahap perbaikan); gagal → kolom dikosongkan
        // + acuan: catatan SIPOTRET terakhir SEBELUM periode ini (kartu, approve, file perbaikan, catatan & riwayat
        //   seperti Publikasi Bermasalah) beserta isian awal tinjauannya
        $sip = null;
        $acuan = [];
        $kartuSip = [];
        $sebelum = [];
        $isian = [];
        if ($sipotret->enabled()) {
            try {
                $sip = $sipotret->statusPeriode($pubIds, $bulan, $tahun);
                $periodeKirim = $sipotret->periodeHistory($hasil->flatten()->pluck('sipotret_history_id')->all());
                $acuan = $sipotret->acuanSebelum($pubIds, $bulan, $tahun);
                // kartu = baris SIPOTRET periode ini (seperti Publikasi Bermasalah per bulan): catatan, perbaikan & approve
                // periode ini; isian awal tinjauan tetap dari catatan sebelumnya ($acuan)
                $kartuSip = $sipotret->barisPeriode($pubIds, $bulan, $tahun);
                $sebelum = $sipotret->statusSebelum($pubIds, $bulan, $tahun);
                foreach (collect($acuan)->groupBy(fn($a) => $a['bulan'] . '-' . $a['tahun']) as $grup) {
                    $isian += $sipotret->isianPeriode($grup->pluck('pub_id_api')->all(), $grup[0]['bulan'], $grup[0]['tahun']);
                }
            } catch (\Throwable $e) { report($e); }
        }

        // periode sebuah hasil, dari yang paling pasti: periode tempat ia dikirim ke SIPOTRET → periode pemeriksaan
        // yang dicatat saat Periksa ditekan → bulan sebelum tanggal pemeriksaan (diperiksa November = periode Oktober)
        $periodeHasil = function ($h) use ($periodeKirim) {
            if ($h->sipotret_history_id && isset($periodeKirim[(int) $h->sipotret_history_id])) {
                return $periodeKirim[(int) $h->sipotret_history_id];
            }
            if ($h->periode_bulan && $h->periode_tahun) {
                return ['bulan' => (int) $h->periode_bulan, 'tahun' => (int) $h->periode_tahun];
            }
            $p = $h->created_at?->copy()->subMonthNoOverflow();
            return $p ? ['bulan' => $p->month, 'tahun' => $p->year] : null;
        };
        $diBulan = fn($h) => ($x = $periodeHasil($h)) && $x['bulan'] === $bulan && $x['tahun'] === $tahun;
        $baris = $tugas->map(function ($t) use ($hasil, $sip, $diBulan, $kartuSip, $sebelum) {
            $lalu = $sebelum[$t->pub_id_api] ?? null;
            $semua = $hasil[$t->pub_id_api] ?? collect();
            $bulanIni = $semua->filter($diBulan)->values();
            $akhir = $bulanIni->first();
            $tinjauSelesai = $akhir && $akhir->detail_count > 0 && $akhir->ditinjau_count === $akhir->detail_count;
            $terkirim = $bulanIni->first(fn($h) => $h->sipotret_terkirim_at);
            // diperiksa periode ini & semua kriteria Sesuai/Skip tanpa catatan tambahan: tidak ada yang perlu dikirim
            $sesuaiDiperiksa = $tinjauSelesai && (int) $akhir->ts_count === 0 && (int) $akhir->catatan_tambahan_count === 0;
            $status = match (true) {
                // tanpa hasil pubchecker tapi periode ini sudah tercatat di SIPOTRET (diperiksa langsung di SIPOTRET)
                !$akhir && !empty($sip[$t->pub_id_api]) => 'sipotret',
                // belum diperiksa periode ini, tapi sudah tidak bermasalah sejak periode sebelumnya
                !$akhir && $lalu && $lalu['catatan'] === 0 => 'ok',
                !$akhir        => 'belum',
                (bool) $terkirim => 'terkirim',
                $sesuaiDiperiksa => 'ok',
                $tinjauSelesai => 'selesai',
                default        => 'tinjau',
            };
            // baris kartu (bentuk SipotretService::susunDaftar); tanpa catatan SIPOTRET sebelumnya → baris kosong
            $kartu = ($kartuSip[$t->pub_id_api] ?? [
                'history_id' => null, 'pub_id_api' => $t->pub_id_api, 'bulan' => null, 'tahun' => null,
                'rev' => null, 'tahap' => null, 'file_rev' => null, 'diubah_at' => null, 'catatan' => [], 'riwayat' => [],
            ]);
            $kartu['judul']   = $t->judul ?: ($kartu['judul'] ?? $t->pub_id_api);
            $kartu['wilayah'] = $t->pub_domain ?: ($kartu['wilayah'] ?? null);
            return [
                'kartu'     => $kartu,
                'lalu'      => $lalu,           // status SIPOTRET terakhir sebelum periode ini
                'tugas'     => $t,
                'status'    => $status,
                'sesuaiDiperiksa' => $sesuaiDiperiksa, // "Sudah sesuai" karena diperiksa (bukan karena periode lalu bersih)
                'hasil'     => $akhir,          // pemeriksaan terakhir di bulan ini
                'jumlah'    => $bulanIni->count(),
                'terkirim'  => $terkirim,
                'terakhir'  => $semua->first(),  // pemeriksaan terakhir kapan pun (petunjuk bila bulan ini belum)
                'sipotret'  => $sip === null ? null : ($sip[$t->pub_id_api] ?? false), // false = tidak tercatat di periode ini
            ];
        });

        // urut per wilayah (urutan kab/kota seperti di SIPOTRET), lalu judul; wilayah tak dikenal di akhir
        $urutWilayah = array_flip(array_map('strval', array_keys(SipotretService::WILAYAH)));
        $baris = $baris->sortBy(fn($r) => [
            $urutWilayah[(string) $r['kartu']['wilayah']] ?? PHP_INT_MAX,
            mb_strtolower((string) $r['tugas']->judul),
        ])->values();
        $wilayahAda = $baris->map(fn($r) => (string) $r['kartu']['wilayah'])->unique()->values()->all();
        $wilayah = $request->input('wilayah');
        if ($wilayah !== null && $wilayah !== '') $baris = $baris->filter(fn($r) => (string) $r['kartu']['wilayah'] === (string) $wilayah)->values();

        $jumlah = ['semua' => $baris->count()] + $baris->countBy('status')->all() + array_fill_keys(array_keys(self::STATUS), 0);
        $status = $request->input('status');
        if ($status) $baris = $baris->where('status', $status)->values();

        return view('checker.tugas', [
            'baris'       => $baris,
            'jumlah'      => $jumlah,
            'bulan'       => $bulan,
            'tahun'       => $tahun,
            'waktuPeriksa' => $waktuPeriksa,
            'periodeKini' => ['bulan' => $lalu->month, 'tahun' => $lalu->year],
            'status'      => $status,
            'wilayah'     => $wilayah,
            'wilayahAda'  => $wilayahAda,
            'pemeriksaId' => $pemeriksaId,
            'pemeriksa'   => $user->isAdmin() ? self::daftarPemeriksa() : [],
            'sipotretAda' => $sip !== null,
            'isian'       => $isian,
            // approve file perbaikan SIPOTRET: sama dengan Publikasi Bermasalah (provinsi & pemeriksa)
            'bisaApprove' => $user->isProvinsi() && $user->canPeriksa(),
            'tugasRingkas' => $baris->mapWithKeys(fn($r) => [$r['tugas']->pub_id_api => $r['tugas']->ringkas($user)])->all(),
        ]);
    }

    // ── POST /checker/penugasan — tugaskan / lepas penugasan beberapa publikasi sekaligus ──
    // body: { pubs: [{ pub_id, judul?, domain? }], user_id: id pemeriksa | null (lepas penugasan) }
    public function simpan(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Hanya admin yang bisa menugaskan pemeriksaan.');

        $data = $request->validate([
            'pubs'          => 'required|array|min:1|max:200',
            'pubs.*.pub_id' => 'required|string|max:100',
            'pubs.*.judul'  => 'nullable|string|max:500',
            'pubs.*.domain' => 'nullable|string|max:10',
            'user_id'       => 'nullable|integer|exists:users,id',
        ]);

        $user = $data['user_id'] ? User::find($data['user_id']) : null;
        if ($user && !$user->canPeriksa()) {
            return response()->json(['message' => 'User ini bukan pemeriksa (role harus admin atau pemeriksa).'], 422);
        }

        foreach ($data['pubs'] as $p) {
            if (!$user) {
                PenugasanPemeriksaan::where('pub_id_api', $p['pub_id'])->delete();
                continue;
            }
            PenugasanPemeriksaan::updateOrCreate(['pub_id_api' => $p['pub_id']], array_filter([
                'judul'      => $p['judul'] ?? null,
                'pub_domain' => $p['domain'] ?? null,
            ]) + ['user_id' => $user->id, 'ditugaskan_oleh' => $request->user()->id]);
        }

        $tugas = PenugasanPemeriksaan::untuk(array_column($data['pubs'], 'pub_id'));
        return response()->json([
            'tugas' => $tugas->map->ringkas($request->user()),
            'pesan' => $user
                ? count($data['pubs']) . ' publikasi ditugaskan ke ' . $user->full_name . '.'
                : 'Penugasan ' . count($data['pubs']) . ' publikasi dilepas.',
        ]);
    }
}
