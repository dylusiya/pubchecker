<nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
    <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
        <div class="me-3">
            <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-bs-toggle="minimize">
                <span class="icon-menu"></span>
            </button>
        </div>
        <div>
            <a class="navbar-brand brand-logo" href="{{ route('dashboard') }}">
                <h4 class="text-primary mb-0">DAERAH SULIT</h4>
            </a>
            <a class="navbar-brand brand-logo-mini" href="{{ route('dashboard') }}">
                <h5 class="text-primary mb-0">DS</h5>
            </a>
        </div>
    </div>
    <div class="navbar-menu-wrapper d-flex align-items-top">
        <ul class="navbar-nav">
            <li class="nav-item fw-semibold d-none d-lg-block ms-0">
                <h1 class="welcome-text">Selamat Datang, <span class="text-black fw-bold">{{ auth()->user()->first_name ?? auth()->user()->name }}</span></h1>
                <h3 class="welcome-sub-text">BPS {{ auth()->user()->kabupaten ?? 'Satker' }} - {{ auth()->user()->provinsi }}</h3>
            </li>
        </ul>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown d-none d-lg-block user-dropdown">
                <a class="nav-link" id="UserDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                    @if(auth()->user()->foto)
                        <img class="img-xs rounded-circle" src="{{ auth()->user()->foto }}" alt="Profile">
                    @else
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                            <i class="mdi mdi-account text-white"></i>
                        </div>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
                    <div class="dropdown-header text-center">
                        @if(auth()->user()->foto)
                            <img class="img-md rounded-circle mb-2" src="{{ auth()->user()->foto }}" alt="Profile" style="width: 50px; height: 50px;">
                        @else
                            <div class="bg-primary rounded-circle mb-2 d-flex align-items-center justify-content-center mx-auto" style="width: 50px; height: 50px;">
                                <i class="mdi mdi-account text-white" style="font-size: 30px;"></i>
                            </div>
                        @endif
                        <p class="mb-1 mt-2 fw-semibold">{{ auth()->user()->name }}</p>
                        <p class="fw-light text-muted mb-0 small">{{ auth()->user()->email ?? auth()->user()->username }}</p>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('profile.index') }}">
                        <i class="dropdown-item-icon mdi mdi-account-outline text-primary me-2"></i> My Profile
                    </a>
                    <a class="dropdown-item" href="{{ route('sso.logout') }}">
                        <i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i> Sign Out
                    </a>
                </div>
            </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-bs-toggle="offcanvas">
            <span class="mdi mdi-menu"></span>
        </button>
    </div>
</nav>