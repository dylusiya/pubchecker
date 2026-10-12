<?php

namespace App\Http\Controllers;

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
            $daftar = $sesi->hasilPemeriksaan()->orderBy('id')->get()->map(function ($hasil) use ($bulan, $tahun) {
                $r = $this->sipotret->rencana($hasil, $bulan, $tahun);
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
                    'items'        => array_map(fn($i) => array_diff_key($i, ['_item' => 1, '_custom' => 1]), $r['items']),
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
        ]);
        [$bulan, $tahun] = $this->periode($request);
        if ($error = $this->cekAktif()) return $error;

        $hasil = $sesi->hasilPemeriksaan()->whereIn('id', $request->input('hasil_ids'))->orderBy('id')->get();
        $laporan = $hasil->map(function ($h) use ($bulan, $tahun) {
            try {
                return ['id' => $h->id, 'judul' => $h->judul] + $this->sipotret->kirim($h, $bulan, $tahun);
            } catch (\Throwable $e) {
                report($e);
                return ['id' => $h->id, 'judul' => $h->judul, 'error' => $e->getMessage()];
            }
        });

        return response()->json(['bulan' => $bulan, 'tahun' => $tahun, 'laporan' => $laporan]);
    }

    /** Periode pemeriksaan SIPOTRET; bawaan bulan & tahun berjalan (sama seperti menu Pasca Rilis). */
    private function periode(Request $request): array
    {
        $request->validate([
            'bulan' => 'nullable|integer|between:1,12',
            'tahun' => 'nullable|integer|between:2000,2100',
        ]);
        return [(int) ($request->input('bulan') ?: date('n')), (int) ($request->input('tahun') ?: date('Y'))];
    }

    private function cekAktif(): ?JsonResponse
    {
        return $this->sipotret->enabled()
            ? null
            : response()->json(['error' => 'Koneksi SIPOTRET belum diatur (SIPOTRET_DB_* di .env).'], 422);
    }
}
