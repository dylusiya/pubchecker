@extends('layouts.app')

@section('title', 'Laporan Rilis Publikasi')
@section('pretitle', 'Laporan ARC · jadwal rilis publikasi kab/kota')
@section('page-title', 'Laporan Rilis Publikasi Kab/Kota')
@section('page-actions')
    @if($laporan)
        <div class="dropdown">
            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ti ti-copy"></i> Salin
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                <span class="dropdown-header">Salin tabel, lalu tempel (Ctrl+V) di dokumen</span>
                <button type="button" class="dropdown-item" data-salin="1">1. Rekap monitoring</button>
                <button type="button" class="dropdown-item" data-salin="2">2. Lima besar temuan</button>
                <button type="button" class="dropdown-item" data-salin="3">3. Hasil pemantauan ARC</button>
                <button type="button" class="dropdown-item" data-salin="4">4. Rincian ARC per jenis</button>
                <button type="button" class="dropdown-item" data-salin="5">5. Rekapitulasi pemantauan</button>
                <div class="dropdown-divider"></div>
                <button type="button" class="dropdown-item" data-salin="semua"><i class="ti ti-files me-1"></i> Semua tabel sekaligus</button>
            </div>
        </div>
        <a href="{{ route('laporan.arc.excel', ['tahun' => $tahun, 'bulan' => $bulan]) }}" class="btn">
            <i class="ti ti-download"></i> Unduh Excel
        </a>
    @endif
@endsection

@push('styles')
<style>
    .arc-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .arc-bar { min-width: 9rem; }
    .arc-bar .progress { height: .4rem; }
    .arc-tabel tfoot td { font-weight: 600; border-top: 2px solid var(--tblr-border-color); }
    .arc-tabel thead th { white-space: normal; vertical-align: bottom; }
    .arc-ringkas .h1 { line-height: 1.1; }
    /* daftar publikasi: ukuran huruf seperti teks biasa (bawaan accordion terlalu besar) */
    #daftarArc .accordion-button { font-size: .875rem; padding-top: .6rem; padding-bottom: .6rem; }
    #daftarArc .list-group-item { font-size: .8125rem; }
    #daftarArc .list-group-item .small { font-size: .75rem; }
</style>
@endpush

@php
    $n   = fn($v) => $v === null ? '…' : number_format($v, 0, ',', '.');
    $pct = fn($a, $b) => ($a === null || !$b) ? null : (int) round($a / $b * 100);
    $periode    = $bulan === 1 ? "Januari {$tahun}" : "Januari–{$namaBulan[$bulan]} {$tahun}";
    $batasSesuai = 3; // kab/kota dengan publikasi sesuai standar kurang dari ini perlu perhatian
    $akhirBulan  = \Carbon\Carbon::create($tahun, $bulan)->endOfMonth()->format('Y-m-d');
@endphp

