@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')
<div class="row">
    <div class="col-12">
        <!-- Header Profil -->
        <div class="card card-rounded mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body py-5">
                <div class="d-flex align-items-center flex-wrap">
                    @if($user->foto)
                        <img src="{{ $user->foto }}" 
                             class="rounded-circle border border-white border-4" 
                             style="width: 120px; height: 120px; object-fit: cover;"
                             alt="Foto Profil">
                    @else
                        <div class="bg-white rounded-circle border border-white border-4 d-flex align-items-center justify-content-center" 
                             style="width: 120px; height: 120px;">
                            <i class="mdi mdi-account text-primary" style="font-size: 60px;"></i>
                        </div>
                    @endif
                    <div class="ms-4 text-white flex-grow-1">
                        <h2 class="mb-2">{{ $user->name }}</h2>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @if($user->jabatan)
                                <span class="badge badge-light text-primary px-3 py-2">
                                    <i class="mdi mdi-briefcase me-1"></i>{{ $user->jabatan }}
                                </span>
                            @endif
                            @if($user->golongan)
                                <span class="badge badge-warning px-3 py-2">
                                    <i class="mdi mdi-star me-1"></i>{{ $user->golongan }}
                                </span>
                            @endif
                            @if($user->role === 'admin')
                                <span class="badge badge-danger px-3 py-2">
                                    <i class="mdi mdi-shield-account me-1"></i>Administrator
                                </span>
                            @elseif($user->role === 'approver')
                                <span class="badge badge-success px-3 py-2">
                                    <i class="mdi mdi-check-circle me-1"></i>Approver
                                </span>
                            @else
                                <span class="badge badge-info px-3 py-2">
                                    <i class="mdi mdi-account me-1"></i>User
                                </span>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-3">
                            @if($user->email)
                                <div>
                                    <i class="mdi mdi-email me-1"></i>{{ $user->email }}
                                </div>
                            @endif
                            @if($user->nip_baru)
                                <div>
                                    <i class="mdi mdi-badge-account me-1"></i>{{ $user->nip_baru }}
                                </div>
                            @endif
                            @if($user->kabupaten)
                                <div>
                                    <i class="mdi mdi-map-marker me-1"></i>BPS {{ $user->kabupaten }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informasi Detail -->
        <div class="row">
            <!-- Informasi Pribadi -->
            <div class="col-lg-6 grid-margin stretch-card">
                <div class="card card-rounded h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                <i class="mdi mdi-account-circle text-primary me-2"></i>Informasi Pribadi
                            </h5>
                        </div>
                        <div class="info-group">
                            <div class="info-item">
                                <label>Nama Lengkap</label>
                                <p>{{ $user->name }}</p>
                            </div>
                            @if($user->first_name || $user->last_name)
                            <div class="info-item">
                                <label>Nama Depan / Belakang</label>
                                <p>{{ $user->first_name ?? '-' }} / {{ $user->last_name ?? '-' }}</p>
                            </div>
                            @endif
                            <div class="info-item">
                                <label>Username SSO</label>
                                <p><span class="badge badge-primary">{{ $user->username }}</span></p>
                            </div>
                            <div class="info-item">
                                <label>Email</label>
                                <p>{{ $user->email ?? '-' }}</p>
                            </div>
                            @if($user->nip || $user->nip_baru)
                            <div class="info-item">
                                <label>NIP</label>
                                <p>
                                    @if($user->nip_baru)
                                        <strong>{{ $user->nip_baru }}</strong>
                                        @if($user->nip)
                                            <br><small class="text-muted">Lama: {{ $user->nip }}</small>
                                        @endif
                                    @else
                                        {{ $user->nip }}
                                    @endif
                                </p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informasi Kepegawaian -->
            <div class="col-lg-6 grid-margin stretch-card">
                <div class="card card-rounded h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                <i class="mdi mdi-briefcase text-primary me-2"></i>Informasi Kepegawaian
                            </h5>
                        </div>
                        <div class="info-group">
                            <div class="info-item">
                                <label>Jabatan</label>
                                <p>{{ $user->jabatan ?? '-' }}</p>
                            </div>
                            @if($user->golongan || $user->eselon)
                            <div class="info-item">
                                <label>Golongan / Eselon</label>
                                <p>
                                    @if($user->golongan)
                                        <span class="badge badge-warning">{{ $user->golongan }}</span>
                                    @else
                                        -
                                    @endif
                                    @if($user->eselon)
                                        / <span class="badge badge-info">{{ $user->eselon }}</span>
                                    @endif
                                </p>
                            </div>
                            @endif
                            <div class="info-item">
                                <label>Kode Organisasi</label>
                                <p>{{ $user->kode_organisasi ?? '-' }}</p>
                            </div>
                            <div class="info-item">
                                <label>Role Sistem</label>
                                <p>
                                    @if($user->role === 'admin')
                                        <span class="badge badge-danger">
                                            <i class="mdi mdi-shield-account"></i> Administrator
                                        </span>
                                    @elseif($user->role === 'approver')
                                        <span class="badge badge-success">
                                            <i class="mdi mdi-check-circle"></i> Approver
                                        </span>
                                    @else
                                        <span class="badge badge-secondary">
                                            <i class="mdi mdi-account"></i> User
                                        </span>
                                    @endif
                                </p>
                            </div>
                            @if($user->isAdmin())
                            <div class="info-item">
                                <label>Hak Akses</label>
                                <p><span class="badge badge-danger">Akses Penuh Semua Data</span></p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informasi Unit Kerja -->
        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h5 class="mb-3">
                            <i class="mdi mdi-map-marker text-primary me-2"></i>Informasi Unit Kerja & Lokasi
                        </h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-item">
                                    <label>Provinsi</label>
                                    <p>
                                        @if($user->kode_provinsi)
                                            <span class="badge badge-secondary">{{ $user->kode_provinsi }}</span>
                                        @endif
                                        {{ $user->provinsi ?? '-' }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-item">
                                    <label>Kabupaten/Kota</label>
                                    <p>
                                        @if($user->kode_kabupaten)
                                            <span class="badge badge-secondary">{{ $user->kode_kabupaten }}</span>
                                        @endif
                                        {{ $user->kabupaten ?? '-' }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <label>Alamat Kantor</label>
                                    <p>{{ $user->alamat_kantor ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.info-group {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    padding-bottom: 1rem;
    border-bottom: 1px solid #f0f0f0;
}

.info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.info-item label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item p {
    margin-bottom: 0;
    font-size: 0.95rem;
    color: #333;
}

.card-rounded {
    transition: transform 0.2s, box-shadow 0.2s;
}

.card-rounded:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
</style>
@endsection