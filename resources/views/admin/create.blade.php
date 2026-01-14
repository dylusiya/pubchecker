@extends('layouts.admin')

@section('title', 'Tambah User')

@section('content')
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card card-rounded">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title mb-1">Tambah User Baru</h4>
                        <p class="card-description mb-0">Isi form untuk menambahkan user baru</p>
                    </div>
                    <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-arrow-left"></i> Kembali
                    </a>
                </div>
                
                <form action="{{ route('admin.store') }}" method="POST" class="forms-sample">
                    @csrf
                    
                    <div class="form-group">
                        <label for="username" class="fw-bold">Username SSO <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="{{ old('username') }}" placeholder="username.bps" required>
                        @error('username')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password" class="fw-bold">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Minimal 8 karakter" required>
                                <small class="text-muted">Harus mengandung huruf besar, kecil, dan angka</small>
                                @error('password')
                                    <small class="text-danger d-block">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password_confirmation" class="fw-bold">Konfirmasi Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password_confirmation" 
                                       name="password_confirmation" placeholder="Ulangi password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="name" class="fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="{{ old('name') }}" placeholder="Nama Lengkap" required>
                        @error('name')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="fw-bold">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="{{ old('email') }}" placeholder="email@bps.go.id">
                        @error('email')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="fw-bold">Role <span class="text-danger">*</span></label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>User</option>
                            <option value="approver" {{ old('role') == 'approver' ? 'selected' : '' }}>Approver</option>
                            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        <small class="text-muted">Admin: akses semua | Approver: review data | User: input data</small>
                        @error('role')
                            <small class="text-danger d-block">{{ $message }}</small>
                        @enderror
                    </div>

                    <!-- Wilayah Akses -->
                    <div id="wilayahSection">
                        <div class="alert alert-info" id="adminInfo" style="display: none;">
                            <i class="mdi mdi-shield-check me-2"></i>
                            Administrator memiliki akses ke semua wilayah.
                        </div>

                        <div id="wilayahForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="kode_provinsi" class="fw-bold">Provinsi</label>
                                        <select class="form-control" id="kode_provinsi" name="kode_provinsi">
                                            <option value="">-- Semua Provinsi --</option>
                                            @php
                                                $provinces = \App\Models\MasterSls::select('kdprov', 'nmprov')
                                                    ->distinct()
                                                    ->orderBy('kdprov')
                                                    ->get();
                                            @endphp
                                            @foreach($provinces as $prov)
                                                <option value="{{ $prov->kdprov }}" {{ old('kode_provinsi') == $prov->kdprov ? 'selected' : '' }}>
                                                    {{ $prov->kdprov }} - {{ $prov->nmprov }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="kode_kabupaten" class="fw-bold">Kabupaten/Kota</label>
                                        <select class="form-control" id="kode_kabupaten" name="kode_kabupaten">
                                            <option value="">-- Semua Kabupaten --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="mdi mdi-content-save"></i> Simpan
                    </button>
                    <a href="{{ route('admin.index') }}" class="btn btn-light">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    function toggleWilayahSection() {
        const role = $('#role').val();
        if (role === 'admin') {
            $('#adminInfo').show();
            $('#wilayahForm').hide();
        } else {
            $('#adminInfo').hide();
            $('#wilayahForm').show();
        }
    }

    $('#role').on('change', toggleWilayahSection);
    toggleWilayahSection();

    $('#kode_provinsi').on('change', function() {
        const kdprov = $(this).val();
        const $kabSelect = $('#kode_kabupaten');
        
        $kabSelect.html('<option value="">-- Loading... --</option>');
        
        if (!kdprov) {
            $kabSelect.html('<option value="">-- Semua Kabupaten --</option>');
            return;
        }
        
        $.ajax({
            url: '{{ route("admin.kabupaten", ":kdprov") }}'.replace(':kdprov', kdprov),
            method: 'GET',
            success: function(data) {
                let options = '<option value="">-- Semua Kabupaten --</option>';
                data.forEach(function(kab) {
                    options += `<option value="${kab.kdkab}">${kab.kdkab} - ${kab.nmkab}</option>`;
                });
                $kabSelect.html(options);
            },
            error: function(xhr) {
                console.error('Error loading kabupaten:', xhr);
                $kabSelect.html('<option value="">-- Error loading --</option>');
            }
        });
    });
});
</script>
@endpush
@endsection