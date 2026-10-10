@extends('layouts.app')

@php
    $roleBadge = match($user->role) {
        'admin'    => ['bg-red-lt',   'shield-lock',  'Administrator'],
        'approver' => ['bg-green-lt', 'circle-check', 'Approver'],
        default    => ['bg-blue-lt',  'user',         'User'],
    };
@endphp

@section('title', 'Profil Saya')
@section('pretitle', 'Akun')
@section('page-title', 'Profil Saya')

@section('content')
<div class="row row-cards">

    {{-- Identitas --}}
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center g-4">
                    <div class="col-auto">
                        @if($user->foto)
                            <span class="avatar avatar-xl" style="background-image: url('{{ $user->foto }}')"></span>
                        @else
                            <span class="avatar avatar-xl bg-primary-lt fs-1">{{ strtoupper(substr($user->first_name ?? $user->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="col">
                        <h2 class="mb-1">{{ $user->name }}</h2>
                        <div class="text-secondary mb-2">{{ $user->jabatan ?? 'Pegawai BPS' }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge {{ $roleBadge[0] }}"><i class="ti ti-{{ $roleBadge[1] }} me-1"></i>{{ $roleBadge[2] }}</span>
                            @if($user->golongan)
                                <span class="badge bg-yellow-lt"><i class="ti ti-star me-1"></i>{{ $user->golongan }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-12 col-md-auto">
                        <div class="d-flex flex-column gap-1 text-secondary">
                            @if($user->email)    <div><i class="ti ti-mail me-2"></i>{{ $user->email }}</div> @endif
                            @if($user->nip_baru) <div><i class="ti ti-id-badge-2 me-2"></i>{{ $user->nip_baru }}</div> @endif
                            @if($user->kabupaten)<div><i class="ti ti-map-pin me-2"></i>BPS {{ $user->kabupaten }}</div> @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Informasi pribadi --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-user-circle me-2 text-primary"></i>Informasi Pribadi</h3></div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Nama Lengkap</div>
                        <div class="datagrid-content">{{ $user->name }}</div>
                    </div>
                    @if($user->first_name || $user->last_name)
                    <div class="datagrid-item">
                        <div class="datagrid-title">Nama Depan / Belakang</div>
                        <div class="datagrid-content">{{ $user->first_name ?? '-' }} / {{ $user->last_name ?? '-' }}</div>
                    </div>
                    @endif
                    <div class="datagrid-item">
                        <div class="datagrid-title">Username SSO</div>
                        <div class="datagrid-content"><span class="badge bg-blue-lt">{{ $user->username }}</span></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Email</div>
                        <div class="datagrid-content">{{ $user->email ?? '-' }}</div>
                    </div>
                    @if($user->nip || $user->nip_baru)
                    <div class="datagrid-item">
                        <div class="datagrid-title">NIP</div>
                        <div class="datagrid-content">
                            {{ $user->nip_baru ?? $user->nip }}
                            @if($user->nip_baru && $user->nip)
                                <div class="text-secondary small">Lama: {{ $user->nip }}</div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Informasi kepegawaian --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-briefcase me-2 text-primary"></i>Informasi Kepegawaian</h3></div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Jabatan</div>
                        <div class="datagrid-content">{{ $user->jabatan ?? '-' }}</div>
                    </div>
                    @if($user->golongan || $user->eselon)
                    <div class="datagrid-item">
                        <div class="datagrid-title">Golongan / Eselon</div>
                        <div class="datagrid-content">{{ $user->golongan ?? '-' }} / {{ $user->eselon ?? '-' }}</div>
                    </div>
                    @endif
                    <div class="datagrid-item">
                        <div class="datagrid-title">Kode Organisasi</div>
                        <div class="datagrid-content">{{ $user->kode_organisasi ?? '-' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Role Sistem</div>
                        <div class="datagrid-content">
                            <span class="badge {{ $roleBadge[0] }}"><i class="ti ti-{{ $roleBadge[1] }} me-1"></i>{{ $roleBadge[2] }}</span>
                        </div>
                    </div>
                    @if($user->isAdmin())
                    <div class="datagrid-item">
                        <div class="datagrid-title">Hak Akses</div>
                        <div class="datagrid-content"><span class="status status-red">Akses penuh semua data</span></div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Unit kerja --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-map-pin me-2 text-primary"></i>Informasi Unit Kerja &amp; Lokasi</h3></div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Provinsi</div>
                        <div class="datagrid-content">
                            @if($user->kode_provinsi)<span class="badge bg-secondary-lt me-1">{{ $user->kode_provinsi }}</span>@endif
                            {{ $user->provinsi ?? '-' }}
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Kabupaten/Kota</div>
                        <div class="datagrid-content">
                            @if($user->kode_kabupaten)<span class="badge bg-secondary-lt me-1">{{ $user->kode_kabupaten }}</span>@endif
                            {{ $user->kabupaten ?? '-' }}
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Alamat Kantor</div>
                        <div class="datagrid-content">{{ $user->alamat_kantor ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
