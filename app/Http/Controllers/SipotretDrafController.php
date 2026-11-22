<?php

namespace App\Http\Controllers;

use App\Models\HasilPemeriksaan;
use App\Models\PenugasanPraRilis;
use App\Services\SipotretDrafService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Draf publikasi BPS Prov. Kalsel yang sudah diupload PJK di SIPOTRET: daftar per tahun, dan PDF-nya
 * (diteruskan dari OwnCloud/folder SIPOTRET) untuk diperiksa lewat halaman Pemeriksaan (?draf=<tr_id>).
 */
class SipotretDrafController extends Controller
{
    public function __construct(private SipotretDrafService $draf) {}

    // ── GET /checker/sipotret-draf?tahun=&q= ─────────────────
    public function index(Request $request)
    {
        $request->validate(['tahun' => ['nullable', 'regex:/^(semua|\d{4})$/'], 'q' => 'nullable|string|max:100']);
        $aktif  = $this->draf->enabled();
        // "semua" = semua tahun publikasi; bawaan tahun ini
        $tahun  = $request->input('tahun') === 'semua' ? 'semua' : (int) ($request->input('tahun') ?: date('Y'));
        $cari   = $request->input('q');
        $daftarTahun = [];
        $publikasi   = [];
        $error       = null;
        $master      = ['katalog' => [], 'issn' => [], 'users' => []]; // pilihan form Tambah publikasi

        if ($aktif) {
            try {
                $daftarTahun = $this->draf->daftarTahun();
                $publikasi   = $this->draf->daftar($tahun === 'semua' ? null : $tahun, $cari);
                $master      = $this->draf->masterKelengkapan();
            } catch (\Throwable $e) {
                report($e);
                $error = 'Database SIPOTRET tidak bisa dibaca: ' . $e->getMessage();
            }
        }
        if ($tahun !== 'semua' && !in_array($tahun, $daftarTahun, true)) $daftarTahun[] = $tahun;
        rsort($daftarTahun);

        // versi yang sudah pernah diperiksa di pubchecker → hasil terakhirnya
        $diperiksa = [];
        $trIds = collect($publikasi)->pluck('versi')->flatten(1)->pluck('tr_id')->all();
        if ($trIds && HasilPemeriksaan::punyaKolomDraf()) {
            $diperiksa = HasilPemeriksaan::whereIn('sipotret_tr_id', $trIds)->orderBy('id')
                ->get(['id', 'sesi_id', 'sipotret_tr_id', 'status_akhir', 'created_at', 'sipotret_terkirim_at'])
                ->keyBy('sipotret_tr_id')->all();
        }

        $user  = $request->user();
        $tugas = PenugasanPraRilis::untuk(array_column($publikasi, 'pub_id'))
            ->map->ringkas($user)->all();
        $pemeriksa = $user?->isAdmin() && PenugasanPraRilis::aktif() ? PenugasanController::daftarPemeriksa() : [];

        return view('checker.sipotret-draf', compact('aktif', 'tahun', 'cari', 'daftarTahun', 'publikasi', 'diperiksa', 'error',
            'tugas', 'pemeriksa', 'master'));
    }

