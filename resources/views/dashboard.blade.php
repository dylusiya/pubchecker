@extends('layouts.admin')

@section('title', 'Dashboard Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-sm-12">
        <!-- Status Daerah Sulit Terbaru -->
        <div class="card card-rounded">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="card-title card-title-dash">Status Daerah Sulit Terbaru</h4>
                        <p class="card-subtitle card-subtitle-dash">Data yang baru ditambahkan atau diupdate</p>
                    </div>
                    <div>
                        <a href="{{ route('daerah-sulit.index') }}" class="btn btn-primary text-white btn-lg">
                            <i class="mdi mdi-database"></i> Lihat Semua Data
                        </a>
                    </div>
                </div>
                
                <div class="table-responsive mt-4">
                    <table class="table select-table">
                        <thead>
                            <tr>
                                <th>ID SLS</th>
                                <th>Nama SLS</th>
                                <th>Wilayah</th>
                                <th>Status</th>
                                <th>Biaya</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $tahunAktif = date('Y');
                                $recentData = \App\Models\StatusDaerahSulit::with(['masterSls'])
                                    ->where('tahun_anggaran', $tahunAktif)
                                    ->whereNull('deleted_at')
                                    ->latest('updated_at')
                                    ->take(10)
                                    ->get();
                            @endphp
                            
                            @forelse($recentData as $data)
                            <tr>
                                <td>
                                    <h6>{{ $data->masterSls->idsls }}</h6>
                                    <small class="text-muted">
                                        <i class="mdi mdi-clock-outline"></i> 
                                        {{ $data->updated_at->diffForHumans() }}
                                    </small>
                                </td>
                                <td>
                                    <div>{{ $data->masterSls->nmsls }}</div>
                                    @if($data->masterSls->nama_ketua ?? false)
                                    <small class="text-muted">Ketua: {{ $data->masterSls->nama_ketua }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-muted small">
                                        {{ $data->masterSls->nmkab }}<br>
                                        Kec. {{ $data->masterSls->nmkec }}<br>
                                        {{ $data->masterSls->nmdesa }}
                                    </div>
                                </td>
                                <td>
                                    @if($data->is_daerah_sulit)
                                        <span class="badge badge-opacity-danger">
                                            <i class="mdi mdi-alert-circle"></i> Daerah Sulit
                                        </span>
                                    @else
                                        <span class="badge badge-opacity-success">
                                            <i class="mdi mdi-check-circle"></i> Tidak Sulit
                                        </span>
                                    @endif
                                    <br>
                                    @if($data->status_approval == 'disetujui')
                                        <span class="badge badge-opacity-success mt-1">
                                            <i class="mdi mdi-check"></i> Disetujui
                                        </span>
                                    @elseif($data->status_approval == 'ditolak')
                                        <span class="badge badge-opacity-danger mt-1">
                                            <i class="mdi mdi-close"></i> Ditolak
                                        </span>
                                    @elseif($data->status_approval == 'pending')
                                        <span class="badge badge-opacity-warning mt-1">
                                            <i class="mdi mdi-clock"></i> Pending
                                        </span>
                                    @else
                                        <span class="badge badge-opacity-secondary mt-1">
                                            <i class="mdi mdi-file"></i> Draft
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($data->is_daerah_sulit && $data->perkiraan_biaya > 0)
                                        <strong>Rp {{ number_format($data->perkiraan_biaya, 0, ',', '.') }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $data->metode_transportasi }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('daerah-sulit.show', $data->id) }}" 
                                       class="btn btn-sm btn-info text-white">
                                        <i class="mdi mdi-eye"></i> Lihat
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="mdi mdi-map-marker-off" style="font-size: 48px; color: #ccc;"></i>
                                    <p class="text-muted mt-2">Belum ada data status daerah sulit untuk tahun {{ $tahunAktif }}</p>
                                    <a href="{{ route('daerah-sulit.create') }}" class="btn btn-primary mt-2">
                                        <i class="mdi mdi-plus"></i> Tambah Status Daerah Sulit
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
        <div class="row mt-4">
            <div class="col-12">
                <div class="card card-rounded">
                    <div class="card-body">
                        <div class="d-sm-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h4 class="card-title card-title-dash">Riwayat Perubahan Terbaru</h4>
                                <p class="card-subtitle card-subtitle-dash">10 aktivitas terakhir</p>
                            </div>
                            <div>
                                <a href="{{ route('daerah-sulit.history') }}" class="btn btn-outline-primary">
                                    Lihat Semua <i class="mdi mdi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>SLS</th>
                                        <th>Aksi</th>
                                        <th>User</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $historyRecent = \App\Models\HistoryStatusDaerahSulit::with(['masterSls', 'user'])
                                            ->latest('created_at')
                                            ->take(10)
                                            ->get();
                                    @endphp
                                    
                                    @forelse($historyRecent as $history)
                                    <tr>
                                        <td>
                                            <small class="text-muted">
                                                {{ $history->created_at->format('d/m/Y H:i') }}
                                                <br>
                                                <i class="mdi mdi-clock-outline"></i> {{ $history->created_at->diffForHumans() }}
                                            </small>
                                        </td>
                                        <td>
                                            <strong>{{ $history->masterSls->idsls }}</strong>
                                            <br>
                                            <small class="text-muted">{{ Str::limit($history->masterSls->nmsls, 30) }}</small>
                                        </td>
                                        <td>
                                            <span class="badge {{ $history->aksi_badge_class }}">
                                                <i class="mdi {{ $history->aksi_icon }}"></i>
                                                {{ $history->aksi_display }}
                                            </span>
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
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <i class="mdi mdi-history" style="font-size: 48px; color: #ccc;"></i>
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
    </div>
</div>
@endsection