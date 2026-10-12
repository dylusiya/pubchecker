@extends('layouts.app')

@section('title', 'Edit User')
@section('pretitle', 'Manajemen User')
@section('page-title', 'Edit User: ' . $user->name)
@section('page-subtitle')
    Username: <strong>{{ $user->username }}</strong>
@endsection
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

                <form action="{{ route('admin.users.update', $user->id) }}" method="POST" class="">
                    @csrf
                    @method('PUT')
                    
                    <!-- Info User -->
                    <div class="alert alert-light border">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Username</small>
                                <strong>{{ $user->username }}</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Email</small>
                                <strong>{{ $user->email ?? '-' }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Nama -->
                    <div class="mb-3">
                        <label for="name" class="fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" 
                               value="{{ old('name', $user->name) }}" 
                               placeholder="Nama Lengkap" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="fw-bold">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" 
                               id="email" name="email" 
                               value="{{ old('email', $user->email) }}" 
                               placeholder="email@bps.go.id">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <!-- Role -->
                    <div class="mb-3">
                        <label for="role" class="fw-bold">Role <span class="text-danger">*</span></label>
                        <select class="form-control @error('role') is-invalid @enderror" id="role" name="role" required>
                            <option value="user" {{ $user->role == 'user' ? 'selected' : '' }}>User</option>
                            <option value="admin" {{ $user->role == 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        <small class="text-muted">Admin: akses penuh | User: akses terbatas</small>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr class="my-4">
                    
                    <!-- Password -->
                    <h5 class="mb-3">Ubah Password (Opsional)</h5>
                    <p class="text-muted mb-3">Kosongkan jika tidak ingin mengubah password</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password">Password Baru</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                       id="password" name="password" 
                                       placeholder="Minimal 8 karakter">
                                <small class="text-muted">Harus mengandung huruf besar, kecil, dan angka</small>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirmation">Konfirmasi Password</label>
                                <input type="password" class="form-control" 
                                       id="password_confirmation" 
                                       name="password_confirmation" 
                                       placeholder="Ulangi password">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="ti ti-device-floppy"></i> Update User
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-light">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection