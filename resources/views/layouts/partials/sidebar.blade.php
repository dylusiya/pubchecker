<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <!-- Dashboard -->
        <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('dashboard') }}">
                <i class="mdi mdi-grid-large menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>
        
        <!-- Survey Management -->
        <li class="nav-item nav-category">Survey Management</li>
        
        <li class="nav-item {{ request()->routeIs('admin.survey.dashboard') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.survey.dashboard') }}">
                <i class="menu-icon mdi mdi-chart-bar"></i>
                <span class="menu-title">Dashboard Analytics</span>
            </a>
        </li>
        
        <li class="nav-item {{ request()->routeIs('admin.survey.index') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.survey.index') }}">
                <i class="menu-icon mdi mdi-table-large"></i>
                <span class="menu-title">Data Survey</span>
                @php
                    $todayCount = \App\Models\SurveyResponse::whereDate('tanggal_submit', today())->count();
                @endphp
                @if($todayCount > 0)
                    <span class="badge badge-success ms-2">{{ $todayCount }}</span>
                @endif
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.survey.export') }}">
                <i class="menu-icon mdi mdi-file-excel"></i>
                <span class="menu-title">Export Data</span>
            </a>
        </li>
        
        <!-- Public Survey -->
        <li class="nav-item nav-category">Public Access</li>
        
        <li class="nav-item">
            <a class="nav-link" href="{{ route('survey.index') }}" target="_blank">
                <i class="menu-icon mdi mdi-open-in-new"></i>
                <span class="menu-title">Form Survey Public</span>
                <i class="mdi mdi-external-link text-muted ms-auto"></i>
            </a>
        </li>

        <!-- User Management - HANYA TAMPIL UNTUK ADMIN -->
        @if(auth()->check() && auth()->user()->role === 'admin')
        <li class="nav-item nav-category">Administrator</li>

        <li class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.users.index') }}">
                <i class="menu-icon mdi mdi-account-multiple"></i>
                <span class="menu-title">Manajemen User</span>
            </a>
        </li>
        @endif
        
        <!-- Account -->
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
                <a class="nav-link" href="#" onclick="event.preventDefault(); document.getElementById('logout-form-sidebar').submit();">
                    <i class="menu-icon mdi mdi-logout text-danger"></i>
                    <span class="menu-title text-danger">Logout</span>
                </a>
            </form>
        </li>
    </ul>
</nav>