<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">

        {{-- Dashboard --}}
        <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('dashboard') }}">
                <i class="mdi mdi-grid-large menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>

        {{-- Publication Checker --}}
        <li class="nav-item nav-category">Publication Checker</li>

        <li class="nav-item {{ request()->routeIs('checker.index') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('checker.index') }}">
                <i class="menu-icon mdi mdi-file-search-outline"></i>
                <span class="menu-title">Pemeriksaan</span>
            </a>
        </li>

        <li class="nav-item {{ request()->routeIs('checker.riwayat*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('checker.riwayat') }}">
                <i class="menu-icon mdi mdi-history"></i>
                <span class="menu-title">Riwayat Sesi</span>
                @php $totalSesi = \App\Models\SesiPemeriksaan::count(); @endphp
                @if($totalSesi > 0)
                    <span class="badge badge-success ms-2">{{ $totalSesi }}</span>
                @endif
            </a>
        </li>

        <li class="nav-item {{ request()->routeIs('checker.bps.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('checker.bps.index') }}">
                <i class="menu-icon mdi mdi-cloud-download-outline"></i>
                <span class="menu-title">Import dari API BPS</span>
            </a>
        </li>

        {{-- Administrator — hanya tampil untuk admin --}}
        @if(auth()->check() && auth()->user()->role === 'admin')
        <li class="nav-item nav-category">Administrator</li>

        <li class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.users.index') }}">
                <i class="menu-icon mdi mdi-account-multiple"></i>
                <span class="menu-title">Manajemen User</span>
            </a>
        </li>

        <li class="nav-item {{ request()->routeIs('admin.kriteria.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.kriteria.index') }}">
                <i class="menu-icon mdi mdi-format-list-checks"></i>
                <span class="menu-title">Kelola Kriteria</span>
                @php $totalKriteria = \App\Models\KriteriaPemeriksaan::where('aktif', true)->count(); @endphp
                @if($totalKriteria > 0)
                    <span class="badge badge-primary ms-2">{{ $totalKriteria }}</span>
                @endif
            </a>
        </li>
        @endif

        {{-- Akun --}}
        <li class="nav-item nav-category">Akun</li>

        <li class="nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('profile.index') }}">
                <i class="menu-icon mdi mdi-account"></i>
                <span class="menu-title">Profil Saya</span>
            </a>
        </li>

        <li class="nav-item">
            <form method="POST" action="{{ route('logout') }}" id="logout-form-sidebar">
                @csrf
                <a class="nav-link" href="#"
                   onclick="event.preventDefault(); document.getElementById('logout-form-sidebar').submit();">
                    <i class="menu-icon mdi mdi-logout text-danger"></i>
                    <span class="menu-title text-danger">Logout</span>
                </a>
            </form>
        </li>

    </ul>
</nav>