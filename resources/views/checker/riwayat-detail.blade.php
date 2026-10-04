@extends('layouts.app')

@section('title', 'Detail Sesi #' . $sesi->id)

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Header --}}
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="card-title mb-1">
                            <i class="mdi mdi-file-document-multiple-outline text-primary me-2"></i>
                            Detail Sesi #{{ $sesi->id }}
                        </h4>
                        <p class="text-muted mb-0 small">
                            <code>{{ $sesi->uuid }}</code>
                            &nbsp;·&nbsp;
                            {{ $sesi->created_at->format('d M Y, H:i') }}
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('checker.riwayat') }}" class="btn btn-light btn-sm border">
                            <i class="mdi mdi-arrow-left me-1"></i> Kembali
                        </a>
                        <a href="{{ route('checker.riwayat.export', $sesi) }}" class="btn btn-success btn-sm">
                            <i class="mdi mdi-microsoft-excel me-1"></i> Export Excel
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stat Cards --}}
        <div class="row mb-3">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-primary-card">
                    <p>Total Publikasi</p>
                    <h3>{{ $sesi->total_file }}</h3>
                    <p>File diperiksa</p>
                    <i class="mdi mdi-file-multiple-outline icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-success-card">
                    <p>Semua Kriteria OK</p>
                    <h3>{{ $sesi->total_ok }}</h3>
                    <p>Tidak ada masalah</p>
                    <i class="mdi mdi-check-circle-outline icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-warning-card">
                    <p>Perlu Dicek</p>
                    <h3>{{ $sesi->total_warn }}</h3>
                    <p>Ada catatan</p>
                    <i class="mdi mdi-alert-outline icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-danger-card">
                    <p>Ada Masalah</p>
                    <h3>{{ $sesi->total_err }}</h3>
                    <p>Kriteria tidak terpenuhi</p>
                    <i class="mdi mdi-close-circle-outline icon"></i>
                </div>
            </div>
        </div>

        {{-- Detail per file --}}
        <div class="card card-rounded">
            <div class="card-body">
                <h5 class="card-title mb-3">Hasil per Publikasi</h5>

                @forelse($sesi->hasilPemeriksaan as $hasil)
                <div class="border rounded mb-2" id="pub-{{ $hasil->id }}">

                    {{-- Row header --}}
                    <div class="d-flex align-items-center p-3 gap-2 bg-white rounded result-row-header"
                         onclick="togglePub({{ $hasil->id }})">
                        <i class="mdi
                            {{ $hasil->status_akhir === 'masalah'     ? 'mdi-close-circle-outline text-danger' :
                              ($hasil->status_akhir === 'perlu_dicek'  ? 'mdi-alert-outline text-warning'       :
                               'mdi-check-circle-outline text-success') }}"
                           style="font-size:18px; flex-shrink:0;"></i>

                        <span class="flex-grow-1 fw-semibold small text-truncate"
                              title="{{ $hasil->nama_file }}">{{ $hasil->nama_file }}</span>

                        <div class="d-flex gap-1 flex-shrink-0">
                            @if($hasil->total_ok)
                                <span class="badge bg-success">{{ $hasil->total_ok }} OK</span>
                            @endif
                            @if($hasil->total_perlu_dicek)
                                <span class="badge bg-warning text-dark">{{ $hasil->total_perlu_dicek }} Dicek</span>
                            @endif
                            @if($hasil->total_tidak_ada)
                                <span class="badge bg-danger">{{ $hasil->total_tidak_ada }} Masalah</span>
                            @endif
                            @if($hasil->total_tdk_diperiksa)
                                <span class="badge bg-secondary">{{ $hasil->total_tdk_diperiksa }} Skip</span>
                            @endif
                        </div>

                        <small class="text-muted flex-shrink-0 ms-1">
                            {{ $hasil->total_halaman ?? '?' }} hal.
                        </small>
                        <i class="mdi mdi-chevron-down text-muted ms-1 pub-chevron-{{ $hasil->id }}"
                           style="transition: transform .2s; flex-shrink:0;"></i>
                    </div>

                    {{-- Detail body --}}
                    <div id="detail-{{ $hasil->id }}" style="display:none;"
                         class="p-3 border-top bg-light rounded-bottom">

                        @if($hasil->error_msg)
                            <div class="alert alert-danger py-2 small mb-3">
                                <i class="mdi mdi-alert me-1"></i>{{ $hasil->error_msg }}
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-bordered mb-0 bg-white"
                                   style="font-size:12px;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:70px;">Kode</th>
                                        <th style="width:150px;">Kategori</th>
                                        <th>Deskripsi</th>
                                        <th style="width:130px;">Status</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($hasil->detail as $d)
                                    <tr>
                                        <td class="font-monospace text-muted" style="font-size:11px;">
                                            {{ $d->kriteria_id }}
                                        </td>
                                        <td class="text-muted small">{{ $d->kategori }}</td>
                                        <td style="font-size:12px;">{{ $d->deskripsi }}</td>
                                        <td>
                                            @php
                                                $badgeCls = match($d->status) {
                                                    'OK'              => 'bg-success',
                                                    'PERLU DICEK'     => 'bg-warning text-dark',
                                                    'TIDAK ADA'       => 'bg-danger',
                                                    default           => 'bg-secondary',
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeCls }}">{{ $d->status }}</span>
                                        </td>
                                        <td class="text-muted small">{{ $d->catatan ?: '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
                @empty
                <div class="text-center py-5 text-muted">
                    <i class="mdi mdi-file-question-outline d-block mb-2"
                       style="font-size:40px; opacity:.3;"></i>
                    Tidak ada data hasil pemeriksaan
                </div>
                @endforelse

            </div>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
    .stat-card {
        border-radius: 10px;
        padding: 20px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .stat-card h3 {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .stat-card p {
        margin-bottom: 0;
        opacity: 0.9;
        font-size: 0.9rem;
    }
    .stat-card .icon {
        font-size: 3rem;
        opacity: 0.25;
        position: absolute;
        right: 20px;
        bottom: 15px;
    }
    .bg-primary-card  { background: #667eea; }
    .bg-success-card  { background: #28a745; }
    .bg-warning-card  { background: #ffc107; color: #212529 !important; }
    .bg-warning-card p, .bg-warning-card h3 { color: #212529 !important; }
    .bg-danger-card   { background: #dc3545; }

    .result-row-header {
        cursor: pointer;
        transition: background .15s;
    }
    .result-row-header:hover { background: #f8f9fa !important; }
</style>
@endpush

@push('scripts')
<script>
function togglePub(id) {
    const body  = document.getElementById('detail-' + id);
    const chev  = document.querySelector('.pub-chevron-' + id);
    const open  = body.style.display === 'none';
    body.style.display    = open ? 'block' : 'none';
    chev.style.transform  = open ? 'rotate(180deg)' : '';
}
</script>
@endpush