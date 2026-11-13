<?php

namespace App\Http\Controllers;

use App\Models\PenugasanPemeriksaan;
use App\Models\SesiPemeriksaan;
use App\Services\SipotretService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kirim hasil "Tidak Sesuai" satu sesi ke SIPOTRET (Pasca Rilis). */
class SipotretController extends Controller
{
    public function __construct(private SipotretService $sipotret) {}

    // ── GET /checker/riwayat/{sesi}/sipotret?bulan=&tahun= — pratinjau ──
    public function preview(Request $request, SesiPemeriksaan $sesi): JsonResponse
    {
        [$bulan, $tahun] = $this->periode($request);
        if ($error = $this->cekAktif()) return $error;

        try {
            $semua = $sesi->hasilPemeriksaan()->orderBy('id')->get();
            $tugas = PenugasanPemeriksaan::untuk($semua->pluck('pub_id_api'));
            $daftar = $semua->map(function ($hasil) use ($bulan, $tahun, $tugas, $request) {
                $r = $this->sipotret->rencana($hasil, $bulan, $tahun);
                // ditugaskan ke pemeriksa lain: hanya dilihat, tidak bisa dikirim
                $t = $tugas[(string) $hasil->pub_id_api] ?? null;
                if ($t && !PenugasanPemeriksaan::boleh($request->user(), $hasil->pub_id_api, $t)) {
                    $r['bisa'] = false;
                    $r['alasan'] = $t->pesanTolak();
                }
                return [
                    'id'           => $hasil->id,
                    'judul'        => $hasil->judul,
                    'terkirim_at'  => $hasil->sipotret_terkirim_at?->format('d M Y H:i'),
                    'bisa'         => $r['bisa'],
                    'alasan'       => $r['alasan'],
                    'pasca_ada'    => (bool) $r['pasca'],
                    'cocok_judul'  => $r['cocok_judul'],
                    'history_ada'  => (bool) $r['history_id'],
                    'periode'      => $r['periode'],
                    'items'        => array_map(fn($i) => array_diff_key($i, ['_item' => 1, '_custom' => 1, '_row_id' => 1, '_row' => 1]), $r['items']),
                    'hanya_sipotret' => $r['hanya_sipotret'],
                ];
            });
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Tidak bisa membaca database SIPOTRET: ' . $e->getMessage()], 502);
        }

        return response()->json(['bulan' => $bulan, 'tahun' => $tahun, 'hasil' => $daftar]);
    }

    // ── POST /checker/riwayat/{sesi}/sipotret — kirim ──
    public function kirim(Request $request, SesiPemeriksaan $sesi): JsonResponse
    {
        $request->validate([
            'hasil_ids'   => 'required|array|min:1',
            'hasil_ids.*' => 'integer',
            'perbarui'    => 'nullable|boolean',
        ]);
        [$bulan, $tahun] = $this->periode($request);
        if ($error = $this->cekAktif()) return $error;
        $perbarui = $request->boolean('perbarui');

        $hasil = $sesi->hasilPemeriksaan()->whereIn('id', $request->input('hasil_ids'))->orderBy('id')->get();
        $tugas = PenugasanPemeriksaan::untuk($hasil->pluck('pub_id_api'));
        $laporan = $hasil->map(function ($h) use ($bulan, $tahun, $perbarui, $tugas, $request) {
            $t = $tugas[(string) $h->pub_id_api] ?? null;
            if ($t && !PenugasanPemeriksaan::boleh($request->user(), $h->pub_id_api, $t)) {
                return ['id' => $h->id, 'judul' => $h->judul, 'error' => $t->pesanTolak()];
            }
            try {
                return ['id' => $h->id, 'judul' => $h->judul] + $this->sipotret->kirim($h, $bulan, $tahun, $perbarui);
            } catch (\Throwable $e) {
                report($e);
                return ['id' => $h->id, 'judul' => $h->judul, 'error' => $e->getMessage()];
            }
        });

        return response()->json(['bulan' => $bulan, 'tahun' => $tahun, 'laporan' => $laporan]);
    }

    /** Periode pemeriksaan SIPOTRET; bawaan bulan & tahun berjalan (sama seperti menu Pasca Rilis). */
    // ── GET /checker/sipotret/isian?pub_id=&bulan=&tahun= — isian awal dari periode acuan ──
    // Dipakai detail publikasi sebelum memeriksa ("Lanjutkan dari catatan SIPOTRET <periode>").
    public function isian(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pub_id' => 'required|string|max:100',
            'bulan'  => 'required|integer|between:1,12',
            'tahun'  => 'required|integer|between:2000,2100',
        ]);
        if ($error = $this->cekAktif()) return $error;

        try {
            $isian = $this->sipotret->isianPeriode([$data['pub_id']], $data['bulan'], $data['tahun'])[$data['pub_id']] ?? null;
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Tidak bisa membaca database SIPOTRET: ' . $e->getMessage()], 502);
        }

        return response()->json(['isian' => $isian]);
    }

    // ── PATCH /checker/hasil/{hasil}/acuan-sipotret — simpan/ganti periode acuan isian awal ──
    // bulan & tahun kosong = tanpa acuan. Balasan: isian periode itu untuk dipasang ke tinjauan.
    public function acuan(Request $request, \App\Models\HasilPemeriksaan $hasil): JsonResponse
    {
        $data = $request->validate([
            'bulan' => 'nullable|required_with:tahun|integer|between:1,12',
            'tahun' => 'nullable|required_with:bulan|integer|between:2000,2100',
        ]);
        $hasil->update(['sipotret_acuan_bulan' => $data['bulan'] ?? null, 'sipotret_acuan_tahun' => $data['tahun'] ?? null]);

        $isian = null;
        if (!empty($data['bulan']) && $hasil->pub_id_api) {
            if ($error = $this->cekAktif()) return $error;
            try {
                $isian = $this->sipotret->isianPeriode([$hasil->pub_id_api], $data['bulan'], $data['tahun'])[$hasil->pub_id_api] ?? null;
            } catch (\Throwable $e) {
                report($e);
                return response()->json(['error' => 'Tidak bisa membaca database SIPOTRET: ' . $e->getMessage()], 502);
            }
        }

        return response()->json(['acuan' => $hasil->acuanSipotret(), 'isian' => $isian]);
    }

    private function periode(Request $request): array
    {
        $request->validate([
            'bulan' => 'nullable|integer|between:1,12',
            'tahun' => 'nullable|integer|between:2000,2100',
        ]);
        return [(int) ($request->input('bulan') ?: date('n')), (int) ($request->input('tahun') ?: date('Y'))];
    }

    /**
     * POST /checker/sipotret/salin-sebelumnya — salin catatan periode sebelumnya ke periode pemeriksaan untuk
     * publikasi yang belum upload / belum di-approve. simpan=false: pratinjau saja.
     * Publikasi yang ditugaskan ke pemeriksa lain dilewati (admin selalu boleh).
     */
    public function salinSebelumnya(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pub_ids'   => 'required|array|min:1|max:300',
            'pub_ids.*' => 'string|max:100',
            'bulan'     => 'required|integer|between:1,12',
            'tahun'     => 'required|integer|between:2000,2100',
            'simpan'    => 'nullable|boolean',
        ]);
        if ($error = $this->cekAktif()) return $error;

        $tugas = PenugasanPemeriksaan::untuk($data['pub_ids']);
        $boleh = $ditolak = [];
        foreach ($data['pub_ids'] as $id) {
            $t = $tugas[(string) $id] ?? null;
            if ($t && !PenugasanPemeriksaan::boleh($request->user(), $id, $t)) $ditolak[] = ['pub_id' => (string) $id,
                'judul' => $t->judul, 'catatan' => 0, 'baru' => 0, 'sudah' => 0, 'bisa' => false, 'alasan' => $t->pesanTolak()];
            else $boleh[] = $id;
        }
        try {
            $laporan = $this->sipotret->salinSebelumnya($boleh, (int) $data['bulan'], (int) $data['tahun'], $request->boolean('simpan'));
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Gagal menulis/membaca SIPOTRET: ' . $e->getMessage()], 502);
        }
        return response()->json(['laporan' => array_merge($laporan, $ditolak)]);
    }

    private function cekAktif(): ?JsonResponse
    {
        return $this->sipotret->enabled()
            ? null
            : response()->json(['error' => 'Koneksi SIPOTRET belum diatur (SIPOTRET_DB_* di .env).'], 422);
    }
}
