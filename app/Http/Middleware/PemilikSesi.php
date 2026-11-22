<?php

namespace App\Http\Middleware;

use App\Models\SesiPemeriksaan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route ber-{sesi} (Riwayat detail, export, tinjau, kirim SIPOTRET, hapus): hanya pemilik sesi atau admin.
 * Alias "sesi.milik" (app/Http/Kernel.php).
 */
class PemilikSesi
{
    public function handle(Request $request, Closure $next): Response
    {
        $sesi = $request->route('sesi');
        if (!$sesi instanceof SesiPemeriksaan) $sesi = SesiPemeriksaan::find($sesi);
        if ($sesi && !$sesi->boleh($request->user())) {
            abort(403, 'Sesi pemeriksaan ini milik pemeriksa lain.');
        }
        return $next($request);
    }
}
