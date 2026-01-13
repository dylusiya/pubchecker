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
                                <a href="{{ asset('storage/' . $status->file_pendukung) }}" target="_blank" class="btn btn-outline-primary btn-icon-text">
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