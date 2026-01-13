@extends('layouts.admin')

@section('title', 'Detail Data Mikro')

@section('content')
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card card-rounded">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">{{ $dataMikro->nama_data }}</h4>
                        <p class="card-subtitle card-subtitle-dash">
                            <i class="mdi mdi-calendar text-primary"></i> {{ $dataMikro->tahun }} 
                            <span class="mx-2">|</span>
                            <i class="mdi mdi-map-marker text-primary"></i> 
                            @if($dataMikro->wilayah)
                                @if($dataMikro->wilayah->jenis == 'provinsi')
                                    {{ $dataMikro->wilayah->nama }}
                                @elseif($dataMikro->wilayah->jenis == 'kota')
                                    Kota {{ $dataMikro->wilayah->nama }}
                                @else
                                    Kabupaten {{ $dataMikro->wilayah->nama }}
                                @endif
                            @else
                                -
                            @endif
                            @if($dataMikro->survei)
                                <span class="mx-2">|</span>
                                <i class="mdi mdi-file-document text-primary"></i>
                                {{ $dataMikro->survei->kode }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('data-mikro.index') }}" class="btn btn-light">
                            <i class="mdi mdi-arrow-left"></i> Kembali
                        </a>
                        @if(auth()->user()->isAdmin())
                        <a href="{{ route('data-mikro.edit', $dataMikro->id) }}" class="btn btn-primary text-white">
                            <i class="mdi mdi-pencil"></i> Edit
                        </a>
                        @endif
                    </div>
                </div>
                
                <div class="row">
                    <!-- Info Umum -->
                    <div class="col-md-6">
                        <div class="card card-rounded mb-3" style="background: #f8f9fa;">
                            <div class="card-body">
                                <h5 class="mb-3"><i class="mdi mdi-information text-primary"></i> Informasi Umum</h5>
                                <table class="table table-borderless mb-0">
                                    <tr>
                                        <td width="40%"><strong>Survei</strong></td>
                                        <td>
                                            @if($dataMikro->survei)
                                                <span class="badge badge-opacity-info">{{ $dataMikro->survei->nama }}</span>
                                                <br><small class="text-muted">{{ $dataMikro->survei->kode }}</small>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Wilayah</strong></td>
                                        <td>
                                            @if($dataMikro->wilayah)
                                                @if($dataMikro->wilayah->jenis == 'provinsi')
                                                    <span class="badge badge-opacity-warning">{{ $dataMikro->wilayah->nama }}</span>
                                                @elseif($dataMikro->wilayah->jenis == 'kota')
                                                    <span class="badge badge-opacity-success">Kota {{ $dataMikro->wilayah->nama }}</span>
                                                @else
                                                    <span class="badge badge-opacity-primary">Kab. {{ $dataMikro->wilayah->nama }}</span>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Kode Wilayah</strong></td>
                                        <td>{{ $dataMikro->wilayah->kode ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tahun</strong></td>
                                        <td><span class="badge badge-opacity-primary">{{ $dataMikro->tahun }}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Dibuat Oleh</strong></td>
                                        <td>{{ $dataMikro->creator->name ?? 'Unknown' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tanggal Input</strong></td>
                                        <td>{{ $dataMikro->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Keterangan -->
                    <div class="col-md-6">
                        <div class="card card-rounded mb-3" style="background: #f8f9fa;">
                            <div class="card-body">
                                <h5 class="mb-3"><i class="mdi mdi-text text-primary"></i> Keterangan</h5>
                                @if($dataMikro->keterangan)
                                    <p class="mb-0">{{ $dataMikro->keterangan }}</p>
                                @else
                                    <p class="text-muted mb-0">Tidak ada keterangan</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Download Data Mikro -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card card-rounded" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="card-body">
                                <h5 class="text-white mb-4">
                                    <i class="mdi mdi-download"></i> Download Data Mikro
                                </h5>
                                
                                <a href="{{ $dataMikro->data_link }}" 
                                   target="_blank" 
                                   class="btn btn-light btn-lg w-100 d-flex align-items-center justify-content-between">
                                    <span>
                                        <i class="mdi mdi-database text-primary"></i>
                                        <strong class="ms-2">Data Mikro {{ $dataMikro->wilayah->nama ?? '' }} {{ $dataMikro->tahun }}</strong>
                                    </span>
                                    <i class="mdi mdi-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- File Pendukung dari Survei -->
                @if($dataMikro->survei && $dataMikro->survei->attachments->count() > 0)
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card card-rounded">
                            <div class="card-body">
                                <h5 class="mb-3">
                                    <i class="mdi mdi-file-document-multiple text-primary"></i> 
                                    File Pendukung Survei
                                </h5>
                                <p class="text-muted small">File pendukung dari survei {{ $dataMikro->survei->nama }}</p>
                                
                                <div class="row">
                                    @foreach($dataMikro->survei->attachments as $attachment)
                                    <div class="col-md-6 mb-3">
                                        <a href="{{ route('survei.download-attachment', [$dataMikro->survei->id, $attachment->id]) }}" 
                                           class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center justify-content-between">
                                            <span>
                                                @if($attachment->type == 'kuesioner')
                                                    <i class="mdi mdi-file-document-outline text-info"></i>
                                                @elseif($attachment->type == 'layout')
                                                    <i class="mdi mdi-file-chart-outline text-success"></i>
                                                @elseif($attachment->type == 'surat')
                                                    <i class="mdi mdi-email-outline text-warning"></i>
                                                @else
                                                    <i class="mdi mdi-information-outline text-primary"></i>
                                                @endif
                                                <strong class="ms-2">{{ $attachment->type_label }}</strong>
                                                <br>
                                                <small class="text-muted ms-4">{{ $attachment->file_size_human }}</small>
                                            </span>
                                            <i class="mdi mdi-download"></i>
                                        </a>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @elseif($dataMikro->survei)
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert-circle-outline"></i>
                            <strong>Info:</strong> Survei ini belum memiliki file pendukung. 
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('survei.show', $dataMikro->survei->id) }}" class="alert-link">Klik di sini untuk upload file</a>.
                            @endif
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- User Access (Admin Only) -->
                @if(auth()->user()->isAdmin())
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card card-rounded">
                            <div class="card-body">
                                <h5 class="mb-3">
                                    <i class="mdi mdi-account-multiple text-primary"></i> 
                                    User yang Memiliki Akses ({{ $dataMikro->assignedUsers->count() }})
                                </h5>
                                
                                @if($dataMikro->assignedUsers->count() > 0)
                                <div class="row">
                                    @foreach($dataMikro->assignedUsers as $user)
                                    <div class="col-md-4 mb-3">
                                        <div class="d-flex align-items-center p-3 border rounded">
                                            <div class="me-2 position-relative" style="width: 40px; height: 40px;">
                                                @if($user->foto)
                                                    <img src="{{ $user->foto }}" 
                                                         alt="" 
                                                         class="rounded-circle user-avatar-{{ $user->id }}" 
                                                         style="width: 40px; height: 40px; position: absolute;"
                                                         onerror="document.querySelector('.user-avatar-{{ $user->id }}').style.display='none'; document.querySelector('.user-fallback-{{ $user->id }}').style.display='flex';">
                                                    <div class="bg-primary rounded-circle user-fallback-{{ $user->id }} align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px; display: none; position: absolute;">
                                                        <i class="mdi mdi-account text-white"></i>
                                                    </div>
                                                @else
                                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="mdi mdi-account text-white"></i>
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                <h6 class="mb-0">{{ $user->name }}</h6>
                                                <small class="text-muted">{{ $user->username }}</small>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @else
                                <div class="text-center py-4">
                                    <i class="mdi mdi-account-off" style="font-size: 48px; color: #ccc;"></i>
                                    <p class="text-muted mt-2">Belum ada user yang di-assign</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                
            </div>
        </div>
    </div>
</div>
@endsection