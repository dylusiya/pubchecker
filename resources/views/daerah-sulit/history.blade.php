@extends('layouts.admin')

@section('title', 'Riwayat Perubahan Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin">
        <!-- Stats Cards -->
        <div class="row">
            <div class="col-sm-12">
                <div class="statistics-details d-flex align-items-center justify-content-start gap-5">
                    <div>
                        <p class="statistics-title">Total Riwayat</p>
                        <h3 class="rate-percentage text-success">{{ number_format($histories->total(), 0, ',', '.') }}</h3>
                        <p class="text-success d-flex small fw-bold"><i class="mdi mdi-history me-1"></i>Semua Aktivitas</p>
                    </div>
                    <div>
                        <p class="statistics-title">Tambah Data</p>
                        <h3 class="rate-percentage text-primary">
                            {{ number_format(\App\Models\HistoryStatusDaerahSulit::where('aksi', 'create')
                                ->when(request('tahun'), fn($q) => $q->where('tahun_anggaran', request('tahun')))
                                ->count(), 0, ',', '.') }}
                        </h3>
                        <p class="text-primary d-flex small fw-bold"><i class="mdi mdi-plus-circle me-1"></i>Create</p>
                    </div>
                    <div>
                        <p class="statistics-title">Update Data</p>
                        <h3 class="rate-percentage text-warning">
                            {{ number_format(\App\Models\HistoryStatusDaerahSulit::where('aksi', 'update')
                                ->when(request('tahun'), fn($q) => $q->where('tahun_anggaran', request('tahun')))
                                ->count(), 0, ',', '.') }}
                        </h3>
                        <p class="text-warning d-flex small fw-bold"><i class="mdi mdi-pencil me-1"></i>Update</p>
                    </div>
                    <div>
                        <p class="statistics-title">Approval</p>
                        <h3 class="rate-percentage text-info">
                            {{ number_format(\App\Models\HistoryStatusDaerahSulit::whereIn('aksi', ['approval', 'rejection'])
                                ->when(request('tahun'), fn($q) => $q->where('tahun_anggaran', request('tahun')))
                                ->count(), 0, ',', '.') }}
                        </h3>
                        <p class="text-info d-flex small fw-bold"><i class="mdi mdi-check-circle me-1"></i>Review</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card card-rounded mt-3">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">Riwayat Perubahan Status Daerah Sulit</h4>
                        <p class="card-subtitle card-subtitle-dash">Audit trail semua aktivitas perubahan data</p>
                    </div>
                    <div>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="bg-light p-3 rounded mb-4 border">
                    <form method="GET" action="{{ route('daerah-sulit.history') }}" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Tahun</label>
                            <select name="tahun" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua --</option>
                                @for($y = date('Y') + 1; $y >= 2020; $y--)
                                    <option value="{{ $y }}" {{ request('tahun') == $y ? 'selected' : '' }}>
                                        {{ $y }}
                                    </option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Jenis Aksi</label>
                            <select name="aksi" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua --</option>
                                <option value="create" {{ request('aksi') == 'create' ? 'selected' : '' }}>Tambah</option>
                                <option value="update" {{ request('aksi') == 'update' ? 'selected' : '' }}>Update</option>
                                <option value="delete" {{ request('aksi') == 'delete' ? 'selected' : '' }}>Hapus</option>
                                <option value="approval" {{ request('aksi') == 'approval' ? 'selected' : '' }}>Disetujui</option>
                                <option value="rejection" {{ request('aksi') == 'rejection' ? 'selected' : '' }}>Ditolak</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">User</label>
                            <select name="user_id" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua User --</option>
                                @php
                                    $users = \App\Models\User::orderBy('name')->get();
                                @endphp
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Cari ID/Nama SLS</label>
                            <input type="text" name="search" class="form-control form-control-sm" 
                                   placeholder="ID SLS atau Nama SLS..." 
                                   value="{{ request('search') }}">
                        </div>

                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                <i class="mdi mdi-magnify"></i> Filter
                            </button>
                            <a href="{{ route('daerah-sulit.history') }}" class="btn btn-light btn-sm border px-3">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Pagination Options -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted small">
                        <i class="mdi mdi-information-outline me-1"></i>
                        Menampilkan 
                        <strong>{{ $histories->firstItem() ?? 0 }}</strong> 
                        sampai 
                        <strong>{{ $histories->lastItem() ?? 0 }}</strong> 
                        dari 
                        <strong>{{ $histories->total() }}</strong> 
                        riwayat
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr class="bg-light">
                                <th class="py-3" width="140">Waktu</th>
                                <th class="py-3">Identitas SLS</th>
                                <th class="py-3">Wilayah</th>
                                <th class="py-3 text-center">Aksi</th>
                                <th class="py-3">User</th>
                                <th class="py-3">Keterangan</th>
                                @if(auth()->user()->isAdmin())
                                <th class="py-3 text-center" width="80">Detail</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($histories as $history)
                            <tr>
                                <td class="py-3">
                                    <div class="fw-bold">{{ $history->created_at->format('d/m/Y') }}</div>
                                    <div class="small text-muted">{{ $history->created_at->format('H:i') }}</div>
                                    <div class="small text-muted">
                                        <i class="mdi mdi-clock-outline"></i> 
                                        {{ $history->created_at->diffForHumans() }}
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-dark">{{ $history->masterSls->idsls }}</div>
                                    <div class="small text-muted text-uppercase">{{ Str::limit($history->masterSls->nmsls, 30) }}</div>
                                    <div class="small">
                                        <span class="badge badge-secondary">TA {{ $history->tahun_anggaran }}</span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="small fw-bold">{{ $history->masterSls->nmkab }}</div>
                                    <div class="small text-muted">{{ $history->masterSls->nmkec }} - {{ $history->masterSls->nmdesa }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge {{ $history->aksi_badge_class }}">
                                        <i class="mdi {{ $history->aksi_icon }}"></i>
                                        {{ $history->aksi_display }}
                                    </span>
                                    @if($history->field_changed)
                                        <div class="small text-muted mt-1">
                                            {{ Str::limit($history->field_changed, 25) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold">{{ $history->user->name ?? 'System' }}</div>
                                    <div class="small text-muted">{{ $history->user->username ?? '-' }}</div>
                                </td>
                                <td class="py-3">
                                    @if($history->alasan_perubahan)
                                        <div class="small">{{ Str::limit($history->alasan_perubahan, 50) }}</div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                @if(auth()->user()->isAdmin())
                                <td class="py-3 text-center">
                                    <button type="button" class="btn btn-outline-info btn-xs" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#detailModal{{ $history->id }}"
                                            title="Lihat Detail">
                                        <i class="mdi mdi-eye"></i>
                                    </button>
                                </td>
                                @endif
                            </tr>

                            <!-- Detail Modal -->
                            <div class="modal fade" id="detailModal{{ $history->id }}" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title">
                                                <i class="mdi mdi-file-document-outline me-2"></i>
                                                Detail Riwayat Perubahan
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Waktu:</strong> {{ $history->created_at->format('d/m/Y H:i:s') }}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>User:</strong> {{ $history->user->name ?? 'System' }}
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>ID SLS:</strong> {{ $history->masterSls->idsls }}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Aksi:</strong> 
                                                    <span class="badge {{ $history->aksi_badge_class }}">
                                                        {{ $history->aksi_display }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <strong>Nama SLS:</strong> {{ $history->masterSls->nmsls }}
                                            </div>

                                            @if($history->field_changed)
                                            <div class="mb-3">
                                                <strong>Field yang Berubah:</strong>
                                                <div class="alert alert-info mt-2">
                                                    {{ $history->field_changed }}
                                                </div>
                                            </div>
                                            @endif

                                            @if($history->alasan_perubahan)
                                            <div class="mb-3">
                                                <strong>Alasan Perubahan:</strong>
                                                <div class="alert alert-warning mt-2">
                                                    {{ $history->alasan_perubahan }}
                                                </div>
                                            </div>
                                            @endif

                                            @if($history->status_sebelum && $history->status_sesudah)
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <strong>Status Sebelum:</strong>
                                                    <div class="bg-light p-3 mt-2 rounded" style="max-height: 300px; overflow-y: auto;">
                                                        <small><pre>{{ json_encode($history->status_sebelum, JSON_PRETTY_PRINT) }}</pre></small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Status Sesudah:</strong>
                                                    <div class="bg-light p-3 mt-2 rounded" style="max-height: 300px; overflow-y: auto;">
                                                        <small><pre>{{ json_encode($history->status_sesudah, JSON_PRETTY_PRINT) }}</pre></small>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif

                                            @if($history->file_pendukung)
                                            <div class="mt-3">
                                                <strong>File Pendukung:</strong>
                                                <a href="{{ asset('storage/' . $history->file_pendukung) }}" 
                                                   target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                                    <i class="mdi mdi-download"></i> Unduh File
                                                </a>
                                            </div>
                                            @endif

                                            <div class="mt-3">
                                                <strong>IP Address:</strong> {{ $history->ip_address ?? '-' }}<br>
                                                <strong>User Agent:</strong> 
                                                <small class="text-muted">{{ Str::limit($history->user_agent ?? '-', 100) }}</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            @if($history->status_daerah_sulit_id)
                                                <a href="{{ route('daerah-sulit.show', $history->status_daerah_sulit_id) }}" 
                                                   class="btn btn-primary" target="_blank">
                                                    <i class="mdi mdi-open-in-new"></i> Lihat Data
                                                </a>
                                            @endif
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @empty
                            <tr>
                                <td colspan="7" class="py-5 text-center">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="mdi mdi-history text-light mb-3" style="font-size: 60px;"></i>
                                        <h5 class="text-muted fw-normal">Tidak ada riwayat perubahan</h5>
                                        <p class="text-muted small">Belum ada aktivitas yang tercatat</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($histories->hasPages())
                <div class="mt-4">
                    {{ $histories->links() }}
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
@endsection