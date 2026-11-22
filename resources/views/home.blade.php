@extends('layouts.app')

@php
    // HomeController lama (belum mengirim ringkasan pra rilis) tidak boleh membuat dashboard error 500
    $pra      ??= null;
    $praError ??= $pra ? null : 'Ringkasan pra rilis belum tersedia (HomeController di server belum diperbarui).';
    $bulanPendek = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $bisaPra     = Gate::allows('periksa') && Route::has('checker.sipotret_draf.index');
@endphp

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-actions')
    <form method="get" class="d-flex">
        <select name="tahun" class="form-select" onchange="this.form.submit()" title="Tahun">
            @for($t = date('Y') + 1; $t >= 2024; $t--)
                <option value="{{ $t }}" @selected($t == $tahun)>{{ $t }}</option>
            @endfor
        </select>
    </form>
    <a href="{{ route('laporan.arc') }}" class="btn"><i class="ti ti-report-analytics"></i> Laporan ARC</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            @if($bisaPra)
                <li class="nav-item">
                    <a href="#tabPra" class="nav-link active" data-bs-toggle="tab" role="tab"><i class="ti ti-file-pencil me-1"></i> Pra Rilis</a>
                </li>
            @endif
            <li class="nav-item">
                <a href="#tabPasca" class="nav-link {{ $bisaPra ? '' : 'active' }}" data-bs-toggle="tab" role="tab"><i class="ti ti-world-check me-1"></i> Pasca Rilis</a>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        {{-- ================= Pra rilis ================= --}}
        @if($bisaPra)
        <div class="tab-pane active show" id="tabPra" role="tabpanel">
            <div class="card-body">
                @if($praError)
                    <div class="alert alert-warning mb-0">{{ $praError }}</div>
                @else
                    @php $praUrl = fn($ubin = null) => route('checker.sipotret_draf.index', ['tahun' => $tahun]) . ($ubin ? '#' . $ubin : ''); @endphp
                    {{-- angka utama; klik = buka Pra Rilis dengan saringan yang sama --}}
                    <div class="row row-cards mb-3">
                        @foreach([
                            ['pemeriksa', 'Tugas pemeriksa', 'user-check', 'primary'],
                            ['pjk',       'Menunggu PJK',    'hourglass', 'orange'],
                            ['mepet',     'Tenggat ≤ 3 hari', 'bell-ringing', 'warning'],
                            ['telat',     'Lewat batas',     'alert-triangle', 'danger'],
                            ['selesai',   'Selesai',         'circle-check', 'success'],
                        ] as [$k, $label, $ikon, $w])
                            <div class="col-6 col-md-4 col-xl">
                                <a href="{{ $praUrl($k) }}" class="text-reset text-decoration-none">
                                    <x-stat :label="$label" :value="$pra['jumlah'][$k]" :icon="$ikon" :color="$w" class="card-link" />
                                </a>
                            </div>
                        @endforeach
                    </div>

                    <div class="row row-cards">
                        {{-- tenggat terdekat --}}
                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Tenggat terdekat</h3>
                                    <div class="card-actions"><a href="{{ $praUrl() }}" class="btn btn-sm btn-ghost-primary">Semua {{ $pra['total'] }}</a></div>
                                </div>
                                <div class="list-group list-group-flush list-group-hoverable">
                                    @forelse($pra['terdekat'] as $r)
                                        @php $tk = $r['tenggat']; @endphp
                                        <a href="{{ route('checker.sipotret_draf.show', $r['pub_id']) }}" class="list-group-item list-group-item-action">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="badge bg-{{ $tk['level'] }} text-white" style="min-width:4.5rem;">
                                                    {{ $tk['sisa'] < 0 ? abs($tk['sisa']) . ' hr lewat' : ($tk['sisa'] === 0 ? 'hari ini' : $tk['sisa'] . ' hari') }}
                                                </span>
                                                <div class="flex-fill text-truncate">
                                                    <div class="text-body text-truncate">{{ $r['judul'] }}</div>
                                                    <div class="small text-secondary">{{ $tk['label'] }} · {{ \Carbon\Carbon::parse($tk['tgl'])->locale('id')->translatedFormat('D, d M') }}</div>
                                                </div>
                                            </div>
                                        </a>
                                    @empty
                                        <div class="list-group-item text-secondary text-center py-4"><i class="ti ti-mood-happy me-1"></i> Tidak ada tenggat berjalan</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        {{-- rencana vs sudah rilis per bulan --}}
                        <div class="col-lg-5">
                            <div class="card">
                                <div class="card-header"><h3 class="card-title">Rilis per bulan</h3></div>
                                <div class="card-body">
                                    @foreach($pra['perBulan'] as $b => $j)
                                        @php $pct = $j['rencana'] ? round($j['rilis'] / $j['rencana'] * 100) : 0; @endphp
                                        <div class="row g-2 align-items-center mb-1 {{ $j['rencana'] ? '' : 'opacity-50' }}">
                                            <div class="col-2 small">{{ $bulanPendek[$b] }}</div>
                                            <div class="col">
                                                <div class="progress progress-sm" title="{{ $j['rilis'] }} dari {{ $j['rencana'] }} sudah rilis">
                                                    <div class="progress-bar bg-success" style="width: {{ $pct }}%"></div>
                                                </div>
                                            </div>
                                            <div class="col-3 small text-end text-secondary">{{ $j['rencana'] ? $j['rilis'] . '/' . $j['rencana'] : '-' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ================= Pasca rilis ================= --}}
        <div class="tab-pane {{ $bisaPra ? '' : 'active show' }}" id="tabPasca" role="tabpanel">
            @if($rekapError)
                <div class="card-body"><div class="alert alert-warning mb-0">{{ $rekapError }}</div></div>
            @else
                @php
                    $wilayah = \App\Services\SipotretService::WILAYAH
                        + collect(array_keys($rekap))->mapWithKeys(fn($k) => [$k => "Domain {$k}"])->all();
                    $jumlah = fn(array $sel) => [
                        'masalah'    => array_sum(array_column($sel, 'masalah')),
                        'menunggu'   => array_sum(array_column($sel, 'menunggu')),
                        'diperbaiki' => array_sum(array_column($sel, 'diperbaiki')),
                    ];
                    // hijau: semua approve · kuning: ada perbaikan menunggu approve · merah: belum ada perbaikan
                    $kelas = fn($s) => $s['diperbaiki'] >= $s['masalah'] ? 'bg-green-lt' : ($s['menunggu'] > 0 ? 'bg-yellow-lt' : 'bg-red-lt');
                    $perBulan = [];
                    foreach (range(1, 12) as $b) {
                        $perBulan[$b] = $jumlah(array_filter(array_map(fn($r) => $r[$b] ?? null, $rekap)));
                    }
                    $total = $jumlah($perBulan);
                    $belumTotal = $total['masalah'] - $total['diperbaiki'] - $total['menunggu'];
                @endphp
                <div class="card-body">
                    <div class="row row-cards">
                        <div class="col-6 col-md-3"><x-stat label="Bermasalah" :value="$total['masalah']" icon="alert-circle" color="blue" /></div>
                        <div class="col-6 col-md-3"><x-stat label="Sudah approve" :value="$total['diperbaiki']" icon="circle-check" color="green" /></div>
                        <div class="col-6 col-md-3"><x-stat label="Menunggu approve" :value="$total['menunggu']" icon="clock" color="yellow" /></div>
                        <div class="col-6 col-md-3"><x-stat label="Belum upload" :value="$belumTotal" icon="x" color="red" /></div>
                    </div>
                </div>
                {{-- per kab/kota per bulan: sel = approve / bermasalah --}}
                <div class="table-responsive border-top">
                    <table class="table card-table table-vcenter table-sm text-nowrap">
                        <thead>
                            <tr>
                                <th>Kab/Kota</th>
                                @foreach(range(1, 12) as $b)<th class="text-center">{{ $bulanPendek[$b] }}</th>@endforeach
                                <th class="text-center">Total</th>
                                <th class="text-center">Menunggu</th>
                                <th class="text-center">Belum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wilayah as $kode => $nama)
                                @php $baris = $jumlah($rekap[$kode] ?? []); $belum = $baris['masalah'] - $baris['diperbaiki'] - $baris['menunggu']; @endphp
                                <tr>
                                    <td>
                                        @if($baris['masalah'])
                                            <a href="{{ route('dashboard.sipotret', ['wilayah' => $kode, 'tahun' => $tahun]) }}">{{ $nama }}</a>
                                        @else
                                            {{ $nama }}
                                        @endif
                                    </td>
                                    @foreach(range(1, 12) as $b)
                                        @php $s = $rekap[$kode][$b] ?? null; @endphp
                                        <td class="text-center">
                                            @if($s)
                                                <a href="{{ route('dashboard.sipotret', ['wilayah' => $kode, 'tahun' => $tahun, 'bulan' => $b]) }}" class="badge {{ $kelas($s) }}"
                                                   title="{{ $s['diperbaiki'] }} approve · {{ $s['menunggu'] }} menunggu · {{ $s['masalah'] }} bermasalah">{{ $s['diperbaiki'] }}/{{ $s['masalah'] }}@if($s['menunggu']) <i class="ti ti-clock"></i>{{ $s['menunggu'] }}@endif</a>
                                            @else
                                                <span class="text-secondary">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="text-center fw-medium">{{ $baris['masalah'] ? $baris['diperbaiki'] . '/' . $baris['masalah'] : '-' }}</td>
                                    <td class="text-center">
                                        @if($baris['menunggu'])
                                            <a href="{{ route('dashboard.sipotret', ['wilayah' => $kode, 'tahun' => $tahun, 'status' => 'menunggu']) }}" class="badge bg-yellow text-white">{{ $baris['menunggu'] }}</a>
                                        @else<span class="text-secondary">-</span>@endif
                                    </td>
                                    <td class="text-center">
                                        @if($belum > 0)<span class="badge bg-red text-white">{{ $belum }}</span>@else<span class="text-secondary">-</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-medium">
                                <td>Total</td>
                                @foreach(range(1, 12) as $b)
                                    <td class="text-center">{{ $perBulan[$b]['masalah'] ? $perBulan[$b]['diperbaiki'] . '/' . $perBulan[$b]['masalah'] : '-' }}</td>
                                @endforeach
                                <td class="text-center">{{ $total['masalah'] ? $total['diperbaiki'] . '/' . $total['masalah'] : '-' }}</td>
                                <td class="text-center">{{ $total['menunggu'] ?: '-' }}</td>
                                <td class="text-center">{{ $belumTotal ?: '-' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer small d-flex flex-wrap gap-2">
                    <span class="badge bg-green-lt">semua approve</span>
                    <span class="badge bg-yellow-lt">menunggu approve</span>
                    <span class="badge bg-red-lt">belum upload</span>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // buka tab dari alamat (#pasca) dan ingat tab terakhir di alamat
    (() => {
        const tab = location.hash === '#pasca' ? document.querySelector('a[href="#tabPasca"]') : null;
        if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
        document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(a => a.addEventListener('shown.bs.tab', () => {
            history.replaceState(null, '', a.getAttribute('href') === '#tabPasca' ? '#pasca' : location.pathname + location.search);
        }));
    })();
</script>
@endpush
