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
    .bg-primary-card {
        background: #667eea;
    }
    .bg-success-card {
        background: #28a745;
    }
    .bg-info-card {
        background: #17a2b8;
    }
    .bg-warning-card {
        background: #ffc107;
    }
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
        <!-- Header -->
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-1">Dashboard e-SKD</h4>
                        <p class="text-muted mb-0 small">Sistem Survey Kepuasan Masyarakat BPS Kalsel</p>
                    </div>
                    <div>
                        <a href="{{ route('admin.survey.dashboard') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-chart-bar"></i> Dashboard Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tahun/Periode -->
        <div class="card card-rounded mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('dashboard') }}" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label class="form-label mb-0 fw-bold">Filter :</label>
                    </div>

                    <!-- Filter Tahun (Utama) -->
                    <div class="col-auto">
                        <label class="form-label mb-0 small">Tahun</label>
                        <select name="tahun" id="tahun" class="form-select form-select-sm text-dark">
                            <option value="">Semua Tahun</option>
                            @for($y = now()->year; $y >= 2020; $y--)
                                <option value="{{ $y }}" {{ request('tahun') == $y ? 'selected' : '' }}>
                                    {{ $y }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <!-- Filter Periode -->
                    <div class="col-auto">
                        <label class="form-label mb-0 small">Periode</label>
                        <select name="periode" id="periode" class="form-select form-select-sm text-dark" onchange="toggleCustomPeriod()">
                            <option value="semua" {{ request('periode', 'semua') == 'semua' ? 'selected' : '' }}>Semua Periode</option>
                            <option value="triwulan" {{ request('periode') == 'triwulan' ? 'selected' : '' }}>Per Triwulan</option>
                            <option value="bulan" {{ request('periode') == 'bulan' ? 'selected' : '' }}>Per Bulan</option>
                        </select>
                    </div>

                    <!-- Filter Triwulan (muncul jika pilih triwulan) -->
                    <div class="col-auto" id="filter-triwulan" style="display: {{ request('periode') == 'triwulan' ? 'block' : 'none' }};">
                        <label class="form-label mb-0 small">Triwulan</label>
                        <select name="triwulan" class="form-select form-select-sm text-dark">
                            <option value="">Semua Triwulan</option>
                            <option value="1" {{ request('triwulan') == 1 ? 'selected' : '' }}>Triwulan I (Jan-Mar)</option>
                            <option value="2" {{ request('triwulan') == 2 ? 'selected' : '' }}>Triwulan II (Apr-Jun)</option>
                            <option value="3" {{ request('triwulan') == 3 ? 'selected' : '' }}>Triwulan III (Jul-Sep)</option>
                            <option value="4" {{ request('triwulan') == 4 ? 'selected' : '' }}>Triwulan IV (Okt-Des)</option>
                        </select>
                    </div>

                    <!-- Filter Bulan (muncul jika pilih bulan) -->
                    <div class="col-auto" id="filter-bulan" style="display: {{ request('periode') == 'bulan' ? 'block' : 'none' }};">
                        <label class="form-label mb-0 small">Bulan</label>
                        <select name="bulan" class="form-select form-select-sm text-dark">
                            <option value="">Semua Bulan</option>
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ request('bulan') == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-auto">
                        <label class="form-label mb-0 small text-white">.</label>
                        <button type="submit" class="btn btn-sm btn-primary d-block">
                            <i class="mdi mdi-filter"></i> Terapkan
                        </button>
                    </div>
                    <div class="col-auto">
                        @if(request()->hasAny(['tahun', 'periode', 'bulan', 'triwulan']))
                            <label class="form-label mb-0 small text-white">.</label>
                            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-light border d-block">
                                <i class="mdi mdi-refresh"></i> Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @php
            use App\Models\SurveyResponse;
            use Illuminate\Support\Facades\DB;
            
            $tahun = request('tahun');
            $periode = request('periode', 'semua');
            $bulan = request('bulan');
            $triwulan = request('triwulan');

            // Base query
            $query = SurveyResponse::query();

            // Filter berdasarkan tahun dulu
            if ($tahun) {
                $query->whereYear('tanggal_submit', $tahun);
            }

            // Kemudian filter berdasarkan periode
            if ($periode == 'bulan' && $bulan) {
                $query->whereMonth('tanggal_submit', $bulan);
            } elseif ($periode == 'triwulan' && $triwulan) {
                $startMonth = ($triwulan - 1) * 3 + 1;
                $endMonth = $triwulan * 3;
                $query->whereMonth('tanggal_submit', '>=', $startMonth)
                    ->whereMonth('tanggal_submit', '<=', $endMonth);
            }
            
            $totalResponden = (clone $query)->count();
            
            $avgKepuasan = (clone $query)->selectRaw('AVG((
                informasi_pelayanan_kepuasan + 
                persyaratan_kepuasan + 
                prosedur_kepuasan + 
                jangka_waktu_kepuasan + 
                biaya_kepuasan + 
                produk_kepuasan + 
                sarana_kepuasan + 
                akses_data_kepuasan + 
                respons_petugas_kepuasan + 
                informasi_petugas_kepuasan + 
                fasilitas_pengaduan_kepuasan + 
                diskriminasi_kepuasan + 
                kecurangan_kepuasan + 
                gratifikasi_kepuasan + 
                pungli_kepuasan + 
                percaloan_kepuasan
            ) / 16) as avg_kepuasan')->value('avg_kepuasan');
            
            $totalLakiLaki = (clone $query)->where('jenis_kelamin', 'Laki-laki')->count();
            $totalPerempuan = (clone $query)->where('jenis_kelamin', 'Perempuan')->count();
        @endphp

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-primary-card">
                    <p>Total Responden</p>
                    <h3>{{ number_format($totalResponden, 0, ',', '.') }}</h3>
                    <i class="mdi mdi-account-multiple icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-success-card">
                    <p>Rata-rata Kepuasan</p>
                    <h3>{{ number_format($avgKepuasan ?? 0, 2, ',', '.') }}<small style="font-size: 1.2rem;">/10</small></h3>
                    <i class="mdi mdi-emoticon-happy-outline icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-info-card">
                    <p>Responden Laki-laki</p>
                    <h3>{{ number_format($totalLakiLaki, 0, ',', '.') }}</h3>
                    <i class="mdi mdi-human-male icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-warning-card">
                    <p>Responden Perempuan</p>
                    <h3>{{ number_format($totalPerempuan, 0, ',', '.') }}</h3>
                    <i class="mdi mdi-human-female icon"></i>
                </div>
            </div>
        </div>

        <!-- Statistik Per Kategori Instansi -->
        <div class="card card-rounded mb-4">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">Statistik Per Kategori Instansi</h4>
                        <p class="card-subtitle card-subtitle-dash">Jumlah responden dan rata-rata kepuasan berdasarkan kategori instansi</p>
                    </div>
                </div>

                @php
                    // Get stats per kategori instansi
                    $instansiStats = SurveyResponse::selectRaw('
                            kategori_instansi,
                            COUNT(*) as total_responden,
                            AVG((
                                informasi_pelayanan_kepuasan + 
                                persyaratan_kepuasan + 
                                prosedur_kepuasan + 
                                jangka_waktu_kepuasan + 
                                biaya_kepuasan + 
                                produk_kepuasan + 
                                sarana_kepuasan + 
                                akses_data_kepuasan + 
                                respons_petugas_kepuasan + 
                                informasi_petugas_kepuasan + 
                                fasilitas_pengaduan_kepuasan + 
                                diskriminasi_kepuasan + 
                                kecurangan_kepuasan + 
                                gratifikasi_kepuasan + 
                                pungli_kepuasan + 
                                percaloan_kepuasan
                            ) / 16) as avg_kepuasan
                        ')
                        ->when($tahun, function($q) use ($tahun) {
                            $q->whereYear('tanggal_submit', $tahun);
                        })
                        ->when($periode == 'bulan' && $bulan, function($q) use ($bulan) {
                            $q->whereMonth('tanggal_submit', $bulan);
                        })
                        ->when($periode == 'triwulan' && $triwulan, function($q) use ($triwulan) {
                            $startMonth = ($triwulan - 1) * 3 + 1;
                            $endMonth = $triwulan * 3;
                            $q->whereMonth('tanggal_submit', '>=', $startMonth)
                            ->whereMonth('tanggal_submit', '<=', $endMonth);
                        })
                        ->groupBy('kategori_instansi')
                        ->orderBy('total_responden', 'desc')
                        ->get();
                @endphp

                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3">#</th>
                                <th class="py-3">Kategori Instansi</th>
                                <th class="py-3 text-center">
                                    <i class="mdi mdi-account-multiple text-primary"></i> Jumlah Responden
                                </th>
                                <th class="py-3 text-center">
                                    <i class="mdi mdi-emoticon-happy text-success"></i> Rata-rata Kepuasan
                                </th>
                                <th class="py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($instansiStats as $index => $stat)
                            <tr>
                                <td class="py-3">{{ $index + 1 }}</td>
                                <td class="py-3">
                                    <div class="fw-bold">{{ $stat->kategori_instansi }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge badge-primary px-3">{{ number_format($stat->total_responden, 0, ',', '.') }} Responden</span>
                                </td>
                                <td class="py-3 text-center">
                                    <strong class="text-success">{{ number_format($stat->avg_kepuasan, 2, ',', '.') }}/10</strong>
                                </td>
                                <td class="py-3 text-center">
                                    <a href="{{ route('admin.survey.index', ['kategori_instansi' => $stat->kategori_instansi]) }}" 
                                       class="btn btn-outline-primary btn-xs">
                                        <i class="mdi mdi-eye"></i> Lihat
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center">
                                    <i class="mdi mdi-chart-bar text-light" style="font-size: 48px;"></i>
                                    <p class="text-muted mt-2">Belum ada data survey</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($instansiStats->count() > 0)
                        <tfoot class="bg-light">
                            <tr>
                                <th colspan="2" class="py-3">TOTAL</th>
                                <th class="py-3 text-center">
                                    <span class="badge badge-primary px-3">{{ number_format($instansiStats->sum('total_responden'), 0, ',', '.') }} Responden</span>
                                </th>
                                <th class="py-3 text-center">
                                    <strong class="text-success">{{ number_format($instansiStats->avg('avg_kepuasan'), 2, ',', '.') }}/10</strong>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!-- Survey Terbaru -->
        <div class="card card-rounded mb-4">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">Survey Terbaru</h4>
                        <p class="card-subtitle card-subtitle-dash">10 survey terakhir yang masuk</p>
                    </div>
                    <div>
                        <a href="{{ route('admin.survey.index') }}" class="btn btn-primary text-white btn-sm">
                            <i class="mdi mdi-database"></i> Lihat Semua Data
                        </a>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3">Waktu Submit</th>
                                <th class="py-3">Nama Responden</th>
                                <th class="py-3">Instansi</th>
                                <th class="py-3 text-center">Kepuasan</th>
                                <th class="py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                // Recent surveys
                                $recentSurveys = \App\Models\SurveyResponse::latest('updated_at')
                                    ->take(10)
                                    ->get();
                            @endphp
                            
                            @forelse($recentSurveys as $survey)
                            <tr>
                                <td class="py-3">
                                    @if($survey->tanggal_submit)
                                        <div class="fw-bold">{{ $survey->tanggal_submit->format('d/m/Y H:i') }}</div>
                                        <small class="text-muted">
                                            <i class="mdi mdi-clock-outline"></i> 
                                            {{ $survey->tanggal_submit->diffForHumans() }}
                                        </small>
                                    @else
                                        <div class="badge badge-opacity-warning">Belum Submit</div>
                                        <div class="small text-muted mt-1">
                                            Updated: {{ $survey->updated_at->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div>{{ $survey->nama }}</div>
                                    <small class="text-muted">{{ $survey->email }}</small>
                                </td>
                                <td class="py-3">
                                    <div class="small fw-bold">{{ $survey->nama_instansi }}</div>
                                    <div class="small text-muted">{{ $survey->kategori_instansi }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    @if($survey->average_kepuasan)
                                        <span class="badge badge-success">
                                            {{ number_format($survey->average_kepuasan, 2) }}/10
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    <a href="{{ route('admin.survey.show', $survey->id) }}" 
                                       class="btn btn-outline-info btn-xs">
                                        <i class="mdi mdi-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center">
                                    <i class="mdi mdi-clipboard-text-outline text-light" style="font-size: 48px;"></i>
                                    <p class="text-muted mt-2">Belum ada data survey</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h4 class="card-title card-title-dash">Quick Actions</h4>
                        <div class="list-group">
                            <a href="{{ route('admin.survey.dashboard') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                                <i class="mdi mdi-chart-bar text-primary me-3" style="font-size: 24px;"></i>
                                <div>
                                    <div class="fw-bold">Dashboard Analytics</div>
                                    <small class="text-muted">Lihat visualisasi data survey</small>
                                </div>
                            </a>
                            <a href="{{ route('admin.survey.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                                <i class="mdi mdi-table text-success me-3" style="font-size: 24px;"></i>
                                <div>
                                    <div class="fw-bold">Data Survey</div>
                                    <small class="text-muted">Kelola dan filter data survey</small>
                                </div>
                            </a>
                            <a href="{{ route('survey.index') }}" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                                <i class="mdi mdi-open-in-new text-info me-3" style="font-size: 24px;"></i>
                                <div>
                                    <div class="fw-bold">Form Survey Public</div>
                                    <small class="text-muted">Buka formulir survey untuk masyarakat</small>
                                </div>
                            </a>
                            <a href="{{ route('admin.survey.export') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                                <i class="mdi mdi-file-excel text-warning me-3" style="font-size: 24px;"></i>
                                <div>
                                    <div class="fw-bold">Export Data</div>
                                    <small class="text-muted">Download data dalam format Excel</small>
                                </div>
                            </a>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleCustomPeriod() {
    const periode = document.getElementById('periode').value;
    const filterBulan = document.getElementById('filter-bulan');
    const filterTriwulan = document.getElementById('filter-triwulan');
    
    // Hide all first
    filterBulan.style.display = 'none';
    filterTriwulan.style.display = 'none';
    
    // Show based on selection
    if (periode === 'bulan') {
        filterBulan.style.display = 'block';
    } else if (periode === 'triwulan') {
        filterTriwulan.style.display = 'block';
    }
}
</script>
@endpush
@endsection