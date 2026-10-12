<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Login - Publication Checker BPS Kalsel</title>

    <link rel="stylesheet" href="{{ asset('assets/vendors/tabler/css/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/tabler-icons/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pubchecker.css') }}?v={{ filemtime(public_path('assets/css/pubchecker.css')) }}">

    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/images/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/images/favicon-16x16.png') }}">
</head>
<body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container container-tight py-4">

            {{-- Brand --}}
            <div class="text-center mb-4">
                {{-- ukuran dikunci lewat style: atribut height kalah oleh CSS bawaan (img { height:auto }) --}}
                <img src="{{ asset('assets/images/logo_bps.webp') }}" alt="Logo BPS" class="d-block mx-auto mb-3"
                     style="height:64px; width:auto;" onerror="this.style.display='none'">
                <h2 class="mb-0">Publication Checker</h2>
                <div class="text-secondary">BPS Provinsi Kalimantan Selatan</div>
            </div>

            <div class="card card-md">
                <div class="card-body">

                    {{-- Alerts --}}
                    @foreach(['error' => ['danger', 'alert-circle'], 'success' => ['success', 'circle-check']] as $key => [$type, $icon])
                        @if(session($key))
                            <div class="alert alert-{{ $type }} alert-dismissible" role="alert">
                                <div class="d-flex">
                                    <i class="ti ti-{{ $icon }} alert-icon"></i>
                                    <div>{{ session($key) }}</div>
                                </div>
                                <a class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></a>
                            </div>
                        @endif
                    @endforeach

                    {{-- SSO --}}
                    <a href="{{ route('sso.redirect') }}" class="btn btn-primary w-100 btn-lg">
                        <i class="ti ti-shield-check"></i> Login dengan SSO BPS
                    </a>

                    <div class="hr-text">atau</div>

                    {{-- Login lokal --}}
                    <form method="POST" action="{{ route('login.local') }}" autocomplete="off">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="username">Username</label>
                            <div class="input-icon">
                                <span class="input-icon-addon"><i class="ti ti-user"></i></span>
                                <input type="text" name="username" id="username" value="{{ old('username') }}" required
                                       class="form-control @error('username') is-invalid @enderror" placeholder="Username">
                            </div>
                            @error('username')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group input-group-flat">
                                <span class="input-group-text"><i class="ti ti-lock"></i></span>
                                <input type="password" name="password" id="password" required
                                       class="form-control @error('password') is-invalid @enderror" placeholder="Password">
                                <span class="input-group-text">
                                    <a href="#" class="link-secondary" id="togglePassword" title="Tampilkan password">
                                        <i class="ti ti-eye" id="eyeIcon"></i>
                                    </a>
                                </span>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="ti ti-login"></i> Login Lokal
                        </button>
                    </form>
                </div>
            </div>

            <div class="text-center text-secondary mt-3 small">
                &copy; {{ date('Y') }} BPS Provinsi Kalimantan Selatan
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/vendors/tabler/js/tabler.min.js') }}"></script>
    <script>
        window.bootstrap = window.bootstrap || window.tabler?.bootstrap;

        document.getElementById('togglePassword').addEventListener('click', function (e) {
            e.preventDefault();
            const input = document.getElementById('password');
            const icon  = document.getElementById('eyeIcon');
            const show  = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('ti-eye', !show);
            icon.classList.toggle('ti-eye-off', show);
        });

        setTimeout(function () {
            document.querySelectorAll('.alert').forEach(function (el) {
                if (window.bootstrap?.Alert) window.bootstrap.Alert.getOrCreateInstance(el).close();
                else el.remove();
            });
        }, 5000);
    </script>
</body>
</html>
