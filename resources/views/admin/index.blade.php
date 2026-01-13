@extends('layouts.admin')

@section('title', 'Manajemen User')

@section('content')
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card card-rounded">
            <div class="card-body">
                <div class="d-sm-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="card-title card-title-dash">Manajemen User</h4>
                        <p class="card-subtitle card-subtitle-dash">Kelola user dan hak akses aplikasi</p>
                    </div>
                    <div>
                        <a href="{{ route('admin.create') }}" class="btn btn-primary btn-lg text-white mb-0 me-0">
                            <i class="mdi mdi-account-plus"></i> Tambah User
                        </a>
                    </div>
                </div>
                
                <div class="table-responsive mt-4">
                    <table class="table select-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>NIP</th>
                                <th>Role</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                            <tr>
                                <td>
                                    <h6>{{ $user->username }}</h6>
                                </td>
                                <td>
                                    <div class="d-flex">
                                        @if($user->foto)
                                            <img src="{{ $user->foto }}" alt="" class="me-2" style="width: 35px; height: 35px; border-radius: 50%;">
                                        @else
                                            <div class="bg-primary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                                <i class="mdi mdi-account text-white"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <h6>{{ $user->name }}</h6>
                                            <p class="text-muted">{{ $user->jabatan ?? '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p>{{ $user->email ?? '-' }}</p>
                                </td>
                                <td>
                                    <p>{{ $user->nip_baru ?? $user->nip ?? '-' }}</p>
                                </td>
                                <td>
                                    @if($user->role === 'admin')
                                        <div class="badge badge-opacity-warning">Admin</div>
                                    @else
                                        <div class="badge badge-opacity-success">User</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex">
                                        <a href="{{ route('admin.edit', $user->id) }}" class="btn btn-sm btn-primary text-white me-2">
                                            <i class="mdi mdi-pencil"></i>
                                        </a>
                                        @if($user->id !== auth()->id())
                                        <form action="{{ route('admin.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger text-white">
                                                <i class="mdi mdi-delete"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <p class="text-muted">Tidak ada data user</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection