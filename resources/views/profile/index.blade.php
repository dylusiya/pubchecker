@extends('layouts.admin')

@section('title', 'Profil Saya')

@section('content')
<div class="row">
    <div class="col-12">
        <!-- Header Profil -->
        <div class="card card-rounded mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body py-5">
                <div class="d-flex align-items-center">
                    @if($user->foto)
                        <img src="{{ $user->foto }}" 
                             class="rounded-circle border border-white border-4" 
                             style="width: 100px; height: 100px;"
                             alt="Foto Profil">
                    @else
                        <div class="bg-white rounded-circle border border-white border-4 d-flex align-items-center justify-content-center" 
                             style="width: 100px; height: 100px;">
                            <i class="mdi mdi-account text-primary" style="font-size: 50px;"></i>
                        </div>
                    @endif
                    <div class="ms-4 text-white">
                        <h2 class="mb-1">{{ $user->name }}</h2>
                        <p class="mb-1 h5">{{ $user->jabatan ?? 'Pegawai BPS' }}</p>
                        <p class="mb-0">
                            <i class="mdi mdi-email"></i> {{ $user->email ?? $user->username }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informasi Pribadi -->
        <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h4 class="card-title card-title-dash">
                            <i class="mdi mdi-account-circle text-primary"></i> Informasi Pribadi
                        </h4>
                        <div class="table-responsive mt-3">
                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <td width="40%"><strong>Nama Lengkap</strong></td>
                                        <td>{{ $user->name }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nama Depan</strong></td>
                                        <td>{{ $user->first_name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nama Belakang</strong></td>
                                        <td>{{ $user->last_name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Username SSO</strong></td>
                                        <td>
                                            <span class="badge badge-opacity-primary">{{ $user->username }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email</strong></td>
                                        <td>{{ $user->email ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>NIP Lama</strong></td>
                                        <td>{{ $user->nip ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>NIP Baru (18 Digit)</strong></td>
                                        <td>{{ $user->nip_baru ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 grid-margin stretch-card">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h4 class="card-title card-title-dash">
                            <i class="mdi mdi-briefcase text-primary"></i> Informasi Kepegawaian
                        </h4>
                        <div class="table-responsive mt-3">
                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <td width="40%"><strong>Golongan</strong></td>
                                        <td>
                                            @if($user->golongan)
                                                <span class="badge badge-opacity-warning">{{ $user->golongan }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Eselon</strong></td>
                                        <td>{{ $user->eselon ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Jabatan</strong></td>
                                        <td>{{ $user->jabatan ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Kode Organisasi</strong></td>
                                        <td>{{ $user->kode_organisasi ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Role Aplikasi</strong></td>
                                        <td>
                                            @if($user->role === 'admin')
                                                <span class="badge badge-opacity-danger">Administrator</span>
                                            @else
                                                <span class="badge badge-opacity-success">User</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informasi Unit Kerja & Lokasi -->
        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h4 class="card-title card-title-dash">
                            <i class="mdi mdi-map-marker text-primary"></i> Informasi Unit Kerja & Lokasi
                        </h4>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tbody>
                                        <tr>
                                            <td width="40%"><strong>Kode Provinsi</strong></td>
                                            <td>{{ $user->kode_provinsi ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Provinsi</strong></td>
                                            <td>{{ $user->provinsi ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Kode Kabupaten/Kota</strong></td>
                                            <td>{{ $user->kode_kabupaten ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Kabupaten/Kota</strong></td>
                                            <td>{{ $user->kabupaten ?? '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tbody>
                                        <tr>
                                            <td width="40%"><strong>Alamat Kantor</strong></td>
                                            <td>{{ $user->alamat_kantor ?? '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informasi Akses Data Mikro (untuk user biasa) -->
        @if(!$user->isAdmin())
        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h4 class="card-title card-title-dash">
                            <i class="mdi mdi-database text-primary"></i> Data Mikro yang Dapat Diakses
                        </h4>
                        <p class="card-subtitle card-subtitle-dash">
                            Anda memiliki akses ke {{ $user->accessibleDataMikro()->count() }} data mikro
                        </p>
                        <div class="mt-3">
                            <a href="{{ route('data-mikro.index') }}" class="btn btn-primary">
                                <i class="mdi mdi-database"></i> Lihat Data Mikro
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection