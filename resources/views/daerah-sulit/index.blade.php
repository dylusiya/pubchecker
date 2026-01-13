@extends('layouts.admin')

@section('title', 'Data Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin">
        <div class="row">
            <div class="col-sm-12">
                <div class="statistics-details d-flex align-items-center justify-content-start gap-5">
                    <div>
                        <p class="statistics-title">Daerah (SLS) Sulit</p>
                        <h3 class="rate-percentage text-danger">{{ number_format($stats['sulit'], 0, ',', '.') }}</h3>
                        <p class="text-danger d-flex small fw-bold"><i class="mdi mdi-alert-circle me-1"></i>Sulit Diakses</p>
                    </div>
                    <div>
                        <p class="statistics-title">Total Estimasi Biaya</p>
                        <h3 class="rate-percentage text-primary">Rp {{ number_format($stats['total_biaya'], 0, ',', '.') }}</h3>
                        <p class="text-warning d-flex small fw-bold"><i class="mdi mdi-cash-multiple me-1"></i>Anggaran</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-rounded mt-3">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">Daftar Status Akses SLS</h4>
                        <p class="card-subtitle card-subtitle-dash">Daftar klasifikasi tingkat kesulitan transportasi per wilayah</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('daerah-sulit.history') }}" class="btn btn-outline-dark btn-sm">
                            <i class="mdi mdi-history"></i> Riwayat
                        </a>

                        <a href="{{ route('daerah-sulit.import.form') }}" class="btn btn-success btn-sm text-white">
                            <i class="mdi mdi-upload"></i> Import Data
                        </a>

                        <a href="{{ route('daerah-sulit.create') }}" class="btn btn-primary btn-sm text-white">
                            <i class="mdi mdi-plus"></i> Tambah Data
                        </a>
                    </div>
                </div>

                <div class="bg-light p-3 rounded mb-4 border">
                    <form method="GET" action="{{ route('daerah-sulit.index') }}" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Tahun</label>
                            <select name="tahun" class="form-select form-select-sm text-dark">
                                @for($y = date('Y') + 1; $y >= 2020; $y--)
                                    <option value="{{ $y }}" {{ $tahunAktif == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Kabupaten</label>
                            <select name="kdkab" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua Kabupaten --</option>
                                @foreach($kabupatenList as $kab)
                                    <option value="{{ $kab->kdkab }}" {{ $kdkab == $kab->kdkab ? 'selected' : '' }}>{{ $kab->nmkab }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Status Approval</label>
                            <select name="approval" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua --</option>
                                <option value="draft" {{ request('approval', 'disetujui') == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="pending" {{ request('approval', 'disetujui') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="disetujui" {{ request('approval', 'disetujui') == 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                                <option value="ditolak" {{ request('approval', 'disetujui') == 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Cari Wilayah</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama SLS / ID SLS..." value="{{ $search }}">
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="mdi mdi-magnify"></i> Filter</button>
                            <a href="{{ route('daerah-sulit.index') }}" class="btn btn-light btn-sm border px-3">Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Pagination Options -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <form method="GET" action="{{ route('daerah-sulit.index') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="tahun" value="{{ $tahunAktif }}">
                        <input type="hidden" name="kdkab" value="{{ $kdkab }}">
                        <input type="hidden" name="approval" value="{{ request('approval', 'disetujui') }}">
                        <input type="hidden" name="search" value="{{ $search }}">
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                        <input type="hidden" name="order" value="{{ request('order') }}">
                        
                        <span class="text-dark small fw-bold">Tampilkan:</span>
                        <select name="per_page" class="form-select form-select-sm" style="width: 150px;" onchange="this.form.submit()">
                            <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20 per halaman</option>
                            <option value="50" {{ request('per_page', 20) == 50 ? 'selected' : '' }}>50 per halaman</option>
                            <option value="100" {{ request('per_page', 20) == 100 ? 'selected' : '' }}>100 per halaman</option>
                            <option value="all" {{ request('per_page', 20) == 'all' ? 'selected' : '' }}>Semua Data</option>
                        </select>
                    </form>
                    
                    <div class="text-muted small">
                        Menampilkan 
                        <strong>{{ $data->firstItem() ?? 0 }}</strong> 
                        sampai 
                        <strong>{{ $data->lastItem() ?? 0 }}</strong> 
                        dari 
                        <strong>{{ $data->total() }}</strong> 
                        data
                    </div>
                </div>


                @if(session('importErrors'))
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <h6 class="fw-bold"><i class="mdi mdi-alert me-2"></i>Beberapa baris gagal diimport:</h6>
                        <div style="max-height: 150px; overflow-y: auto;">
                            <ul class="mb-0 small">
                                @foreach(session('importErrors') as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr class="bg-light">
                                <th class="py-3">
                                    <a href="{{ route('daerah-sulit.index', array_merge(request()->all(), ['sort' => 'idsls', 'order' => request('sort') == 'idsls' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Identitas SLS
                                        @if(request('sort') == 'idsls')
                                            <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="py-3">
                                    <a href="{{ route('daerah-sulit.index', array_merge(request()->all(), ['sort' => 'kdkab', 'order' => request('sort') == 'kdkab' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Wilayah
                                        @if(request('sort') == 'kdkab')
                                            <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="py-3 text-center">
                                    <a href="{{ route('daerah-sulit.index', array_merge(request()->all(), ['sort' => 'is_daerah_sulit', 'order' => request('sort') == 'is_daerah_sulit' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Tingkat Kesulitan
                                        @if(request('sort') == 'is_daerah_sulit')
                                            <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="py-3 text-end">
                                    <a href="{{ route('daerah-sulit.index', array_merge(request()->all(), ['sort' => 'perkiraan_biaya', 'order' => request('sort') == 'perkiraan_biaya' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Biaya Estimasi
                                        @if(request('sort') == 'perkiraan_biaya')
                                            <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="py-3">
                                    <a href="{{ route('daerah-sulit.index', array_merge(request()->all(), ['sort' => 'status_approval', 'order' => request('sort') == 'status_approval' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Status Approval
                                        @if(request('sort') == 'status_approval')
                                            <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $item)
                            <tr>
                                <td class="py-3">
                                    <div class="fw-bold text-dark">{{ $item->masterSls->idsls }}</div>
                                    <div class="small text-muted text-uppercase">{{ $item->masterSls->nmsls }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="small fw-bold">{{ $item->masterSls->nmkab }}</div>
                                    <div class="small text-muted">{{ $item->masterSls->nmkec }} - {{ $item->masterSls->nmdesa }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    @if($item->is_daerah_sulit)
                                        <label class="badge badge-danger">SULIT</label>
                                        <div class="mt-1 small"><i class="mdi mdi-truck-fast text-muted"></i> {{ $item->metode_transportasi ?? '-' }}</div>
                                    @else
                                        <label class="badge badge-success">NORMAL</label>
                                    @endif
                                </td>
                                <td class="py-3 text-end">
                                    @if($item->is_daerah_sulit)
                                        <span class="fw-bold">Rp {{ number_format($item->perkiraan_biaya, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-muted small">N/A</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    @php
                                        $badges = [
                                            'disetujui' => 'badge-opacity-success',
                                            'ditolak' => 'badge-opacity-danger',
                                            'menunggu_review' => 'badge-opacity-warning',
                                            'draft' => 'badge-opacity-info'
                                        ];
                                        $labels = [
                                            'disetujui' => 'Disetujui',
                                            'ditolak' => 'Ditolak',
                                            'menunggu_review' => 'Review',
                                            'draft' => 'Draft'
                                        ];
                                    @endphp
                                    <span class="badge {{ $badges[$item->status_approval] ?? 'badge-secondary' }}">
                                        {{ $labels[$item->status_approval] ?? $item->status_approval }}
                                    </span>
                                </td>
                                <td class="py-3 text-center">
                                    <div class="btn-group">
                                        <a href="{{ route('daerah-sulit.show', $item->id) }}" class="btn btn-outline-info btn-xs" title="Lihat">
                                            <i class="mdi mdi-eye"></i>
                                        </a>
                                        @if($item->status_approval == 'draft' || $item->status_approval == 'ditolak')
                                        <a href="{{ route('daerah-sulit.edit', $item->id) }}" class="btn btn-outline-warning btn-xs" title="Edit">
                                            <i class="mdi mdi-pencil"></i>
                                        </a>
                                        <form action="{{ route('daerah-sulit.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-xs"><i class="mdi mdi-trash-can"></i></button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="py-5 text-center">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="mdi mdi-database-off text-light mb-3" style="font-size: 60px;"></i>
                                        <h5 class="text-muted fw-normal">Data Tahun {{ $tahunAktif }} Tidak Ditemukan</h5>
                                        <p class="text-muted small">Silahkan pilih tahun lain di filter atau tambahkan data baru untuk tahun ini.</p>
                                        <a href="{{ route('daerah-sulit.create', ['tahun' => $tahunAktif]) }}" class="btn btn-primary btn-sm mt-2 text-white">
                                            <i class="mdi mdi-plus"></i> Tambah Data {{ $tahunAktif }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($data->hasPages())
                <div class="mt-4">
                    {{ $data->links() }}
                </div>
                @endif

                </div>
        </div>
    </div>
</div>

<style>
    
    .table td { vertical-align: middle !important; }
    .badge { font-weight: 600; font-size: 10px; }
    .btn-xs { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
    .statistics-details { border-bottom: 1px solid #eee; padding-bottom: 20px; }
</style>

@endsection