@extends('layouts.app')

@php
    use App\Models\SesiPemeriksaan;
    use App\Models\HasilPemeriksaan;

    $totalSesi    = SesiPemeriksaan::count();
    $totalFile    = HasilPemeriksaan::count();
    $totalOk      = HasilPemeriksaan::where('status_akhir', 'ok')->count();
    $totalMasalah = HasilPemeriksaan::where('status_akhir', 'masalah')->count();
    $totalWarn    = HasilPemeriksaan::where('status_akhir', 'perlu_dicek')->count();
    $recentSesi   = SesiPemeriksaan::latest()->take(10)->get();
    $pct          = fn($n) => $totalFile ? round($n / $totalFile * 100) : 0;
    $user         = Auth::user();
@endphp

@section('title', 'Dashboard')
@section('pretitle', 'Overview')
@section('page-title', 'Dashboard')
@section('page-actions')
    <a href="{{ route('checker.riwayat') }}" class="btn">
        <i class="ti ti-history"></i> Riwayat
    </a>
    <a href="{{ route('checker.index') }}" class="btn btn-primary">
        <i class="ti ti-plus"></i> Pemeriksaan Baru
    </a>
@endsection

@section('content')
<div class="row row-deck row-cards">

    {{-- Sambutan --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h3 class="h2 mb-1">Selamat datang, {{ $user->first_name ?? $user->name }}</h3>
                <p class="text-secondary">
                    Sistem pemeriksaan kover dan kelengkapan publikasi BPS Provinsi Kalimantan Selatan.
                </p>
                <div class="row g-3 mt-2">
                    <div class="col-6">
                        <div class="subheader">File lolos semua kriteria</div>
                        <div class="d-flex align-items-baseline gap-2">
                            <div class="h3 mb-0">{{ $pct($totalOk) }}%</div>
                            <div class="text-secondary small">{{ $totalOk }} file</div>
                        </div>
                        <div class="progress progress-sm mt-2"><div class="progress-bar bg-green" style="width: {{ $pct($totalOk) }}%"></div></div>
                    </div>
                    <div class="col-6">
                        <div class="subheader">Ada masalah</div>
                        <div class="d-flex align-items-baseline gap-2">
                            <div class="h3 mb-0">{{ $pct($totalMasalah) }}%</div>
                            <div class="text-secondary small">{{ $totalMasalah }} file</div>
                        </div>
                        <div class="progress progress-sm mt-2"><div class="progress-bar bg-red" style="width: {{ $pct($totalMasalah) }}%"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <x-stat label="Total Sesi" :value="number_format($totalSesi, 0, ',', '.')" icon="folders" color="blue"
                sub="Sesi pemeriksaan tersimpan" />
    </div>
    <div class="col-sm-6 col-lg-3">
        <x-stat label="File Diperiksa" :value="number_format($totalFile, 0, ',', '.')" icon="files" color="azure"
                sub="Publikasi yang sudah dicek" />
    </div>

    {{-- Ringkasan status --}}
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-green text-white avatar"><i class="ti ti-circle-check fs-2"></i></span></div>
                    <div class="col">
                        <div class="fw-medium">{{ $totalOk }} Semua OK</div>
                        <div class="text-secondary">Lolos semua kriteria</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-yellow text-white avatar"><i class="ti ti-alert-triangle fs-2"></i></span></div>
                    <div class="col">
                        <div class="fw-medium">{{ $totalWarn }} Perlu Dicek</div>
                        <div class="text-secondary">Ada catatan untuk ditinjau</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-red text-white avatar"><i class="ti ti-circle-x fs-2"></i></span></div>
                    <div class="col">
                        <div class="fw-medium">{{ $totalMasalah }} Ada Masalah</div>
                        <div class="text-secondary">Kriteria tidak terpenuhi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Riwayat sesi terbaru --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Riwayat Sesi Terbaru</h3>
                <div class="card-actions">
                    <a href="{{ route('checker.riwayat') }}" class="btn btn-sm">Lihat semua</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter text-nowrap">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Sesi</th>
                            <th class="text-center">File</th>
                            <th class="text-center">OK</th>
                            <th class="text-center">Dicek</th>
                            <th class="text-center">Masalah</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentSesi as $sesi)
                        <tr>
                            <td>
                                <div>{{ $sesi->created_at->format('d M Y') }}</div>
                                <div class="text-secondary small">{{ $sesi->created_at->diffForHumans() }}</div>
                            </td>
                            <td><span class="text-secondary">#{{ $sesi->id }}</span></td>
                            <td class="text-center">{{ $sesi->total_file }}</td>
                            <td class="text-center">
                                @if($sesi->total_ok > 0) <span class="badge bg-green-lt">{{ $sesi->total_ok }}</span> @else <span class="text-secondary">—</span> @endif
                            </td>
                            <td class="text-center">
                                @if($sesi->total_warn > 0) <span class="badge bg-yellow-lt">{{ $sesi->total_warn }}</span> @else <span class="text-secondary">—</span> @endif
                            </td>
                            <td class="text-center">
                                @if($sesi->total_err > 0) <span class="badge bg-red-lt">{{ $sesi->total_err }}</span> @else <span class="text-secondary">—</span> @endif
                            </td>
                            <td>
                                <a href="{{ route('checker.riwayat.detail', $sesi) }}" class="btn btn-sm">Detail</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty py-4">
                                    <div class="empty-icon"><i class="ti ti-history fs-1"></i></div>
                                    <p class="empty-title">Belum ada sesi pemeriksaan</p>
                                    <div class="empty-action">
                                        <a href="{{ route('checker.index') }}" class="btn btn-primary">Mulai Pemeriksaan Pertama</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Aksi cepat --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Aksi Cepat</h3>
            </div>
            <div class="list-group list-group-flush list-group-hoverable">
                @php
                    $aksi = [
                        [route('checker.index'),     'file-search',    'blue',  'Pemeriksaan Baru',  'Upload dan periksa PDF publikasi'],
                        [route('checker.bps.index'), 'cloud-download', 'azure', 'Import dari API BPS','Cari publikasi di portal BPS'],
                        [route('checker.riwayat'),   'history',        'green', 'Riwayat Sesi',      'Lanjutkan tinjauan yang belum selesai'],
                    ];
                    if ($recentSesi->first()) {
                        $aksi[] = [route('checker.riwayat.export', $recentSesi->first()), 'file-spreadsheet', 'yellow', 'Export Sesi Terakhir', 'Download rekap Excel sesi terbaru'];
                    }
                    if ($user->role === 'admin') {
                        $aksi[] = [route('admin.kriteria.index'), 'list-check', 'purple', 'Kelola Kriteria', 'Atur kriteria & contoh gambar'];
                    }
                @endphp
                @foreach($aksi as [$url, $icon, $color, $judul, $ket])
                    <a href="{{ $url }}" class="list-group-item list-group-item-action">
                        <div class="row align-items-center">
                            <div class="col-auto"><span class="avatar avatar-sm bg-{{ $color }}-lt"><i class="ti ti-{{ $icon }}"></i></span></div>
                            <div class="col text-truncate">
                                <div class="text-body">{{ $judul }}</div>
                                <div class="text-secondary small text-truncate">{{ $ket }}</div>
                            </div>
                            <div class="col-auto"><i class="ti ti-chevron-right text-secondary"></i></div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

</div>
@endsection