    // ── GET /checker/sipotret-draf/publikasi/{pub} ───────────
    public function show(int $pub)
    {
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        try {
            $p = $this->draf->detail($pub);
        } catch (\Throwable $e) {
            report($e);
            abort(502, 'Database SIPOTRET tidak bisa dibaca: ' . $e->getMessage());
        }
        abort_unless($p, 404, 'Publikasi tidak ditemukan di SIPOTRET.');

        // hasil pemeriksaan pubchecker (terbaru dulu): versi draf ini, dan — bila sudah rilis — pemeriksaan
        // lewat Import API BPS (pub_id_api yang tercatat di Pasca Rilis SIPOTRET)
        $trIds  = array_column($p['versi'], 'tr_id');
        $apiIds = array_column($p['pasca'], 'pub_id_api');
        $draf   = $trIds && HasilPemeriksaan::punyaKolomDraf();
        $hasil  = !$draf && !$apiIds ? collect() : HasilPemeriksaan::query()
            ->where(function ($q) use ($draf, $trIds, $apiIds) {
                if ($draf)   $q->orWhereIn('sipotret_tr_id', $trIds);
                if ($apiIds) $q->orWhereIn('pub_id_api', $apiIds);
            })
            ->orderByDesc('id')
            ->with(['detail' => fn($q) => $q->where('status', \App\Models\DetailPemeriksaan::STATUS_TIDAK_SESUAI)->orderBy('id'),
                    'catatanTambahan', 'sesi'])
            ->get();
        $flag = array_column($p['versi'], 'flag', 'tr_id');

        // pemeriksaan Pasca Rilis di SIPOTRET per periode (catatan temuan & tahap perbaikan)
        $pascaRiwayat = [];
        $sipotret = app(\App\Services\SipotretService::class);
        foreach ($apiIds as $id) {
            try {
                $pascaRiwayat = array_merge($pascaRiwayat, $sipotret->riwayatPublikasi($id));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        usort($pascaRiwayat, fn($a, $b) => [$b['tahun'], $b['bulan']] <=> [$a['tahun'], $a['bulan']]);

        // pratinjau kiriman ke SIPOTRET per hasil draf: temuan dalam bentuk item SIPOTRET (master / lainnya)
        $kiriman = [];
        foreach ($hasil->filter(fn($h) => $h->sipotret_tr_id) as $h) {
            try {
                $kiriman[$h->id] = $sipotret->temuanItem($h);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $idTerbaru = (int) ($p['versi'][0]['tr_id'] ?? 0);

        // untuk kartu Batalkan Rilis: data Pasca Rilis yang akan ikut terhapus
        $pascaHapus = null;
        if ($p['rilis']) {
            try {
                $pascaHapus = $this->draf->hitungPasca($pub);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // riwayat notifikasi SIPOTRET untuk publikasi ini (ke PJK, ketua tim, admin)
        // pilihan nomor katalog & ISSN untuk form kelengkapan (langkah 1)
        $master = ['katalog' => [], 'issn' => []];
        try {
            $master = $this->draf->masterKelengkapan();
        } catch (\Throwable $e) {
            report($e);
        }

        $notif = ['total' => 0, 'items' => []];
        try {
            $notif = $this->draf->notifikasi($pub, null, null, 100);
        } catch (\Throwable $e) {
            report($e);
        }

        $tugas = PenugasanPraRilis::untuk([$pub])->first();
        $tugasRingkas = $tugas?->ringkas(auth()->user());
        $boleh = PenugasanPraRilis::boleh(auth()->user(), $pub, $tugas);
        $pemeriksa = auth()->user()?->isAdmin() && PenugasanPraRilis::aktif() ? PenugasanController::daftarPemeriksa() : [];

        return view('checker.sipotret-draf-detail', compact('p', 'hasil', 'flag', 'pascaRiwayat', 'kiriman', 'idTerbaru', 'pascaHapus', 'notif',
            'tugas', 'tugasRingkas', 'boleh', 'pemeriksa', 'master'));
    }

    // ── POST /checker/sipotret-draf/penugasan — admin menugaskan / melepas pemeriksaan pra rilis ──
    // body: { pubs: [{ pub_id, judul?, domain? (dipakai sebagai tgl_rilis) }], user_id: id pemeriksa | null (lepas) }
    public function tugaskan(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Hanya admin yang bisa menugaskan pemeriksaan.');
        if (!PenugasanPraRilis::aktif()) {
            return response()->json(['message' => 'Tabel penugasan_pra_rilis belum dibuat: jalankan migrasi / SQL 2026_11_21_090000.'], 422);
        }
        $data = $request->validate([
            'pubs'          => 'required|array|min:1|max:200',
            'pubs.*.pub_id' => 'required|integer|min:1',
            'pubs.*.judul'  => 'nullable|string|max:500',
            'pubs.*.domain' => 'nullable|date', // rencana rilis (nama field mengikuti js/penugasan.js)
            'user_id'       => 'nullable|integer|exists:users,id',
        ]);
        $user = $data['user_id'] ? \App\Models\User::find($data['user_id']) : null;
        if ($user && !$user->canPeriksa()) {
            return response()->json(['message' => 'User ini bukan pemeriksa (role harus admin atau pemeriksa).'], 422);
        }

        foreach ($data['pubs'] as $x) {
            if (!$user) {
                PenugasanPraRilis::where('sipotret_pub_id', $x['pub_id'])->delete();
                continue;
            }
            PenugasanPraRilis::updateOrCreate(['sipotret_pub_id' => (int) $x['pub_id']], array_filter([
                'judul'     => $x['judul'] ?? null,
                'tgl_rilis' => $x['domain'] ?? null,
            ]) + ['user_id' => $user->id, 'ditugaskan_oleh' => $request->user()->id]);
        }
        \Log::info('Penugasan pra rilis', ['pub_id' => array_column($data['pubs'], 'pub_id'), 'ke' => $user?->id, 'oleh' => $request->user()->name]);

        $ids = array_column($data['pubs'], 'pub_id');
        return response()->json([
            'tugas' => PenugasanPraRilis::untuk($ids)->map->ringkas($request->user()),
            'pesan' => $user ? count($ids) . ' publikasi pra rilis ditugaskan ke ' . $user->full_name . '.'
                             : 'Penugasan ' . count($ids) . ' publikasi pra rilis dilepas.',
        ]);
    }

    // ── GET /checker/sipotret-draf/notifikasi?peran=&q=&page= — semua notifikasi SIPOTRET ──
    public function notifikasi(Request $request)
    {
        $request->validate(['peran' => 'nullable|in:pjk,admin,ketua', 'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        $peran = $request->input('peran');
        $cari  = $request->input('q');
        $per   = 50;
        $hal   = (int) ($request->input('page') ?: 1);
        $error = null;
        $notif = ['total' => 0, 'items' => []];
        try {
            $notif = $this->draf->notifikasi(null, $peran, $cari, $per, ($hal - 1) * $per);
        } catch (\Throwable $e) {
            report($e);
            $error = 'Database SIPOTRET tidak bisa dibaca: ' . $e->getMessage();
        }
        $halaman = new \Illuminate\Pagination\LengthAwarePaginator($notif['items'], $notif['total'], $per, $hal,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('checker.sipotret-notifikasi', compact('halaman', 'peran', 'cari', 'error'));
    }

    // ── POST /checker/sipotret-draf/publikasi/{pub}/sprp — terima / tolak berkas SPRP ──
    // ── POST /checker/sipotret-draf/publikasi/{pub}/kelengkapan — langkah 1: isi/edit kelengkapan publikasi ──
    public function kelengkapan(Request $request, int $pub)
    {
        $data = $request->validate([
            'pub_no'           => 'nullable|string|max:100',
            'pub_volume'       => 'nullable|string|max:50',
            'pub_no_katalog'   => 'nullable|string|max:100',
            'pub_issn'         => 'nullable|string|max:50',
            'pub_judul_en'     => 'nullable|string|max:500',
            'pub_judul'        => 'sometimes|required|string|max:500',
            'pub_status'       => 'sometimes|required|in:arc,non_arc',
            'us_id'            => 'sometimes|required|integer|min:1',
            'spv_id'           => 'sometimes|nullable|integer|min:1',
        ], ['pub_judul.required' => 'Judul publikasi tidak boleh kosong.', 'us_id.required' => 'PJK harus dipilih.']);
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        if ($pesan = PenugasanPraRilis::tolak($request->user(), $pub)) return back()->with('error', $pesan);
        try {
            $pesan = $this->draf->simpanKelengkapan($pub, $data);
            // judul di penugasan pubchecker ikut diperbarui supaya daftar tugas tidak memakai judul lama
            if (!empty($data['pub_judul'])) PenugasanPraRilis::where('sipotret_pub_id', $pub)->update(['judul' => $data['pub_judul']]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan ke SIPOTRET: ' . $e->getMessage());
        }
        \Log::info('SIPOTRET kelengkapan diubah', ['pub_id' => $pub, 'oleh' => $request->user()?->name]);
        return redirect()->to(route('checker.sipotret_draf.show', $pub) . '#kartuAlur')->with('success', $pesan);
    }

    // ── POST /checker/sipotret-draf/publikasi — tambah publikasi baru ke SIPOTRET (seperti form input publikasi) ──
    public function tambah(Request $request)
    {
        $data = $request->validate([
            'pub_judul'      => 'required|string|max:500',
            'pub_tgl_rilis'  => 'required|date_format:Y-m-d',
            'pub_status'     => 'required|in:arc,non_arc',
            'us_id'          => 'required|integer|min:1',
            'spv_id'         => 'nullable|integer|min:1',
            'pub_judul_en'   => 'nullable|string|max:500',
            'pub_no'         => 'nullable|string|max:100',
            'pub_volume'     => 'nullable|string|max:50',
            'pub_no_katalog' => 'nullable|string|max:100',
            'pub_issn'       => 'nullable|string|max:50',
        ], ['pub_judul.required' => 'Judul publikasi wajib diisi.', 'pub_tgl_rilis.required' => 'Rencana rilis wajib diisi.',
            'us_id.required' => 'PJK wajib dipilih.']);
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        try {
            $pubId = $this->draf->tambahPublikasi($data);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage())->with('buka_tambah', true);
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan ke SIPOTRET: ' . $e->getMessage())->with('buka_tambah', true);
        }
        \Log::info('SIPOTRET publikasi ditambah lewat pubchecker', ['pub_id' => $pubId, 'oleh' => $request->user()?->name]);
        return redirect()->to(route('checker.sipotret_draf.show', $pubId) . '#langkah-1')
            ->with('success', 'Publikasi ditambahkan ke SIPOTRET. PJK sekarang bisa upload draf; lengkapi isian yang belum ada di langkah 1.');
    }

    /** Abstraksi biasanya diisi PJK di SIPOTRET; admin/pemeriksa bisa melengkapi/membetulkan dari sini. */
    public function abstraksi(Request $request, int $pub)
    {
        $data = $request->validate([
            'abstraksi_id' => 'nullable|string|max:20000',
            'abstraksi_en' => 'nullable|string|max:20000',
        ]);
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        if ($pesan = PenugasanPraRilis::tolak($request->user(), $pub)) return back()->with('error', $pesan);
        try {
            $pesan = $this->draf->simpanAbstraksi($pub, $data['abstraksi_id'] ?? null, $data['abstraksi_en'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Gagal menyimpan ke SIPOTRET: ' . $e->getMessage());
        }
        \Log::info('SIPOTRET abstraksi diubah', ['pub_id' => $pub, 'oleh' => $request->user()?->name]);
        return redirect()->to(route('checker.sipotret_draf.show', $pub) . '#langkah-2')->with('success', $pesan);
    }

    public function sprp(Request $request, int $pub)
    {
        $request->validate(['aksi' => 'required|in:terima,tolak']);
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        if ($pesan = PenugasanPraRilis::tolak($request->user(), $pub)) return back()->with('error', $pesan);
        try {
            $pesan = $this->draf->putusSprp($pub, $request->input('aksi') === 'terima');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Gagal menyimpan ke SIPOTRET: ' . $e->getMessage());
        }
        \Log::info('SIPOTRET SPRP ' . $request->input('aksi'), ['pub_id' => $pub, 'oleh' => $request->user()?->name]);
        return back()->with('success', $pesan);
    }

    // ── POST /checker/sipotret-draf/publikasi/{pub}/rilis — tandai final & sudah rilis (Set Final SIPOTRET) ──
    public function rilis(Request $request, int $pub)
    {
        $data = $request->validate([
            'tr_id'      => 'required|integer|min:1',
            'pub_id_api' => 'nullable|string|max:100',
            'domain'     => 'nullable|required_with:pub_id_api|digits:4',
            'judul_api'  => 'nullable|string|max:500',
            'rilis_api'  => 'nullable|date',
            'bulan'      => 'required|integer|between:1,12',
            'tahun'      => 'required|integer|between:2000,2100',
        ]);
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        if ($pesan = PenugasanPraRilis::tolak($request->user(), $pub)) return back()->with('error', $pesan);
        try {
            $d = $this->draf->draf((int) $data['tr_id']);
            if (!$d || (int) $d->pub_id !== $pub) {
                return back()->with('error', 'Versi draf tidak ditemukan pada publikasi ini.');
            }
            $api = !empty($data['pub_id_api'])
                ? ['pub_id' => $data['pub_id_api'], 'domain' => $data['domain'], 'judul' => $data['judul_api'] ?? null, 'tgl_rilis' => $data['rilis_api'] ?? null]
                : [];
            $pesan = $this->draf->tandaiRilis((int) $data['tr_id'], $api, (int) $data['bulan'], (int) $data['tahun']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Gagal menyimpan ke SIPOTRET: ' . $e->getMessage());
        }
        \Log::info('SIPOTRET tandai rilis', ['pub_id' => $pub, 'tr_id' => $data['tr_id'], 'pub_id_api' => $data['pub_id_api'] ?? null, 'oleh' => $request->user()?->name]);
        return back()->with('success', $pesan);
    }

    // ── POST /checker/sipotret-draf/publikasi/{pub}/upload — admin upload draf atas nama PJK (PJK telat) ──
    public function upload(Request $request, int $pub)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Hanya admin yang bisa upload draf atas nama PJK.');
        $request->validate([
            'file'    => 'required|file|mimes:pdf|max:30720', // batas SIPOTRET 30 MB
            'catatan' => 'nullable|string|max:2000',
            'periksa' => 'nullable|boolean',
        ], ['file.mimes' => 'Draf harus PDF supaya bisa diperiksa.', 'file.max' => 'Ukuran file maksimal 30 MB (batas SIPOTRET).']);
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        try {
            [$trId, $pesan] = $this->draf->uploadDraf($pub, $request->file('file'), $request->input('catatan'), $request->user()->name);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Gagal menyimpan ke SIPOTRET: ' . $e->getMessage());
        }
        \Log::info('SIPOTRET upload draf oleh admin', ['pub_id' => $pub, 'tr_id' => $trId, 'oleh' => $request->user()->name]);
        return $request->boolean('periksa')
            ? redirect()->route('checker.index', ['draf' => $trId])->with('success', $pesan)
            : back()->with('success', $pesan);
    }

    // ── POST /checker/sipotret-draf/publikasi/{pub}/portal — ceklis langkah 5: sudah / belum diupload ke portal ──
    public function portal(Request $request, int $pub)
    {
        $request->validate(['aksi' => 'required|in:tandai,batal']);
        abort_unless(\App\Models\PraRilisPortal::aktif(), 404, 'Tabel pra_rilis_portal belum dibuat (jalankan migrasi / file SQL-nya).');
        if ($pesan = PenugasanPraRilis::tolak($request->user(), $pub)) return back()->with('error', $pesan);
        if ($request->input('aksi') === 'tandai') {
            \App\Models\PraRilisPortal::firstOrCreate(['sipotret_pub_id' => $pub], ['user_id' => $request->user()->id]);
            $pesan = 'Langkah 5 ditandai: PDF final sudah diupload ke portal publikasi BPS.';
        } else {
            \App\Models\PraRilisPortal::where('sipotret_pub_id', $pub)->delete();
            $pesan = 'Tanda upload portal dibatalkan.';
        }
        \Log::info('Pra rilis: ceklis upload portal', ['pub_id' => $pub, 'aksi' => $request->input('aksi'), 'oleh' => $request->user()->name]);
        return redirect()->to(route('checker.sipotret_draf.show', $pub) . '#langkah-5')->with('success', $pesan);
    }

    // ── POST /checker/sipotret-draf/publikasi/{pub}/sprp-upload — admin upload SPRP atas nama PJK (addKelengkapan SIPOTRET) ──
    public function uploadSprp(Request $request, int $pub)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Hanya admin yang bisa upload SPRP atas nama PJK.');
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:30720', // batas SIPOTRET 30 MB
        ], ['file.mimes' => 'SPRP harus PDF atau gambar (JPG/PNG).', 'file.max' => 'Ukuran file maksimal 30 MB (batas SIPOTRET).']);
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        if ($pesan = PenugasanPraRilis::tolak($request->user(), $pub)) return back()->with('error', $pesan);
        try {
            $pesan = $this->draf->uploadSprp($pub, $request->file('file'), $request->user()->name);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Gagal menyimpan ke SIPOTRET: ' . $e->getMessage());
        }
        \Log::info('SIPOTRET upload SPRP oleh admin', ['pub_id' => $pub, 'oleh' => $request->user()->name]);
        return redirect()->to(route('checker.sipotret_draf.show', $pub) . '#langkah-4')->with('success', $pesan);
    }

    // ── POST /checker/sipotret-draf/publikasi/{pub}/batal-rilis — batalkan rilis (revertRilis SIPOTRET) ──
    public function batalRilis(Request $request, int $pub)
    {
        // konfirmasi kedua: ketik "BATALKAN" (selain dialog konfirmasi di browser)
        $data = $request->validate([
            'konfirmasi'  => ['required', 'string', function ($attr, $v, $fail) {
                if (strtoupper(trim($v)) !== 'BATALKAN') $fail('Ketik BATALKAN untuk mengonfirmasi pembatalan rilis.');
            }],
            'hapus_pasca' => 'nullable|boolean',
        ]);
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        if ($pesan = PenugasanPraRilis::tolak($request->user(), $pub)) return back()->with('error', $pesan);
        $hapus = $request->boolean('hapus_pasca');
        try {
            $ringkas = $hapus ? $this->draf->hitungPasca($pub) : null;
            $pesan   = $this->draf->batalRilis($pub, $hapus);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Gagal menyimpan ke SIPOTRET: ' . $e->getMessage());
        }
        \Log::warning('SIPOTRET batal rilis', ['pub_id' => $pub, 'hapus_pasca' => $hapus, 'terhapus' => $ringkas, 'oleh' => $request->user()?->name]);
        return back()->with('success', $pesan);
    }

    // ── POST /checker/hasil/{hasil}/sipotret-draf — kirim hasil pemeriksaan draf ke SIPOTRET ──
    public function kirim(Request $request, HasilPemeriksaan $hasil)
    {
        $request->validate([
            'file'       => 'nullable|file|mimes:pdf|max:30720', // batas upload SIPOTRET 30 MB
            'pakai_draf' => 'nullable|boolean',
        ]);
        // panel tinjauan (fetch) → JSON; form halaman detail → kembali ke halaman itu dengan pesan
        $balas = fn(bool $ok, string $pesan) => $request->expectsJson()
            ? response()->json($ok ? ['message' => $pesan] : ['error' => $pesan], $ok ? 200 : 422)
            : back()->with($ok ? 'success' : 'error', $pesan);
        if (!($hasil->sipotret_tr_id ?? null)) {
            return $balas(false, 'Hasil ini bukan pemeriksaan draf SIPOTRET.');
        }
        if ($hasil->detail()->where('status', \App\Models\DetailPemeriksaan::STATUS_MENUNGGU)->exists()) {
            return $balas(false, 'Pemeriksaan otomatis belum selesai, tunggu atau scan ulang dulu.');
        }

        // PDF hasil pemeriksaan: file yang diupload petugas; atau PDF draf yang diperiksa (tanpa komentar)
        $file = $request->file('file');
        if (!$file && $request->boolean('pakai_draf') && $hasil->pdf_path
            && \Illuminate\Support\Facades\Storage::disk(HasilPemeriksaan::DISK)->exists($hasil->pdf_path)) {
            $file = new \Illuminate\Http\UploadedFile(
                \Illuminate\Support\Facades\Storage::disk(HasilPemeriksaan::DISK)->path($hasil->pdf_path), $hasil->nama_file, 'application/pdf', null, true);
        }

        try {
            $temuan = app(\App\Services\SipotretService::class)->temuanItem($hasil);
            $r = $this->draf->kirimHasil((int) $hasil->sipotret_tr_id, $temuan, $file, $request->user()?->name ?? '-');
        } catch (\RuntimeException $e) {
            return $balas(false, $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return $balas(false, 'Gagal mengirim ke SIPOTRET: ' . $e->getMessage());
        }
        $hasil->forceFill(['sipotret_terkirim_at' => now()])->save();

        return $balas(true, sprintf('Hasil pemeriksaan terkirim ke SIPOTRET: %d item master, %d item lainnya%s.',
            $r['master'], $r['lainnya'], !$r['file'] ? ' (tanpa PDF)'
                : ', PDF hasil pemeriksaan diupload ke ' . (str_starts_with((string) $r['file'], 'owncloud/') ? 'OwnCloud' : 'folder lokal SIPOTRET')));
    }

    // ── GET /checker/sipotret-draf/publikasi/{pub}/berkas/{jenis} — SPRP / SPM ──
    public function berkas(Request $request, int $pub, string $jenis)
    {
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        $b = $this->draf->berkas($pub, $jenis);
        abort_unless($b, 404, 'Berkas belum diupload di SIPOTRET.');
        try {
            $file = $this->draf->ambilFile($b, 'berkas_path');
        } catch (\RuntimeException $e) {
            return response($e->getMessage(), 502)->header('Content-Type', 'text/plain; charset=UTF-8');
        }
        $pdf = $b->ext === 'pdf';
        $res = response()->file($file['path'], [
            'Content-Type'        => $pdf ? 'application/pdf' : 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $pdf && !$request->boolean('unduh') ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $b->nama_file, preg_replace('/[^\x20-\x7E]/u', '_', $b->nama_file)),
            'Cache-Control'       => 'private, max-age=600',
        ]);
        if ($file['hapus']) $res->deleteFileAfterSend();
        return $res;
    }

    // ── GET /checker/sipotret-draf/{tr}/pdf[?berkas=revisi] ──
    public function pdf(Request $request, int $tr)
    {
        abort_unless($this->draf->enabled(), 404, 'Koneksi SIPOTRET belum diatur.');
        $draf = $this->draf->draf($tr);
        abort_unless($draf, 404, 'Draf tidak ditemukan di SIPOTRET.');

        $revisi = $request->query('berkas') === 'revisi'; // file revisi dari IPDS SIPOTRET
        try {
            $file = $this->draf->ambilFile($draf, $revisi ? 'tr_file_revisi' : 'tr_file_upload');
        } catch (\RuntimeException $e) {
            return response($e->getMessage(), 502)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // file revisi IPDS bisa bukan PDF (mis. .docx) → diunduh dengan ekstensi aslinya
        $ext  = $revisi ? (strtolower(pathinfo((string) $draf->tr_file_revisi, PATHINFO_EXTENSION)) ?: 'pdf') : 'pdf';
        $nama = $revisi ? preg_replace('/\.pdf$/i', " - revisi IPDS.{$ext}", $draf->nama_file) : $draf->nama_file;
        $disposisi = HeaderUtils::makeDisposition(
            $request->boolean('unduh') || $ext !== 'pdf' ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $nama, preg_replace('/[^\x20-\x7E]/u', '_', $nama));

        $res = response()->file($file['path'], [
            'Content-Type'        => $ext === 'pdf' ? 'application/pdf' : 'application/octet-stream',
            'Content-Disposition' => $disposisi,
            'Cache-Control'       => 'private, max-age=600',
            // nama file untuk antrean halaman Pemeriksaan
            'X-Nama-File'         => rawurlencode($nama),
        ]);
        if ($file['hapus']) $res->deleteFileAfterSend();
        return $res;
    }
}