@section('content')
    {{-- Pilih periode --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="d-flex flex-wrap align-items-center gap-2">
                <label class="fw-semibold me-1" for="pilihBulan">Periode laporan</label>
                <select id="pilihBulan" name="bulan" class="form-select w-auto" onchange="this.form.submit()">
                    @foreach(range(1, 12) as $b)
                        <option value="{{ $b }}" @selected($b == $bulan)>{{ $namaBulan[$b] }}</option>
                    @endforeach
                </select>
                <select name="tahun" class="form-select w-auto" onchange="this.form.submit()" aria-label="Tahun">
                    @for($t = date('Y'); $t >= 2024; $t--)
                        <option value="{{ $t }}" @selected($t == $tahun)>{{ $t }}</option>
                    @endfor
                </select>
                <span class="text-secondary small">Angka dihitung dari 1 Januari s.d. akhir {{ $namaBulan[$bulan] }} {{ $tahun }}.</span>
                @if($laporan)
                    <span class="ms-auto small text-secondary">
                        Data website BPS: {{ $laporan['diambil']->format('d/m/Y H:i') }}
                        <a href="{{ route('laporan.arc', ['tahun' => $tahun, 'bulan' => $bulan, 'segarkan' => 1]) }}" class="ms-1">
                            <i class="ti ti-refresh"></i> Perbarui
                        </a>
                    </span>
                @endif
            </form>
        </div>
    </div>

@if($error)
    <div class="alert alert-danger">
        <div class="fw-semibold">Laporan belum bisa ditampilkan</div>
        <div class="small">Data dari website BPS tidak bisa diambil. Coba lagi beberapa saat lagi, atau hubungi pengelola aplikasi.</div>
        <div class="small text-secondary mt-1">Detail: {{ $error }}</div>
    </div>
@else
    @php
        $baris = $laporan['baris'];
        $t     = $laporan['total'];
        $jenis = \App\Services\LaporanArcService::JENIS;
        $adaStatus = $laporan['statusError'] === null;

        $sudahRilis = ($t['on_time'] ?? 0) + ($t['tidak_on_time'] ?? 0);
        $pRilis   = $pct($sudahRilis, $t['jadwal_sd']);
        $pTepat   = $pct($t['on_time'], $t['jadwal_sd']);
        $pSesuai  = $adaStatus ? $pct($t['sesuai'], $t['rilis_sd']) : null;
        $warna    = fn(?int $p) => $p === null ? 'secondary' : ($p >= 80 ? 'green' : ($p >= 50 ? 'yellow' : 'red'));

        // kab/kota yang perlu perhatian, dengan alasannya dalam kalimat sederhana
        $perhatian = [];
        foreach ($baris as $b) {
            $alasan = [];
            if ($b['tidak_rilis']) $alasan[] = "{$b['tidak_rilis']} publikasi belum rilis";
            if ($b['tidak_on_time']) $alasan[] = "{$b['tidak_on_time']} rilis terlambat";
            if ($adaStatus && $b['rilis_sd'] && $b['sesuai'] < $batasSesuai) $alasan[] = "baru {$b['sesuai']} dari {$b['rilis_sd']} publikasi sesuai standar";
            if ($alasan) $perhatian[$b['kode']] = ['nama' => $b['nama'], 'alasan' => $alasan];
        }
    @endphp

    @if(!$adaStatus)
        <div class="alert alert-warning">
            <div class="fw-semibold">Data pemeriksaan (SIPOTRET) belum terbaca</div>
            <div class="small">Kolom "sesuai standar" dan "temuan terbanyak" dikosongkan (…). Angka rilis tetap benar.</div>
        </div>
    @endif

    {{-- Ringkasan dalam bahasa sederhana --}}
    <div class="row row-cards mb-3 arc-ringkas">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="subheader mb-1">Sudah rilis</div>
                    <div class="h1 mb-1">{{ $n($sudahRilis) }} <span class="fs-3 text-secondary fw-normal">dari {{ $n($t['jadwal_sd']) }}</span></div>
                    <div class="progress progress-sm mb-2"><div class="progress-bar bg-{{ $warna($pRilis) }}" style="width: {{ $pRilis ?? 0 }}%"></div></div>
                    <div class="text-secondary small">
                        publikasi yang dijadwalkan s.d. {{ $namaBulan[$bulan] }} sudah terbit di website.
                        @if($t['tidak_rilis'])<span class="text-danger fw-semibold">{{ $t['tidak_rilis'] }} belum rilis.</span>@endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="subheader mb-1">Tepat waktu</div>
                    <div class="h1 mb-1">{{ $pTepat === null ? '…' : $pTepat . '%' }}</div>
                    <div class="progress progress-sm mb-2"><div class="progress-bar bg-{{ $warna($pTepat) }}" style="width: {{ $pTepat ?? 0 }}%"></div></div>
                    <div class="text-secondary small">
                        {{ $n($t['on_time']) }} publikasi terbit pada atau sebelum tanggal jadwalnya{{ $t['tidak_on_time'] ? ", {$t['tidak_on_time']} terlambat" : '' }}.
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="subheader mb-1">Sesuai standar</div>
                    <div class="h1 mb-1">{{ $n($t['sesuai']) }} <span class="fs-3 text-secondary fw-normal">dari {{ $n($t['rilis_sd']) }}</span></div>
                    <div class="progress progress-sm mb-2"><div class="progress-bar bg-{{ $warna($pSesuai) }}" style="width: {{ $pSesuai ?? 0 }}%"></div></div>
                    <div class="text-secondary small">publikasi yang sudah rilis tidak punya catatan perbaikan di SIPOTRET bulan {{ $namaBulan[$bulan] }}{{ $pSesuai !== null ? " ({$pSesuai}%)" : '' }}.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Perlu perhatian --}}
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                @if($perhatian)
                    <i class="ti ti-alert-triangle text-warning me-1"></i> {{ count($perhatian) }} kab/kota perlu perhatian
                @else
                    <i class="ti ti-circle-check text-green me-1"></i> Semua kab/kota dalam kondisi baik
                @endif
            </h3>
        </div>
        @if($perhatian)
            <div class="list-group list-group-flush">
                @foreach($perhatian as $x)
                    <div class="list-group-item py-2">
                        <span class="fw-semibold">{{ $x['nama'] }}</span>
                        <span class="text-secondary">· {{ implode(' · ', $x['alasan']) }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card-body text-secondary">Semua publikasi yang dijadwalkan sudah rilis tepat waktu dan sesuai standar.</div>
        @endif
    </div>

    {{-- Detail per tab: urutan & nomor sama dengan tabel di Excel / Salin ke Google Docs --}}
    @php
        $nz = fn($v) => $v === 0 ? '–' : $n($v);
        $wajibJ = array_filter($jenis, fn($j) => $j['wajib']);
        $nonJ   = array_filter($jenis, fn($j) => !$j['wajib']);
    @endphp
    <div class="card mb-3">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><a href="#tab-1" class="nav-link active" data-bs-toggle="tab" role="tab">1. Rekap monitoring</a></li>
                <li class="nav-item"><a href="#tab-2" class="nav-link" data-bs-toggle="tab" role="tab">2. Lima besar temuan</a></li>
                <li class="nav-item"><a href="#tab-3" class="nav-link" data-bs-toggle="tab" role="tab">3. Pemantauan ARC</a></li>
                <li class="nav-item"><a href="#tab-4" class="nav-link" data-bs-toggle="tab" role="tab">4. Rincian per jenis</a></li>
                <li class="nav-item"><a href="#tab-5" class="nav-link" data-bs-toggle="tab" role="tab">5. Rekap pemantauan</a></li>
                <li class="nav-item ms-auto"><a href="#tab-daftar" class="nav-link" data-bs-toggle="tab" role="tab"><i class="ti ti-file-text me-1"></i> Daftar publikasi</a></li>
            </ul>
        </div>
        <div class="tab-content">

            {{-- 1. Rekap monitoring --}}
            <div class="tab-pane active show" id="tab-1" role="tabpanel">
                <div class="card-body pb-0">
                    <p class="text-secondary mb-0">Publikasi ARC yang sudah dirilis dan berapa yang sudah sesuai standar
                        (tidak ada catatan perbaikan di SIPOTRET bulan {{ $namaBulan[$bulan] }}).</p>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter arc-tabel">
                        <thead>
                            <tr>
                                <th>Kabupaten/Kota</th>
                                <th class="arc-num">Dirilis<br><span class="fw-normal text-lowercase">{{ $namaBulan[$bulan] }}</span></th>
                                <th class="arc-num">Dirilis<br><span class="fw-normal text-lowercase">{{ $bulan === 1 ? 'januari' : 'jan–' . mb_strtolower($namaBulan[$bulan]) }}</span></th>
                                <th>Sudah sesuai standar</th>
                                <th class="arc-num">Belum sesuai</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($baris as $b)
                                @php $p = $adaStatus ? $pct($b['sesuai'], $b['rilis_sd']) : null; @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $b['nama'] }}</td>
                                    <td class="arc-num">{{ $nz($b['rilis_bulan']) }}</td>
                                    <td class="arc-num">{{ $nz($b['rilis_sd']) }}</td>
                                    <td class="arc-bar">
                                        @if($adaStatus)
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>{{ $b['sesuai'] }} dari {{ $b['rilis_sd'] }}</span>
                                                <span class="text-secondary">{{ $p ?? 0 }}%</span>
                                            </div>
                                            <div class="progress"><div class="progress-bar bg-{{ $warna($p) }}" style="width: {{ $p ?? 0 }}%"></div></div>
                                        @else
                                            <span class="text-secondary">…</span>
                                        @endif
                                    </td>
                                    <td class="arc-num {{ $b['belum_sesuai'] ? 'text-danger fw-semibold' : '' }}">{{ $nz($b['belum_sesuai']) }}</td>
                                    <td>
                                        @if(isset($perhatian[$b['kode']]))
                                            <span class="badge bg-red-lt">Perlu perhatian</span>
                                        @else
                                            <span class="badge bg-green-lt">Baik</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td class="arc-num">{{ $nz($t['rilis_bulan']) }}</td>
                                <td class="arc-num">{{ $nz($t['rilis_sd']) }}</td>
                                <td>{{ $adaStatus ? "{$t['sesuai']} dari {$t['rilis_sd']}" : '…' }}</td>
                                <td class="arc-num">{{ $nz($t['belum_sesuai']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- 2. Lima besar temuan --}}
            <div class="tab-pane" id="tab-2" role="tabpanel">
                <div class="card-body pb-0">
                    <p class="text-secondary mb-0">Lima jenis kesalahan yang paling sering ditemukan saat memeriksa publikasi, {{ $periode }}.
                        Fokuskan perbaikan pada hal-hal ini.</p>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($laporan['temuan'] as $i => $x)
                        <div class="list-group-item">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="avatar avatar-sm bg-red-lt flex-shrink-0">{{ $i + 1 }}</span>
                                <div class="flex-fill">
                                    <div class="fw-semibold">{{ $x['item'] }}</div>
                                    <div class="text-secondary small">{{ $x['deskripsi'] }}</div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <div class="fw-semibold">{{ $x['publikasi'] }}</div>
                                    <div class="text-secondary small">publikasi</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-secondary">
                            {{ $laporan['temuanError'] ? 'Data pemeriksaan belum terbaca.' : 'Belum ada kesalahan yang tercatat pada periode ini.' }}
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- 3. Hasil pemantauan ARC --}}
            <div class="tab-pane" id="tab-3" role="tabpanel">
                <div class="card-body pb-0">
                    <p class="text-secondary mb-0">Jumlah publikasi wajib dalam jadwal rilis (ARC) {{ $tahun }} tiap kab/kota, dibandingkan tahun {{ $tahun - 1 }}.</p>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter arc-tabel">
                        <thead>
                            <tr>
                                <th class="w-1">Kode</th>
                                <th>Kabupaten/Kota</th>
                                <th class="arc-num">ARC {{ $tahun }}</th>
                                <th class="arc-num">ARC {{ $tahun - 1 }}</th>
                                <th class="arc-num">Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($baris as $b)
                                @php $selisih = $b['jumlah_arc_lalu'] === null ? null : $b['jumlah_arc'] - $b['jumlah_arc_lalu']; @endphp
                                <tr>
                                    <td class="text-secondary">{{ $b['kode'] }}</td>
                                    <td class="fw-semibold">{{ $b['nama'] }}</td>
                                    <td class="arc-num">{{ $nz($b['jumlah_arc']) }}</td>
                                    <td class="arc-num">{{ $nz($b['jumlah_arc_lalu']) }}</td>
                                    <td class="arc-num {{ $selisih ? ($selisih > 0 ? 'text-green' : 'text-danger') : 'text-secondary' }}">
                                        {{ $selisih === null ? '…' : ($selisih === 0 ? '–' : ($selisih > 0 ? '+' : '') . $selisih) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @php $selisihT = $t['jumlah_arc_lalu'] === null ? null : $t['jumlah_arc'] - $t['jumlah_arc_lalu']; @endphp
                            <tr>
                                <td></td><td>Total</td>
                                <td class="arc-num">{{ $nz($t['jumlah_arc']) }}</td>
                                <td class="arc-num">{{ $nz($t['jumlah_arc_lalu']) }}</td>
                                <td class="arc-num">{{ $selisihT === null ? '…' : ($selisihT === 0 ? '–' : ($selisihT > 0 ? '+' : '') . $selisihT) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer small text-secondary">
                    Kolom keberadaan ARC di website dan portal publikasi (✓/x) tidak tersedia di website BPS, jadi diisi manual di file Excel / Google Docs.
                </div>
            </div>

            {{-- 4. Rincian ARC per jenis --}}
            <div class="tab-pane" id="tab-4" role="tabpanel">
                <div class="card-body pb-0">
                    <p class="text-secondary mb-0">Rincian jumlah publikasi {{ $tahun }} per jenis: yang wajib masuk jadwal rilis (ARC wajib) dan yang tidak wajib.</p>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter table-sm arc-tabel">
                        <thead>
                            <tr>
                                <th rowspan="2">Kabupaten/Kota</th>
                                <th colspan="{{ count($wajibJ) + 1 }}" class="text-center">ARC wajib</th>
                                <th colspan="{{ count($nonJ) }}" class="text-center">Non ARC wajib</th>
                            </tr>
                            <tr>
                                @foreach($wajibJ as $j)<th class="arc-num">{{ $j['label'] }}</th>@endforeach
                                <th class="arc-num">Jumlah</th>
                                @foreach($nonJ as $j)<th class="arc-num">{{ $j['label'] }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($baris as $b)
                                <tr>
                                    <td class="fw-semibold">{{ $b['nama'] }}</td>
                                    @foreach(array_keys($wajibJ) as $j)<td class="arc-num">{{ $nz($b['per_jenis'][$j]) }}</td>@endforeach
                                    <td class="arc-num fw-semibold">{{ $nz($b['jumlah_arc']) }}</td>
                                    @foreach(array_keys($nonJ) as $j)<td class="arc-num">{{ $nz($b['per_jenis'][$j]) }}</td>@endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                @foreach(array_keys($wajibJ) as $j)<td class="arc-num">{{ $nz($t['per_jenis'][$j]) }}</td>@endforeach
                                <td class="arc-num">{{ $nz($t['jumlah_arc']) }}</td>
                                @foreach(array_keys($nonJ) as $j)<td class="arc-num">{{ $nz($t['per_jenis'][$j]) }}</td>@endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer small text-secondary">Jenis dikenali dari judul publikasi di website BPS.</div>
            </div>

            {{-- 5. Rekapitulasi pemantauan --}}
            <div class="tab-pane" id="tab-5" role="tabpanel">
                <div class="card-body pb-0">
                    <p class="text-secondary mb-0">Rilis publikasi ARC di website BPS dibandingkan jadwalnya, 1 Januari s.d. akhir {{ $namaBulan[$bulan] }} {{ $tahun }}.
                        Persen dihitung dari jumlah yang dijadwalkan.</p>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter table-sm arc-tabel">
                        <thead>
                            <tr>
                                <th rowspan="2">Kabupaten/Kota</th>
                                <th colspan="2" class="text-center">Dijadwalkan</th>
                                <th colspan="2" class="text-center">Tepat waktu</th>
                                <th colspan="2" class="text-center">Terlambat</th>
                                <th colspan="2" class="text-center">Belum rilis</th>
                                <th colspan="2" class="text-center">Sesuai standar</th>
                                <th rowspan="2" class="arc-num">Publikasi<br>non ARC</th>
                            </tr>
                            <tr>
                                <th class="arc-num">{{ $namaBulan[$bulan] }}</th>
                                <th class="arc-num">s.d. {{ $namaBulan[$bulan] }}</th>
                                @foreach(range(1, 4) as $_)<th class="arc-num">Jml</th><th class="arc-num">%</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($baris as $b)
                                @php
                                    // persen dari yang dijadwalkan; nol / tanpa jadwal ditulis "–", tidak tersedia "…"
                                    $persen = fn($a) => $a === null ? '…' : (!$a ? '–' : (\App\Services\LaporanArcService::persen($a, $b['jadwal_sd']) ?? '–'));
                                    $kurang = $adaStatus && $b['sesuai'] !== null && $b['sesuai'] < $batasSesuai;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $b['nama'] }}</td>
                                    <td class="arc-num">{{ $nz($b['jadwal_bulan']) }}</td>
                                    <td class="arc-num">{{ $nz($b['jadwal_sd']) }}</td>
                                    <td class="arc-num">{{ $nz($b['on_time']) }}</td>
                                    <td class="arc-num text-secondary">{{ $persen($b['on_time']) }}</td>
                                    <td class="arc-num {{ $b['tidak_on_time'] ? 'text-warning fw-semibold' : '' }}">{{ $nz($b['tidak_on_time']) }}</td>
                                    <td class="arc-num text-secondary">{{ $persen($b['tidak_on_time']) }}</td>
                                    <td class="arc-num {{ $b['tidak_rilis'] ? 'text-danger fw-semibold' : '' }}">{{ $nz($b['tidak_rilis']) }}</td>
                                    <td class="arc-num text-secondary">{{ $persen($b['tidak_rilis']) }}</td>
                                    <td class="arc-num">
                                        @if($kurang)<span class="badge bg-red-lt">{{ $nz($b['sesuai']) }}</span>@else{{ $nz($b['sesuai']) }}@endif
                                    </td>
                                    <td class="arc-num {{ $kurang ? 'text-danger' : 'text-secondary' }}">{{ $persen($b['sesuai']) }}</td>
                                    <td class="arc-num">{{ $nz($b['non_arc']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @php $persenT = fn($a) => $a === null ? '…' : (!$a ? '–' : (\App\Services\LaporanArcService::persen($a, $t['jadwal_sd']) ?? '–')); @endphp
                            <tr>
                                <td>Total</td>
                                <td class="arc-num">{{ $nz($t['jadwal_bulan']) }}</td>
                                <td class="arc-num">{{ $nz($t['jadwal_sd']) }}</td>
                                <td class="arc-num">{{ $nz($t['on_time']) }}</td>
                                <td class="arc-num">{{ $persenT($t['on_time']) }}</td>
                                <td class="arc-num">{{ $nz($t['tidak_on_time']) }}</td>
                                <td class="arc-num">{{ $persenT($t['tidak_on_time']) }}</td>
                                <td class="arc-num">{{ $nz($t['tidak_rilis']) }}</td>
                                <td class="arc-num">{{ $persenT($t['tidak_rilis']) }}</td>
                                <td class="arc-num">{{ $nz($t['sesuai']) }}</td>
                                <td class="arc-num">{{ $persenT($t['sesuai']) }}</td>
                                <td class="arc-num">{{ $nz($t['non_arc']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer small text-secondary">
                    <span class="badge bg-red-lt">merah</span> = publikasi sesuai standar kurang dari {{ $batasSesuai }}.
                    Belum rilis = sudah lewat jadwal tetapi belum terbit sampai akhir bulan laporan.
                </div>
            </div>

            {{-- Daftar publikasi --}}
            <div class="tab-pane" id="tab-daftar" role="tabpanel">
                <div class="card-body border-bottom py-2">
                    <input type="search" class="form-control" id="cariPublikasi" placeholder="Cari judul publikasi…" aria-label="Cari judul publikasi">
                </div>
                <div class="accordion accordion-flush" id="daftarArc">
                    @foreach($baris as $b)
                        <div class="accordion-item" data-wilayah>
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#daftar-{{ $b['kode'] }}">
                                    <span class="me-auto fw-semibold">{{ $b['nama'] }}</span>
                                    <span class="text-secondary small me-3">{{ count($b['publikasi']) }} publikasi</span>
                                </button>
                            </h2>
                            <div id="daftar-{{ $b['kode'] }}" class="accordion-collapse collapse" data-bs-parent="#daftarArc">
                                <div class="list-group list-group-flush">
                                    @forelse($b['publikasi'] as $pub)
                                        @php
                                            $st    = $b['status'][$pub['pub_id']] ?? null;
                                            $telat = $pub['rilis'] && $pub['jadwal'] && $pub['rilis'] > $pub['jadwal'];
                                            // tanggal Indonesia (locale aplikasi en): 27 Feb 2026, 5 Agu 2026
                                            $tgl   = fn($d) => $d ? (int) substr($d, 8, 2) . ' ' . mb_substr($namaBulan[(int) substr($d, 5, 2)], 0, 3) . ' ' . substr($d, 0, 4) : '-';
                                        @endphp
                                        <div class="list-group-item py-2" data-judul="{{ mb_strtolower($pub['judul']) }}">
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <div class="flex-fill" style="min-width: 14rem;">
                                                    <div>{{ $pub['judul'] }}</div>
                                                    <div class="small text-secondary">
                                                        Jadwal {{ $tgl($pub['jadwal']) }} · Rilis {{ $tgl($pub['rilis']) }}
                                                    </div>
                                                </div>
                                                @if(!$pub['rilis'])
                                                    <span class="badge bg-red-lt">Belum rilis</span>
                                                @elseif($telat)
                                                    <span class="badge bg-yellow-lt">Terlambat</span>
                                                @else
                                                    <span class="badge bg-green-lt">Tepat waktu</span>
                                                @endif
                                                {{-- sesuai standar = tanpa catatan di SIPOTRET pada bulan laporan (hanya yang sudah rilis s.d. bulan itu) --}}
                                                @if(!$adaStatus || !$pub['rilis'] || $pub['rilis'] > $akhirBulan)
                                                @elseif(empty($st['catatan']))
                                                    <span class="badge bg-green-lt" title="Tidak ada catatan di SIPOTRET bulan {{ $namaBulan[$bulan] }}">Sesuai standar</span>
                                                @else
                                                    <span class="badge bg-red-lt" title="Catatan di SIPOTRET bulan {{ $namaBulan[$bulan] }}">{{ $st['catatan'] }} catatan perbaikan</span>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="list-group-item text-secondary">Belum ada publikasi pada periode ini.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="card-body text-secondary d-none" id="cariKosong">Tidak ada publikasi yang cocok.</div>
            </div>
        </div>
    </div>

    {{-- tabel format laporan resmi untuk tombol "Salin ke Google Docs" (tidak tampil) --}}
    @include('partials.laporan-arc-salin')
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
        <div id="toastSalin" class="toast align-items-center border-0" role="status" aria-live="polite">
            <div class="d-flex">
                <div class="toast-body"></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
            </div>
        </div>
    </div>

    {{-- Penjelasan istilah --}}
    <div class="card">
        <div class="card-header">
            <a class="card-title text-reset" data-bs-toggle="collapse" href="#caraBaca" role="button">
                <i class="ti ti-help-circle me-1"></i> Cara membaca laporan ini
            </a>
        </div>
        <div class="collapse" id="caraBaca">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-3">ARC</dt>
                    <dd class="col-sm-9"><em>Advance Release Calendar</em>: jadwal rilis publikasi yang diumumkan di awal tahun.</dd>
                    <dt class="col-sm-3">Dijadwalkan</dt>
                    <dd class="col-sm-9">Publikasi wajib yang jadwal rilisnya jatuh antara 1 Januari dan akhir bulan laporan. Untuk publikasi yang belum
                        terbit, jadwalnya diperkirakan dari jadwal tahun {{ $tahun - 1 }}, karena website BPS baru menampilkan publikasi setelah rilis.</dd>
                    <dt class="col-sm-3">Tepat waktu / terlambat</dt>
                    <dd class="col-sm-9">Tanggal rilis di website BPS dibandingkan dengan tanggal jadwalnya.</dd>
                    <dt class="col-sm-3">Belum rilis</dt>
                    <dd class="col-sm-9">Sudah lewat jadwal, tetapi belum terbit sampai akhir bulan laporan.</dd>
                    <dt class="col-sm-3">Sesuai standar</dt>
                    <dd class="col-sm-9">Publikasi yang sudah rilis dan tidak punya catatan perbaikan sama sekali di SIPOTRET pada bulan laporan
                        (mis. laporan Oktober: tidak ada catatan di periode Oktober).</dd>
                    <dt class="col-sm-3">Perlu perhatian</dt>
                    <dd class="col-sm-9">Ada publikasi yang belum rilis atau terlambat, atau publikasi yang sesuai standar kurang dari {{ $batasSesuai }}.</dd>
                    <dt class="col-sm-3">Unduh Excel</dt>
                    <dd class="col-sm-9">Lima tabel dalam format laporan resmi (satu sheet per tabel), siap ditempel ke bahan rapat.</dd>
                </dl>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    // Tab terakhir yang dibuka diingat lewat #hash (bisa dibagikan / tetap saat periode diganti)
    (() => {
        const tab = document.querySelector('.nav-tabs a[href="' + location.hash + '"]');
        if (tab && window.bootstrap) window.bootstrap.Tab.getOrCreateInstance(tab).show();
        document.querySelectorAll('.nav-tabs a[data-bs-toggle="tab"]').forEach(a =>
            a.addEventListener('shown.bs.tab', () => history.replaceState(null, '', a.getAttribute('href'))));
        // pilih periode: pertahankan tab yang sedang dibuka
        document.querySelectorAll('form select').forEach(s => s.addEventListener('change', () => {
            if (location.hash) s.form.action = location.pathname + location.hash;
        }));

        // Salin tabel format resmi sebagai HTML ber-gaya (warna, Arial, garis): Google Docs mempertahankan gayanya,
        // tidak seperti tempel dari spreadsheet (jadi objek tertaut "Untitled spreadsheet", warna & huruf hilang)
        const toast = document.getElementById('toastSalin');
        const kabar = (pesan, ok) => {
            if (!toast) return;
            toast.className = 'toast align-items-center border-0 text-white ' + (ok ? 'bg-success' : 'bg-danger');
            toast.querySelector('.toast-body').textContent = pesan;
            window.bootstrap?.Toast.getOrCreateInstance(toast, { delay: 4000 }).show();
        };
        async function salin(html) {
            const wadah = document.createElement('div');
            wadah.innerHTML = html;
            const teks = wadah.innerText;
            try {
                await navigator.clipboard.write([new ClipboardItem({
                    'text/html':  new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([teks], { type: 'text/plain' }),
                })]);
                return true;
            } catch (e) {
                // cadangan (browser lama / http): pilih isi elemen sementara lalu salin
                wadah.style.cssText = 'position:fixed;left:-9999px;top:0;';
                document.body.appendChild(wadah);
                const r = document.createRange();
                r.selectNodeContents(wadah);
                const sel = getSelection();
                sel.removeAllRanges(); sel.addRange(r);
                const ok = document.execCommand('copy');
                sel.removeAllRanges(); wadah.remove();
                return ok;
            }
        }
        document.querySelectorAll('[data-salin]').forEach(btn => btn.addEventListener('click', async () => {
            const pilih = btn.dataset.salin;
            const tpl = [...document.querySelectorAll('template[data-tabel]')]
                .filter(t => pilih === 'semua' || t.dataset.tabel === pilih);
            // antar tabel diberi satu baris kosong
            const html = tpl.map(t => t.innerHTML).join('<p><br></p>');
            const ok = await salin(html);
            kabar(ok ? (pilih === 'semua' ? 'Semua tabel disalin.' : 'Tabel ' + pilih + ' disalin.') + ' Tempel (Ctrl+V) di Google Docs.'
                     : 'Gagal menyalin. Coba lagi, atau gunakan Unduh Excel.', ok);
        }));

        // cari judul di daftar publikasi: buka kab/kota yang punya hasil
        const cari = document.getElementById('cariPublikasi');
        cari?.addEventListener('input', () => {
            const q = cari.value.trim().toLowerCase();
            let ada = 0;
            document.querySelectorAll('#daftarArc [data-wilayah]').forEach(w => {
                const item = w.querySelectorAll('[data-judul]');
                let cocok = 0;
                item.forEach(i => { const ok = !q || i.dataset.judul.includes(q); i.classList.toggle('d-none', !ok); cocok += ok; });
                w.classList.toggle('d-none', q !== '' && cocok === 0);
                w.querySelector('.accordion-collapse').classList.toggle('show', q !== '' && cocok > 0);
                w.querySelector('.accordion-button').classList.toggle('collapsed', !(q !== '' && cocok > 0));
                ada += cocok;
            });
            document.getElementById('cariKosong').classList.toggle('d-none', ada > 0);
        });
    })();
</script>
@endpush
