@extends('layouts.app')

@section('title', 'Manajemen User')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin">
        <div class="row">
            <div class="col-sm-12">
                <div class="statistics-details d-flex align-items-center justify-content-start gap-5">
                    <div>
                        <p class="statistics-title">Total User</p>
                        <h3 class="rate-percentage text-info">{{ number_format($users->total(), 0, ',', '.') }}</h3>
                        <p class="text-info d-flex small fw-bold"><i class="mdi mdi-account-group me-1"></i>Terdaftar</p>
                    </div>
                    <div>
                        <p class="statistics-title">Admin</p>
                        <h3 class="rate-percentage text-warning">{{ number_format($stats['admin'] ?? 0, 0, ',', '.') }}</h3>
                        <p class="text-warning d-flex small fw-bold"><i class="mdi mdi-shield-check me-1"></i>Administrator</p>
                    </div>
                    <div>
                        <p class="statistics-title">Regular User</p>
                        <h3 class="rate-percentage text-success">{{ number_format($stats['user'] ?? 0, 0, ',', '.') }}</h3>
                        <p class="text-success d-flex small fw-bold"><i class="mdi mdi-account me-1"></i>Operator</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-rounded mt-3">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title card-title-dash">Manajemen User</h4>
                        <p class="card-subtitle card-subtitle-dash">Kelola data pengguna aplikasi</p>
                    </div>
                    @if(auth()->check() && auth()->user()->role === 'admin')
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm text-white">
                            <i class="mdi mdi-account-plus"></i> Tambah User
                        </a>
                    </div>
                    @endif
                </div>

                <!-- Filter -->
                <div class="bg-light p-3 rounded mb-4 border">
                    <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Role</label>
                            <select name="role" class="form-select form-select-sm text-dark">
                                <option value="">-- Semua Role --</option>
                                <option value="admin" {{ request('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                                <option value="user" {{ request('role') == 'user' ? 'selected' : '' }}>User</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Cari User</label>
                            <input type="text" name="search" class="form-control form-control-sm" 
                                   placeholder="Cari Username, Nama, Email, atau NIP..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                <i class="mdi mdi-magnify"></i> Filter
                            </button>
                            @if(request('search') || request('role'))
                                <a href="{{ route('admin.users.index') }}" class="btn btn-light btn-sm border px-3">Reset</a>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Per Page -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <form method="GET" action="{{ route('admin.users.index') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="role" value="{{ request('role') }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                        <input type="hidden" name="order" value="{{ request('order') }}">
                        
                        <span class="text-dark small fw-bold">Tampilkan:</span>
                        <select name="per_page" class="form-select form-select-sm" style="width: 150px;" onchange="this.form.submit()">
                            <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20 per halaman</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 per halaman</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 per halaman</option>
                            <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>Semua Data</option>
                        </select>
                    </form>
                    
                    <div class="text-muted small">
                        Menampilkan 
                        <strong>{{ $users->firstItem() ?? 0 }}</strong> sampai 
                        <strong>{{ $users->lastItem() ?? 0 }}</strong> dari 
                        <strong>{{ $users->total() }}</strong> data
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr class="bg-light">
                                <th class="py-3">
                                    <a href="{{ route('admin.users.index', array_merge(request()->all(), ['sort' => 'username', 'order' => request('sort') == 'username' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Username @if(request('sort') == 'username') <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i> @endif
                                    </a>
                                </th>
                                <th class="py-3">
                                    <a href="{{ route('admin.users.index', array_merge(request()->all(), ['sort' => 'name', 'order' => request('sort') == 'name' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Nama @if(request('sort') == 'name') <i class="mdi mdi-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i> @endif
                                    </a>
                                </th>
                                <th class="py-3">Info</th>
                                <th class="py-3 text-center">Role</th>
                                @if(auth()->check() && auth()->user()->role === 'admin')
                                <th class="py-3 text-center">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                            <tr>
                                <td class="py-3">
                                    <strong class="text-dark">{{ $user->username }}</strong>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        @if($user->foto)
                                            <img src="{{ $user->foto }}" alt="" class="me-2 avatar-img">
                                        @else
                                            <div class="bg-primary rounded-circle me-2 d-flex align-items-center justify-content-center avatar-placeholder">
                                                <span class="text-white fw-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="fw-bold">{{ $user->name }}</div>
                                            <div class="small text-muted">{{ $user->jabatan ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="small">{{ $user->email ?? '-' }}</div>
                                    <div class="small text-muted">NIP: {{ $user->nip_baru ?? $user->nip ?? '-' }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    @if($user->role === 'admin')
                                        <span class="badge badge-warning">Admin</span>
                                    @else
                                        <span class="badge badge-success">User</span>
                                    @endif
                                </td>
                                @if(auth()->check() && auth()->user()->role === 'admin')
                                <td class="py-3 text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-outline-primary btn-xs" title="Edit">
                                            <i class="mdi mdi-pencil"></i>
                                        </a>
                                        @if($user->id !== auth()->id())
                                        <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-xs" title="Hapus">
                                                <i class="mdi mdi-delete"></i>
                                            </button>
                                        </form>
                                        @else
                                        <button class="btn btn-outline-secondary btn-xs" disabled title="Tidak bisa hapus diri sendiri">
                                            <i class="mdi mdi-delete"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ auth()->check() && auth()->user()->role === 'admin' ? '5' : '4' }}" class="py-5 text-center">
                                    <i class="mdi mdi-account-off text-light mb-3" style="font-size: 60px;"></i>
                                    <h5 class="text-muted fw-normal">Data user tidak ditemukan</h5>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($users->hasPages())
                <div class="mt-4">
                    {{ $users->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .table td { vertical-align: middle !important; font-size: 0.875rem !important; }
    .table th { font-size: 0.875rem !important; font-weight: 600 !important; }
    .badge { font-weight: 600; font-size: 0.75rem; padding: 0.35em 0.65em; }
    .btn-xs { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
    .statistics-details { border-bottom: 1px solid #eee; padding-bottom: 20px; }
    .avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
    .avatar-placeholder { width: 32px; height: 32px; font-size: 14px; }
    .form-select, .form-control { color: #495057 !important; font-size: 0.875rem !important; }
</style>
@endpush
@endsection