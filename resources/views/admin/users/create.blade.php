@extends('layouts.app')

@section('title', 'Tambah User')
@section('pretitle', 'Manajemen User')
@section('page-title', 'Tambah User Baru')
@section('page-subtitle', 'Isi form untuk menambahkan user baru')
@section('page-actions')
    <a href="{{ route('admin.users.index') }}" class="btn">
        <i class="ti ti-arrow-left"></i> Kembali
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">

                <form action="{{ route('admin.users.store') }}" method="POST" class="">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="username" class="fw-bold">Username SSO <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('username') is-invalid @enderror" 
                               id="username" name="username" 
                               value="{{ old('username') }}" 
                               placeholder="username.bps" required>
                        <small class="text-muted">Gunakan format username SSO BPS</small>
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="fw-bold">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                       id="password" name="password" 
                                       placeholder="Minimal 8 karakter" required>
                                <small class="text-muted">Harus mengandung huruf besar, kecil, dan angka</small>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirmation" class="fw-bold">Konfirmasi Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" 
                                       id="password_confirmation" 
                                       name="password_confirmation" 
                                       placeholder="Ulangi password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" 
                               value="{{ old('name') }}" 
                               placeholder="Nama Lengkap" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="fw-bold">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" 
                               id="email" name="email" 
                               value="{{ old('email') }}" 
                               placeholder="email@bps.go.id">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="fw-bold">Role <span class="text-danger">*</span></label>
                        <select class="form-control @error('role') is-invalid @enderror" id="role" name="role" required>
                            <option value="">-- Pilih Role --</option>
                            <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>User</option>
                            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        <small class="text-muted">Admin: akses penuh | User: akses terbatas</small>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="alert alert-info mt-4">
                        <i class="ti ti-info-circle me-2"></i>
                        <strong>Catatan:</strong> User yang dibuat akan dapat login menggunakan username dan password ini. 
                        Untuk sinkronisasi dengan SSO BPS, pastikan username sesuai dengan SSO.
                    </div>
                    
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="ti ti-device-floppy"></i> Simpan
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-light">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection