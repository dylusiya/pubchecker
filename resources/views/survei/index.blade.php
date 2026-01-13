@extends('layouts.admin')

@section('title', 'Manajemen Survei')

@section('content')
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card card-rounded">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="card-title card-title-dash">Manajemen Survei</h4>
                        <p class="card-subtitle card-subtitle-dash">Master data survei dan kelengkapan file pendukung</p>
                    </div>
                    <div>
                        <a href="{{ route('survei.create') }}" class="btn btn-primary btn-lg text-white mb-0 me-0">
                            <i class="mdi mdi-plus"></i> Tambah Survei
                        </a>
                    </div>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <i class="mdi mdi-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="mdi mdi-alert-circle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                
                <div class="table-responsive mt-4">
                    <table class="table select-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Survei</th>
                                <th>Tahun</th>
                                <th>Kelengkapan File</th>
                                <th>Data Mikro</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($surveiList as $survei)
                            <tr>
                                <td>
                                    <span class="badge badge-opacity-primary">{{ $survei->kode }}</span>
                                </td>
                                <td>
                                    <h6 class="mb-0">{{ $survei->nama }}</h6>
                                    @if($survei->deskripsi)
                                        <small class="text-muted">{{ Str::limit($survei->deskripsi, 50) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-opacity-info">{{ $survei->tahun_mulai ?? '-' }}</span>
                                </td>
                                <td>
                                    @php
                                        $totalTypes = 3; // kuesioner, layout, surat
                                        $distinctTypes = $survei->attachments->pluck('type')->unique()->count();
                                        $percentage = $distinctTypes > 0 ? round(($distinctTypes / $totalTypes) * 100) : 0;
                                    @endphp
                                    
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                            <div class="progress-bar 
                                                @if($percentage == 100) bg-success
                                                @elseif($percentage >= 50) bg-warning
                                                @else bg-danger
                                                @endif" 
                                                role="progressbar" 
                                                style="width: {{ $percentage }}%">
                                            </div>
                                        </div>
                                        <small class="text-muted">{{ $distinctTypes }}/{{ $totalTypes }}</small>
                                    </div>
                                    
                                    @if($percentage == 100)
                                        <small class="text-success">
                                            <i class="mdi mdi-check-circle"></i> Lengkap
                                        </small>
                                    @else
                                        <small class="text-warning">
                                            <i class="mdi mdi-alert-circle"></i> Belum lengkap
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-opacity-secondary">
                                        {{ $survei->data_mikro_count }} data
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex">
                                        <a href="{{ route('survei.show', $survei->id) }}" 
                                           class="btn btn-sm btn-info text-white me-2"
                                           title="Detail & Upload File">
                                            <i class="mdi mdi-eye"></i>
                                        </a>
                                        <a href="{{ route('survei.edit', $survei->id) }}" 
                                           class="btn btn-sm btn-primary text-white me-2"
                                           title="Edit">
                                            <i class="mdi mdi-pencil"></i>
                                        </a>
                                        <form action="{{ route('survei.destroy', $survei->id) }}" 
                                              method="POST" 
                                              class="d-inline" 
                                              onsubmit="return confirm('Yakin ingin menghapus survei ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="btn btn-sm btn-danger text-white"
                                                    title="Hapus"
                                                    @if($survei->data_mikro_count > 0) disabled @endif>
                                                <i class="mdi mdi-delete"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="mdi mdi-file-document-outline" style="font-size: 48px; color: #ccc;"></i>
                                    <p class="text-muted mt-2">Belum ada data survei</p>
                                    <a href="{{ route('survei.create') }}" class="btn btn-primary mt-2">
                                        <i class="mdi mdi-plus"></i> Tambah Survei
                                    </a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    {{ $surveiList->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection