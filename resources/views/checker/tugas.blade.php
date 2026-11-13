@extends('layouts.app')

@php
    $bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $STATUS    = \App\Http\Controllers\PenugasanController::STATUS;
    $param     = array_filter(['bulan' => $bulan, 'tahun' => $tahun, 'status' => $status, 'wilayah' => $wilayah, 'pemeriksa' => $pemeriksa ? $pemeriksaId : null]);
    // nama wilayah dari kode domain BPS (6301 → Tanah Laut); kosong = wilayah tidak tersimpan
    $namaWilayah = fn($kode) => $kode === '' || $kode === null ? 'Wilayah tidak diketahui'
        : $kode . ' - ' . (\App\Services\SipotretService::WILAYAH[(int) $kode] ?? 'Domain ' . $kode);
    $perWilayah  = $baris->countBy(fn($r) => (string) $r['kartu']['wilayah']);
    $filter    = fn(array $ubah) => route('checker.tugas', array_filter(array_merge($param, $ubah)));
    $tahapSipotret = [
        'bersih'   => ['bg-green-lt',  'Tidak ada temuan'],
        'belum'    => ['bg-red-lt',    'Belum upload perbaikan'],
        'menunggu' => ['bg-yellow-lt', 'Menunggu approve'],
        'approve'  => ['bg-green-lt',  'Sudah approve'],
    ];
    $judulHalaman = $pemeriksa ? 'Tugas Pemeriksaan' : 'Tugas Saya';
    // periode Oktober diperiksa di bulan November
    $namaPeriode  = $bulanNama[$bulan] . ' ' . $tahun;
    $namaPeriksa  = $bulanNama[$waktuPeriksa->month] . ' ' . $waktuPeriksa->year;
@endphp

