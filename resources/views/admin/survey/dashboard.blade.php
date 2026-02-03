@extends('layouts.app')

@section('title', 'Dashboard Survey')

@push('plugin-styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
@endpush

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
    .chart-container {
        position: relative;
        height: 400px;
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
                        <h4 class="card-title mb-1">Dashboard Analytics Survey</h4>
                        <p class="text-muted mb-0 small">Visualisasi dan analisis data survey kepuasan masyarakat</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm border">
                            <i class="mdi mdi-home"></i> Dashboard
                        </a>
                        <a href="{{ route('admin.survey.index') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-table"></i> Data Survey
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card card-rounded mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('admin.survey.dashboard') }}" class="row g-2 align-items-center">
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

                    <!-- Filter Triwulan -->
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

                    <!-- Filter Bulan -->
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
                            <a href="{{ route('admin.survey.dashboard') }}" class="btn btn-sm btn-light border d-block">
                                <i class="mdi mdi-refresh"></i> Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-primary-card">
                    <p>Total Responden</p>
                    <h3>{{ number_format($statistics['total_responses'], 0, ',', '.') }}</h3>
                    <i class="mdi mdi-account-multiple icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-success-card">
                    <p>Rata-rata Kepuasan</p>
                    <h3>{{ $statistics['avg_kepuasan'] }}<small style="font-size: 1.2rem;">/10</small></h3>
                    <i class="mdi mdi-emoticon-happy-outline icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-info-card">
                    <p>Responden Laki-laki</p>
                    <h3>{{ number_format($statistics['by_gender']['Laki-laki'] ?? 0, 0, ',', '.') }}</h3>
                    <i class="mdi mdi-human-male icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card bg-warning-card">
                    <p>Responden Perempuan</p>
                    <h3>{{ number_format($statistics['by_gender']['Perempuan'] ?? 0, 0, ',', '.') }}</h3>
                    <i class="mdi mdi-human-female icon"></i>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="mdi mdi-chart-bar text-primary me-2"></i>
                            Perbandingan Kepentingan vs Kepuasan
                        </h5>
                        <div class="chart-container">
                            <canvas id="comparisonChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="mdi mdi-gender-male-female text-success me-2"></i>
                            Distribusi Jenis Kelamin
                        </h5>
                        <div class="chart-container">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row">
            <div class="col-12 mb-3">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="mdi mdi-chart-line text-info me-2"></i>
                            Tingkat Kepuasan per Aspek Pelayanan
                        </h5>
                        <div class="chart-container" style="height: 500px;">
                            <canvas id="satisfactionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 3 -->
        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="mdi mdi-office-building text-warning me-2"></i>
                            Top 5 Kategori Instansi
                        </h5>
                        <div class="chart-container">
                            <canvas id="instansiChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="card card-rounded">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="mdi mdi-star text-danger me-2"></i>
                            Tingkat Kepentingan per Aspek
                        </h5>
                        <div class="chart-container">
                            <canvas id="importanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('plugin-scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@push('scripts')
<script>
    // Toggle filter periode
    function toggleCustomPeriod() {
        const periode = document.getElementById('periode').value;
        const filterBulan = document.getElementById('filter-bulan');
        const filterTriwulan = document.getElementById('filter-triwulan');
        
        filterBulan.style.display = 'none';
        filterTriwulan.style.display = 'none';
        
        if (periode === 'bulan') {
            filterBulan.style.display = 'block';
        } else if (periode === 'triwulan') {
            filterTriwulan.style.display = 'block';
        }
    }

    const chartData = @json($chartData);
    const statistics = @json($statistics);

    // Chart.js default config
    Chart.defaults.font.family = 'Plus Jakarta Sans, sans-serif';
    Chart.defaults.color = '#6c757d';

    // 1. Comparison Chart (Kepentingan vs Kepuasan)
    const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
    const kepentinganData = Object.values(chartData.averages).map(item => item.kepentingan);
    const kepuasanData = Object.values(chartData.averages).map(item => item.kepuasan);

    new Chart(comparisonCtx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Tingkat Kepentingan',
                    data: kepentinganData,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Tingkat Kepuasan',
                    data: kepuasanData,
                    backgroundColor: 'rgba(75, 192, 192, 0.8)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    ticks: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + '/10';
                        }
                    }
                }
            }
        }
    });

    // 2. Gender Chart
    const genderCtx = document.getElementById('genderChart').getContext('2d');
    new Chart(genderCtx, {
        type: 'doughnut',
        data: {
            labels: ['Laki-laki', 'Perempuan'],
            datasets: [{
                data: [
                    statistics.by_gender['Laki-laki'] || 0,
                    statistics.by_gender['Perempuan'] || 0
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 99, 132, 0.8)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // 3. Satisfaction Chart (Horizontal Bar)
    const satisfactionCtx = document.getElementById('satisfactionChart').getContext('2d');
    new Chart(satisfactionCtx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Tingkat Kepuasan',
                data: kepuasanData,
                backgroundColor: function(context) {
                    const value = context.parsed.x;
                    if (value >= 8) return 'rgba(40, 167, 69, 0.8)';
                    if (value >= 6) return 'rgba(255, 193, 7, 0.8)';
                    return 'rgba(220, 53, 69, 0.8)';
                },
                borderColor: function(context) {
                    const value = context.parsed.x;
                    if (value >= 8) return 'rgba(40, 167, 69, 1)';
                    if (value >= 6) return 'rgba(255, 193, 7, 1)';
                    return 'rgba(220, 53, 69, 1)';
                },
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    max: 10,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Kepuasan: ' + context.parsed.x + '/10';
                        }
                    }
                }
            }
        }
    });

    // 4. Instansi Chart
    const instansiCtx = document.getElementById('instansiChart').getContext('2d');
    const instansiLabels = Object.keys(statistics.by_instansi);
    const instansiData = Object.values(statistics.by_instansi);

    new Chart(instansiCtx, {
        type: 'pie',
        data: {
            labels: instansiLabels,
            datasets: [{
                data: instansiData,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // 5. Importance Chart (Radar)
    const importanceCtx = document.getElementById('importanceChart').getContext('2d');
    new Chart(importanceCtx, {
        type: 'radar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Tingkat Kepentingan',
                data: kepentinganData,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(255, 99, 132, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 10,
                    ticks: {
                        stepSize: 2,
                        display: false
                    },
                    pointLabels: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed.r + '/10';
                        }
                    }
                }
            }
        }
    });
</script>
@endpush