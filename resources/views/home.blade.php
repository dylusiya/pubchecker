@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .stat-card {
        border-radius: 10px;
        padding: 20px;
        color: white;
        margin-bottom: 20px;
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
        opacity: 0.3;
        position: absolute;
        right: 20px;
        bottom: 20px;
    }
    .bg-primary-card { background: #667eea; }
    .bg-success-card { background: #28a745; }
    .bg-info-card    { background: #17a2b8; }
    .bg-warning-card { background: #ffc107; }
    .bg-danger-card  { background: #dc3545; }
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
    .list-group-item {
        border: 1px solid rgba(0,0,0,.125);
        transition: all 0.3s ease;
    }
    .list-group-item:hover {
        background-color: #f8f9fa;
        transform: translateX(5px);
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Header --}}
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-1">Dashboard Publication Checker</h4>
                        <p class="text-muted mb-0 small">Sistem Pemeriksaan Kover Publikasi BPS Kalsel</p>
                    </div>
                    <div>
                        <a href="{{ route('checker.index') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-file-search-outline"></i> Mulai Pemeriksaan
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @php
            use App\Models\SesiPemeriksaan;
            use App\Models\HasilPemeriksaan;

            $totalSesi    = SesiPemeriksaan::count();
            $totalFile    = HasilPemeriksaan::count();
            $totalOk      = HasilPemeriksaan::where('status_akhir', 'ok')->count();
            $totalMasalah = HasilPemeriksaan::where('status_akhir', 'masalah')->count();
            $totalWarn    = HasilPemeriksaan::where('status_akhir', 'perlu_dicek')->count();
        @endphp

        {{-- Stat cards --}}
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-primary-card">
                    <p>Total Sesi Pemeriksaan</p>
                    <h3>{{ number_format($totalSesi, 0, ',', '.') }}</h3>
                    <i class="mdi mdi-folder-multiple-outline icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-success-card">
                    <p>Total File Diperiksa</p>
                    <h3>{{ number_format($totalFile, 0, ',', '.') }}</h3>
                    <i class="mdi mdi-file-multiple icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-info-card">
                    <p>Semua Kriteria OK</p>
                    <h3>{{ number_format($totalOk, 0, ',', '.') }}</h3>
                    <i class="mdi mdi-check-circle-outline icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-danger-card">
                    <p>Ada Masalah</p>
                    <h3>{{ number_format($totalMasalah, 0, ',', '.') }}</h3>
                    <i class="mdi mdi-close-circle-outline icon"></i>
                </div>
            </div>
        </div>

        {{-- Riwayat sesi --}}
        <div class="card card-rounded mb-4">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">Riwayat Sesi Terbaru</h4>
                        <p class="card-subtitle card-subtitle-dash">10 sesi pemeriksaan terakhir</p>
                    </div>
                    <div>
                        <a href="{{ route('checker.riwayat') }}" class="btn btn-primary text-white btn-sm">
                            <i class="mdi mdi-history"></i> Lihat Semua Riwayat
                        </a>
                    </div>
                </div>

                @php
                    $recentSesi = SesiPemeriksaan::latest()->take(10)->get();
                @endphp

                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3">Tanggal</th>
                                <th class="py-3">UUID Sesi</th>
                                <th class="py-3 text-center">Total File</th>
                                <th class="py-3 text-center">OK</th>
                                <th class="py-3 text-center">Perlu Dicek</th>
                                <th class="py-3 text-center">Masalah</th>
                                <th class="py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSesi as $sesi)
                            <tr>
                                <td class="py-3">
                                    <div class="fw-bold">{{ $sesi->created_at->format('d/m/Y') }}</div>
                                    <small class="text-muted">
                                        <i class="mdi mdi-clock-outline"></i>
                                        {{ $sesi->created_at->diffForHumans() }}
                                    </small>
                                </td>
                                <td class="py-3">
                                    <code class="text-primary" style="font-size: 11px;">{{ $sesi->uuid }}</code>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge badge-primary px-3">{{ $sesi->total_file }} file</span>
                                </td>
                                <td class="py-3 text-center">
                                    @if($sesi->total_ok > 0)
                                        <span class="badge badge-success">{{ $sesi->total_ok }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    @if($sesi->total_warn > 0)
                                        <span class="badge badge-warning text-dark">{{ $sesi->total_warn }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    @if($sesi->total_err > 0)
                                        <span class="badge badge-danger">{{ $sesi->total_err }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    <a href="{{ route('checker.riwayat.detail', $sesi) }}"
                                       class="btn btn-outline-primary btn-xs">
                                        <i class="mdi mdi-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-5 text-center">
                                    <i class="mdi mdi-history text-light" style="font-size: 48px;"></i>
                                    <p class="text-muted mt-2">Belum ada sesi pemeriksaan</p>
                                    <a href="{{ route('checker.index') }}" class="btn btn-primary btn-sm">
                                        Mulai Pemeriksaan Pertama
                                    </a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Quick Actions + Info Sistem --}}
        <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h4 class="card-title card-title-dash">Quick Actions</h4>
                        <div class="list-group">
                            <a href="{{ route('checker.index') }}"
                               class="list-group-item list-group-item-action d-flex align-items-center py-3">
                                <i class="mdi mdi-file-search-outline text-primary me-3" style="font-size: 24px;"></i>
                                <div>
                                    <div class="fw-bold">Pemeriksaan Baru</div>
                                    <small class="text-muted">Upload dan periksa PDF publikasi BPS</small>
                                </div>
                            </a>
                            <a href="{{ route('checker.riwayat') }}"
                               class="list-group-item list-group-item-action d-flex align-items-center py-3">
                                <i class="mdi mdi-history text-success me-3" style="font-size: 24px;"></i>
                                <div>
                                    <div class="fw-bold">Riwayat Sesi</div>
                                    <small class="text-muted">Lihat semua sesi dan hasil pemeriksaan</small>
                                </div>
                            </a>
                            @if($recentSesi->first())
                            <a href="{{ route('checker.riwayat.export', $recentSesi->first()) }}"
                               class="list-group-item list-group-item-action d-flex align-items-center py-3">
                                <i class="mdi mdi-microsoft-excel text-warning me-3" style="font-size: 24px;"></i>
                                <div>
                                    <div class="fw-bold">Export Sesi Terakhir</div>
                                    <small class="text-muted">Download rekap Excel sesi terbaru</small>
                                </div>
                            </a>
                            @endif
                            @if(auth()->check() && auth()->user()->role === 'admin')
                            <a href="{{ route('admin.users.index') }}"
                               class="list-group-item list-group-item-action d-flex align-items-center py-3">
                                <i class="mdi mdi-account-multiple text-info me-3" style="font-size: 24px;"></i>
                                <div>
                                    <div class="fw-bold">Manajemen User</div>
                                    <small class="text-muted">Kelola akun pengguna aplikasi</small>
                                </div>
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 grid-margin stretch-card">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h4 class="card-title card-title-dash mb-4">Informasi Sistem</h4>
                        <div class="alert alert-info">
                            <h6 class="alert-heading d-flex align-items-center">
                                <i class="mdi mdi-information me-2"></i> Selamat Datang!
                            </h6>
                            <hr>
                            <p class="mb-2"><strong>User:</strong> {{ Auth::user()->name }}</p>
                            <p class="mb-2"><strong>Username:</strong> {{ Auth::user()->username }}</p>
                            <p class="mb-2"><strong>Email:</strong> {{ Auth::user()->email }}</p>
                            <p class="mb-2"><strong>Instansi:</strong> {{ Auth::user()->provinsi ?? 'BPS Kalsel' }}</p>
                            <p class="mb-0"><strong>Login:</strong> {{ now()->format('d/m/Y H:i:s') }}</p>
                        </div>

                        {{-- Ringkasan status --}}
                        <div class="mt-3">
                            <h6 class="fw-bold mb-2">Ringkasan Keseluruhan</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">File OK</span>
                                <span class="badge badge-success">{{ $totalOk }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Perlu Dicek</span>
                                <span class="badge badge-warning text-dark">{{ $totalWarn }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Ada Masalah</span>
                                <span class="badge badge-danger">{{ $totalMasalah }}</span>
                            </div>
                            @if($totalFile > 0)
                            <div class="progress mt-3" style="height: 8px;">
                                <div class="progress-bar bg-success"
                                     style="width: {{ round($totalOk / $totalFile * 100) }}%"
                                     title="OK"></div>
                                <div class="progress-bar bg-warning"
                                     style="width: {{ round($totalWarn / $totalFile * 100) }}%"
                                     title="Perlu Dicek"></div>
                                <div class="progress-bar bg-danger"
                                     style="width: {{ round($totalMasalah / $totalFile * 100) }}%"
                                     title="Masalah"></div>
                            </div>
                            <small class="text-muted">
                                {{ round($totalOk / $totalFile * 100) }}% file lolos semua kriteria
                            </small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection