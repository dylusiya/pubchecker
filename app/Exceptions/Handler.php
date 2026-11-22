<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Token CSRF kedaluwarsa saat login lokal (419): form terkirim dua kali (login pertama sudah berhasil &
        // memperbarui token), atau halaman login sudah basi (dibuka lama / tombol Back setelah logout).
        // Jangan tampilkan halaman 419: sudah login → lanjut ke dashboard; belum → kembali ke form login.
        $this->renderable(function (HttpException $e, $request) {
            if ($e->getStatusCode() !== 419 || ! $request->routeIs('login.local')) {
                return null;
            }
            if (Auth::check()) {
                return redirect()->intended(route('dashboard'));
            }

            return redirect()->route('login')
                ->withInput($request->only('username'))
                ->with('error', 'Halaman login sudah kedaluwarsa. Silakan login lagi.');
        });
    }
}
