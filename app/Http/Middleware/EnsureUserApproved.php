<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserApproved
{
    /**
     * User role 'pending' (login SSO pertama, belum didaftarkan/disetujui admin)
     * hanya bisa melihat halaman tunggu sampai admin mengubah role-nya.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->isPending()) {
            if ($request->expectsJson()) {
                abort(403, 'Akun Anda belum disetujui admin.');
            }

            return redirect()->route('pending');
        }

        return $next($request);
    }
}
