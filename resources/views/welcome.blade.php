<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login - Daerah Sulit BPS Kalsel</title>
    
    <!-- plugins:css -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/feather/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/ti-icons/css/themify-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/typicons/typicons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/simple-line-icons/css/simple-line-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/vendor.bundle.base.css') }}">
    
    <!-- Layout styles -->
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/images/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/images/favicon-16x16.png') }}">
</head>
<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth px-0">
                <div class="row w-100 mx-0">
                    <div class="col-lg-4 mx-auto">
                        <div class="auth-form-light text-left py-5 px-4 px-sm-5">
                            <!-- Brand Logo -->
                            <div class="brand-logo text-center">
                                <h2 class="text-primary fw-bold">DAERAH SULIT</h2>
                                <p class="text-muted">BPS Provinsi Kalimantan Selatan</p>
                            </div>
                            
                            <h4 class="text-center mt-4">Selamat Datang</h4>
                            <h6 class="fw-light text-center">Login untuk melanjutkan</h6>

                            <!-- Alerts -->
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                                    <i class="mdi mdi-alert-circle me-2"></i>{{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                                    <i class="mdi mdi-check-circle me-2"></i>{{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            <!-- SSO Login Button -->
                            <div class="mt-4">
                                <a href="{{ route('sso.redirect') }}" class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn d-flex align-items-center justify-content-center">
                                    <i class="mdi mdi-shield-check me-2"></i>
                                    LOGIN DENGAN SSO BPS
                                </a>
                            </div>

                            <!-- Divider -->
                            <div class="my-3 d-flex justify-content-between align-items-center">
                                <div class="w-100" style="height: 1px; background: #e0e0e0;"></div>
                                <span class="px-3 text-muted small">atau</span>
                                <div class="w-100" style="height: 1px; background: #e0e0e0;"></div>
                            </div>

                            <!-- Local Login Form -->
                            <form class="pt-3" method="POST" action="{{ route('login.local') }}">
                                @csrf
                                <div class="form-group">
                                    <input type="text" 
                                           name="username" 
                                           class="form-control form-control-lg @error('username') is-invalid @enderror" 
                                           id="username" 
                                           placeholder="Username SSO"
                                           value="{{ old('username') }}"
                                           required>
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <input type="password" 
                                           name="password" 
                                           class="form-control form-control-lg @error('password') is-invalid @enderror" 
                                           id="password" 
                                           placeholder="Password"
                                           required>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-block btn-info btn-lg font-weight-medium auth-form-btn d-flex align-items-center justify-content-center">
                                        <i class="mdi mdi-login me-2"></i>
                                        LOGIN LOKAL
                                    </button>
                                </div>
                            </form>

                            <!-- Info Alert -->
                            <div class="alert alert-info mt-4 mb-0" role="alert">
                                <div class="d-flex align-items-start">
                                    <i class="mdi mdi-information me-2" style="font-size: 20px;"></i>
                                    <div>
                                        <strong>Informasi:</strong><br>
                                        <small>Gunakan akun SSO BPS untuk login ke aplikasi.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="text-center mt-4 fw-light">
                                <small class="text-muted">
                                    &copy; {{ date('Y') }} BPS Provinsi Kalimantan Selatan<br>
                                    Badan Pusat Statistik
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- plugins:js -->
    <script src="{{ asset('assets/vendors/js/vendor.bundle.base.js') }}"></script>
    <script src="{{ asset('assets/js/off-canvas.js') }}"></script>
    <script src="{{ asset('assets/js/template.js') }}"></script>
    <script src="{{ asset('assets/js/settings.js') }}"></script>
    <script src="{{ asset('assets/js/hoverable-collapse.js') }}"></script>
    <script src="{{ asset('assets/js/todolist.js') }}"></script>
</body>
</html>