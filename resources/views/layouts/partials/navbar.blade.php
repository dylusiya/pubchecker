<nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
    <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
        <div class="me-3">
            <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-bs-toggle="minimize">
                <span class="icon-menu"></span>
            </button>
        </div>
        <div>
            <a class="navbar-brand brand-logo" href="{{ route('checker.index') }}">
                <h4 class="text-primary mb-0">PUB CHECKER</h4>
            </a>
            <a class="navbar-brand brand-logo-mini" href="{{ route('checker.index') }}">
                <h5 class="text-primary mb-0">PBC</h5>
            </a>
        </div>
    </div>

    <div class="navbar-menu-wrapper d-flex align-items-top">
        <ul class="navbar-nav">
            <li class="nav-item fw-semibold d-none d-lg-block ms-0">
                <h1 class="welcome-text">
                    Selamat Datang,
                    <span class="text-black fw-bold">
                        {{ auth()->user()->first_name ?? auth()->user()->name }}
                    </span>
                </h1>
                <h3 class="welcome-sub-text">
                    BPS {{ auth()->user()->kabupaten ?? auth()->user()->provinsi ?? 'Kalimantan Selatan' }}
                </h3>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto">

            {{-- ── Notifikasi Sesi Terakhir ── --}}
            <li class="nav-item dropdown">
                @php
                    $lastSesi = \App\Models\SesiPemeriksaan::latest()->first();
                    $hasError = $lastSesi && $lastSesi->total_err > 0;
                @endphp
                <a class="nav-link count-indicator" id="notificationDropdown"
                   href="#" data-bs-toggle="dropdown">
                    <i class="icon-bell"></i>
                    @if($hasError)
                        <span class="count"></span>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list pb-0"
                     aria-labelledby="notificationDropdown">
                    <a class="dropdown-item py-3 border-bottom">
                        <p class="mb-0 fw-medium float-start">Sesi Terakhir</p>
                        <span class="badge badge-pill badge-primary float-end">
                            {{ $lastSesi ? $lastSesi->total_file . ' file' : '-' }}
                        </span>
                    </a>
                    @if($lastSesi)
                    <a href="{{ route('checker.riwayat.detail', $lastSesi) }}"
                       class="dropdown-item preview-item py-3">
                        <div class="preview-thumbnail">
                            <i class="mdi mdi-file-search-outline text-primary"></i>
                        </div>
                        <div class="preview-item-content">
                            <h6 class="preview-subject fw-normal text-dark mb-1">
                                Sesi #{{ $lastSesi->id }}
                            </h6>
                            <p class="fw-light small-text mb-0">
                                {{ $lastSesi->created_at->diffForHumans() }} &nbsp;·&nbsp;
                                <span class="text-success">✓ {{ $lastSesi->total_ok }}</span>
                                <span class="text-danger ms-1">✗ {{ $lastSesi->total_err }}</span>
                            </p>
                        </div>
                    </a>
                    @else
                    <div class="dropdown-item preview-item py-3">
                        <div class="preview-item-content">
                            <p class="fw-light small-text mb-0 text-muted">
                                Belum ada sesi pemeriksaan
                            </p>
                        </div>
                    </div>
                    @endif
                    <a href="{{ route('checker.riwayat') }}"
                       class="dropdown-item preview-item border-top py-3">
                        <p class="mb-0">
                            <span class="fw-normal">Lihat Semua Riwayat</span>
                            <i class="mdi mdi-arrow-right"></i>
                        </p>
                    </a>
                </div>
            </li>

            {{-- ── Profil User ── --}}
            <li class="nav-item dropdown d-none d-lg-block user-dropdown">
                <a class="nav-link" id="UserDropdown" href="#"
                   data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                         style="width:35px; height:35px;">
                        <span class="text-white fw-bold" style="font-size:14px;">
                            {{ strtoupper(substr(auth()->user()->first_name ?? auth()->user()->name, 0, 1)) }}
                        </span>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right navbar-dropdown"
                     aria-labelledby="UserDropdown">

                    <div class="dropdown-header text-center">
                        <div class="bg-primary rounded-circle mb-2 d-flex align-items-center justify-content-center mx-auto"
                             style="width:50px; height:50px;">
                            <span class="text-white fw-bold" style="font-size:20px;">
                                {{ strtoupper(substr(auth()->user()->first_name ?? auth()->user()->name, 0, 1)) }}
                            </span>
                        </div>
                        <p class="mb-1 mt-2 fw-semibold">{{ auth()->user()->name }}</p>
                        <p class="fw-light text-muted mb-0 small">
                            {{ auth()->user()->email ?? auth()->user()->username }}
                        </p>
                        <p class="fw-light text-muted mb-0 small">
                            {{ auth()->user()->jabatan ?? 'Pegawai BPS' }}
                        </p>
                    </div>

                    <div class="dropdown-divider"></div>

                    <a class="dropdown-item" href="{{ route('profile.index') }}">
                        <i class="dropdown-item-icon mdi mdi-account-outline text-primary me-2"></i>
                        My Profile
                    </a>

                    <div class="dropdown-divider"></div>

                    <form method="POST" action="{{ route('logout') }}" id="logout-form">
                        @csrf
                        <a class="dropdown-item" href="#"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>
                            Sign Out
                        </a>
                    </form>
                </div>
            </li>

        </ul>

        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center"
                type="button" data-bs-toggle="offcanvas">
            <span class="mdi mdi-menu"></span>
        </button>
    </div>
</nav>