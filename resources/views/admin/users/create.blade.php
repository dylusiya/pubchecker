@extends('layouts.app')

@section('title', 'Tambah User')
@section('pretitle', 'Manajemen User')
@section('page-title', 'Tambah User Baru')
@section('page-subtitle', 'Pegawai BPS cukup diisi username SSO — data lainnya diambil dari SSO')
@section('page-actions')
    <a href="{{ route('admin.users.index') }}" class="btn">
        <i class="ti ti-arrow-left"></i> Kembali
    </a>
@endsection

@php $jenis = old('jenis', 'sso'); @endphp

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">

                <form action="{{ route('admin.users.store') }}" method="POST" id="formUser">
                    @csrf

                    {{-- Jenis akun --}}
                    <div class="mb-3">
                        <label class="form-label">Jenis akun</label>
                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column flex-md-row gap-2">
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="jenis" value="sso" class="form-selectgroup-input" @checked($jenis === 'sso')>
                                <div class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3"><span class="form-selectgroup-check"></span></span>
                                    <span>
                                        <strong>Pegawai BPS (SSO)</strong>
                                        <span class="d-block text-secondary small">Cukup username & role — nama, email, NIP, jabatan, satker terisi otomatis saat login SSO pertama</span>
                                    </span>
                                </div>
                            </label>
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="jenis" value="lokal" class="form-selectgroup-input" @checked($jenis === 'lokal')>
                                <div class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3"><span class="form-selectgroup-check"></span></span>
                                    <span>
                                        <strong>Akun lokal</strong>
                                        <span class="d-block text-secondary small">Login dengan username & password (tanpa SSO)</span>
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label required">Username</label>
                        <input type="text" class="form-control @error('username') is-invalid @enderror"
                               id="username" name="username" value="{{ old('username') }}"
                               placeholder="mis. budi.santoso" autocomplete="off" required>
                        <small class="text-secondary" data-jenis="sso">Username SSO BPS (bagian sebelum @bps.go.id)</small>
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label required">Role</label>
                        <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                            <option value="user" @selected(old('role', 'user') === 'user')>User</option>
                            <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                        </select>
                        <small class="text-secondary">Admin: akses penuh termasuk kelola kriteria & user | User: pemeriksaan & riwayat</small>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Hanya untuk akun lokal --}}
                    <div data-jenis="lokal">
                        <div class="mb-3">
                            <label for="name" class="form-label required">Nama Lengkap</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name') }}" placeholder="Nama Lengkap">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                   id="email" name="email" value="{{ old('email') }}" placeholder="email@bps.go.id">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>

                    {{-- Password: wajib untuk akun lokal; opsional (cadangan) untuk pegawai SSO --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label" id="labelPassword">Password</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="password" name="password" placeholder="Minimal 8 karakter" autocomplete="new-password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password_confirmation" class="form-label" id="labelPassword2">Konfirmasi Password</label>
                            <input type="password" class="form-control" id="password_confirmation"
                                   name="password_confirmation" placeholder="Ulangi password" autocomplete="new-password">
                        </div>
                        <div class="col-12 mb-3 mt-n2" data-jenis="sso">
                            <small class="text-secondary">
                                <i class="ti ti-shield-lock"></i> Opsional — password cadangan supaya pengguna tetap bisa masuk
                                lewat <strong>Login Lokal</strong> bila SSO sedang bermasalah. Kosongkan bila hanya lewat SSO.
                            </small>
                        </div>
                    </div>

                    <div class="alert alert-info" data-jenis="sso">
                        <i class="ti ti-info-circle me-1"></i>
                        Sampai pengguna ini login lewat <strong>Login dengan SSO BPS</strong>, namanya tampil sebagai username
                        dengan tanda <span class="badge bg-yellow-lt">Menunggu login SSO</span> di daftar user.
                        Data dari SSO diperbarui setiap kali ia login.
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i> Simpan
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Tampilkan isian sesuai jenis akun; isian yang disembunyikan tidak wajib & tidak dikirim
(() => {
    const form = document.getElementById('formUser');
    const terapkan = () => {
        const jenis = form.querySelector('[name=jenis]:checked').value;
        form.querySelectorAll('[data-jenis]').forEach(el => {
            const tampil = el.dataset.jenis === jenis;
            el.hidden = !tampil;
            el.querySelectorAll('input').forEach(i => { i.disabled = !tampil; });
        });
        ['name', 'password', 'password_confirmation'].forEach(id => {
            document.getElementById(id).required = jenis === 'lokal';
        });
        // password tetap tampil di kedua jenis: wajib (lokal) atau cadangan opsional (SSO)
        document.getElementById('labelPassword').textContent  = jenis === 'lokal' ? 'Password' : 'Password cadangan';
        document.getElementById('labelPassword2').textContent = jenis === 'lokal' ? 'Konfirmasi Password' : 'Konfirmasi password cadangan';
        ['labelPassword', 'labelPassword2'].forEach(id => document.getElementById(id).classList.toggle('required', jenis === 'lokal'));
    };
    form.querySelectorAll('[name=jenis]').forEach(r => r.addEventListener('change', terapkan));
    terapkan();
})();
</script>
@endpush
