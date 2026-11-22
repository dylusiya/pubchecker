@php
    $user     = auth()->user();
    $namaUser = $user->first_name ?? $user->name;
    // sesi sendiri (admin: semua); model lama tanpa scope milik() → sesi terakhir siapa pun, bukan error 500
    $lastSesi = (method_exists(\App\Models\SesiPemeriksaan::class, 'scopeMilik')
        ? \App\Models\SesiPemeriksaan::milik($user) : \App\Models\SesiPemeriksaan::query())->latest()->first();
    $hasError = $lastSesi && $lastSesi->total_err > 0;
@endphp

{{-- Header atas (di kanan sidebar): mode gelap, sesi terakhir, akun. Logo & menu ada di sidebar. --}}
<header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
        <div class="navbar-nav flex-row order-md-last ms-auto">
            {{-- Mode gelap/terang --}}
            <div class="d-none d-md-flex">
                <a href="#" class="nav-link px-0 hide-theme-dark" onclick="toggleTheme(event)" title="Mode gelap" data-bs-toggle="tooltip" data-bs-placement="bottom">
                    <i class="ti ti-moon fs-2"></i>
                </a>
                <a href="#" class="nav-link px-0 hide-theme-light" onclick="toggleTheme(event)" title="Mode terang" data-bs-toggle="tooltip" data-bs-placement="bottom">
                    <i class="ti ti-sun fs-2"></i>
                </a>

                {{-- Sesi terakhir --}}
                <div class="nav-item dropdown d-none d-md-flex me-3">
                    <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1" aria-label="Sesi terakhir">
                        <i class="ti ti-bell fs-2"></i>
                        @if($hasError)
                            <span class="badge bg-red"></span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end dropdown-menu-card">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Sesi Terakhir</h3>
                                <span class="badge bg-primary-lt ms-auto">{{ $lastSesi ? $lastSesi->total_file . ' file' : '-' }}</span>
                            </div>
                            <div class="list-group list-group-flush list-group-hoverable">
                                @if($lastSesi)
                                    <a href="{{ route('checker.riwayat.detail', $lastSesi) }}" class="list-group-item list-group-item-action">
                                        <div class="row align-items-center">
                                            <div class="col-auto"><span class="status-dot {{ $hasError ? 'status-dot-animated bg-red' : 'bg-green' }} d-block"></span></div>
                                            <div class="col text-truncate">
                                                <div class="text-body d-block">Sesi #{{ $lastSesi->id }}</div>
                                                <div class="d-block text-secondary text-truncate mt-n1">
                                                    {{ $lastSesi->created_at->diffForHumans() }} ·
                                                    <span class="text-success">✓ {{ $lastSesi->total_ok }}</span>
                                                    <span class="text-danger ms-1">✗ {{ $lastSesi->total_err }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                @else
                                    <div class="list-group-item text-secondary">Belum ada sesi pemeriksaan</div>
                                @endif
                                <a href="{{ route('checker.riwayat') }}" class="list-group-item list-group-item-action text-center">
                                    Lihat Semua Riwayat
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Akun --}}
            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Menu akun">
                    <span class="avatar avatar-sm bg-primary-lt">{{ strtoupper(substr($namaUser, 0, 1)) }}</span>
                    <div class="d-none d-xl-block ps-2">
                        <div>{{ $user->name }}</div>
                        <div class="mt-1 small text-secondary">{{ $user->jabatan ?? 'BPS ' . ($user->kabupaten ?? $user->provinsi ?? 'Kalimantan Selatan') }}</div>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <div class="dropdown-header">
                        <div class="fw-semibold text-body">{{ $user->name }}</div>
                        <div class="small text-secondary">{{ $user->email ?? $user->username }}</div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('profile.index') }}" class="dropdown-item"><i class="ti ti-user dropdown-item-icon"></i> Profil Saya</a>
                    <a href="#" class="dropdown-item d-md-none" onclick="toggleTheme(event)"><i class="ti ti-moon dropdown-item-icon"></i> Mode Gelap/Terang</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="#"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="ti ti-logout dropdown-item-icon"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
