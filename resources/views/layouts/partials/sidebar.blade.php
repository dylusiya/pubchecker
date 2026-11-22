@php
    $isAdmin       = auth()->check() && auth()->user()->role === 'admin';
    // sesi sendiri (admin: semua); model lama tanpa scope milik() → semua sesi, bukan error 500
    $totalSesi     = method_exists(\App\Models\SesiPemeriksaan::class, 'scopeMilik')
        ? \App\Models\SesiPemeriksaan::milik(auth()->user())->count() : \App\Models\SesiPemeriksaan::count();
    $totalKriteria = $isAdmin ? \App\Models\KriteriaPemeriksaan::where('aktif', true)->count() : 0;
    // publikasi yang ditugaskan ke user ini (tabel bisa belum ada bila migrasi penugasan belum dijalankan)
    try { $totalTugas = auth()->check() ? \App\Models\PenugasanPemeriksaan::where('user_id', auth()->id())->count() : 0; }
    catch (\Throwable $e) { $totalTugas = 0; }
    try { $totalTugas += auth()->check() && \App\Models\PenugasanPraRilis::aktif() ? \App\Models\PenugasanPraRilis::where('user_id', auth()->id())->count() : 0; }
    catch (\Throwable $e) {}

    $menu = [
        ['route' => 'dashboard',         'active' => 'dashboard',        'icon' => 'home',           'label' => 'Dashboard'],
        // urutan = siklus publikasi: draf PJK di SIPOTRET (pra rilis) → tayang di Web BPS (pasca rilis)
        ['grup' => 'Pemeriksaan',
         'route' => 'checker.sipotret_draf.index', 'active' => 'checker.sipotret_draf.*', 'icon' => 'file-pencil', 'label' => 'Pra Rilis', 'can' => 'periksa',
         'title' => 'Pemeriksaan pra rilis: draf yang diupload PJK di SIPOTRET'],
        ['route' => 'checker.bps.index', 'active' => 'checker.bps.*',    'icon' => 'world-check',    'label' => 'Pasca Rilis',
         'title' => 'Pemeriksaan pasca rilis: publikasi yang sudah tayang di Web BPS (API)'],
        ['route' => 'checker.index',     'active' => 'checker.index',    'icon' => 'upload',         'label' => 'Upload Manual', 'can' => 'periksa',
         'title' => 'Periksa PDF dari komputer (di luar SIPOTRET / Web BPS)'],
        ['grup' => 'Kerja saya',
         'route' => 'checker.tugas',     'active' => 'checker.tugas',    'icon' => 'user-check',     'label' => 'Tugas Saya', 'can' => 'periksa', 'badge' => $totalTugas],
        ['route' => 'checker.riwayat',   'active' => 'checker.riwayat*', 'icon' => 'history',        'label' => 'Riwayat', 'badge' => $totalSesi,
         'title' => 'Semua sesi pemeriksaan: pra rilis, pasca rilis, dan upload manual'],
    ];
    if ($isAdmin) {
        $menu = array_merge($menu, [
            ['grup' => 'Administrator',
             'route' => 'admin.kriteria.index',        'active' => ['admin.kriteria.index', 'admin.kriteria.create', 'admin.kriteria.edit'], 'icon' => 'list-check', 'label' => 'Kelola Kriteria', 'badge' => $totalKriteria],
            ['route' => 'admin.kriteria.contoh.index', 'active' => 'admin.kriteria.contoh.*', 'icon' => 'photo',      'label' => 'Contoh per Kategori'],
            ['route' => 'admin.users.index',           'active' => 'admin.users.*',           'icon' => 'users',      'label' => 'Manajemen User'],
        ]);
    }
    // role 'user' hanya melihat — sembunyikan menu yang butuh izin
    // route yang belum ada (mis. routes/web.php belum terupload / route cache lama) disembunyikan, bukan error 500
    // setiap item membawa grupnya dulu, supaya judul bagian tetap muncul walau item pertama grup disembunyikan
    $grupKini = null;
    foreach ($menu as &$m) { $grupKini = $m['grup'] ?? $grupKini; $m['grup'] = $grupKini; }
    unset($m);
    $menu = array_values(array_filter($menu, fn ($m) => (empty($m['can']) || Gate::allows($m['can'])) && Route::has($m['route'])));
@endphp

{{-- Sidebar kiri bawaan Tabler (navbar-vertical). Mode "folded-hover" (data-bs-sidebar di <html>, dipasang di layouts.app):
     terlipat jadi ikon, melebar saat disorot. Tombol data-bs-toggle="sidebar-folded" (tabler.min.js) menyematkan / melipat
     dan menyimpan pilihan di localStorage "tabler-sidebar". Badge & judul ada di .nav-link-title supaya ikut tersembunyi saat terlipat. --}}
<aside class="navbar navbar-vertical navbar-expand-lg d-print-none">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
                aria-controls="sidebar-menu" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="navbar-brand navbar-brand-autodark d-flex align-items-center">
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                <span class="avatar avatar-sm bg-primary text-white flex-shrink-0"><i class="ti ti-file-search fs-2"></i></span>
                <span class="nav-link-title fw-bold text-body fs-3">Pub Checker</span>
            </a>
            <button type="button" class="btn btn-icon btn-sm btn-ghost-secondary ms-auto d-none d-lg-inline-flex" data-bs-toggle="sidebar-folded"
                    aria-pressed="false" title="Lipat / sematkan menu">
                <i class="ti ti-layout-sidebar-left-collapse"></i>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav pt-lg-3">
                @php $grupSebelum = null; @endphp
                @foreach($menu as $m)
                    {{-- judul bagian bawaan Tabler: saat sidebar terlipat otomatis jadi garis pemisah --}}
                    @php $grup = $m['grup']; @endphp
                    @if($grup !== $grupSebelum)
                        <li class="nav-section-title">{{ $grup }}</li>
                        @php $grupSebelum = $grup; @endphp
                    @endif
                    <li class="nav-item {{ request()->routeIs(...(array) $m['active']) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route($m['route']) }}" title="{{ $m['title'] ?? $m['label'] }}">
                            <span class="nav-link-icon"><i class="ti ti-{{ $m['icon'] }} fs-2"></i></span>
                            <span class="nav-link-title">
                                {{ $m['label'] }}
                                @if(!empty($m['badge']))<span class="badge badge-sm bg-primary-lt ms-2">{{ $m['badge'] }}</span>@endif
                            </span>
                        </a>
                    </li>
                @endforeach

            </ul>
        </div>
    </div>
</aside>
