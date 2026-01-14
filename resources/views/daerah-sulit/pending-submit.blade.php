@extends('layouts.admin')

@section('title', 'Submit Data Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title text-primary">Submit Data untuk Review</h4>
                        <p class="card-description">Pilih data yang siap disubmit untuk direview admin</p>
                    </div>
                    <a href="{{ route('daerah-sulit.index') }}" class="btn btn-outline-secondary btn-icon-text btn-sm">
                        <i class="mdi mdi-arrow-left btn-icon-prepend"></i> Kembali
                    </a>
                </div>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ $stats['total_draft'] }}</h3>
                                <small>Draft (Belum Disubmit)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ $stats['total_ditolak'] }}</h3>
                                <small>Ditolak (Perlu Diperbaiki)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter -->
                <div class="bg-light p-3 rounded mb-4 border">
                    <form method="GET" action="{{ route('daerah-sulit.pending-submit') }}" class="row g-2 align-items-end">
                        <div class="col-md-1">
                            <label class="form-label small fw-bold text-dark">Tahun</label>
                            <select name="tahun" class="form-select form-select-sm text-dark">
                                @for($y = date('Y') + 1; $y >= 2020; $y--)
                                    <option value="{{ $y }}" {{ request('tahun', date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-dark">Kabupaten</label>
                            <select name="kdkab" id="filter-kab" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua Kabupaten --</option>
                                @php
                                    $user = auth()->user();
                                    $kabupatenList = \App\Models\MasterSls::select('kdkab', 'nmkab')
                                        ->when(!$user->isAdmin() && $user->kode_kabupaten, function($q) use ($user) {
                                            $q->where('kdkab', $user->kode_kabupaten);
                                        })
                                        ->distinct()->orderBy('kdkab')->get();
                                @endphp
                                @foreach($kabupatenList as $kab)
                                    <option value="{{ $kab->kdkab }}" {{ request('kdkab') == $kab->kdkab ? 'selected' : '' }}>
                                        [{{ $kab->kdkab }}] {{ $kab->nmkab }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-dark">Kecamatan</label>
                            <select name="kdkec" id="filter-kec" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua Kecamatan --</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-dark">Desa</label>
                            <select name="kddesa" id="filter-desa" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua Desa --</option>
                            </select>
                        </div>

                        <div class="col-md-1">
                            <label class="form-label small fw-bold text-dark">Status</label>
                            <select name="status_filter" class="form-select form-select-sm text-dark">
                                <option value="">Semua</option>
                                <option value="draft" {{ request('status_filter') == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="ditolak" {{ request('status_filter') == 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-dark">Cari Wilayah</label>
                            <input type="text" name="search" class="form-control form-control-sm text-dark" 
                                placeholder="ID / Nama SLS..." value="{{ request('search') }}">
                        </div>

                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="mdi mdi-magnify"></i> Filter</button>
                            <a href="{{ route('daerah-sulit.pending-submit') }}" class="btn btn-light btn-sm border px-3"><i class="mdi mdi-refresh"></i></a>
                        </div>
                    </form>
                </div>

                <!-- Pagination Options -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <form method="GET" action="{{ route('daerah-sulit.pending-submit') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="tahun" value="{{ request('tahun', date('Y')) }}">
                        <input type="hidden" name="kdkab" value="{{ request('kdkab') }}">
                        <input type="hidden" name="kdkec" value="{{ request('kdkec') }}">
                        <input type="hidden" name="kddesa" value="{{ request('kddesa') }}">
                        <input type="hidden" name="status_filter" value="{{ request('status_filter') }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="sort" value="{{ request('sort', 'idsls') }}">
                        <input type="hidden" name="order" value="{{ request('order', 'asc') }}">
                        
                        <span class="text-dark small fw-bold">Tampilkan:</span>
                        <select name="per_page" class="form-select form-select-sm text-dark" style="width: 150px;" onchange="this.form.submit()">
                            <option value="20" {{ $perPage == 20 ? 'selected' : '' }}>20 per halaman</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per halaman</option>
                            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per halaman</option>
                            <option value="all" {{ $perPage == 'all' ? 'selected' : '' }}>Semua Data</option>
                        </select>
                    </form>
                    
                    <div class="text-muted small">
                        Menampilkan 
                        <strong class="text-dark">{{ $data->firstItem() ?? 0 }}</strong> 
                        sampai 
                        <strong class="text-dark">{{ $data->lastItem() ?? 0 }}</strong> 
                        dari 
                        <strong class="text-dark">{{ $data->total() }}</strong> 
                        data
                        @if($perPage == 'all')
                            <span class="badge badge-info ms-2">Semua data ditampilkan</span>
                        @endif
                    </div>
                </div>

                @if($data->count() > 0)
                <!-- Action Buttons -->
                <div class="mb-3 d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-primary" id="btnSubmitSelected">
                        <i class="mdi mdi-send"></i> Submit Terpilih (<span id="countSelected">0</span>)
                    </button>
                    <button type="button" class="btn btn-danger" id="btnDeleteSelected">
                        <i class="mdi mdi-delete"></i> Hapus Terpilih (<span id="countSelected2">0</span>)
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnSelectAll">
                        <i class="mdi mdi-checkbox-multiple-marked"></i> Pilih Semua
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnDeselectAll">
                        <i class="mdi mdi-checkbox-multiple-blank-outline"></i> Batal Pilih
                    </button>
                    
                    @if($perPage == 'all')
                        <span class="badge badge-info align-self-center">Menampilkan semua {{ $data->total() }} data</span>
                    @endif
                </div>

                <!-- Table -->
                <form id="bulkForm">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="checkAll" class="form-check-input">
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-submit', array_merge(request()->all(), ['sort' => 'idsls', 'order' => request('sort') == 'idsls' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            ID SLS
                                            @if(request('sort', 'idsls') == 'idsls')
                                                <i class="mdi mdi-chevron-{{ request('order', 'asc') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-submit', array_merge(request()->all(), ['sort' => 'nmsls', 'order' => request('sort') == 'nmsls' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            Nama SLS
                                            @if(request('sort') == 'nmsls')
                                                <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-submit', array_merge(request()->all(), ['sort' => 'kdkab', 'order' => request('sort') == 'kdkab' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            Wilayah
                                            @if(request('sort') == 'kdkab')
                                                <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-submit', array_merge(request()->all(), ['sort' => 'is_daerah_sulit', 'order' => request('sort') == 'is_daerah_sulit' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            Status
                                            @if(request('sort') == 'is_daerah_sulit')
                                                <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-submit', array_merge(request()->all(), ['sort' => 'perkiraan_biaya', 'order' => request('sort') == 'perkiraan_biaya' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            Biaya
                                            @if(request('sort') == 'perkiraan_biaya')
                                                <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-submit', array_merge(request()->all(), ['sort' => 'status_approval', 'order' => request('sort') == 'status_approval' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            Status Approval
                                            @if(request('sort') == 'status_approval')
                                                <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data as $item)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="{{ $item->id }}" class="form-check-input checkbox-item">
                                    </td>
                                    <td>
                                        <strong>{{ $item->masterSls->idsls }}</strong>
                                    </td>
                                    <td>{{ Str::limit($item->masterSls->nmsls, 40) }}</td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $item->masterSls->nmkab }}<br>
                                            Kec. {{ $item->masterSls->nmkec }}
                                        </small>
                                    </td>
                                    <td>
                                        @if($item->is_daerah_sulit)
                                            <span class="badge badge-danger">Sulit</span>
                                        @else
                                            <span class="badge badge-success">Tidak Sulit</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->is_daerah_sulit)
                                            <strong>Rp {{ number_format($item->perkiraan_biaya, 0, ',', '.') }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $item->metode_transportasi }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->status_approval == 'draft')
                                            <span class="badge badge-secondary">
                                                <i class="mdi mdi-file"></i> Draft
                                            </span>
                                        @else
                                            <span class="badge badge-danger">
                                                <i class="mdi mdi-close-circle"></i> Ditolak
                                            </span>
                                            @if($item->catatan_approval)
                                                <br>
                                                <small class="text-muted" title="{{ $item->catatan_approval }}">
                                                    {{ Str::limit($item->catatan_approval, 30) }}
                                                </small>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('daerah-sulit.show', $item->id) }}" 
                                           class="btn btn-sm btn-info" target="_blank">
                                            <i class="mdi mdi-eye"></i>
                                        </a>
                                        <a href="{{ route('daerah-sulit.edit', $item->id) }}" 
                                           class="btn btn-sm btn-warning">
                                            <i class="mdi mdi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                @if($data->hasPages())
                <div class="mt-4">
                    {{ $data->appends(request()->query())->links() }}
                </div>
                @endif

                @else
                <div class="text-center py-5">
                    <i class="mdi mdi-check-all" style="font-size: 64px; color: #ccc;"></i>
                    <p class="text-muted mt-3">Tidak ada data yang perlu disubmit</p>
                    <a href="{{ route('daerah-sulit.create') }}" class="btn btn-primary mt-2">
                        <i class="mdi mdi-plus"></i> Tambah Data Baru
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Submit Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('daerah-sulit.bulk-submit') }}" method="POST">
                @csrf
                <input type="hidden" name="selected_ids" id="selectedIdsSubmit">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="mdi mdi-send me-2"></i>Submit Data Terpilih
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="mdi mdi-send-circle-outline text-primary" style="font-size: 64px;"></i>
                    </div>
                    <p class="text-center mb-3">
                        Anda akan submit <strong id="submitCount">0</strong> data untuk direview oleh approveradmin.
                    </p>
                    <div class="alert alert-info">
                        <small>
                            <i class="mdi mdi-information me-1"></i>
                            <strong>Catatan:</strong> Setelah disubmit, data tidak dapat diedit hingga mendapat keputusan dari approver/admin.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Ya, Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('daerah-sulit.bulk-delete') }}" method="POST">
                @csrf
                <input type="hidden" name="selected_ids" id="selectedIdsDelete">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="mdi mdi-delete me-2"></i>Hapus Data Terpilih
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="mdi mdi-alert-circle-outline text-danger" style="font-size: 64px;"></i>
                    </div>
                    <p class="text-center mb-3">
                        Anda akan menghapus <strong id="deleteCount">0</strong> data.
                    </p>
                    <div class="alert alert-danger">
                        <small>
                            <i class="mdi mdi-alert me-1"></i>
                            <strong>Peringatan:</strong> Data yang dihapus tidak dapat dikembalikan. Hanya data dengan status <strong>Draft</strong> yang dapat dihapus.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* Fix dropdown text color */
    .form-select, 
    .form-control,
    select.form-select,
    select.form-control {
        color: #495057 !important;
        font-size: 0.875rem !important;
    }
    
    .form-select option,
    .form-control option {
        color: #495057 !important;
    }
    
    /* Table styling */
    .table td { 
        vertical-align: middle !important; 
        font-size: 0.875rem;
    }
    
    .table th {
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    /* Badge styling */
    .badge { 
        font-weight: 600; 
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
    
    /* Form label */
    .form-label.small {
        font-size: 0.875rem;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const initialKec = urlParams.get('kdkec');
    const initialDesa = urlParams.get('kddesa');

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

    $('#filter-kab').on('change', function() {
        let kdkab = $(this).val();
        $('#filter-kec').empty().append('<option value="">-- Semua Kecamatan --</option>');
        $('#filter-desa').empty().append('<option value="">-- Semua Desa --</option>');
        loadKecamatan(kdkab);
    });

    $('#filter-kec').on('change', function() {
        loadDesa($('#filter-kab').val(), $(this).val());
    });

    if ($('#filter-kab').val()) {
        loadKecamatan($('#filter-kab').val(), initialKec);
    }

    
    // Check all
    $('#checkAll').on('change', function() {
        $('.checkbox-item').prop('checked', $(this).prop('checked'));
        updateCount();
    });

    // Individual checkbox
    $('.checkbox-item').on('change', function() {
        updateCount();
        
        const total = $('.checkbox-item').length;
        const checked = $('.checkbox-item:checked').length;
        $('#checkAll').prop('checked', total === checked);
    });

    // Select all button
    $('#btnSelectAll').on('click', function() {
        $('.checkbox-item').prop('checked', true);
        $('#checkAll').prop('checked', true);
        updateCount();
    });

    // Deselect all button
    $('#btnDeselectAll').on('click', function() {
        $('.checkbox-item').prop('checked', false);
        $('#checkAll').prop('checked', false);
        updateCount();
    });

    // Submit button
    $('#btnSubmitSelected').on('click', function() {
        const selected = getSelectedIds();
        if (selected.length === 0) {
            alert('Pilih minimal 1 data');
            return;
        }
        $('#submitCount').text(selected.length);
        $('#submitModal').modal('show');
    });

    // Delete button
    $('#btnDeleteSelected').on('click', function() {
        const selected = getSelectedIds();
        if (selected.length === 0) {
            alert('Pilih minimal 1 data');
            return;
        }
        $('#deleteCount').text(selected.length);
        $('#deleteModal').modal('show');
    });

    // Submit form
    $('#submitModal form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const ids = getSelectedIds();
        
        form.find('input[name="ids[]"]').remove();
        
        ids.forEach(id => {
            form.append('<input type="hidden" name="ids[]" value="' + id + '">');
        });
        
        form.off('submit').submit();
    });

    // Delete form
    $('#deleteModal form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const ids = getSelectedIds();
        
        form.find('input[name="ids[]"]').remove();
        
        ids.forEach(id => {
            form.append('<input type="hidden" name="ids[]" value="' + id + '">');
        });
        
        form.off('submit').submit();
    });

    function getSelectedIds() {
        const ids = [];
        $('.checkbox-item:checked').each(function() {
            ids.push($(this).val());
        });
        return ids;
    }

    function updateCount() {
        const count = $('.checkbox-item:checked').length;
        $('#countSelected').text(count);
        $('#countSelected2').text(count);
        $('#btnSubmitSelected, #btnDeleteSelected').prop('disabled', count === 0);
    }

    updateCount();
});
</script>
@endpush
@endsection