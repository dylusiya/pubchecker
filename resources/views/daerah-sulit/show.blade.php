@extends('layouts.admin')

@section('title', 'Detail Status Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title text-primary">Detail Status Daerah Sulit</h4>
                    </div>
                    <a href="{{ route('daerah-sulit.index') }}" class="btn btn-outline-secondary btn-icon-text">
                        <i class="mdi mdi-arrow-left btn-icon-prepend"></i> Kembali
                    </a>
                </div>

                <!-- Status Badge -->
                <div class="mb-4">
                    <span class="badge badge-opacity-{{ $status->is_daerah_sulit ? 'danger' : 'success' }} px-3 py-2">
                        <i class="mdi mdi-{{ $status->is_daerah_sulit ? 'alert' : 'check-circle' }} me-1"></i>
                        {{ $status->is_daerah_sulit ? 'DAERAH SULIT' : 'TIDAK SULIT' }}
                    </span>
                    
                    <span class="badge badge-opacity-{{ $status->status_approval == 'disetujui' ? 'success' : ($status->status_approval == 'ditolak' ? 'danger' : 'warning') }} px-3 py-2 ms-2">
                        <i class="mdi mdi-{{ $status->status_approval == 'disetujui' ? 'check-circle' : ($status->status_approval == 'ditolak' ? 'close-circle' : 'clock') }} me-1"></i>
                        {{ strtoupper(str_replace('_', ' ', $status->status_display)) }}
                    </span>
                </div>

                <!-- Informasi Utama -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="mdi mdi-map-marker text-primary me-2"></i>Lokasi</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr>
                                        <td width="100"><strong>IDSLS</strong></td>
                                        <td>: {{ $status->masterSls->idsls}}</td>
                                    </tr>
                                    <tr>
                                        <td width="100"><strong>Kabupaten</strong></td>
                                        <td>: {{ $status->masterSls->nmkab }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Kecamatan</strong></td>
                                        <td>: {{ $status->masterSls->nmkec }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Desa/Kel</strong></td>
                                        <td>: {{ $status->masterSls->nmdesa }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nama SLS</strong></td>
                                        <td>: {{ $status->masterSls->nmsls }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="mdi mdi-calendar text-primary me-2"></i>Kegiatan</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>{{ $status->kegiatan }}</strong></p>
                                <p class="mb-0 text-muted">Tahun {{ $status->tahun_anggaran }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                

                @if($status->is_daerah_sulit)
                <!-- Detail Kesulitan -->
                <div class="card mb-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0"><i class="mdi mdi-map-marker-distance me-2"></i>Detail Kesulitan Akses</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h4 class="text-danger">Rp {{ number_format($status->perkiraan_biaya, 0, ',', '.') }}</h4>
                                <small class="text-muted">Perkiraan Biaya</small>
                            </div>
                            <div class="col-md-4">
                                <h5 class="text-primary">{{ $status->metode_transportasi ?? '-' }}</h5>
                                <small class="text-muted">Moda Transportasi</small>
                            </div>
                            <div class="col-md-4">
                                <h5 class="text-warning">{{ $status->waktu_tempuh_menit ?? 0 }} Menit</h5>
                                <small class="text-muted">Waktu Tempuh</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div>
                            <strong>Keterangan:</strong>
                            <p class="mt-2 mb-0">{{ $status->keterangan ?? '-' }}</p>
                        </div>
                    </div>
                </div>
                @else
                <!-- Keterangan untuk Tidak Sulit -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="mdi mdi-text text-primary me-2"></i>Keterangan</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $status->keterangan ?? '-' }}</p>
                    </div>
                </div>
                @endif

                <!-- File & Approval -->
                <div class="row mb-4">
                    @if($status->file_pendukung)
                    <div class="col-md-{{ $status->status_approval != 'draft' ? '6' : '12' }}">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="mdi mdi-file text-primary me-2"></i>File Pendukung</h6>
                            </div>
                            <div class="card-body">
                                <a href="{{ asset('storage/app/public/' . $status->file_pendukung) }}" target="_blank" class="btn btn-outline-primary btn-icon-text">
                                    <i class="mdi mdi-download btn-icon-prepend"></i> Unduh File
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($status->status_approval != 'draft')
                    <div class="col-md-{{ $status->file_pendukung ? '6' : '12' }}">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="mdi mdi-account-check text-primary me-2"></i>Info Approval</h6>
                            </div>
                            <div class="card-body">
                                @if($status->approved_by)
                                    <p class="mb-1"><strong>{{ $status->approver->name ?? '-' }}</strong></p>
                                    <p class="mb-0 text-muted">{{ $status->approved_at ? $status->approved_at->format('d/m/Y H:i') : '-' }}</p>
                                @endif
                                @if($status->catatan_approval)
                                    <div class="alert alert-info mt-2 mb-0">
                                        <small>{{ $status->catatan_approval }}</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 justify-content-end mb-4">
                    @if($status->canEdit())
                        <a href="{{ route('daerah-sulit.edit', $status->id) }}" class="btn btn-warning btn-icon-text">
                            <i class="mdi mdi-pencil btn-icon-prepend"></i> Edit
                        </a>
                    @endif

                    @if($status->canSubmit())
                        <button type="button" class="btn btn-primary btn-icon-text" data-bs-toggle="modal" data-bs-target="#submitModal">
                            <i class="mdi mdi-send btn-icon-prepend"></i> Submit
                        </button>
                    @endif

                    @if($status->canApprove() && (auth()->user()->isAdmin() || auth()->user()->isApprover()))
                        <button type="button" class="btn btn-success btn-icon-text" data-bs-toggle="modal" data-bs-target="#approveModal">
                            <i class="mdi mdi-check btn-icon-prepend"></i> Setujui
                        </button>
                        <button type="button" class="btn btn-danger btn-icon-text" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="mdi mdi-close btn-icon-prepend"></i> Tolak
                        </button>
                    @endif

                    @if($status->canDelete())
                        <button type="button" class="btn btn-danger btn-icon-text" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="mdi mdi-delete btn-icon-prepend"></i> Hapus
                        </button>
                    @endif
                </div>

                <!-- Riwayat Status SLS Ini -->
                @php
                    $riwayatSls = \App\Models\StatusDaerahSulit::where('master_sls_id', $status->master_sls_id)
                        ->whereNull('deleted_at')
                        ->with(['creator', 'approver'])
                        ->orderBy('tahun_anggaran', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->get();
                @endphp

                @if($riwayatSls->count() > 1)
                <div class="card mt-3">
                    <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="mdi mdi-timeline-text me-2"></i>
                            Riwayat Status SLS {{ $status->masterSls->idsls }}
                        </h6>
                        <span class="badge badge-light text-primary">{{ $riwayatSls->count() }} record | {{ $riwayatSls->unique('tahun_anggaran')->count() }} tahun</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="timeline">
                            @foreach($riwayatSls as $index => $item)
                            <div class="timeline-item {{ $item->id == $status->id ? 'active-record' : '' }}">
                                <div class="timeline-badge {{ $item->is_daerah_sulit ? 'bg-danger' : 'bg-success' }}">
                                    <i class="mdi mdi-{{ $item->is_daerah_sulit ? 'alert' : 'check' }}"></i>
                                </div>
                                <div class="timeline-panel">
                                    <div class="timeline-heading">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1">
                                                    @if($item->is_daerah_sulit)
                                                        <span class="badge badge-danger me-2">DAERAH SULIT</span>
                                                    @else
                                                        <span class="badge badge-success me-2">TIDAK SULIT</span>
                                                    @endif
                                                    <span class="badge badge-primary">{{ $item->tahun_anggaran }}</span>
                                                    @if($item->id == $status->id)
                                                        <span class="badge badge-warning ms-1">
                                                            <i class="mdi mdi-eye-check"></i> Sedang Dilihat
                                                        </span>
                                                    @endif
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="mdi mdi-calendar"></i> {{ $item->created_at->format('d F Y, H:i') }} WIB
                                                    <span class="mx-2">•</span>
                                                    <i class="mdi mdi-account"></i> {{ $item->creator->name ?? '-' }}
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                @php
                                                    $approvalClasses = [
                                                        'draft' => 'badge-secondary',
                                                        'pending' => 'badge-warning',
                                                        'disetujui' => 'badge-success',
                                                        'ditolak' => 'badge-danger',
                                                    ];
                                                    $approvalIcons = [
                                                        'draft' => 'mdi-file-document',
                                                        'pending' => 'mdi-clock-outline',
                                                        'disetujui' => 'mdi-check-circle',
                                                        'ditolak' => 'mdi-close-circle',
                                                    ];
                                                @endphp
                                                <span class="badge {{ $approvalClasses[$item->status_approval] ?? 'badge-secondary' }}">
                                                    <i class="mdi {{ $approvalIcons[$item->status_approval] ?? 'mdi-help' }}"></i>
                                                    {{ strtoupper($item->status_approval) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="timeline-body">
                                        <div class="row g-3">
                                            <!-- Kegiatan -->
                                            <div class="col-md-12">
                                                <div class="info-box">
                                                    <strong><i class="mdi mdi-briefcase text-primary"></i> Kegiatan:</strong>
                                                    <p class="mb-0 ms-3">{{ $item->kegiatan }}</p>
                                                </div>
                                            </div>

                                            @if($item->is_daerah_sulit)
                                            <!-- Detail Kesulitan -->
                                            <div class="col-md-4">
                                                <div class="info-box">
                                                    <strong><i class="mdi mdi-cash text-success"></i> Perkiraan Biaya:</strong>
                                                    <p class="mb-0 ms-3 text-danger fw-bold">
                                                        Rp {{ number_format($item->perkiraan_biaya, 0, ',', '.') }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="info-box">
                                                    <strong><i class="mdi mdi-truck-fast text-warning"></i> Transportasi:</strong>
                                                    <p class="mb-0 ms-3">{{ $item->metode_transportasi ?? '-' }}</p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="info-box">
                                                    <strong><i class="mdi mdi-clock text-info"></i> Waktu Tempuh:</strong>
                                                    <p class="mb-0 ms-3">{{ $item->waktu_tempuh_menit ?? 0 }} menit</p>
                                                </div>
                                            </div>
                                            @endif

                                            <!-- Keterangan -->
                                            <div class="col-md-12">
                                                <div class="info-box">
                                                    <strong><i class="mdi mdi-text text-primary"></i> Keterangan:</strong>
                                                    <p class="mb-0 ms-3 text-justify">{{ $item->keterangan ?? '-' }}</p>
                                                </div>
                                            </div>

                                            @if($item->status_approval == 'disetujui' && $item->approver)
                                            <!-- Info Approval -->
                                            <div class="col-md-12">
                                                <div class="alert alert-success mb-0 py-2">
                                                    <small>
                                                        <i class="mdi mdi-check-circle me-1"></i>
                                                        <strong>Disetujui oleh {{ $item->approver->name }}</strong>
                                                        pada {{ $item->approved_at->format('d/m/Y H:i') }}
                                                        @if($item->catatan_approval)
                                                            <br>
                                                            <span class="ms-3">Catatan: {{ $item->catatan_approval }}</span>
                                                        @endif
                                                    </small>
                                                </div>
                                            </div>
                                            @elseif($item->status_approval == 'ditolak' && $item->catatan_approval)
                                            <!-- Info Penolakan -->
                                            <div class="col-md-12">
                                                <div class="alert alert-danger mb-0 py-2">
                                                    <small>
                                                        <i class="mdi mdi-close-circle me-1"></i>
                                                        <strong>Ditolak:</strong> {{ $item->catatan_approval }}
                                                    </small>
                                                </div>
                                            </div>
                                            @endif
                                        </div>

                                        <!-- Action Button -->
                                        @if($item->id != $status->id)
                                        <div class="mt-3">
                                            <a href="{{ route('daerah-sulit.show', $item->id) }}" 
                                            class="btn btn-sm btn-outline-primary">
                                                <i class="mdi mdi-eye"></i> Lihat Detail Record Ini
                                            </a>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>                        
                    </div>
                </div>
                @endif

                <!-- History (Compact) -->
                @if($status->histories && $status->histories->count() > 0)
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="mdi mdi-history text-primary me-2"></i>Riwayat Perubahan</h6>
                        <span class="badge badge-info">{{ $status->histories->count() }} aktivitas</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    @foreach($status->histories->take(5) as $history)
                                    <tr>
                                        <td width="150">
                                            <small class="text-muted">{{ $history->created_at->format('d/m/Y H:i') }}</small>
                                        </td>
                                        <td width="120">
                                            <span class="badge {{ $history->aksi_badge_class }} badge-sm">
                                                {{ $history->aksi_display }}
                                            </span>
                                        </td>
                                        <td width="150">
                                            <small>{{ $history->user->name ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ Str::limit($history->alasan_perubahan ?? '-', 50) }}</small>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($status->histories->count() > 5)
                            <div class="text-center mt-2">
                                <small class="text-muted">Menampilkan 5 dari {{ $status->histories->count() }} riwayat</small>
                            </div>
                        @endif
                    </div>
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
            <form action="{{ route('daerah-sulit.approve', $status->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="mdi mdi-check me-2"></i>Setujui Status
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="mdi mdi-check-circle-outline text-success" style="font-size: 64px;"></i>
                    </div>
                    <p class="text-center mb-3">Apakah Anda yakin ingin menyetujui data ini?</p>
                    <div class="form-group">
                        <label>Catatan (Opsional)</label>
                        <textarea name="catatan_approval" class="form-control" rows="3" placeholder="Tambahkan catatan approval..."></textarea>
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
            <form action="{{ route('daerah-sulit.reject', $status->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="mdi mdi-close me-2"></i>Tolak Status
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="mdi mdi-close-circle-outline text-danger" style="font-size: 64px;"></i>
                    </div>
                    <p class="text-center mb-3">Apakah Anda yakin ingin menolak data ini?</p>
                    <div class="form-group">
                        <label>Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="catatan_approval" class="form-control" rows="4" placeholder="Jelaskan alasan penolakan minimal 20 karakter..." required minlength="20"></textarea>
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

<!-- Submit Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('daerah-sulit.submit', $status->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="mdi mdi-send me-2"></i>Submit untuk Review
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="mdi mdi-send-circle-outline text-primary" style="font-size: 64px;"></i>
                    </div>
                    <p class="text-center mb-3">
                        Apakah Anda yakin ingin submit data ini untuk direview?
                    </p>
                    
                    @if($status->status_approval == 'ditolak')
                        <div class="alert alert-warning">
                            <small>
                                <i class="mdi mdi-information me-1"></i>
                                <strong>Catatan:</strong> Data ini sebelumnya ditolak. Pastikan sudah diperbaiki sesuai catatan penolakan sebelum disubmit kembali.
                            </small>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <small>
                                <i class="mdi mdi-information me-1"></i>
                                <strong>Catatan:</strong> Setelah disubmit, data tidak dapat diedit hingga mendapat keputusan dari admin.
                            </small>
                        </div>
                    @endif
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
            <form action="{{ route('daerah-sulit.destroy', $status->id) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="mdi mdi-delete me-2"></i>Hapus Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="mdi mdi-alert-circle-outline text-danger" style="font-size: 64px;"></i>
                    </div>
                    <p class="text-center mb-3">Apakah Anda yakin ingin menghapus data ini?</p>
                    <div class="alert alert-danger">
                        <small>
                            <i class="mdi mdi-alert me-1"></i>
                            Data yang dihapus tidak dapat dikembalikan.
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
@endsection

@push('styles')
<style>
/* Timeline Styles */
.timeline {
    position: relative;
    padding: 20px 0;
    list-style: none;
}

.timeline-item {
    position: relative;
    padding-left: 60px;
    margin-bottom: 30px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 18px;
    top: 40px;
    bottom: -30px;
    width: 2px;
    background: #e0e0e0;
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-badge {
    position: absolute;
    left: 0;
    top: 0;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    z-index: 1;
}

.timeline-panel {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.timeline-panel:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.active-record .timeline-panel {
    border: 2px solid #4B49AC;
    background: #f8f9ff;
}

.active-record .timeline-badge {
    box-shadow: 0 0 0 4px rgba(75, 73, 172, 0.2);
}

.info-box {
    margin-bottom: 15px;
}

.info-box:last-child {
    margin-bottom: 0;
}

.info-box strong {
    display: block;
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.info-box p {
    font-size: 0.95rem;
    line-height: 1.6;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #4B49AC 0%, #7978E9 100%);
}

.text-justify {
    text-align: justify;
}
</style>
@endpush