@section('title', $judulHalaman)
@section('pretitle', 'Penugasan Pemeriksaan')
@section('page-title', $judulHalaman . ': Periode ' . $namaPeriode)
@section('page-subtitle', ($pemeriksa ? 'Publikasi yang ditugaskan ke pemeriksa' : 'Publikasi yang ditugaskan ke Anda')
    . " beserta progres pemeriksaan periode {$namaPeriode} (diperiksa pada {$namaPeriksa})")

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Filter --}}
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small" for="fBulan">Periode</label>
                        <select name="bulan" id="fBulan" class="form-select form-select-sm" onchange="this.form.submit()">
                            @foreach(range(1, 12) as $b)
                                <option value="{{ $b }}" @selected($b === $bulan)>{{ $bulanNama[$b] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small" for="fTahun">Tahun</label>
                        <select name="tahun" id="fTahun" class="form-select form-select-sm" onchange="this.form.submit()">
                            @for($t = date('Y') + 1; $t >= 2024; $t--)
                                <option value="{{ $t }}" @selected($t === $tahun)>{{ $t }}</option>
                            @endfor
                        </select>
                    </div>
                    @if($pemeriksa)
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small" for="fPemeriksa">Pemeriksa</label>
                            <select name="pemeriksa" id="fPemeriksa" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua pemeriksa</option>
                                @foreach($pemeriksa as $u)
                                    <option value="{{ $u['id'] }}" @selected($u['id'] === $pemeriksaId)>{{ $u['nama'] }}{{ $u['id'] === auth()->id() ? ' (saya)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if(count($wilayahAda) > 1 || $wilayah)
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small" for="fWilayah">Wilayah</label>
                            <select name="wilayah" id="fWilayah" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua wilayah</option>
                                @foreach($wilayahAda as $kode)
                                    <option value="{{ $kode }}" @selected((string) $wilayah === $kode)>{{ $namaWilayah($kode) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                    <div class="col-md-auto">
                        <a href="{{ $filter($periodeKini) }}" class="btn btn-sm" title="Periode bulan lalu, yang diperiksa bulan ini">
                            <i class="ti ti-calendar-event"></i> Periode terkini
                        </a>
                    </div>
                    <div class="col-12 col-md">
                        <small class="text-secondary">
                            <i class="ti ti-info-circle"></i> Periode <strong>{{ $namaPeriode }}</strong> diperiksa pada <strong>{{ $namaPeriksa }}</strong>
                            dan dikirim ke SIPOTRET sebagai periode {{ $namaPeriode }}.
                        </small>
                    </div>
                </form>
            </div>
        </div>

        {{-- Ringkasan bulan terpilih --}}
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg"><x-stat label="Total Tugas" :value="$jumlah['semua']" icon="user-check" color="blue" sub="Publikasi ditugaskan" /></div>
            <div class="col-sm-6 col-lg"><x-stat label="Belum Diperiksa" :value="$jumlah['belum']" icon="circle-dashed" color="red" :sub="'Periode ' . $namaPeriode" /></div>
            <div class="col-sm-6 col-lg"><x-stat label="Tinjauan Berjalan" :value="$jumlah['tinjau']" icon="clipboard" color="yellow" sub="Belum semua kriteria" /></div>
            <div class="col-sm-6 col-lg"><x-stat label="Siap Dikirim" :value="$jumlah['selesai']" icon="clipboard-check" color="azure" sub="Tinjauan selesai" /></div>
            <div class="col-sm-6 col-lg"><x-stat label="Selesai" :value="$jumlah['terkirim'] + $jumlah['sipotret'] + $jumlah['ok']" icon="send" color="green"
                :sub="implode(', ', array_filter([$jumlah['terkirim'] ? $jumlah['terkirim'] . ' terkirim' : null, $jumlah['sipotret'] ? $jumlah['sipotret'] . ' dicatat di SIPOTRET' : null, $jumlah['ok'] ? $jumlah['ok'] . ' sudah sesuai' : null])) ?: 'Terkirim / sudah sesuai'" /></div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">
                        Daftar Tugas
                        <span class="badge bg-primary ms-1">{{ $baris->count() }}</span>
                    </h5>
                    @if($baris->isNotEmpty())
                        <button type="button" class="btn btn-sm btn-ghost-secondary ms-auto" onclick="toggleSemuaDetail(this)">
                            <i class="ti ti-list-details"></i> <span>Tampilkan semua catatan</span>
                        </button>
                    @endif
                    <div class="btn-group btn-group-sm">
                        <a href="{{ $filter(['status' => null]) }}" class="btn {{ !$status ? 'btn-primary' : '' }}">Semua {{ $jumlah['semua'] }}</a>
                        @foreach($STATUS as $kunci => [$label])
                            <a href="{{ $filter(['status' => $kunci]) }}" class="btn {{ $status === $kunci ? 'btn-primary' : '' }}">{{ $label }} {{ $jumlah[$kunci] }}</a>
                        @endforeach
                    </div>
                </div>

                @if($baris->isNotEmpty())
                    {{-- sama dengan Publikasi Bermasalah: periksa sekaligus, approve, (admin) tugaskan; periodenya tetap --}}
                    @include('checker.partials.pub-pilih-bar')
                @endif

                @php $wilayahSebelum = null; @endphp
                @forelse($baris as $i => $r)
                    @php
                        $t = $r['tugas'];
                        $kodeWilayah = (string) $r['kartu']['wilayah'];
                        [$labelStatus, $warnaStatus, $ikonStatus] = $STATUS[$r['status']];
                        $progres = [
                            'status'         => $r['status'],
                            'label'          => $labelStatus,
                            'warna'          => $warnaStatus,
                            'ikon'           => $ikonStatus,
                            'terkirim'       => $r['terkirim']?->sipotret_terkirim_at?->format('d M Y H:i'),
                            'hasil'          => $r['hasil'],
                            'jumlah'         => $r['jumlah'],
                            'terakhir'       => $r['terakhir'],
                            'lalu'           => $r['lalu'],
                            'sesuaiDiperiksa' => $r['sesuaiDiperiksa'] ?? false,
                            'sipotret'       => $sipotretAda ? $r['sipotret'] : null,
                            'nama_pemeriksa' => $pemeriksa ? $t->user?->full_name : null,
                            'ditugaskan'     => 'Ditugaskan ' . $t->updated_at?->format('d M Y') . ($t->penugas ? ' oleh ' . $t->penugas->full_name : ''),
                            'bulan'          => $bulan,
                            'tahun'          => $tahun,
                            'nama_periode'   => $namaPeriode,
                            'nama_periksa'   => $namaPeriksa,
                            'bulan_periksa'  => $bulanNama[$waktuPeriksa->month],
                        ];
                    @endphp

                    {{-- judul kelompok per wilayah --}}
                    @if($kodeWilayah !== $wilayahSebelum)
                        @php $wilayahSebelum = $kodeWilayah; @endphp
                        <div class="d-flex align-items-center gap-2 {{ $i ? 'mt-4' : '' }} mb-2 pb-1 border-bottom pub-grup-wilayah">
                            <i class="ti ti-map-pin text-primary"></i>
                            <strong>{{ $namaWilayah($kodeWilayah) }}</strong>
                            <span class="badge bg-primary-lt">{{ $perWilayah[$kodeWilayah] ?? 0 }} publikasi</span>
                        </div>
                    @endif

                    @include('checker.partials.pub-card', ['p' => $r['kartu'], 'progres' => $progres])
                @empty
                    <div class="text-center py-4 text-muted">
                        <i class="ti ti-user-check d-block mb-2" style="font-size:36px;opacity:.3;"></i>
                        {{ $status ? 'Tidak ada tugas dengan status ini.' : 'Belum ada publikasi yang ditugaskan.' }}
                        @if(auth()->user()->isAdmin())
                            <div class="small mt-1">Tugaskan publikasi dari Import API BPS, Publikasi Bermasalah, atau detail publikasi.</div>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        @if($baris->isNotEmpty())
            @include('checker.partials.pub-batch')
        @endif

    </div>
</div>
@endsection

@if($baris->isNotEmpty())
    {{-- isian awal tinjauan = catatan SIPOTRET acuan (sebelum periode ini); hasil dikirim ke SIPOTRET periode halaman ini --}}
    @include('checker.partials.pub-scripts', [
        'items' => $baris->map(fn($r) => [
            'pub_id'     => $r['kartu']['pub_id_api'],
            'title'      => $r['kartu']['judul'],
            'history_id' => $r['kartu']['history_id'],
            'tahap'      => $r['kartu']['tahap'],
            'file_url'   => $r['kartu']['file_rev'] ? route('dashboard.sipotret.file', $r['kartu']['history_id']) : null,
            'tugas'      => $tugasRingkas[$r['tugas']->pub_id_api] ?? null,
            'domain'     => (string) $r['kartu']['wilayah'],
            'diubah_at'  => $r['kartu']['file_rev'] ? ($r['kartu']['diubah_at'] ?? null) : null, // unggah file perbaikan di SIPOTRET (urut revisi terakhir)
        ])->all(),
        'periodeTetap' => ['bulan' => $bulan, 'tahun' => $tahun],
        'ulangSetelahTugaskan' => true,
    ])
@endif
