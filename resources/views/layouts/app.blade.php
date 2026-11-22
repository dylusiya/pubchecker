<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- 419 (token CSRF basi) → ambil token baru & ulangi permintaan sekali --}}
    {{-- tidak memakai route()/filemtime() langsung: route belum terdaftar (route:cache lama) atau file belum
         terunggah tidak boleh membuat semua halaman error 500 --}}
    @if (file_exists(public_path('js/csrf-retry.js')))
        <script src="{{ asset('js/csrf-retry.js') }}?v={{ filemtime(public_path('js/csrf-retry.js')) }}" data-url="{{ url('csrf-token') }}"></script>
    @endif
    <title>@yield('title', 'Pub Checker') - BPS Kalsel</title>

    {{-- Mode gelap/terang dipasang sebelum CSS dimuat supaya tidak berkedip --}}
    <script>
        try { if (localStorage.getItem('pubchecker.theme') === 'dark') document.documentElement.setAttribute('data-bs-theme', 'dark'); } catch (e) {}
        // sidebar Tabler: bawaan terlipat & melebar saat disorot; "default" = disematkan terbuka (tombol lipat di sidebar)
        try { if (localStorage.getItem('tabler-sidebar') !== 'default') document.documentElement.setAttribute('data-bs-sidebar', 'folded-hover'); }
        catch (e) { document.documentElement.setAttribute('data-bs-sidebar', 'folded-hover'); }
    </script>

    {{-- Tabler (Bootstrap 5) + Tabler Icons — disimpan lokal di assets/vendors --}}
    <link rel="stylesheet" href="{{ asset('assets/vendors/tabler/css/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/tabler-icons/tabler-icons-300.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pubchecker.css') }}?v={{ filemtime(public_path('assets/css/pubchecker.css')) }}">

    @stack('plugin-styles')

    @includeIf('layouts.partials.favicon')

    @stack('styles')
</head>
<body>
    <div class="page">
        {{-- Sidebar kiri (Tabler navbar-vertical, folded-hover). Header atas sengaja DI DALAM .page-wrapper: Tabler 1.6
             menyembunyikan .navbar-vertical bila .page juga punya anak navbar horizontal langsung. --}}
        @include('layouts.partials.sidebar')

        <div class="page-wrapper">
            @include('layouts.partials.navbar')

            {{-- Page header: halaman mengisi @section('pretitle'), @section('page-title'), @section('page-actions') --}}
            @hasSection('page-title')
                <div class="page-header d-print-none">
                    <div class="container-xl">
                        <div class="row g-2 align-items-center">
                            <div class="col">
                                @hasSection('pretitle')
                                    <div class="page-pretitle">@yield('pretitle')</div>
                                @endif
                                <h2 class="page-title">@yield('page-title')</h2>
                                @hasSection('page-subtitle')
                                    <div class="text-secondary mt-1">@yield('page-subtitle')</div>
                                @endif
                            </div>
                            @hasSection('page-actions')
                                <div class="col-auto ms-auto d-print-none">
                                    <div class="btn-list">@yield('page-actions')</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="page-body">
                {{-- .content-wrapper dipakai JS beberapa halaman sebagai tempat menyisipkan alert --}}
                <div class="container-xl content-wrapper">
                    @foreach([
                        'success' => ['success', 'circle-check',   'Berhasil!'],
                        'error'   => ['danger',  'alert-circle',   'Error!'],
                        'info'    => ['info',    'info-circle',    'Info!'],
                        'warning' => ['warning', 'alert-triangle', 'Peringatan!'],
                    ] as $key => [$type, $icon, $label])
                        @if(session($key))
                            <div class="alert alert-{{ $type }} alert-dismissible" role="alert">
                                <div class="d-flex">
                                    <i class="ti ti-{{ $icon }} alert-icon"></i>
                                    <div><strong>{{ $label }}</strong> {{ session($key) }}</div>
                                </div>
                                <a class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></a>
                            </div>
                        @endif
                    @endforeach

                    @yield('content')
                </div>
            </div>

            @include('layouts.partials.footer')
        </div>
    </div>

    <form method="POST" action="{{ route('logout') }}" id="logout-form" class="d-none">@csrf</form>

    <script src="{{ asset('assets/vendors/tabler/js/tabler.min.js') }}"></script>
    <script>
        // Tabler membawa Bootstrap 5 di dalamnya; sediakan global `bootstrap` untuk kode halaman
        window.bootstrap = window.bootstrap || window.tabler?.bootstrap;
    </script>
    {{-- Dialog.confirm / Dialog.alert / form[data-confirm]: modal pengganti dialog bawaan browser --}}
    <script src="{{ asset('js/dialog.js') }}?v={{ filemtime(public_path('js/dialog.js')) }}"></script>
    <script>

        function toggleTheme(e) {
            e?.preventDefault();
            const dark = document.documentElement.getAttribute('data-bs-theme') !== 'dark';
            if (dark) document.documentElement.setAttribute('data-bs-theme', 'dark');
            else document.documentElement.removeAttribute('data-bs-theme');
            try { localStorage.setItem('pubchecker.theme', dark ? 'dark' : 'light'); } catch (err) {}
        }
    </script>

    @stack('plugin-scripts')
    @stack('scripts')
</body>
</html>
