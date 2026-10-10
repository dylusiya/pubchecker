@extends('layouts.app')

@section('title', 'Manajemen User')
@section('pretitle', 'Administrator')
@section('page-title', 'Manajemen User')
@section('page-subtitle', 'Kelola data pengguna aplikasi')
@section('page-actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="ti ti-user-plus"></i> Tambah User
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="row row-cards">
            @foreach([
                ['Total User',   $users->total(),       'users-group',  'blue',   'Terdaftar'],
                ['Admin',        $stats['admin'] ?? 0,  'shield-check', 'yellow', 'Administrator'],
                ['Regular User', $stats['user'] ?? 0,   'user',         'green',  'Operator'],
            ] as [$label, $value, $icon, $color, $sub])
            <div class="col-sm-6 col-lg-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-{{ $color }} text-white avatar"><i class="ti ti-{{ $icon }} fs-2"></i></span>
                            </div>
                            <div class="col">
                                <div class="fw-medium">{{ number_format($value, 0, ',', '.') }} {{ $label }}</div>
                                <div class="text-secondary">{{ $sub }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-center mb-3">
                    <h3 class="card-title mb-0">Daftar Pengguna</h3>
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
                                <i class="ti ti-search"></i> Filter
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
                                        Username @if(request('sort') == 'username') <i class="ti ti-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i> @endif
                                    </a>
                                </th>
                                <th class="py-3">
                                    <a href="{{ route('admin.users.index', array_merge(request()->all(), ['sort' => 'name', 'order' => request('sort') == 'name' && request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark">
                                        Nama @if(request('sort') == 'name') <i class="ti ti-chevron-{{ request('order') == 'asc' ? 'up' : 'down' }}"></i> @endif
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
                                            <span class="avatar avatar-sm me-2" style="background-image: url('{{ $user->foto }}')"></span>
                                        @else
                                            <span class="avatar avatar-sm me-2 bg-primary-lt">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
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
                                        <span class="badge bg-yellow-lt">Admin</span>
                                    @else
                                        <span class="badge bg-green-lt">User</span>
                                    @endif
                                </td>
                                @if(auth()->check() && auth()->user()->role === 'admin')
                                <td class="py-3 text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-outline-primary btn-sm btn-icon" title="Edit">
                                            <i class="ti ti-pencil"></i>
                                        </a>
                                        @if($user->id !== auth()->id())
                                        <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm btn-icon" title="Hapus">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                        @else
                                        <button class="btn btn-outline-secondary btn-sm btn-icon" disabled title="Tidak bisa hapus diri sendiri">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ auth()->check() && auth()->user()->role === 'admin' ? '5' : '4' }}" class="py-5 text-center">
                                    <i class="ti ti-user-off text-light mb-3" style="font-size: 60px;"></i>
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
@endsection