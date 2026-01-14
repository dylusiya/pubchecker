<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('dashboard') }}">
                <i class="mdi mdi-grid-large menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>
        
        <li class="nav-item nav-category">Data</li>
        <li id="menu-daerah" class="nav-item {{ Route::is('daerah-sulit.index') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('daerah-sulit.index') }}">
                <i class="menu-icon mdi mdi-map-marker-alert"></i>
                <span class="menu-title">Daerah Sulit</span>
            </a>
        </li>

        <li id="menu-submit" class="nav-item {{ Route::is('daerah-sulit.pending-submit') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('daerah-sulit.pending-submit') }}">
                <i class="menu-icon mdi mdi-send"></i>
                <span class="menu-title">Submit Data</span>
                @php
                    $user = auth()->user();
                    $draftQuery = \App\Models\StatusDaerahSulit::whereIn('status_approval', ['draft', 'ditolak']);
                    if (!$user->isAdmin() && $user->isKabupaten()) {
                        $draftQuery->whereHas('masterSls', function($q) use ($user) {
                            $q->where('kdkab', $user->kode_kabupaten);
                        });
                    }
                    $draftCount = $draftQuery->count();
                @endphp
                @if($draftCount > 0)
                    <span class="badge badge-warning ms-2">{{ $draftCount }}</span>
                @endif
            </a>
        </li>

        @if(auth()->user()->isAdmin() || auth()->user()->isApprover())
        <li id="menu-approval" class="nav-item {{ Route::is('daerah-sulit.pending-approval') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('daerah-sulit.pending-approval') }}">
                <i class="menu-icon mdi mdi-clipboard-check"></i>
                <span class="menu-title">Approval</span>
                @php
                    $pendingCount = \App\Models\StatusDaerahSulit::where('status_approval', 'pending')->count();
                @endphp
                @if($pendingCount > 0)
                    <span class="badge badge-danger ms-2">{{ $pendingCount }}</span>
                @endif
            </a>
        </li>
        @endif

        <li class="nav-item {{ request()->routeIs('master-sls.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('master-sls.index') }}">
                <i class="menu-icon mdi mdi-map-marker-multiple"></i>
                <span class="menu-title">Master SLS</span>
            </a>
        </li>
        
        <li class="nav-item {{ request()->routeIs('daerah-sulit.history') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('daerah-sulit.history') }}">
                <i class="menu-icon mdi mdi-history"></i>
                <span class="menu-title">Riwayat Perubahan</span>
            </a>
        </li>
        
        @if(auth()->user()->isAdmin())
        <li class="nav-item nav-category">Administrator</li>
        <li class="nav-item {{ request()->routeIs('admin.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.index') }}">
                <i class="menu-icon mdi mdi-account-multiple"></i>
                <span class="menu-title">Manajemen User</span>
            </a>
        </li>
        @endif
        
        <li class="nav-item nav-category">Akun</li>
        <li class="nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('profile.index') }}">
                <i class="menu-icon mdi mdi-account"></i>
                <span class="menu-title">Profil Saya</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('sso.logout') }}">
                <i class="menu-icon mdi mdi-logout"></i>
                <span class="menu-title">Logout</span>
            </a>
        </li>
    </ul>
</nav>