@php
    $isAdmin       = auth()->check() && auth()->user()->role === 'admin';
    $totalSesi     = \App\Models\SesiPemeriksaan::count();
    $totalKriteria = $isAdmin ? \App\Models\KriteriaPemeriksaan::where('aktif', true)->count() : 0;
    // publikasi yang ditugaskan ke user ini (tabel bisa belum ada bila migrasi penugasan belum dijalankan)
    try { $totalTugas = auth()->check() ? \App\Models\PenugasanPemeriksaan::where('user_id', auth()->id())->count() : 0; }
    catch (\Throwable $e) { $totalTugas = 0; }

    $menu = [
        ['route' => 'dashboard',         'active' => 'dashboard',        'icon' => 'home',           'label' => 'Dashboard'],
        ['route' => 'checker.index',     'active' => 'checker.index',    'icon' => 'file-search',    'label' => 'Pemeriksaan', 'can' => 'periksa'],
        ['route' => 'checker.bps.index', 'active' => 'checker.bps.*',    'icon' => 'cloud-download', 'label' => 'Import API BPS'],
        ['route' => 'checker.riwayat',   'active' => 'checker.riwayat*', 'icon' => 'history',        'label' => 'Riwayat Sesi', 'badge' => $totalSesi],
        ['route' => 'checker.tugas',     'active' => 'checker.tugas',    'icon' => 'user-check',     'label' => 'Tugas Saya', 'can' => 'periksa', 'badge' => $totalTugas],
    ];
    $adminMenu = [
        ['route' => 'admin.kriteria.index',        'active' => ['admin.kriteria.index', 'admin.kriteria.create', 'admin.kriteria.edit'], 'icon' => 'list-check', 'label' => 'Kelola Kriteria', 'badge' => $totalKriteria],
        ['route' => 'admin.kriteria.contoh.index', 'active' => 'admin.kriteria.contoh.*', 'icon' => 'photo',      'label' => 'Contoh per Kategori'],
        ['route' => 'admin.users.index',           'active' => 'admin.users.*',           'icon' => 'users',      'label' => 'Manajemen User'],
    ];
    // role 'user' hanya melihat — sembunyikan menu yang butuh izin
    $menu = array_filter($menu, fn ($m) => empty($m['can']) || Gate::allows($m['can']));
    $adminActive = $isAdmin && request()->routeIs('admin.*');
@endphp

{{-- Menu horizontal di bawah header (nama file tetap "sidebar" agar include lama tidak berubah) --}}
<header class="navbar-expand-md">
    <div class="collapse navbar-collapse" id="navbar-menu">
        <div class="navbar">
            <div class="container-xl">
                <ul class="navbar-nav">
                    @foreach($menu as $m)
                        <li class="nav-item {{ request()->routeIs($m['active']) ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route($m['route']) }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-{{ $m['icon'] }}"></i></span>
                                <span class="nav-link-title">{{ $m['label'] }}</span>
                                @if(!empty($m['badge']))
                                    <span class="badge badge-sm bg-primary-lt ms-2">{{ $m['badge'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach

                    @if($isAdmin)
                        <li class="nav-item dropdown {{ $adminActive ? 'active' : '' }}">
                            <a class="nav-link dropdown-toggle" href="#navbar-admin" data-bs-toggle="dropdown"
                               data-bs-auto-close="outside" role="button" aria-expanded="false">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-settings"></i></span>
                                <span class="nav-link-title">Administrator</span>
                            </a>
                            <div class="dropdown-menu">
                                @foreach($adminMenu as $m)
                                    <a class="dropdown-item {{ request()->routeIs(...(array) $m['active']) ? 'active' : '' }}" href="{{ route($m['route']) }}">
                                        <i class="ti ti-{{ $m['icon'] }} dropdown-item-icon"></i> {{ $m['label'] }}
                                        @if(!empty($m['badge']))
                                            <span class="badge badge-sm bg-primary-lt ms-auto">{{ $m['badge'] }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</header>
