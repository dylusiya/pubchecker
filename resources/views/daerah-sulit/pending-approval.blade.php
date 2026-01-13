@extends('layouts.admin')

@section('title', 'Approval Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title text-primary">Approval Status Daerah Sulit</h4>
                        <p class="card-description">Data yang menunggu persetujuan</p>
                    </div>
                    <a href="{{ route('daerah-sulit.index') }}" class="btn btn-outline-secondary btn-icon-text">
                        <i class="mdi mdi-arrow-left btn-icon-prepend"></i> Kembali
                    </a>
                </div>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ $stats['total_pending'] }}</h3>
                                <small>Menunggu Approval</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ $stats['total_sulit'] }}</h3>
                                <small>Daerah Sulit</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter -->
                <div class="bg-light p-3 rounded mb-4 border">
                    <form method="GET" action="{{ route('daerah-sulit.pending-approval') }}" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-dark">Tahun</label>
                            <select name="tahun" class="form-select form-select-sm text-dark">
                                @for($y = date('Y') + 1; $y >= 2020; $y--)
                                    <option value="{{ $y }}" {{ request('tahun', date('Y')) == $y ? 'selected' : '' }}>
                                        {{ $y }}
                                    </option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-dark">Kabupaten/Kota</label>
                            <select name="kdkab" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua Kabupaten/Kota --</option>
                                @foreach($kabupatenList as $kab)
                                    <option value="{{ $kab->kdkab }}" {{ request('kdkab') == $kab->kdkab ? 'selected' : '' }}>
                                        {{ $kab->nmkab }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Pencarian</label>
                            <input type="text" name="search" class="form-control form-control-sm text-dark" 
                                placeholder="ID SLS / Nama SLS..." 
                                value="{{ request('search') }}">
                        </div>

                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                <i class="mdi mdi-magnify"></i> Filter
                            </button>
                            <a href="{{ route('daerah-sulit.pending-approval') }}" class="btn btn-light btn-sm border px-3">
                                <i class="mdi mdi-refresh"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Pagination Options -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <form method="GET" action="{{ route('daerah-sulit.pending-approval') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="tahun" value="{{ request('tahun', date('Y')) }}">
                        <input type="hidden" name="kdkab" value="{{ request('kdkab') }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="sort" value="{{ request('sort', 'idsls') }}">
                        <input type="hidden" name="order" value="{{ request('order', 'asc') }}">
                        
                        <span class="text-dark small fw-bold">Tampilkan:</span>
                        <select name="per_page" class="form-select form-select-sm text-dark" style="width: 150px;" onchange="this.form.submit()">
                            <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20 per halaman</option>
                            <option value="50" {{ request('per_page', 20) == 50 ? 'selected' : '' }}>50 per halaman</option>
                            <option value="100" {{ request('per_page', 20) == 100 ? 'selected' : '' }}>100 per halaman</option>
                            <option value="all" {{ request('per_page', 20) == 'all' ? 'selected' : '' }}>Semua Data</option>
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
                        @if(request('per_page') == 'all')
                            <span class="badge badge-info ms-2">Semua data ditampilkan</span>
                        @endif
                    </div>
                </div>

                @if($data->count() > 0)
                <!-- Action Buttons -->
                <div class="mb-3 d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-success" id="btnApproveSelected">
                        <i class="mdi mdi-check"></i> Setujui Terpilih (<span id="countSelected">0</span>)
                    </button>
                    <button type="button" class="btn btn-danger" id="btnRejectSelected">
                        <i class="mdi mdi-close"></i> Tolak Terpilih (<span id="countSelected2">0</span>)
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnSelectAll">
                        <i class="mdi mdi-checkbox-multiple-marked"></i> Pilih Semua
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnDeselectAll">
                        <i class="mdi mdi-checkbox-multiple-blank-outline"></i> Batal Pilih
                    </button>
                    
                    @if(request('per_page') == 'all')
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
                                        <a href="{{ route('daerah-sulit.pending-approval', array_merge(request()->all(), ['sort' => 'idsls', 'order' => request('sort') == 'idsls' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            ID SLS
                                            @if(request('sort', 'idsls') == 'idsls')
                                                <i class="mdi mdi-chevron-{{ request('order', 'asc') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-approval', array_merge(request()->all(), ['sort' => 'nmsls', 'order' => request('sort') == 'nmsls' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            Nama SLS
                                            @if(request('sort') == 'nmsls')
                                                <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-approval', array_merge(request()->all(), ['sort' => 'kdkab', 'order' => request('sort') == 'kdkab' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            Wilayah
                                            @if(request('sort') == 'kdkab')
                                                <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-approval', array_merge(request()->all(), ['sort' => 'is_daerah_sulit', 'order' => request('sort') == 'is_daerah_sulit' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            Status
                                            @if(request('sort') == 'is_daerah_sulit')
                                                <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-approval', array_merge(request()->all(), ['sort' => 'perkiraan_biaya', 'order' => request('sort') == 'perkiraan_biaya' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            Biaya
                                            @if(request('sort') == 'perkiraan_biaya')
                                                <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('daerah-sulit.pending-approval', array_merge(request()->all(), ['sort' => 'updated_at', 'order' => request('sort') == 'updated_at' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                            Disubmit
                                            @if(request('sort') == 'updated_at')
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
                                        <small class="text-muted">
                                            {{ $item->creator->name ?? '-' }}<br>
                                            {{ $item->updated_at->format('d/m/Y H:i') }}
                                        </small>
                                    </td>
                                    <td>
                                        <a href="{{ route('daerah-sulit.show', $item->id) }}" 
                                           class="btn btn-sm btn-info" target="_blank">
                                            <i class="mdi mdi-eye"></i>
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
                    <p class="text-muted mt-3">Tidak ada data yang menunggu approval</p>
                    <a href="{{ route('daerah-sulit.index') }}" class="btn btn-primary mt-2">
                        <i class="mdi mdi-arrow-left"></i> Kembali ke Daftar
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('daerah-sulit.bulk-approve') }}" method="POST">
                @csrf
                <input type="hidden" name="selected_ids" id="selectedIdsApprove">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Setujui Data Terpilih</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="mdi mdi-check-circle-outline text-success" style="font-size: 64px;"></i>
                    </div>
                    <p class="text-center mb-3">
                        Anda akan menyetujui <strong id="approveCount">0</strong> data.
                    </p>
                    <div class="alert alert-success">
                        <small>
                            <i class="mdi mdi-information me-1"></i>
                            <strong>Catatan:</strong> Data yang disetujui akan langsung masuk ke database resmi.
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Catatan (Opsional)</label>
                        <textarea name="catatan_approval" class="form-control" rows="3" placeholder="Tambahkan catatan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Ya, Setujui</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('daerah-sulit.bulk-reject') }}" method="POST">
                @csrf
                <input type="hidden" name="selected_ids" id="selectedIdsReject">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Data Terpilih</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="mdi mdi-close-circle-outline text-danger" style="font-size: 64px;"></i>
                    </div>
                    <p class="text-center mb-3">
                        Anda akan menolak <strong id="rejectCount">0</strong> data.
                    </p>
                    <div class="alert alert-danger">
                        <small>
                            <i class="mdi mdi-alert me-1"></i>
                            <strong>Peringatan:</strong> Data yang ditolak akan dikembalikan ke status Draft untuk diperbaiki oleh petugas. <!-- ✅ Ubah text -->
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="catatan_approval" class="form-control" rows="4" placeholder="Jelaskan alasan penolakan minimal 20 karakter..." required minlength="20"></textarea> <!-- ✅ Ubah placeholder -->
                        <small class="text-muted">Minimal 20 karakter</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Tolak</button>
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
    // Check all
    $('#checkAll').on('change', function() {
        $('.checkbox-item').prop('checked', $(this).prop('checked'));
        updateCount();
    });

    // Individual checkbox
    $('.checkbox-item').on('change', function() {
        updateCount();
        
        // Update check all status
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

    // Approve button
    $('#btnApproveSelected').on('click', function() {
        const selected = getSelectedIds();
        if (selected.length === 0) {
            alert('Pilih minimal 1 data');
            return;
        }
        $('#approveCount').text(selected.length);
        $('#selectedIdsApprove').val(JSON.stringify(selected));
        $('#approveModal').modal('show');
    });

    // Reject button
    $('#btnRejectSelected').on('click', function() {
        const selected = getSelectedIds();
        if (selected.length === 0) {
            alert('Pilih minimal 1 data');
            return;
        }
        $('#rejectCount').text(selected.length);
        $('#selectedIdsReject').val(JSON.stringify(selected));
        $('#rejectModal').modal('show');
    });

    // Update approve form submission
    $('#approveModal form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const ids = getSelectedIds();
        
        // Clear existing hidden inputs
        form.find('input[name="ids[]"]').remove();
        
        // Add selected IDs as array
        ids.forEach(id => {
            form.append('<input type="hidden" name="ids[]" value="' + id + '">');
        });
        
        form.off('submit').submit();
    });

    // Update reject form submission
    $('#rejectModal form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const ids = getSelectedIds();
        
        // Clear existing hidden inputs
        form.find('input[name="ids[]"]').remove();
        
        // Add selected IDs as array
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
        
        // Enable/disable buttons
        $('#btnApproveSelected, #btnRejectSelected').prop('disabled', count === 0);
    }

    // Initial update
    updateCount();
});
</script>
@endpush
@endsection