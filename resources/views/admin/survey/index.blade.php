@extends('layouts.app')

@section('title', 'Data Survey')

@section('content')
<div class="row">
    <div class="col-sm-12">
        <!-- Header -->
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-1">Data Survey Kepuasan Masyarakat</h4>
                        <p class="text-muted mb-0 small">Kelola dan analisis hasil survey</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm border">
                            <i class="mdi mdi-home"></i> Dashboard
                        </a>
                        <a href="{{ route('admin.survey.dashboard') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-chart-bar"></i> Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card card-rounded mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('admin.survey.index') }}" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label class="form-label mb-0 fw-bold">Filter :</label>
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-0 small">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" 
                               value="{{ request('start_date') }}">
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-0 small">Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" 
                               value="{{ request('end_date') }}">
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-0 small">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Semua Status</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Selesai</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-0 small">Kategori Instansi</label>
                        <select name="kategori_instansi" class="form-select form-select-sm">
                            <option value="">Semua Kategori</option>
                            <option value="Pemerintah Daerah" {{ request('kategori_instansi') == 'Pemerintah Daerah' ? 'selected' : '' }}>Pemerintah Daerah</option>
                            <option value="Pemerintah Pusat" {{ request('kategori_instansi') == 'Pemerintah Pusat' ? 'selected' : '' }}>Pemerintah Pusat</option>
                            <option value="BUMN/BUMD" {{ request('kategori_instansi') == 'BUMN/BUMD' ? 'selected' : '' }}>BUMN/BUMD</option>
                            <option value="Swasta" {{ request('kategori_instansi') == 'Swasta' ? 'selected' : '' }}>Swasta</option>
                            <option value="Perguruan Tinggi" {{ request('kategori_instansi') == 'Perguruan Tinggi' ? 'selected' : '' }}>Perguruan Tinggi</option>
                            <option value="Organisasi/Lembaga" {{ request('kategori_instansi') == 'Organisasi/Lembaga' ? 'selected' : '' }}>Organisasi/Lembaga</option>
                            <option value="Perorangan" {{ request('kategori_instansi') == 'Perorangan' ? 'selected' : '' }}>Perorangan</option>
                            <option value="Lainnya" {{ request('kategori_instansi') == 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-0 small">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            <option value="Laki-laki" {{ request('jenis_kelamin') == 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                            <option value="Perempuan" {{ request('jenis_kelamin') == 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-0 small text-white">.</label>
                        <button type="submit" class="btn btn-sm btn-primary d-block">
                            <i class="mdi mdi-filter"></i> Terapkan
                        </button>
                    </div>
                    <div class="col-auto">
                        @if(request()->hasAny(['start_date', 'end_date', 'kategori_instansi', 'jenis_kelamin', 'status']))
                            <label class="form-label mb-0 small text-white">.</label>
                            <a href="{{ route('admin.survey.index') }}" class="btn btn-sm btn-light border d-block">
                                <i class="mdi mdi-refresh"></i> Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card card-rounded">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center gap-4">
                            <div>
                                <p class="text-muted mb-1 small">Total Data</p>
                                <h3 class="mb-0 text-primary fw-bold">{{ number_format($statistics['total_responses'] ?? 0, 0, ',', '.') }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card card-rounded">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3">#</th>
                                <th class="py-3">Waktu</th>
                                <th class="py-3">Responden</th>
                                <th class="py-3">Instansi</th>
                                <th class="py-3 text-center">Status</th>
                                <th class="py-3 text-center">Kepuasan</th>
                                <th class="py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($responses as $index => $response)
                            <tr>
                                <td class="py-3">{{ $responses->firstItem() + $index }}</td>
                                <td class="py-3">
                                    @if($response->tanggal_submit)
                                        <div class="fw-bold">{{ $response->tanggal_submit->format('d/m/Y') }}</div>
                                        <small class="text-muted">{{ $response->tanggal_submit->format('H:i') }}</small>
                                    @else
                                        <div class="fw-bold text-muted">{{ $response->updated_at->format('d/m/Y') }}</div>
                                        <small class="text-muted italic">(Draft)</small>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold">{{ $response->nama ?? '-' }}</div>
                                    <small class="text-muted">{{ $response->email ?? '-' }}</small>
                                    <div class="small text-muted">
                                        <i class="mdi mdi-phone"></i> {{ $response->nomor_hp ?? '-' }}
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold">{{ $response->nama_instansi ?? '-' }}</div>
                                    <small class="text-muted">{{ $response->kategori_instansi ?? '-' }}</small>
                                </td>
                                <td class="py-3 text-center">
                                    @if($response->status == 'completed')
                                        <span class="badge badge-success">Selesai</span>
                                    @else
                                        <span class="badge badge-warning">Draft</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    @if($response->status == 'completed')
                                        @php
                                            $avgKepuasan = $response->average_kepuasan;
                                            $badgeClass = $avgKepuasan >= 8 ? 'badge-success' : ($avgKepuasan >= 6 ? 'badge-warning' : 'badge-danger');
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">
                                            {{ number_format($avgKepuasan, 2) }}/10
                                        </span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="{{ route('admin.survey.show', $response->id) }}" 
                                           class="btn btn-outline-info btn-xs" title="Detail">
                                            <i class="mdi mdi-eye"></i>
                                        </a>
                                        <form action="{{ route('admin.survey.destroy', $response->id) }}" 
                                              method="POST" class="d-inline" 
                                              onsubmit="return confirm('Yakin ingin menghapus data survey ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-xs" title="Hapus">
                                                <i class="mdi mdi-delete"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-5 text-center">
                                    <i class="mdi mdi-clipboard-text-outline text-light mb-3" style="font-size: 60px;"></i>
                                    <h5 class="text-muted fw-normal">Belum ada data survey</h5>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($responses->hasPages())
                <div class="mt-4">
                    {{ $responses->appends(request()->except('page'))->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .btn-xs { 
        padding: 0.25rem 0.5rem; 
        font-size: 0.75rem; 
    }
</style>
@endpush
@endsection