@extends('layouts.admin')

@section('title', 'Master SLS')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin">
        <!-- Stats Cards -->
        <div class="row">
            <div class="col-sm-12">
                <div class="statistics-details d-flex align-items-center justify-content-start gap-5">
                    <div>
                        <p class="statistics-title">Total SLS</p>
                        <h3 class="rate-percentage text-info">{{ number_format($stats['total'], 0, ',', '.') }}</h3>
                        <p class="text-info d-flex small fw-bold"><i class="mdi mdi-map-marker-multiple me-1"></i>Semua Data</p>
                    </div>
                    <div>
                        <p class="statistics-title">SLS Aktif</p>
                        <h3 class="rate-percentage text-success">{{ number_format($stats['active'], 0, ',', '.') }}</h3>
                        <p class="text-success d-flex small fw-bold"><i class="mdi mdi-check-circle me-1"></i>Aktif</p>
                    </div>
                    <div>
                        <p class="statistics-title">Jenis SLS</p>
                        <h3 class="rate-percentage text-primary">{{ number_format($stats['sls'], 0, ',', '.') }}</h3>
                        <p class="text-primary d-flex small fw-bold"><i class="mdi mdi-home me-1"></i>SLS</p>
                    </div>
                    <div>
                        <p class="statistics-title">Non SLS</p>
                        <h3 class="rate-percentage text-warning">{{ number_format($stats['non_sls'], 0, ',', '.') }}</h3>
                        <p class="text-warning d-flex small fw-bold"><i class="mdi mdi-tree me-1"></i>Non SLS</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card card-rounded mt-3">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">Master SLS</h4>
                        <p class="card-subtitle card-subtitle-dash">
                            Daftar Satuan Lingkungan Setempat (SLS)
                            @if(!auth()->user()->isAdmin() && auth()->user()->kabupaten)
                                <span class="badge badge-info ms-2">
                                    <i class="mdi mdi-map-marker"></i> {{ auth()->user()->kabupaten }}
                                </span>
                            @endif
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        @if(auth()->user()->isAdmin())
                            <div class="btn-group">
                                <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="mdi mdi-download"></i> Download
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('master-sls.export') }}">
                                            <i class="mdi mdi-file-delimited"></i> Export CSV
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('master-sls.export-excel') }}">
                                            <i class="mdi mdi-file-excel"></i> Export Excel
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('master-sls.template.excel') }}">
                                            <i class="mdi mdi-file-download"></i> Template Excel
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <a href="{{ route('master-sls.import') }}" class="btn btn-primary btn-sm text-white">
                                <i class="mdi mdi-upload"></i> Import Data
                            </a>
                        @else
                            <div class="btn-group">
                                <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="mdi mdi-download"></i> Export Data
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('master-sls.export') }}">
                                            <i class="mdi mdi-file-delimited"></i> Export CSV
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('master-sls.export-excel') }}">
                                            <i class="mdi mdi-file-excel"></i> Export Excel
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        @endif
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
                <div class="bg-light p-3 rounded mb-4 border">
                    <form method="GET" action="{{ route('master-sls.index') }}" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Kabupaten</label>
                            <select name="kdkab" id="filter-kab" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua Kabupaten --</option>
                                @foreach($kabupatenList as $kab)
                                    <option value="{{ $kab->kdkab }}" {{ $kdkab == $kab->kdkab ? 'selected' : '' }}>
                                        [{{ $kab->kdkab }}] {{ $kab->nmkab }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Kecamatan</label>
                            <select name="kdkec" id="filter-kec" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua Kecamatan --</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Desa</label>
                            <select name="kddesa" id="filter-desa" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua Desa --</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Pencarian</label>
                            <input type="text" name="search" class="form-control form-control-sm" 
                                placeholder="ID / Nama Wilayah..." value="{{ $search }}">
                        </div>

                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                <i class="mdi mdi-magnify"></i> Filter
                            </button>
                            <a href="{{ route('master-sls.index') }}" class="btn btn-light btn-sm border px-3">Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Pagination Options -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <form method="GET" action="{{ route('master-sls.index') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="kdkab" value="{{ $kdkab }}">
                        <input type="hidden" name="kdkec" value="{{ $kdkec }}">
                        <input type="hidden" name="kddesa" value="{{ $kddesa }}">
                        <input type="hidden" name="search" value="{{ $search }}">
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                        <input type="hidden" name="order" value="{{ request('order') }}">
                        
                        <span class="text-dark small fw-bold">Tampilkan:</span>
                        <select name="per_page" class="form-select form-select-sm" style="width: 150px;" onchange="this.form.submit()">
                            <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50 per halaman</option>
                            <option value="100" {{ request('per_page', 50) == 100 ? 'selected' : '' }}>100 per halaman</option>
                            <option value="200" {{ request('per_page', 50) == 200 ? 'selected' : '' }}>200 per halaman</option>
                            <option value="all" {{ request('per_page', 50) == 'all' ? 'selected' : '' }}>Semua Data</option>
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

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr class="bg-light">
                                <th class="py-3">
                                    <a href="{{ route('master-sls.index', array_merge(request()->all(), ['sort' => 'idsls', 'order' => request('sort') == 'idsls' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        ID SLS @if(request('sort') == 'idsls') <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i> @endif
                                    </a>
                                </th>
                                <th class="py-3">
                                    <a href="{{ route('master-sls.index', array_merge(request()->all(), ['sort' => 'nmkab', 'order' => request('sort') == 'nmkab' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Kabupaten @if(request('sort') == 'nmkab') <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i> @endif
                                    </a>
                                </th>
                                <th class="py-3">
                                    <a href="{{ route('master-sls.index', array_merge(request()->all(), ['sort' => 'nmkec', 'order' => request('sort') == 'nmkec' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Kecamatan @if(request('sort') == 'nmkec') <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i> @endif
                                    </a>
                                </th>
                                <th class="py-3">
                                    <a href="{{ route('master-sls.index', array_merge(request()->all(), ['sort' => 'nmdesa', 'order' => request('sort') == 'nmdesa' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Desa @if(request('sort') == 'nmdesa') <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i> @endif
                                    </a>
                                </th>
                                <th class="py-3">
                                    <a href="{{ route('master-sls.index', array_merge(request()->all(), ['sort' => 'nmsls', 'order' => request('sort') == 'nmsls' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Nama SLS @if(request('sort') == 'nmsls') <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i> @endif
                                    </a>
                                </th>
                                <th class="py-3 text-center">
                                    <a href="{{ route('master-sls.index', array_merge(request()->all(), ['sort' => 'jenis', 'order' => request('sort') == 'jenis' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Jenis @if(request('sort') == 'jenis') <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i> @endif
                                    </a>
                                </th>
                                @if(auth()->user()->isAdmin())
                                    <th class="py-3 text-center">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $sls)
                            <tr>
                                <td class="py-3">
                                    <strong class="text-dark">{{ $sls->idsls }}</strong>
                                </td>
                                <td class="py-3">[{{ $sls->kdkab }}] {{ $sls->nmkab }}</td>
                                <td class="py-3">[{{ $sls->kdkec }}] {{ $sls->nmkec }}</td>
                                <td class="py-3">[{{ $sls->kddesa }}] {{ $sls->nmdesa }}</td>
                                <td class="py-3">
                                    <div class="fw-bold text-wrap" style="max-width: 200px;">{{ $sls->nmsls }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    @if($sls->jenis == 'SLS')
                                        <span class="badge badge-primary">SLS</span>
                                    @else
                                        <span class="badge badge-warning text-dark" style="font-size: 0.7rem;">{{ str_replace('NONSLS_', '', $sls->jenis) }}</span>
                                    @endif
                                </td>
                                @if(auth()->user()->isAdmin())
                                <td class="py-3 text-center">
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-xs btn-delete" 
                                            data-id="{{ $sls->id }}" 
                                            data-idsls="{{ $sls->idsls }}"
                                            data-nmsls="{{ $sls->nmsls }}">
                                        <i class="mdi mdi-delete"></i>
                                    </button>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ auth()->user()->isAdmin() ? 7 : 6 }}" class="py-5 text-center">
                                    <div class="text-muted">Tidak ada data ditemukan</div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(auth()->user()->isAdmin())
                <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form id="deleteForm" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-body">
                                    <div class="text-center mb-3">
                                        <i class="mdi mdi-alert-circle-outline text-danger" style="font-size: 3rem;"></i>
                                    </div>
                                    <p class="text-center">Apakah Anda yakin ingin menghapus data SLS ini?</p>
                                    <div class="bg-light p-3 rounded">
                                        <table class="table table-borderless table-sm mb-0">
                                            <tr>
                                                <td width="100">ID SLS</td>
                                                <td>: <strong id="del-idsls"></strong></td>
                                            </tr>
                                            <tr>
                                                <td>Nama SLS</td>
                                                <td>: <strong id="del-nmsls"></strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-danger text-white">Ya, Hapus Data</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif

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

