<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak</title>
    <link rel="stylesheet" href="{{ asset('assets/vendors/tabler/css/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/tabler-icons/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pubchecker.css') }}?v={{ filemtime(public_path('assets/css/pubchecker.css')) }}">
</head>
<body class="border-top-wide border-primary d-flex flex-column">
    <div class="page page-center">
        <div class="container-tight py-4">
            <div class="empty">
                <div class="empty-header">403</div>
                <p class="empty-title">Akses Ditolak</p>
                <p class="empty-subtitle text-secondary">
                    {{ $exception->getMessage() ?: 'Anda tidak memiliki akses ke halaman ini.' }}
                </p>
                <div class="empty-action">
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">
                        <i class="ti ti-home"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
