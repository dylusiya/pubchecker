<?php

namespace App\Http\Middleware;

use App\Models\CatatanTambahan;
use App\Models\HasilPemeriksaan;
use App\Models\PenugasanPemeriksaan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tinjau / simpan hasil pemeriksaan hanya untuk pemeriksa yang ditugaskan ke publikasinya (atau admin).
 * Publikasi diambil dari parameter rute {hasil} atau {catatan} (catatan tambahan → hasilnya).
 */
class CekPenugasan
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasil = $request->route('hasil');
        if (!$hasil instanceof HasilPemeriksaan) {
            $catatan = $request->route('catatan');
            $hasil = $catatan instanceof CatatanTambahan ? $catatan->hasil : null;
        }

        if ($hasil?->pub_id_api && !PenugasanPemeriksaan::boleh($request->user(), $hasil->pub_id_api)) {
            $pesan = PenugasanPemeriksaan::where('pub_id_api', $hasil->pub_id_api)->with('user')->first()?->pesanTolak()
                ?? 'Publikasi ini ditugaskan ke pemeriksa lain.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $pesan, 'error' => $pesan], 403);
            }
            return redirect()->back(fallback: route('checker.riwayat'))->with('error', $pesan);
        }

        return $next($request);
    }
}