@push('styles')
<style>
    .table td { 
        vertical-align: middle !important; 
        font-size: 0.875rem !important;
    }
    
    .table th {
        font-size: 0.875rem !important;
        font-weight: 600 !important;
    }
    
    .badge { 
        font-weight: 600; 
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
    
    .btn-xs { 
        padding: 0.25rem 0.5rem; 
        font-size: 0.75rem; 
    }
    
    .statistics-details { 
        border-bottom: 1px solid #eee; 
        padding-bottom: 20px; 
    }
    
    .form-select, 
    .form-control {
        color: #495057 !important;
        font-size: 0.875rem !important;
    }
    
    .form-label.small {
        font-size: 0.875rem;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // --- 1. LOGIC DROPDOWN DINAMIS ---
    const initialKec = "{{ request('kdkec') }}";
    const initialDesa = "{{ request('kddesa') }}";

    function loadKecamatan(kdkab, selectedKec = null) {
        if (!kdkab) return;
        $.get("{{ route('daerah-sulit.get-kecamatan') }}", { kdkab: kdkab }, function(data) {
            $('#filter-kec').empty().append('<option value="">-- Semua Kecamatan --</option>');
            $.each(data, function(key, val) {
                let selected = (selectedKec == val.kdkec) ? 'selected' : '';
                $('#filter-kec').append(`<option value="${val.kdkec}" ${selected}>[${val.kdkec}] ${val.nmkec}</option>`);
            });
            if (selectedKec) loadDesa(kdkab, selectedKec, initialDesa);
        });
    }

    function loadDesa(kdkab, kdkec, selectedDesa = null) {
        if (!kdkab || !kdkec) return;
        $.get("{{ route('daerah-sulit.get-desa') }}", { kdkab: kdkab, kdkec: kdkec }, function(data) {
            $('#filter-desa').empty().append('<option value="">-- Semua Desa --</option>');
            $.each(data, function(key, val) {
                let selected = (selectedDesa == val.kddesa) ? 'selected' : '';
                $('#filter-desa').append(`<option value="${val.kddesa}" ${selected}>[${val.kddesa}] ${val.nmdesa}</option>`);
            });
        });
    }

    // Event saat Kabupaten berubah
    $('#filter-kab').on('change', function() {
        let kdkab = $(this).val();
        $('#filter-kec').empty().append('<option value="">-- Semua Kecamatan --</option>');
        $('#filter-desa').empty().append('<option value="">-- Semua Desa --</option>');
        loadKecamatan(kdkab);
    });

    // Event saat Kecamatan berubah
    $('#filter-kec').on('change', function() {
        loadDesa($('#filter-kab').val(), $(this).val());
    });

    // Inisialisasi awal jika ada filter yang terpilih saat reload/back
    if ($('#filter-kab').val()) {
        loadKecamatan($('#filter-kab').val(), initialKec);
    }


    // --- 2. LOGIC MODAL KONFIRMASI HAPUS ---
    $('.btn-delete').on('click', function() {
        // Ambil data dari atribut tombol yang diklik
        const id = $(this).data('id');
        const idsls = $(this).data('idsls');
        const nmsls = $(this).data('nmsls');
        
        // Isi konten di dalam Modal
        $('#del-idsls').text(idsls);
        $('#del-nmsls').text(nmsls);
        
        // Buat URL hapus secara dinamis berdasarkan ID
        let url = "{{ route('master-sls.destroy', ':id') }}";
        url = url.replace(':id', id);
        
        // Set action form di dalam modal
        $('#deleteForm').attr('action', url);
        
        // Munculkan Modal
        $('#deleteModal').modal('show');
    });
});
</script>
@endpush
@endsection