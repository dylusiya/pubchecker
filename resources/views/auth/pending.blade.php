<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Menunggu Persetujuan - Publication Checker BPS Kalsel</title>

    <link rel="stylesheet" href="{{ asset('assets/vendors/tabler/css/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/tabler-icons/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pubchecker.css') }}?v={{ filemtime(public_path('assets/css/pubchecker.css')) }}">

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32x32.png') }}">
</head>
<body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container container-tight py-4">

            <div class="text-center mb-4">
                <img src="{{ asset('assets/images/logo_bps.webp') }}" alt="Logo BPS" class="d-block mx-auto mb-3"
                     style="height:64px; width:auto;" onerror="this.style.display='none'">
                <h2 class="mb-0">Publication Checker</h2>
                <div class="text-secondary">BPS Provinsi Kalimantan Selatan</div>
            </div>

            <div class="card card-md">
                <div class="card-body text-center">
                    <span class="avatar avatar-lg bg-yellow-lt mb-3"><i class="ti ti-clock-hour-4 fs-1"></i></span>
                    <h3 class="mb-2">Akun menunggu persetujuan</h3>
                    <p class="text-secondary mb-2">
                        Halo <strong>{{ auth()->user()->name }}</strong>, login SSO berhasil.
                        Akun <strong>{{ auth()->user()->username }}</strong> belum diberi akses oleh admin.
                    </p>
                    <p class="text-secondary small mb-4">
                        Silakan hubungi admin Publication Checker untuk mengaktifkan akun Anda, lalu klik <em>Cek Lagi</em>.
                    </p>

                    <a href="{{ route('pending') }}" class="btn btn-primary w-100 mb-2">
                        <i class="ti ti-refresh"></i> Cek Lagi
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="ti ti-logout"></i> Keluar
                        </button>
                    </form>
                </div>
            </div>

            <div class="text-center text-secondary mt-3 small">
                &copy; {{ date('Y') }} BPS Provinsi Kalimantan Selatan
            </div>
        </div>
    </div>
</body>
</html>
