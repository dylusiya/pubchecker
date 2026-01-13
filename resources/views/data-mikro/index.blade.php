@extends('layouts.admin')

@section('title', 'Data Mikro')

@section('content')
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card card-rounded">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="card-title card-title-dash">Data Mikro BPS Kalsel</h4>
                        <p class="card-subtitle card-subtitle-dash">Daftar data mikro yang tersedia</p>
                    </div>
                    @if(auth()->user()->isAdmin())
                    <div>
                        <a href="{{ route('data-mikro.create') }}" class="btn btn-primary btn-lg text-white mb-0 me-0">
                            <i class="mdi mdi-plus"></i> Tambah Data Mikro
                        </a>
                    </div>
                    @endif
                </div>
                
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <i class="mdi mdi-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="mdi mdi-alert-circle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                
                <!-- Filter & Search -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Cari data..." id="searchInput">
                    </div>
                </div>
                
                <div class="table-responsive mt-4">
                    <table class="table select-table">
                        <thead>
                            <tr>
                                <th>Nama Data</th>
                                <th>Survei</th>
                                <th>Wilayah</th>
                                <th>Tahun</th>
                                @if(auth()->user()->isAdmin())
                                <th>User Akses</th>
                                @endif
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dataMikro as $data)
                            <tr>
                                <td>
                                    <h6>{{ $data->nama_data }}</h6>
                                    <p class="text-muted small mb-0">
                                        <i class="mdi mdi-account-circle text-primary"></i>
                                        {{ $data->creator->name ?? 'Unknown' }}
                                    </p>
                                </td>
                                <td>
                                    @if($data->survei)
                                        <span class="badge badge-opacity-info">{{ $data->survei->kode }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($data->wilayah)
                                        @if($data->wilayah->jenis == 'provinsi')
                                            <span class="badge badge-opacity-warning">{{ $data->wilayah->nama }}</span>
                                        @elseif($data->wilayah->jenis == 'kota')
                                            <span class="badge badge-opacity-success">Kota {{ $data->wilayah->nama }}</span>
                                        @else
                                            <span class="badge badge-opacity-primary">Kab. {{ $data->wilayah->nama }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-opacity-primary">{{ $data->tahun }}</span>
                                </td>
                                @if(auth()->user()->isAdmin())
                                <td>
                                    <span class="badge badge-opacity-success">
                                        <i class="mdi mdi-account-multiple"></i> {{ $data->assignedUsers->count() }}
                                    </span>
                                </td>
                                @endif
                                <td>
                                    <div class="d-flex">
                                        <a href="{{ route('data-mikro.show', $data->id) }}" 
                                           class="btn btn-sm btn-info text-white me-2"
                                           title="Lihat Detail">
                                            <i class="mdi mdi-eye"></i>
                                        </a>
                                        
                                        @if(auth()->user()->isAdmin())
                                        <a href="{{ route('data-mikro.edit', $data->id) }}" 
                                           class="btn btn-sm btn-primary text-white me-2"
                                           title="Edit">
                                            <i class="mdi mdi-pencil"></i>
                                        </a>
                                        <form action="{{ route('data-mikro.destroy', $data->id) }}" 
                                              method="POST" 
                                              class="d-inline" 
                                              onsubmit="return confirm('Yakin ingin menghapus data ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="btn btn-sm btn-danger text-white"
                                                    title="Hapus">
                                                <i class="mdi mdi-delete"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ auth()->user()->isAdmin() ? 6 : 5 }}" class="text-center py-5">
                                    <i class="mdi mdi-database-remove" style="font-size: 48px; color: #ccc;"></i>
                                    <p class="text-muted mt-2">Belum ada data mikro</p>
                                    @if(auth()->user()->isAdmin())
                                    <a href="{{ route('data-mikro.create') }}" class="btn btn-primary mt-2">
                                        <i class="mdi mdi-plus"></i> Tambah Data Mikro
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    {{ $dataMikro->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Simple search
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
</script>
@endpush