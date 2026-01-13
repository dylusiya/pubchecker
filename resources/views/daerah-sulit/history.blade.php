@extends('layouts.admin')

@section('title', 'Riwayat Perubahan Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title text-primary">Riwayat Perubahan Status Daerah Sulit</h4>
                        <p class="card-description">Audit trail semua aktivitas perubahan data</p>
                    </div>
                    <a href="{{ route('daerah-sulit.index') }}" class="btn btn-outline-secondary btn-icon-text">
                        <i class="mdi mdi-arrow-left btn-icon-prepend"></i> Kembali
                    </a>
                </div>

                <!-- Filter Section -->
                <div class="card mb-4 bg-light">
                    <div class="card-body">
                        <form method="GET" action="{{ route('daerah-sulit.history') }}" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Tahun</label>
                                <select name="tahun" class="form-select">
                                    <option value="">-- Semua Tahun --</option>
                                    @for($y = date('Y') + 1; $y >= 2020; $y--)
                                        <option value="{{ $y }}" {{ request('tahun') == $y ? 'selected' : '' }}>
                                            {{ $y }}
                                        </option>
                                    @endfor
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Jenis Aksi</label>
                                <select name="aksi" class="form-select">
                                    <option value="">-- Semua Aksi --</option>
                                    <option value="create" {{ request('aksi') == 'create' ? 'selected' : '' }}>Tambah</option>
                                    <option value="update" {{ request('aksi') == 'update' ? 'selected' : '' }}>Update</option>
                                    <option value="delete" {{ request('aksi') == 'delete' ? 'selected' : '' }}>Hapus</option>
                                    <option value="approval" {{ request('aksi') == 'approval' ? 'selected' : '' }}>Disetujui</option>
                                    <option value="rejection" {{ request('aksi') == 'rejection' ? 'selected' : '' }}>Ditolak</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">User</label>
                                <select name="user_id" class="form-select">
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
                                <label class="form-label">Cari ID/Nama SLS</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Ketik ID SLS atau Nama..." 
                                       value="{{ request('search') }}">
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-filter"></i> Filter
                                </button>
                                <a href="{{ route('daerah-sulit.history') }}" class="btn btn-light">
                                    <i class="mdi mdi-refresh"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ $histories->total() }}</h3>
                                <small>Total Riwayat</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">
                                    {{ \App\Models\HistoryStatusDaerahSulit::where('aksi', 'create')
                                        ->when(request('tahun'), fn($q) => $q->where('tahun_anggaran', request('tahun')))
                                        ->count() }}
                                </h3>
                                <small>Tambah</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">
                                    {{ \App\Models\HistoryStatusDaerahSulit::where('aksi', 'update')
                                        ->when(request('tahun'), fn($q) => $q->where('tahun_anggaran', request('tahun')))
                                        ->count() }}
                                </h3>
                                <small>Update</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">
                                    {{ \App\Models\HistoryStatusDaerahSulit::whereIn('aksi', ['approval', 'rejection'])
                                        ->when(request('tahun'), fn($q) => $q->where('tahun_anggaran', request('tahun')))
                                        ->count() }}
                                </h3>
                                <small>Approval/Rejection</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="150">Waktu</th>
                                <th>ID SLS</th>
                                <th>Nama SLS</th>
                                <th>Wilayah</th>
                                <th>Aksi</th>
                                <th>User</th>
                                <th>Keterangan</th>
                                <th width="100">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($histories as $history)
                            <tr>
                                <td>
                                    <div>{{ $history->created_at->format('d/m/Y') }}</div>
                                    <small class="text-muted">{{ $history->created_at->format('H:i') }}</small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="mdi mdi-clock-outline"></i> 
                                        {{ $history->created_at->diffForHumans() }}
                                    </small>
                                </td>
                                <td>
                                    <strong>{{ $history->masterSls->idsls }}</strong>
                                    <br>
                                    <small class="text-muted">TA {{ $history->tahun_anggaran }}</small>
                                </td>
                                <td>{{ Str::limit($history->masterSls->nmsls, 40) }}</td>
                                <td>
                                    <small class="text-muted">
                                        {{ $history->masterSls->nmkab }}<br>
                                        Kec. {{ $history->masterSls->nmkec }}
                                    </small>
                                </td>
                                <td>
                                    <span class="badge {{ $history->aksi_badge_class }}">
                                        <i class="mdi {{ $history->aksi_icon }}"></i>
                                        {{ $history->aksi_display }}
                                    </span>
                                    @if($history->field_changed)
                                        <br>
                                        <small class="text-muted">
                                            Field: {{ Str::limit($history->field_changed, 30) }}
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $history->user->name ?? 'System' }}</div>
                                    <small class="text-muted">{{ $history->user->username ?? '-' }}</small>
                                </td>
                                <td>
                                    @if($history->alasan_perubahan)
                                        <small>{{ Str::limit($history->alasan_perubahan, 60) }}</small>
                                    @else
                                        <small class="text-muted">-</small>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#detailModal{{ $history->id }}">
                                        <i class="mdi mdi-eye"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Detail Modal -->
                            <div class="modal fade" id="detailModal{{ $history->id }}" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="mdi mdi-file-document-outline me-2"></i>
                                                Detail Riwayat Perubahan
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                                    <div class="bg-light p-3 mt-2 rounded">
                                                        <small><pre>{{ json_encode($history->status_sebelum, JSON_PRETTY_PRINT) }}</pre></small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Status Sesudah:</strong>
                                                    <div class="bg-light p-3 mt-2 rounded">
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
                                <td colspan="8" class="text-center py-5">
                                    <i class="mdi mdi-history" style="font-size: 64px; color: #ccc;"></i>
                                    <p class="text-muted mt-3">Tidak ada riwayat perubahan</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $histories->links() }}
                </div>

                <!-- Info -->
                <div class="alert alert-info mt-3">
                    <i class="mdi mdi-information me-2"></i>
                    <strong>Informasi:</strong> Menampilkan {{ $histories->count() }} dari {{ $histories->total() }} total riwayat
                </div>
            </div>
        </div>
    </div>
</div>
@endsection