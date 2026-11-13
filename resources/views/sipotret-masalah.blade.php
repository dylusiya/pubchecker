@extends('layouts.app')

@php
    $bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $param  = array_filter(['tahun' => $tahun, 'bulan' => $bulan, 'status' => $status, 'tugas' => $filterTugas ?? null]);
    $filter = fn(array $ubah) => route('dashboard.sipotret', ['wilayah' => $wilayah] + array_filter(array_merge($param, $ubah)));
    // tahap pub_file_rev_status → [warna, ikon, label]
    $tahapTampil = [
        'belum'    => ['bg-red-lt',    'x',     'Belum upload perbaikan'],
        'menunggu' => ['bg-yellow-lt', 'clock', 'Menunggu approve'],
        'approve'  => ['bg-green-lt',  'check', 'Sudah approve'],
    ];
    // kab/kota hanya konfirmasi sudah mengunggah ke portal publikasi BPS; file perbaikan hanya ada untuk provinsi
    $revLabel = ['uploaded_kabkota' => 'kab/kota konfirmasi sudah upload ke portal', 'uploaded' => 'file perbaikan diunggah'];
    $tglPendek = fn($s) => $s ? (fn($d) => $d->day . ' ' . mb_substr($bulanNama[$d->month], 0, 3) . ' ' . $d->year)(\Illuminate\Support\Carbon::parse($s)) : null;
@endphp

@section('title', "Publikasi Bermasalah: {$nama}")
@section('pretitle', 'SIPOTRET · Pasca Rilis')
@section('page-title', "Publikasi Bermasalah: {$nama}")
@section('page-subtitle', 'Publikasi yang punya catatan Pasca Rilis di SIPOTRET. Centang hingga 10 publikasi untuk diperiksa ulang sekaligus')
@section('page-actions')
    <a href="{{ route('dashboard', ['tahun' => $tahun]) }}" class="btn">
        <i class="ti ti-arrow-left"></i> Dashboard
    </a>
    <a href="{{ route('checker.riwayat') }}" class="btn btn-primary">
        <i class="ti ti-history"></i> Riwayat
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Filter --}}
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3">Filter Publikasi Bermasalah</h5>
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Kab/Kota</label>
                        <select class="form-select form-select-sm text-black" onchange="location = this.value">
                            @foreach(\App\Services\SipotretService::WILAYAH as $kode => $n)
                                <option value="{{ route('dashboard.sipotret', ['wilayah' => $kode] + $param) }}"
                                    @selected($kode == $wilayah)>{{ $kode }} - {{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">Tahun</label>
                        <select name="tahun" class="form-select form-select-sm text-black" onchange="this.form.submit()">
                            @for($t = date('Y'); $t >= 2024; $t--)
                                <option value="{{ $t }}" @selected($t == $tahun)>{{ $t }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Bulan</label>
                        <select name="bulan" class="form-select form-select-sm text-black" onchange="this.form.submit()">
                            @foreach(range(1, 12) as $b)
                                <option value="{{ $b }}" @selected($b == $bulan)>{{ $bulanNama[$b] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Status Perbaikan</label>
                        <select name="status" class="form-select form-select-sm text-black" onchange="this.form.submit()">
                            <option value="">Semua</option>
                            @foreach($tahapTampil as $kunci => [, , $label])
                                <option value="{{ $kunci }}" @selected($status === $kunci)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>

        @if($error)
            <div class="alert alert-warning">{{ $error }}</div>
        @else
            {{-- Daftar publikasi --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="card-title mb-0">
                            {{ $bulanNama[$bulan] }} {{ $tahun }}
                            <span class="badge bg-primary ms-1">{{ count($daftar) }}</span>
                        </h5>
                        @if($daftar)
                            <button type="button" class="btn btn-sm btn-ghost-secondary ms-auto" onclick="toggleSemuaDetail(this)">
                                <i class="ti ti-list-details"></i> <span>Tampilkan semua catatan</span>
                            </button>
                        @endif
                        <div class="btn-group btn-group-sm">
                            <a href="{{ $filter(['status' => null]) }}" class="btn {{ !$status ? 'btn-primary' : '' }}">Semua {{ $jumlah['semua'] }}</a>
                            <a href="{{ $filter(['status' => 'belum']) }}" class="btn {{ $status === 'belum' ? 'btn-danger' : '' }}">Belum upload {{ $jumlah['belum'] }}</a>
                            <a href="{{ $filter(['status' => 'menunggu']) }}" class="btn {{ $status === 'menunggu' ? 'btn-warning' : '' }}">Menunggu approve {{ $jumlah['menunggu'] }}</a>
                            <a href="{{ $filter(['status' => 'approve']) }}" class="btn {{ $status === 'approve' ? 'btn-success' : '' }}">Approve {{ $jumlah['approve'] }}</a>
                        </div>
                        {{-- penugasan pemeriksaan --}}
                        <div class="btn-group btn-group-sm">
                            <a href="{{ $filter(['tugas' => null]) }}" class="btn {{ !$filterTugas ? 'btn-primary' : '' }}" title="Semua penugasan">Semua tugas</a>
                            <a href="{{ $filter(['tugas' => 'saya']) }}" class="btn {{ $filterTugas === 'saya' ? 'btn-green' : '' }}">Tugas saya {{ $jumlahTugas['saya'] }}</a>
                            <a href="{{ $filter(['tugas' => 'belum']) }}" class="btn {{ $filterTugas === 'belum' ? 'btn-secondary' : '' }}">Belum ditugaskan {{ $jumlahTugas['belum'] }}</a>
                        </div>
                    </div>

                    @if($daftar)
                        {{-- Pilihan beberapa publikasi untuk diperiksa sekaligus / approve / ditugaskan (komponen bersama Tugas Saya) --}}
                        @include('checker.partials.pub-pilih-bar', [
                            'periodePilih' => $periodeBaru,
                            'periodeInfo'  => 'hasil pemeriksaan ulang dikirim ke SIPOTRET pada periode ini (bawaan: sebulan setelah periode terakhir). '
                                . 'Catatan SIPOTRET <strong>' . e($bulanNama[$bulan] . ' ' . $tahun) . '</strong> (filter di atas) jadi acuan isian awal tinjauan '
                                . 'dan disimpan di Riwayat; baru masuk SIPOTRET saat dikirim.',
                        ])
                    @endif

                    @forelse($daftar as $i => $p)
                        @include('checker.partials.pub-card', ['p' => $p + ['wilayah' => (string) $wilayah]])
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="ti ti-mood-happy d-block mb-2" style="font-size:36px;opacity:.3;"></i>
                            Tidak ada publikasi bermasalah untuk filter yang dipilih
                        </div>
                    @endforelse
                </div>
            </div>

            @include('checker.partials.pub-batch')
        @endif

    </div>
</div>
@endsection


@if(!$error && $daftar)
    {{-- Publikasi dari SIPOTRET; detail (PDF, kover, ISSN) dilengkapi dari API BPS lewat pub_id.
         file_url: file perbaikan yang diunggah di SIPOTRET, bila ada file inilah yang diperiksa (bukan PDF portal).
         isian: catatan SIPOTRET periode filter (acuan) per pub_id, periodenya ikut disimpan di hasil. --}}
    @include('checker.partials.pub-scripts', [
        'items' => array_map(fn($p) => [
            'pub_id'     => $p['pub_id_api'],
            'title'      => $p['judul'],
            'history_id' => $p['history_id'],
            'tahap'      => $p['tahap'],
            'file_url'   => $p['file_rev'] ? route('dashboard.sipotret.file', $p['history_id']) : null,
            'tugas'      => $tugas[$p['pub_id_api']] ?? null,
            'domain'     => (string) $wilayah,
            'diubah_at'  => $p['file_rev'] ? ($p['diubah_at'] ?? null) : null, // unggah file perbaikan di SIPOTRET (urut revisi terakhir)
        ], $daftar),
    ])
@endif
