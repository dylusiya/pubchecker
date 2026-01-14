@extends('layouts.admin')

@section('title', 'Dashboard Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-sm-12">
        <!-- Filter Tahun -->
        <div class="card card-rounded mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('dashboard') }}" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label class="form-label mb-0 fw-bold">Tahun :</label>
                    </div>
                    <div class="col-auto">
                        <select name="tahun" class="form-select form-select-sm text-dark" onchange="this.form.submit()">
                            @for($y = date('Y') + 1; $y >= 2020; $y--)
                                <option value="{{ $y }}" {{ request('tahun', date('Y')) == $y ? 'selected' : '' }}>
                                    {{ $y }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-auto">
                        @if(request('tahun') && request('tahun') != date('Y'))
                            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-light border">
                                <i class="mdi mdi-refresh"></i> Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistik Overview -->
        <div class="row">
            <div class="col-sm-12">
                <div class="statistics-details d-flex align-items-center justify-content-start gap-5 mb-4">
                    @php
                        $tahunAktif = request('tahun', date('Y'));
                        $user = auth()->user();
                        
                        // Base query dengan filter user access menggunakan ACCESSOR
                        $statsQuery = \App\Models\StatusDaerahSulit::where('tahun_anggaran', $tahunAktif)
                            ->where('status_approval', 'disetujui')
                            ->whereNull('deleted_at');
                        
                        // Filter by user access
                        if (!$user->isAdmin()) {
                            if ($user->kode_kabupaten) {
                                $statsQuery->whereHas('masterSls', function($q) use ($user) {
                                    $q->where('kdkab', $user->kdkab); // gunakan accessor
                                });
                            } elseif ($user->kode_provinsi) {
                                $statsQuery->whereHas('masterSls', function($q) use ($user) {
                                    $q->where('kdprov', $user->kdprov);
                                });
                            }
                        }
                        
                        $totalSulit = (clone $statsQuery)->where('is_daerah_sulit', true)->count();
                        $totalBiaya = (clone $statsQuery)->where('is_daerah_sulit', true)->sum('perkiraan_biaya');
                        
                        // Draft - filter by user
                        $totalDraft = \App\Models\StatusDaerahSulit::where('tahun_anggaran', $tahunAktif)
                            ->where('status_approval', 'draft')
                            ->when(!$user->isAdmin(), function($q) use ($user) {
                                if ($user->kode_kabupaten) {
                                    $q->whereHas('masterSls', function($sq) use ($user) {
                                        $sq->where('kdkab', $user->kdkab);
                                    });
                                } elseif ($user->kode_provinsi) {
                                    $q->whereHas('masterSls', function($sq) use ($user) {
                                        $sq->where('kdprov', $user->kdprov);
                                    });
                                }
                            })
                            ->count();
                        
                        // Pending - filter by user
                        $totalPending = \App\Models\StatusDaerahSulit::where('tahun_anggaran', $tahunAktif)
                            ->where('status_approval', 'pending')
                            ->when(!$user->isAdmin(), function($q) use ($user) {
                                if ($user->kode_kabupaten) {
                                    $q->whereHas('masterSls', function($sq) use ($user) {
                                        $sq->where('kdkab', $user->kdkab);
                                    });
                                } elseif ($user->kode_provinsi) {
                                    $q->whereHas('masterSls', function($sq) use ($user) {
                                        $sq->where('kdprov', $user->kdprov);
                                    });
                                }
                            })
                            ->count();
                        
                        // Total kabupaten - filter by user
                        $totalKabupaten = \App\Models\StatusDaerahSulit::join('master_sls', 'status_daerah_sulit.master_sls_id', '=', 'master_sls.id')
                            ->where('status_daerah_sulit.tahun_anggaran', $tahunAktif)
                            ->where('status_daerah_sulit.status_approval', 'disetujui')
                            ->where('status_daerah_sulit.is_daerah_sulit', true)
                            ->whereNull('status_daerah_sulit.deleted_at')
                            ->when(!$user->isAdmin(), function($q) use ($user) {
                                if ($user->kode_kabupaten) {
                                    $q->where('master_sls.kdkab', $user->kdkab);
                                } elseif ($user->kode_provinsi) {
                                    $q->where('master_sls.kdprov', $user->kdprov);
                                }
                            })
                            ->distinct('master_sls.kdkab')
                            ->count('master_sls.kdkab');
                    @endphp
                    
                    <div>
                        <p class="statistics-title">Daerah Sulit</p>
                        <h3 class="rate-percentage text-danger">{{ number_format($totalSulit, 0, ',', '.') }}</h3>
                        <p class="text-danger d-flex small fw-bold"><i class="mdi mdi-alert-circle me-1"></i>SLS Sulit Diakses</p>
                    </div>
                    <div>
                        <p class="statistics-title">Total Biaya</p>
                        <h3 class="rate-percentage text-primary">Rp {{ number_format($totalBiaya / 1000000, 0, ',', '.') }} Jt</h3>
                        <p class="text-primary d-flex small fw-bold"><i class="mdi mdi-cash-multiple me-1"></i>Estimasi Anggaran</p>
                    </div>
                    <div>
                        <p class="statistics-title">Kabupaten/Kota</p>
                        <h3 class="rate-percentage text-info">{{ number_format($totalKabupaten, 0, ',', '.') }}</h3>
                        <p class="text-info d-flex small fw-bold"><i class="mdi mdi-map-marker me-1"></i>Wilayah Terdampak</p>
                    </div>
                    <div>
                        <p class="statistics-title">Pending Review</p>
                        <h3 class="rate-percentage text-warning">{{ number_format($totalPending, 0, ',', '.') }}</h3>
                        <p class="text-warning d-flex small fw-bold"><i class="mdi mdi-clock me-1"></i>Menunggu Approval</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistik Per Kabupaten -->
        <div class="card card-rounded mb-4">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">Statistik Per Kabupaten/Kota</h4>
                        <p class="card-subtitle card-subtitle-dash">Jumlah daerah sulit dan estimasi biaya tahun {{ $tahunAktif }}</p>
                    </div>
                </div>

                @php
                    // Get stats per kabupaten - SEMUA USER BISA LIHAT SEMUA KABUPATEN
                    // Hanya filter by provinsi jika user provinsi
                    $kabupatenStats = \App\Models\StatusDaerahSulit::selectRaw('
                            master_sls.kdkab,
                            master_sls.nmkab,
                            COUNT(*) as total_sulit,
                            SUM(status_daerah_sulit.perkiraan_biaya) as total_biaya,
                            AVG(status_daerah_sulit.perkiraan_biaya) as rata_biaya
                        ')
                        ->join('master_sls', 'status_daerah_sulit.master_sls_id', '=', 'master_sls.id')
                        ->where('status_daerah_sulit.tahun_anggaran', $tahunAktif)
                        ->where('status_daerah_sulit.status_approval', 'disetujui')
                        ->where('status_daerah_sulit.is_daerah_sulit', true)
                        ->whereNull('status_daerah_sulit.deleted_at')
                        // HANYA filter by provinsi (tidak filter by kabupaten)
                        ->when(!$user->isAdmin() && $user->kode_provinsi && !$user->kode_kabupaten, function($q) use ($user) {
                            $q->where('master_sls.kdprov', $user->kdprov);
                        })
                        ->groupBy('master_sls.kdkab', 'master_sls.nmkab')
                        ->orderBy('kdkab')
                        ->get();
                @endphp

                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3">#</th>
                                <th class="py-3">Kabupaten/Kota</th>
                                <th class="py-3 text-center">
                                    <i class="mdi mdi-alert-circle text-danger"></i> Jumlah Daerah Sulit
                                </th>
                                <th class="py-3 text-end">
                                    <i class="mdi mdi-cash text-primary"></i> Total Biaya
                                </th>
                                <th class="py-3 text-end">
                                    <i class="mdi mdi-chart-line text-info"></i> Rata-rata Biaya
                                </th>
                                <th class="py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($kabupatenStats as $index => $stat)
                            <tr>
                                <td class="py-3">{{ $index + 1 }}</td>
                                <td class="py-3">
                                    <div class="fw-bold">{{ $stat->kdkab }} {{ $stat->nmkab }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge badge-danger px-3">{{ number_format($stat->total_sulit, 0, ',', '.') }} SLS</span>
                                </td>
                                <td class="py-3 text-end">
                                    <strong class="text-primary">Rp {{ number_format($stat->total_biaya, 0, ',', '.') }}</strong>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="text-muted">Rp {{ number_format($stat->rata_biaya, 0, ',', '.') }}</span>
                                </td>
                                <td class="py-3 text-center">
                                    <a href="{{ route('daerah-sulit.index', ['kdkab' => $stat->kdkab, 'tahun' => $tahunAktif, 'approval' => 'disetujui']) }}" 
                                       class="btn btn-outline-primary btn-xs">
                                        <i class="mdi mdi-eye"></i> Lihat
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="py-5 text-center">
                                    <i class="mdi mdi-chart-bar text-light" style="font-size: 48px;"></i>
                                    <p class="text-muted mt-2">Belum ada data daerah sulit yang disetujui untuk tahun {{ $tahunAktif }}</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($kabupatenStats->count() > 0)
                        <tfoot class="bg-light">
                            <tr>
                                <th colspan="2" class="py-3">TOTAL</th>
                                <th class="py-3 text-center">
                                    <span class="badge badge-danger px-3">{{ number_format($kabupatenStats->sum('total_sulit'), 0, ',', '.') }} SLS</span>
                                </th>
                                <th class="py-3 text-end">
                                    <strong class="text-primary">Rp {{ number_format($kabupatenStats->sum('total_biaya'), 0, ',', '.') }}</strong>
                                </th>
                                <th class="py-3 text-end">
                                    <span class="text-muted">Rp {{ number_format($kabupatenStats->avg('rata_biaya'), 0, ',', '.') }}</span>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!-- Status Daerah Sulit Terbaru -->
        <div class="card card-rounded mb-4">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">Status Daerah Sulit Terbaru</h4>
                        <p class="card-subtitle card-subtitle-dash">Data yang baru ditambahkan atau diupdate</p>
                    </div>
                    <div>
                        <a href="{{ route('daerah-sulit.index', ['tahun' => $tahunAktif]) }}" class="btn btn-primary text-white btn-sm">
                            <i class="mdi mdi-database"></i> Lihat Semua Data
                        </a>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3">ID SLS</th>
                                <th class="py-3">Nama SLS</th>
                                <th class="py-3">Wilayah</th>
                                <th class="py-3 text-center">Status</th>
                                <th class="py-3 text-end">Biaya</th>
                                <th class="py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                // Recent data - filter by user access
                                $recentData = \App\Models\StatusDaerahSulit::with(['masterSls'])
                                    ->where('tahun_anggaran', $tahunAktif)
                                    ->whereNull('deleted_at')
                                    ->when(!$user->isAdmin(), function($q) use ($user) {
                                        if ($user->kode_kabupaten) {
                                            $q->whereHas('masterSls', function($sq) use ($user) {
                                                $sq->where('kdkab', $user->kdkab);
                                            });
                                        } elseif ($user->kode_provinsi) {
                                            $q->whereHas('masterSls', function($sq) use ($user) {
                                                $sq->where('kdprov', $user->kdprov);
                                            });
                                        }
                                    })
                                    ->latest('updated_at')
                                    ->take(10)
                                    ->get();
                            @endphp
                            
                            @forelse($recentData as $data)
                            <tr>
                                <td class="py-3">
                                    <div class="fw-bold">{{ $data->masterSls->idsls }}</div>
                                    <small class="text-muted">
                                        <i class="mdi mdi-clock-outline"></i> 
                                        {{ $data->updated_at->diffForHumans() }}
                                    </small>
                                </td>
                                <td class="py-3">
                                    <div>{{ $data->masterSls->nmsls }}</div>
                                    @if($data->masterSls->nama_ketua ?? false)
                                    <small class="text-muted">{{ $data->masterSls->nama_ketua }}</small>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="small fw-bold">{{ $data->masterSls->nmkab }}</div>
                                    <div class="small text-muted">{{ $data->masterSls->nmkec }} - {{ $data->masterSls->nmdesa }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    @if($data->is_daerah_sulit)
                                        <span class="badge badge-danger">
                                            <i class="mdi mdi-alert-circle"></i> Sulit
                                        </span>
                                    @else
                                        <span class="badge badge-success">
                                            <i class="mdi mdi-check-circle"></i> Normal
                                        </span>
                                    @endif
                                    <br>
                                    @php
                                        $approvalBadges = [
                                            'disetujui' => 'badge-success',
                                            'ditolak' => 'badge-danger',
                                            'pending' => 'badge-warning',
                                            'draft' => 'badge-secondary'
                                        ];
                                    @endphp
                                    <span class="badge {{ $approvalBadges[$data->status_approval] ?? 'badge-secondary' }} mt-1">
                                        {{ ucfirst($data->status_approval) }}
                                    </span>
                                </td>
                                <td class="py-3 text-end">
                                    @if($data->is_daerah_sulit && $data->perkiraan_biaya > 0)
                                        <strong>Rp {{ number_format($data->perkiraan_biaya, 0, ',', '.') }}</strong>
                                        <br>
                                        <small class="text-muted">{{ Str::limit($data->metode_transportasi, 20) }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    <a href="{{ route('daerah-sulit.show', $data->id) }}" 
                                       class="btn btn-outline-info btn-xs">
                                        <i class="mdi mdi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="py-5 text-center">
                                    <i class="mdi mdi-map-marker-off text-light" style="font-size: 48px;"></i>
                                    <p class="text-muted mt-2">Belum ada data status daerah sulit untuk tahun {{ $tahunAktif }}</p>
                                    <a href="{{ route('daerah-sulit.create', ['tahun' => $tahunAktif]) }}" class="btn btn-primary btn-sm mt-2">
                                        <i class="mdi mdi-plus"></i> Tambah Data
                                    </a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Riwayat Perubahan Terbaru -->
        <div class="card card-rounded">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">Riwayat Perubahan Terbaru</h4>
                        <p class="card-subtitle card-subtitle-dash">10 aktivitas terakhir</p>
                    </div>
                    <div>
                        <a href="{{ route('daerah-sulit.history') }}" class="btn btn-outline-primary btn-sm">
                            Lihat Semua <i class="mdi mdi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3">Waktu</th>
                                <th class="py-3">SLS</th>
                                <th class="py-3 text-center">Aksi</th>
                                <th class="py-3">User</th>
                                <th class="py-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                // History - filter by user access
                                $historyRecent = \App\Models\HistoryStatusDaerahSulit::with(['masterSls', 'user'])
                                    ->when(!$user->isAdmin(), function($q) use ($user) {
                                        if ($user->kode_kabupaten) {
                                            $q->whereHas('masterSls', function($sq) use ($user) {
                                                $sq->where('kdkab', $user->kdkab);
                                            });
                                        } elseif ($user->kode_provinsi) {
                                            $q->whereHas('masterSls', function($sq) use ($user) {
                                                $sq->where('kdprov', $user->kdprov);
                                            });
                                        }
                                    })
                                    ->latest('created_at')
                                    ->take(10)
                                    ->get();
                            @endphp
                            
                            @forelse($historyRecent as $history)
                            <tr>
                                <td class="py-3">
                                    <div class="small">{{ $history->created_at->format('d/m/Y H:i') }}</div>
                                    <small class="text-muted">
                                        <i class="mdi mdi-clock-outline"></i> {{ $history->created_at->diffForHumans() }}
                                    </small>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold">{{ $history->masterSls->idsls }}</div>
                                    <small class="text-muted">{{ Str::limit($history->masterSls->nmsls, 30) }}</small>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge {{ $history->aksi_badge_class }}">
                                        <i class="mdi {{ $history->aksi_icon }}"></i>
                                        {{ $history->aksi_display }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold">{{ $history->user->name ?? 'System' }}</div>
                                    <small class="text-muted">{{ $history->user->username ?? '-' }}</small>
                                </td>
                                <td class="py-3">
                                    @if($history->alasan_perubahan)
                                        <small>{{ Str::limit($history->alasan_perubahan, 50) }}</small>
                                    @else
                                        <small class="text-muted">-</small>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center">
                                    <i class="mdi mdi-history text-light" style="font-size: 48px;"></i>
                                    <p class="text-muted mt-2">Belum ada riwayat perubahan</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .statistics-details { 
        border-bottom: 1px solid #eee; 
        padding-bottom: 20px; 
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
</style>
@endpush
@endsection