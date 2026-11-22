@extends('layouts.app')

@section('title', 'Notifikasi SIPOTRET')
@section('pretitle', 'Pra Rilis')
@section('page-title', 'Riwayat Notifikasi SIPOTRET')
@section('page-subtitle', 'Notifikasi yang dikirim SIPOTRET dan pubchecker ke PJK, ketua tim, dan admin')
@section('page-actions')
    <a href="{{ route('checker.sipotret_draf.index') }}" class="btn">
        <i class="ti ti-arrow-left"></i> Pra Rilis
    </a>
@endsection

@section('content')
@if($error)<div class="alert alert-danger">{{ $error }}</div>@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Penerima</label>
                <select name="peran" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    <option value="pjk" @selected($peran === 'pjk')>PJK</option>
                    <option value="ketua" @selected($peran === 'ketua')>Ketua tim</option>
                    <option value="admin" @selected($peran === 'admin')>Admin</option>
                </select>
            </div>
            <div class="col">
                <label class="form-label small mb-1">Cari judul notifikasi / publikasi / nama penerima</label>
                <input type="text" name="q" value="{{ $cari }}" class="form-control" placeholder="mis. SPRP">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="ti ti-search me-1"></i> Cari</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ number_format($halaman->total(), 0, ',', '.') }} notifikasi</h3>
    </div>
    <div class="list-group list-group-flush">
        @forelse($halaman as $n)
            @include('checker.partials.notif-item', ['n' => $n, 'denganPub' => true])
        @empty
            <div class="list-group-item text-center text-secondary py-5">Tidak ada notifikasi.</div>
        @endforelse
    </div>
    @if($halaman->hasPages())
        <div class="card-footer d-flex justify-content-end">{{ $halaman->links() }}</div>
    @endif
</div>
@endsection
