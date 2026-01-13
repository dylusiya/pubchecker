@extends('layouts.admin')

@section('title', 'Master SLS')

@section('content')
<div class="row">
    <div class="col-md-12">
        <!-- Stats Cards -->
        <div class="statistics-details d-flex align-items-center justify-content-between mb-4">
            <div>
                <p class="statistics-title">Total SLS</p>
                <h3 class="rate-percentage">{{ $stats['total'] }}</h3>
                <p class="text-info d-flex"><i class="mdi mdi-map-marker-multiple"></i><span>Semua Data</span></p>
            </div>
            <div>
                <p class="statistics-title">SLS Aktif</p>
                <h3 class="rate-percentage">{{ $stats['active'] }}</h3>
                <p class="text-success d-flex"><i class="mdi mdi-check-circle"></i><span>Aktif</span></p>
            </div>
            <div>
                <p class="statistics-title">Jenis SLS</p>
                <h3 class="rate-percentage">{{ $stats['sls'] }}</h3>
                <p class="text-primary d-flex"><i class="mdi mdi-home"></i><span>SLS</span></p>
            </div>
            <div class="d-none d-md-block">
                <p class="statistics-title">Non SLS</p>
                <h3 class="rate-percentage">{{ $stats['non_sls'] }}</h3>
                <p class="text-warning d-flex"><i class="mdi mdi-tree"></i><span>Non SLS</span></p>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title mb-0">Master SLS</h4>
                        <p class="card-subtitle">Daftar Satuan Lingkungan Setempat (SLS)</p>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="btn-group">
                            <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="mdi mdi-download"></i> Download
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="{{ route('master-sls.export') }}">
                                        <i class="mdi mdi-file-delimited"></i> Export CSV
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('master-sls.template.excel') }}">
                                        <i class="mdi mdi-file-excel"></i> Template Excel
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <a href="{{ route('master-sls.import') }}" class="btn btn-primary">
                            <i class="mdi mdi-upload"></i> Import Data
                        </a>
                    </div>
                </div>

                <!-- Alerts -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <!-- Filter & Search -->
                <form method="GET" action="{{ route('master-sls.index') }}" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Kabupaten/Kota</label>
                            <select name="kdkab" class="form-select">
                                <option value="">-- Semua Kabupaten --</option>
                                @foreach($kabupatenList as $kab)
                                    <option value="{{ $kab->kdkab }}" {{ $kdkab == $kab->kdkab ? 'selected' : '' }}>
                                        {{ $kab->nmkab }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cari</label>
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Cari ID SLS, Nama SLS, atau Nama Ketua..."
                                   value="{{ $search }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="mdi mdi-magnify"></i> Cari
                                </button>
                                @if($search || $kdkab)
                                <a href="{{ route('master-sls.index') }}" class="btn btn-secondary">
                                    <i class="mdi mdi-refresh"></i>
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID SLS</th>
                                <th>Nama SLS</th>
                                <th>Nama Ketua</th>
                                <th>Jenis</th>
                                <th>Wilayah</th>
                                <th>Koordinat</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $sls)
                            <tr>
                                <td>
                                    <strong>{{ $sls->idsls }}</strong>
                                </td>
                                <td>{{ $sls->nmsls }}</td>
                                <td>
                                    @if($sls->nama_ketua)
                                        {{ $sls->nama_ketua }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($sls->jenis == 'SLS')
                                        <span class="badge badge-opacity-primary">SLS</span>
                                    @else
                                        <span class="badge badge-opacity-warning">{{ $sls->jenis_display }}</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">
                                        {{ $sls->nmkab }}<br>
                                        Kec. {{ $sls->nmkec }}<br>
                                        {{ $sls->nmdesa }}
                                    </small>
                                </td>
                                <td>
                                    @if($sls->latitude && $sls->longitude)
                                        <small>
                                            {{ number_format($sls->latitude, 6) }},<br>
                                            {{ number_format($sls->longitude, 6) }}
                                        </small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($sls->is_active)
                                        <span class="badge badge-opacity-success">
                                            <i class="mdi mdi-check"></i> Aktif
                                        </span>
                                    @else
                                        <span class="badge badge-opacity-secondary">
                                            <i class="mdi mdi-close"></i> Tidak Aktif
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <form action="{{ route('master-sls.destroy', $sls->id) }}" 
                                          method="POST" 
                                          class="d-inline"
                                          onsubmit="return confirm('Yakin ingin menghapus SLS {{ $sls->idsls }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="mdi mdi-delete"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="mdi mdi-map-marker-off" style="font-size: 48px; color: #ccc;"></i>
                                    <p class="text-muted mt-2">
                                        @if($search || $kdkab)
                                            Tidak ada data yang sesuai dengan pencarian
                                        @else
                                            Belum ada data Master SLS
                                        @endif
                                    </p>
                                    @if(!$search && !$kdkab)
                                    <a href="{{ route('master-sls.import') }}" class="btn btn-primary mt-2">
                                        <i class="mdi mdi-upload"></i> Import Data
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($data->hasPages())
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        Menampilkan {{ $data->firstItem() }} - {{ $data->lastItem() }} dari {{ $data->total() }} data
                    </div>
                    <div>
                        {{ $data->appends(request()->query())->links() }}
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection