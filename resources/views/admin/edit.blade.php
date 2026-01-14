@extends('layouts.admin')

@section('title', 'Edit User')

@section('content')
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card card-rounded">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title mb-1">Edit User: {{ $user->name }}</h4>
                        <p class="card-description mb-0">Username: <strong>{{ $user->username }}</strong></p>
                    </div>
                    <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary">
                        <i class="mdi mdi-arrow-left"></i> Kembali
                    </a>
                </div>
                
                @php
                    // ✅ Konversi dari format SSO (4 digit) ke format dropdown (2 digit)
                    // Untuk ditampilkan di form select
                    $userKdProv = null;
                    $userKdKab = null;
                    
                    if ($user->kode_provinsi) {
                        // Format SSO: 6300 atau 6301 -> ambil 2 digit pertama
                        $userKdProv = substr($user->kode_provinsi, 0, 2); // -> 63
                    }
                    
                    if ($user->kode_kabupaten) {
                        // Format SSO: 6301 -> ambil 2 digit terakhir
                        $userKdKab = substr($user->kode_kabupaten, 2, 2); // -> 01
                    }
                @endphp
                
                <form action="{{ route('admin.update', $user->id) }}" method="POST" class="forms-sample">
                    @csrf
                    @method('PUT')
                    
                    <!-- Role -->
                    <div class="form-group">
                        <label for="role" class="fw-bold">Role <span class="text-danger">*</span></label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="user" {{ $user->role == 'user' ? 'selected' : '' }}>User</option>
                            <option value="approver" {{ $user->role == 'approver' ? 'selected' : '' }}>Approver</option>
                            <option value="admin" {{ $user->role == 'admin' ? 'selected' : '' }}>Admin</option>
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
                                        <label for="kode_provinsi">Provinsi</label>
                                        <select class="form-control" id="kode_provinsi" name="kode_provinsi">
                                            <option value="">-- Semua Provinsi --</option>
                                            @php
                                                $provinces = \App\Models\MasterSls::select('kdprov', 'nmprov')
                                                    ->distinct()
                                                    ->orderBy('kdprov')
                                                    ->get();
                                            @endphp
                                            @foreach($provinces as $prov)
                                                {{-- ✅ Compare dengan $userKdProv (2 digit) --}}
                                                <option value="{{ $prov->kdprov }}" {{ $userKdProv == $prov->kdprov ? 'selected' : '' }}>
                                                    {{ $prov->kdprov }} - {{ $prov->nmprov }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="kode_kabupaten">Kabupaten/Kota</label>
                                        <select class="form-control" id="kode_kabupaten" name="kode_kabupaten">
                                            <option value="">-- Semua Kabupaten --</option>
                                            @if($userKdProv)
                                                @php
                                                    $kabupatens = \App\Models\MasterSls::select('kdkab', 'nmkab')
                                                        ->where('kdprov', $userKdProv)
                                                        ->distinct()
                                                        ->orderBy('kdkab')
                                                        ->get();
                                                @endphp
                                                @foreach($kabupatens as $kab)
                                                    {{-- ✅ Compare dengan $userKdKab (2 digit) --}}
                                                    <option value="{{ $kab->kdkab }}" {{ $userKdKab == $kab->kdkab ? 'selected' : '' }}>
                                                        {{ $kab->kdkab }} - {{ $kab->nmkab }}
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    
                    <!-- Password -->
                    <h5 class="mb-3">Ubah Password (Opsional)</h5>
                    <p class="text-muted mb-3">Kosongkan jika tidak ingin mengubah password</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password">Password Baru</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Minimal 8 karakter">
                                <small class="text-muted">Harus mengandung huruf besar, kecil, dan angka</small>
                                @error('password')
                                    <small class="text-danger d-block">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password_confirmation">Konfirmasi Password</label>
                                <input type="password" class="form-control" id="password_confirmation" 
                                       name="password_confirmation" placeholder="Ulangi password">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="mdi mdi-content-save"></i> Update User
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