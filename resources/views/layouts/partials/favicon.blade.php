{{-- Favicon & ikon aplikasi. ?v= (waktu ubah file) supaya browser langsung memakai ikon baru, tidak tertahan cache.
     File: public/favicon.ico, public/site.webmanifest, public/assets/images/{apple-touch-icon, favicon-32x32,
     favicon-16x16, android-chrome-192x192, android-chrome-512x512}.png --}}
@php $versi = fn(string $f) => file_exists(public_path($f)) ? '?v=' . filemtime(public_path($f)) : ''; @endphp
<link rel="icon" href="{{ asset('favicon.ico') . $versi('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32x32.png') . $versi('assets/images/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/images/favicon-16x16.png') . $versi('assets/images/favicon-16x16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/images/apple-touch-icon.png') . $versi('assets/images/apple-touch-icon.png') }}">
@if(file_exists(public_path('site.webmanifest')))
    <link rel="manifest" href="{{ asset('site.webmanifest') . $versi('site.webmanifest') }}">
@endif
