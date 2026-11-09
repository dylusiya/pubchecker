@extends('layouts.app')

@php
    use App\Models\SesiPemeriksaan;
    use App\Models\HasilPemeriksaan;

    $totalSesi    = SesiPemeriksaan::count();
    $totalFile    = HasilPemeriksaan::count();
    $recentSesi   = SesiPemeriksaan::latest()->take(10)->get();
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
                <p class="text-secondary mb-0">
                    Sistem pemeriksaan kover dan kelengkapan publikasi BPS Provinsi Kalimantan Selatan.
                </p>
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

    {{-- Rekap Pasca Rilis SIPOTRET: publikasi bermasalah per kab/kota per bulan & status centang perbaikan --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Publikasi Bermasalah per Kab/Kota — SIPOTRET</h3>
                    <div class="card-subtitle">Sel: <b>sudah approve / bermasalah</b> (publikasi yang punya catatan Pasca Rilis pada periode tsb) · <i class="ti ti-clock"></i> = file perbaikan menunggu approve</div>
                </div>
                <div class="card-actions">
                    <form method="get" class="d-flex gap-2">
                        <select name="tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                            @for($t = date('Y'); $t >= 2024; $t--)
                                <option value="{{ $t }}" @selected($t == $tahun)>{{ $t }}</option>
                            @endfor
                        </select>
                    </form>
                </div>
            </div>
            @if($rekapError)
                <div class="card-body"><div class="alert alert-warning mb-0">{{ $rekapError }}</div></div>
            @else
                @php
                    $bulanNama = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                    $wilayah = \App\Services\SipotretService::WILAYAH
                        + collect(array_keys($rekap))->mapWithKeys(fn($k) => [$k => "Domain {$k}"])->all();
                    $jumlah = fn(array $sel) => [
                        'masalah'    => array_sum(array_column($sel, 'masalah')),
                        'menunggu'   => array_sum(array_column($sel, 'menunggu')),
                        'diperbaiki' => array_sum(array_column($sel, 'diperbaiki')),
                    ];
                    // hijau: semua approve · kuning: ada perbaikan menunggu approve · merah: belum ada perbaikan
                    $kelas = fn($s) => $s['diperbaiki'] >= $s['masalah'] ? 'bg-green-lt' : ($s['menunggu'] > 0 ? 'bg-yellow-lt' : 'bg-red-lt');
                    $perBulan = [];
                    foreach (range(1, 12) as $b) {
                        $perBulan[$b] = $jumlah(array_filter(array_map(fn($r) => $r[$b] ?? null, $rekap)));
                    }
                    $total = $jumlah($perBulan);
                @endphp
                <div class="table-responsive">
                    <table class="table card-table table-vcenter table-sm text-nowrap">
                        <thead>
                            <tr>
                                <th>Kab/Kota</th>
                                @foreach(range(1, 12) as $b)<th class="text-center">{{ $bulanNama[$b] }}</th>@endforeach
                                <th class="text-center">Total</th>
                                <th class="text-center">Menunggu</th>
                                <th class="text-center">Belum upload</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wilayah as $kode => $nama)
                                @php $baris = $jumlah($rekap[$kode] ?? []); @endphp
                                <tr>
                                    <td>
                                        @if($baris['masalah'])
                                            <a href="{{ route('dashboard.sipotret', ['wilayah' => $kode, 'tahun' => $tahun]) }}">{{ $nama }}</a>
                                        @else
                                            {{ $nama }}
                                        @endif
                                        <span class="text-secondary small">{{ $kode }}</span>
                                    </td>
                                    @foreach(range(1, 12) as $b)
                                        @php $s = $rekap[$kode][$b] ?? null; @endphp
                                        <td class="text-center">
                                            @if($s)
                                                <a href="{{ route('dashboard.sipotret', ['wilayah' => $kode, 'tahun' => $tahun, 'bulan' => $b]) }}" class="badge {{ $kelas($s) }}"
                                                   title="{{ $s['masalah'] }} bermasalah · {{ $s['diperbaiki'] }} approve · {{ $s['menunggu'] }} menunggu approve — klik untuk daftar">{{ $s['diperbaiki'] }}/{{ $s['masalah'] }}@if($s['menunggu']) <i class="ti ti-clock"></i>{{ $s['menunggu'] }}@endif</a>
                                            @else
                                                <span class="text-secondary">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="text-center fw-medium">{{ $baris['masalah'] ? $baris['diperbaiki'] . '/' . $baris['masalah'] : '—' }}</td>
                                    <td class="text-center">
                                        @if($baris['menunggu'])
                                            <a href="{{ route('dashboard.sipotret', ['wilayah' => $kode, 'tahun' => $tahun, 'status' => 'menunggu']) }}"
                                               class="badge bg-yellow text-white" title="Buka daftar perbaikan yang menunggu approve">{{ $baris['menunggu'] }}</a>
                                        @else
                                            <span class="text-secondary">—</span>
                                        @endif
                                    </td>
                                    @php $belum = $baris['masalah'] - $baris['diperbaiki'] - $baris['menunggu']; @endphp
                                    <td class="text-center">
                                        @if($belum > 0)
                                            <span class="badge bg-red text-white">{{ $belum }}</span>
                                        @else
                                            <span class="text-secondary">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-medium">
                                <td>Total</td>
                                @foreach(range(1, 12) as $b)
                                    <td class="text-center">{{ $perBulan[$b]['masalah'] ? $perBulan[$b]['diperbaiki'] . '/' . $perBulan[$b]['masalah'] : '—' }}</td>
                                @endforeach
                                <td class="text-center">{{ $total['masalah'] ? $total['diperbaiki'] . '/' . $total['masalah'] : '—' }}</td>
                                <td class="text-center">{{ $total['menunggu'] ?: '—' }}</td>
                                <td class="text-center">{{ ($total['masalah'] - $total['diperbaiki'] - $total['menunggu']) ?: '—' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer text-secondary small">
                    <span class="badge bg-green-lt">semua approve</span>
                    <span class="badge bg-yellow-lt">ada yang menunggu approve</span>
                    <span class="badge bg-red-lt">belum upload perbaikan</span>
                    <span class="ms-2">Klik nama kab/kota atau sel untuk melihat daftar publikasinya.</span>
                </div>
            @endif
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
