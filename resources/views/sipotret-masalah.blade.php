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

            {{-- Daftar publikasi: filter satu baris di atas, status = tombol berjumlah (seperti Tugas Saya) --}}
            <div class="card mb-3">
                <form method="get" class="card-body border-bottom py-2 d-flex flex-wrap align-items-center gap-2">
                    <select class="form-select form-select-sm" style="width:auto;" onchange="location = this.value" title="Kab/Kota">
                        @foreach(\App\Services\SipotretService::WILAYAH as $kode => $n)
                            <option value="{{ route('dashboard.sipotret', ['wilayah' => $kode] + $param) }}" @selected($kode == $wilayah)>{{ $kode }} - {{ $n }}</option>
                        @endforeach
                    </select>
                    <select name="bulan" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()" title="Periode">
                        @foreach(range(1, 12) as $b)
                            <option value="{{ $b }}" @selected($b == $bulan)>{{ $bulanNama[$b] }}</option>
                        @endforeach
                    </select>
                    <select name="tahun" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        @for($t = date('Y'); $t >= 2024; $t--)
                            <option value="{{ $t }}" @selected($t == $tahun)>{{ $t }}</option>
                        @endfor
                    </select>
                    {{-- penugasan: pilihan ringkas --}}
                    <select class="form-select form-select-sm" style="width:auto;" onchange="location = this.value" title="Penugasan">
                        <option value="{{ $filter(['tugas' => null]) }}" @selected(!$filterTugas)>Semua tugas</option>
                        <option value="{{ $filter(['tugas' => 'saya']) }}" @selected($filterTugas === 'saya')>Tugas saya ({{ $jumlahTugas['saya'] }})</option>
                        <option value="{{ $filter(['tugas' => 'belum']) }}" @selected($filterTugas === 'belum')>Belum ditugaskan ({{ $jumlahTugas['belum'] }})</option>
                    </select>
                    @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                    @if($filterTugas)<input type="hidden" name="tugas" value="{{ $filterTugas }}">@endif
                    <div class="btn-group btn-group-sm ms-md-auto">
                        <a href="{{ $filter(['status' => null]) }}" class="btn {{ !$status ? 'btn-primary' : '' }}">Semua {{ $jumlah['semua'] }}</a>
                        <a href="{{ $filter(['status' => 'belum']) }}" class="btn {{ $status === 'belum' ? 'btn-primary' : '' }}">Belum upload {{ $jumlah['belum'] }}</a>
                        <a href="{{ $filter(['status' => 'menunggu']) }}" class="btn {{ $status === 'menunggu' ? 'btn-primary' : '' }}">Menunggu approve {{ $jumlah['menunggu'] }}</a>
                        <a href="{{ $filter(['status' => 'approve']) }}" class="btn {{ $status === 'approve' ? 'btn-primary' : '' }}">Approve {{ $jumlah['approve'] }}</a>
                    </div>
                </form>
                <div class="card-body">
                    @if($error)
                        <div class="alert alert-warning mb-0">{{ $error }}</div>
                    @else
                    @if($daftar)
                        <div class="d-flex mb-2">
                            <button type="button" class="btn btn-sm btn-ghost-secondary ms-auto" onclick="toggleSemuaDetail(this)" title="Buka / tutup semua catatan">
                                <i class="ti ti-list-details"></i> <span>Tampilkan semua catatan</span>
                            </button>
                        </div>
                    @endif

                    @if($daftar)
                        {{-- Pilihan beberapa publikasi untuk diperiksa sekaligus / approve / ditugaskan (komponen bersama Tugas Saya) --}}
                        @include('checker.partials.pub-pilih-bar', [
                            'periodePilih' => $periodeBaru,
                            'periodeInfo'  => 'hasil dikirim ke SIPOTRET pada periode ini',
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
                    @endif
                </div>
            </div>

            @if(!$error)
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
