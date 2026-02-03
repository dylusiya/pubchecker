<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login - Survey Kepuasan BPS Kalsel</title>
    
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
    
    <style>
        body {
            background: #f5f7fa;
        }
        .auth-form-light {
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            background: white;
        }
        .brand-logo img {
            height: 50px;
            margin-bottom: 1rem;
        }
        .brand-logo h4 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        .brand-logo p {
            font-size: 0.875rem;
            color: #7f8c8d;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }
        .divider span {
            padding: 0 1rem;
            color: #95a5a6;
            font-size: 0.813rem;
        }
        .btn-sso {
            background: #4e73df;
            border: none;
            color: white;
            font-weight: 500;
        }
        .btn-sso:hover {
            background: #2e59d9;
            color: white;
        }
        .btn-local {
            background: white;
            border: 2px solid #4e73df;
            color: #4e73df;
            font-weight: 500;
        }
        .btn-local:hover {
            background: #4e73df;
            color: white;
        }
        .input-group-text {
            border-right: 0;
        }
        .form-control {
            border-left: 0;
        }
        .form-control:focus {
            border-color: #ced4da;
            box-shadow: none;
        }
        .input-group:focus-within .input-group-text {
            border-color: #80bdff;
        }
        .input-group:focus-within .form-control {
            border-color: #80bdff;
        }
        .btn-survey {
            background: white;
            border: 2px solid #1cc88a;
            color: #1cc88a;
            font-weight: 500;
        }
        .btn-survey:hover {
            background: #1cc88a;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth px-0">
                <div class="row w-100 mx-0">
                    <div class="col-lg-4 mx-auto">
                        <div class="auth-form-light text-left py-5 px-4 px-sm-5">
                            <!-- Brand Logo -->
                            <div class="brand-logo text-center mb-4">
                                <img src="{{ asset('assets/images/logo-bps.png') }}" alt="logo" onerror="this.style.display='none'">
                                <h4>Survey Kepuasan Masyarakat</h4>
                                <p>BPS Provinsi Kalimantan Selatan</p>
                            </div>

                            <!-- Alerts -->
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-alert-circle me-2"></i>{{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-circle me-2"></i>{{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            <!-- SSO Login Button -->
                            <div class="mt-3">
                                <a href="{{ route('sso.redirect') }}" class="btn btn-block btn-lg auth-form-btn btn-sso">
                                    <i class="mdi mdi-shield-check me-2"></i>
                                    Login dengan SSO BPS
                                </a>
                            </div>

                            <!-- Divider -->
                            <div class="divider">
                                <span>atau</span>
                            </div>

                            <!-- Local Login Form -->
                            <form method="POST" action="{{ route('login.local') }}">
                                @csrf
                                <div class="form-group">
                                    <label for="username" class="form-label">Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white">
                                            <i class="mdi mdi-account text-muted"></i>
                                        </span>
                                        <input type="text" 
                                               name="username" 
                                               class="form-control form-control-lg @error('username') is-invalid @enderror" 
                                               id="username" 
                                               placeholder="Username"
                                               value="{{ old('username') }}"
                                               required>
                                        @error('username')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white">
                                            <i class="mdi mdi-lock text-muted"></i>
                                        </span>
                                        <input type="password" 
                                               name="password" 
                                               class="form-control form-control-lg @error('password') is-invalid @enderror" 
                                               id="password" 
                                               placeholder="Password"
                                               required>
                                        <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePassword">
                                            <i class="mdi mdi-eye" id="eyeIcon"></i>
                                        </button>
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-block btn-lg auth-form-btn btn-local">
                                        <i class="mdi mdi-login me-2"></i>
                                        Login Lokal
                                    </button>
                                </div>
                            </form>

                            <!-- Divider -->
                            <div class="divider mt-4">
                                <span></span>
                            </div>

                            <!-- Public Survey Access -->
                            <div class="text-center">
                                <a href="{{ route('survey.index') }}" class="btn btn-lg w-100 btn-survey">
                                    <i class="mdi mdi-clipboard-text me-2"></i>
                                    Isi Survey Kepuasan
                                </a>
                                <p class="text-muted mt-2 mb-0 small">
                                    Tidak perlu login untuk mengisi survey
                                </p>
                            </div>

                            <!-- Footer -->
                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    &copy; {{ date('Y') }} BPS Provinsi Kalimantan Selatan
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
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('mdi-eye');
                eyeIcon.classList.add('mdi-eye-off');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('mdi-eye-off');
                eyeIcon.classList.add('mdi-eye');
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>