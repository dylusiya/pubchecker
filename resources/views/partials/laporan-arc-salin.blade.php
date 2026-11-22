{{--
    Lima tabel Laporan ARC dalam format laporan resmi (seperti Excel), untuk tombol "Salin ke Google Docs".
    Semua gaya ditulis inline (warna, huruf Arial, garis, sel gabungan) karena Google Docs hanya membaca gaya inline
    dari HTML yang ditempel. Disimpan di <template> (tidak tampil), satu per tabel: data-tabel="1".."5".
--}}
@php
    $font  = 'font-family:Arial,sans-serif;font-size:9pt;';
    $garis = 'border:1px solid #000000;padding:2pt 4pt;vertical-align:middle;';
    $sTh   = $font . $garis . 'background-color:#2f5b8c;color:#ffffff;font-weight:bold;text-align:center;';
    $sNo   = $font . $garis . 'background-color:#9ab7dc;color:#000000;font-weight:bold;text-align:center;font-size:8pt;';
    $sTd   = $font . $garis . 'text-align:center;';
    $sKiri = $font . $garis . 'text-align:left;';
    $sTot  = $sTh;
    $merah = 'background-color:#e8999a;';
    $sTabel = 'border-collapse:collapse;width:100%;' . $font;
    $sJudul = $font . 'font-size:10pt;font-weight:bold;text-align:center;text-transform:uppercase;margin:0 0 6pt 0;';

    $v  = fn($x) => $x === null ? '…' : ($x === 0 ? '–' : number_format($x, 0, ',', '.'));
    $pc = fn($a, $b) => $a === null ? '…' : (!$a || !$b ? '–' : number_format($a / $b * 100, 2, ',', '.'));
    $nomor = fn(int $n) => collect(range(1, $n))->map(fn($i) => "<td style=\"{$sNo}\">({$i})</td>")->implode('');

    $rows    = array_values($laporan['baris']);
    $tt      = $laporan['total'];
    $jenisL  = \App\Services\LaporanArcService::JENIS;
    $wajibL  = array_filter($jenisL, fn($j) => $j['wajib']);
    $nonL    = array_filter($jenisL, fn($j) => !$j['wajib']);
    $prd     = $bulan === 1 ? "Januari {$tahun}" : "Januari–{$namaBulan[$bulan]} {$tahun}";
    $akhirHr = \Carbon\Carbon::create($tahun, $bulan)->endOfMonth()->day;
    $batas   = 3;
@endphp

{{-- 1. Rekap monitoring --}}
<template data-tabel="1"><div>
<p style="{{ $sJudul }}">Rekap Monitoring Publikasi ARC {{ $tahun }} BPS Kabupaten/Kota di Provinsi Kalimantan Selatan</p>
<table style="{{ $sTabel }}">
    <tr>
        <td style="{{ $sTh }}">No</td><td style="{{ $sTh }}">Kabupaten/Kota</td>
        <td style="{{ $sTh }}">Jumlah Publikasi ARC yang dirilis {{ $namaBulan[$bulan] }} {{ $tahun }}</td>
        <td style="{{ $sTh }}">Jumlah Publikasi ARC yang dirilis {{ $prd }}</td>
        <td style="{{ $sTh }}">Jumlah Publikasi ARC Sudah Sesuai Standar</td>
        <td style="{{ $sTh }}">Jumlah Publikasi ARC Belum Sesuai Standar</td>
    </tr>
    <tr>{!! $nomor(6) !!}</tr>
    @foreach($rows as $i => $b)
        <tr>
            <td style="{{ $sTd }}">{{ $i + 1 }}</td><td style="{{ $sKiri }}">{{ $b['nama'] }}</td>
            <td style="{{ $sTd }}">{{ $v($b['rilis_bulan']) }}</td><td style="{{ $sTd }}">{{ $v($b['rilis_sd']) }}</td>
            <td style="{{ $sTd }}">{{ $v($b['sesuai']) }}</td>
            <td style="{{ $sTd }}{{ $b['belum_sesuai'] ? $merah : '' }}">{{ $v($b['belum_sesuai']) }}</td>
        </tr>
    @endforeach
    <tr>
        <td style="{{ $sTot }}" colspan="2">Total</td>
        <td style="{{ $sTot }}">{{ $v($tt['rilis_bulan']) }}</td><td style="{{ $sTot }}">{{ $v($tt['rilis_sd']) }}</td>
        <td style="{{ $sTot }}">{{ $v($tt['sesuai']) }}</td><td style="{{ $sTot }}">{{ $v($tt['belum_sesuai']) }}</td>
    </tr>
</table>
</div></template>

{{-- 2. Lima besar temuan --}}
<template data-tabel="2"><div>
<p style="{{ $sJudul }}">Rekapitulasi Lima Besar Temuan Ketidaksesuaian Publikasi ARC Selama {{ $prd }}</p>
<table style="{{ $sTabel }}">
    <tr><td style="{{ $sTh }}">No</td><td style="{{ $sTh }}">Item Pemeriksaan</td><td style="{{ $sTh }}">Deskripsi</td><td style="{{ $sTh }}">Keterangan</td></tr>
    <tr>{!! $nomor(4) !!}</tr>
    @forelse($laporan['temuan'] as $i => $x)
        <tr>
            <td style="{{ $sTd }}vertical-align:top;">{{ $i + 1 }}</td>
            <td style="{{ $sKiri }}vertical-align:top;">{{ $x['item'] }}</td>
            <td style="{{ $sKiri }}vertical-align:top;">{{ $x['deskripsi'] }}</td>
            <td style="{{ $sTd }}vertical-align:top;">Ditemukan pada {{ $x['publikasi'] }} publikasi</td>
        </tr>
    @empty
        <tr><td style="{{ $sTd }}" colspan="4">Tidak ada data</td></tr>
    @endforelse
</table>
</div></template>

{{-- 3. Hasil pemantauan ARC --}}
<template data-tabel="3"><div>
<p style="{{ $sJudul }}">Hasil Pemantauan <i>Advance Release Calendar</i> (ARC) Publikasi BPS Kabupaten/Kota {{ $tahun }}</p>
<table style="{{ $sTabel }}">
    <tr>
        <td style="{{ $sTh }}" rowspan="2">No</td><td style="{{ $sTh }}" rowspan="2">Kode</td><td style="{{ $sTh }}" rowspan="2">Kabupaten/Kota</td>
        <td style="{{ $sTh }}" colspan="2">Keberadaan ARC {{ $tahun }}<br>✓: ada, x: tidak ada</td>
        <td style="{{ $sTh }}" rowspan="2">Jumlah Publikasi ARC {{ $tahun }}</td>
        <td style="{{ $sTh }}" rowspan="2">Jumlah Publikasi ARC {{ $tahun - 1 }}</td>
        <td style="{{ $sTh }}" rowspan="2">Keterangan</td>
    </tr>
    <tr><td style="{{ $sTh }}">Website</td><td style="{{ $sTh }}">Portal Publikasi</td></tr>
    <tr>{!! $nomor(8) !!}</tr>
    @foreach($rows as $i => $b)
        <tr>
            <td style="{{ $sTd }}">{{ $i + 1 }}</td><td style="{{ $sTd }}">{{ $b['kode'] }}</td><td style="{{ $sKiri }}">{{ $b['nama'] }}</td>
            <td style="{{ $sTd }}"></td><td style="{{ $sTd }}"></td>
            <td style="{{ $sTd }}">{{ $v($b['jumlah_arc']) }}</td><td style="{{ $sTd }}">{{ $v($b['jumlah_arc_lalu']) }}</td>
            <td style="{{ $sKiri }}"></td>
        </tr>
    @endforeach
    <tr>
        <td style="{{ $sTot }}" colspan="3">Total</td><td style="{{ $sTot }}"></td><td style="{{ $sTot }}"></td>
        <td style="{{ $sTot }}">{{ $v($tt['jumlah_arc']) }}</td><td style="{{ $sTot }}">{{ $v($tt['jumlah_arc_lalu']) }}</td><td style="{{ $sTot }}"></td>
    </tr>
</table>
</div></template>

{{-- 4. Rincian ARC per jenis --}}
<template data-tabel="4"><div>
<p style="{{ $sJudul }}">Rincian <i>Advance Release Calendar</i> (ARC) Publikasi BPS Kabupaten/Kota {{ $tahun }}</p>
<table style="{{ $sTabel }}">
    <tr>
        <td style="{{ $sTh }}" rowspan="2">No</td><td style="{{ $sTh }}" rowspan="2">Kode</td><td style="{{ $sTh }}" rowspan="2">Kabupaten/Kota</td>
        <td style="{{ $sTh }}" colspan="{{ count($wajibL) }}">ARC Wajib</td>
        <td style="{{ $sTh }}" rowspan="2">Jumlah Publikasi ARC Wajib</td>
        <td style="{{ $sTh }}" colspan="{{ count($nonL) }}">Non ARC Wajib</td>
    </tr>
    <tr>
        @foreach($wajibL as $j)<td style="{{ $sTh }}">{{ $j['label'] }}</td>@endforeach
        @foreach($nonL as $j)<td style="{{ $sTh }}">{{ $j['label'] }}</td>@endforeach
    </tr>
    <tr>{!! $nomor(4 + count($jenisL)) !!}</tr>
    @foreach($rows as $i => $b)
        <tr>
            <td style="{{ $sTd }}">{{ $i + 1 }}</td><td style="{{ $sTd }}">{{ $b['kode'] }}</td><td style="{{ $sKiri }}">{{ $b['nama'] }}</td>
            @foreach(array_keys($wajibL) as $j)<td style="{{ $sTd }}">{{ $v($b['per_jenis'][$j]) }}</td>@endforeach
            <td style="{{ $sTd }}">{{ $v($b['jumlah_arc']) }}</td>
            @foreach(array_keys($nonL) as $j)<td style="{{ $sTd }}">{{ $v($b['per_jenis'][$j]) }}</td>@endforeach
        </tr>
    @endforeach
    <tr>
        <td style="{{ $sTot }}" colspan="3">Total</td>
        @foreach(array_keys($wajibL) as $j)<td style="{{ $sTot }}">{{ $v($tt['per_jenis'][$j]) }}</td>@endforeach
        <td style="{{ $sTot }}">{{ $v($tt['jumlah_arc']) }}</td>
        @foreach(array_keys($nonL) as $j)<td style="{{ $sTot }}">{{ $v($tt['per_jenis'][$j]) }}</td>@endforeach
    </tr>
</table>
</div></template>

{{-- 5. Rekapitulasi pemantauan --}}
<template data-tabel="5"><div>
<p style="{{ $sJudul }}">Rekapitulasi Hasil Pemantauan <i>Advance Release Calendar</i> (ARC) Publikasi BPS Kabupaten/Kota {{ $tahun }}</p>
<table style="{{ $sTabel }}">
    <tr>
        <td style="{{ $sTh }}" rowspan="3">No</td><td style="{{ $sTh }}" rowspan="3">Kode</td><td style="{{ $sTh }}" rowspan="3">Kabupaten/Kota</td>
        <td style="{{ $sTh }}" rowspan="3">Jumlah Publikasi Dijadwalkan Rilis bulan {{ $namaBulan[$bulan] }} {{ $tahun }}</td>
        <td style="{{ $sTh }}" rowspan="3">Jumlah Publikasi Dijadwalkan Rilis 1 Januari s.d. {{ $akhirHr }} {{ $namaBulan[$bulan] }} {{ $tahun }}</td>
        <td style="{{ $sTh }}" colspan="8">Rilis di Website</td>
        <td style="{{ $sTh }}" rowspan="3">Publikasi non ARC {{ $tahun }}</td>
    </tr>
    <tr>
        <td style="{{ $sTh }}" colspan="2">Rilis On Time</td><td style="{{ $sTh }}" colspan="2">Rilis Tidak On Time</td>
        <td style="{{ $sTh }}" colspan="2">Tidak Rilis</td><td style="{{ $sTh }}" colspan="2">Sudah Sesuai Standar</td>
    </tr>
    <tr>@foreach(range(1, 4) as $_)<td style="{{ $sTh }}">Jumlah</td><td style="{{ $sTh }}">%</td>@endforeach</tr>
    <tr>{!! $nomor(14) !!}</tr>
    @foreach($rows as $i => $b)
        @php $mr = $b['sesuai'] !== null && $b['sesuai'] < $batas ? $merah : ''; @endphp
        <tr>
            <td style="{{ $sTd }}">{{ $i + 1 }}</td><td style="{{ $sTd }}">{{ $b['kode'] }}</td><td style="{{ $sKiri }}">{{ $b['nama'] }}</td>
            <td style="{{ $sTd }}">{{ $v($b['jadwal_bulan']) }}</td><td style="{{ $sTd }}">{{ $v($b['jadwal_sd']) }}</td>
            <td style="{{ $sTd }}">{{ $v($b['on_time']) }}</td><td style="{{ $sTd }}">{{ $pc($b['on_time'], $b['jadwal_sd']) }}</td>
            <td style="{{ $sTd }}">{{ $v($b['tidak_on_time']) }}</td><td style="{{ $sTd }}">{{ $pc($b['tidak_on_time'], $b['jadwal_sd']) }}</td>
            <td style="{{ $sTd }}">{{ $v($b['tidak_rilis']) }}</td><td style="{{ $sTd }}">{{ $pc($b['tidak_rilis'], $b['jadwal_sd']) }}</td>
            <td style="{{ $sTd }}{{ $mr }}">{{ $v($b['sesuai']) }}</td><td style="{{ $sTd }}{{ $mr }}">{{ $pc($b['sesuai'], $b['jadwal_sd']) }}</td>
            <td style="{{ $sTd }}">{{ $v($b['non_arc']) }}</td>
        </tr>
    @endforeach
    <tr>
        <td style="{{ $sTot }}" colspan="3">Total</td>
        <td style="{{ $sTot }}">{{ $v($tt['jadwal_bulan']) }}</td><td style="{{ $sTot }}">{{ $v($tt['jadwal_sd']) }}</td>
        <td style="{{ $sTot }}">{{ $v($tt['on_time']) }}</td><td style="{{ $sTot }}">{{ $pc($tt['on_time'], $tt['jadwal_sd']) }}</td>
        <td style="{{ $sTot }}">{{ $v($tt['tidak_on_time']) }}</td><td style="{{ $sTot }}">{{ $pc($tt['tidak_on_time'], $tt['jadwal_sd']) }}</td>
        <td style="{{ $sTot }}">{{ $v($tt['tidak_rilis']) }}</td><td style="{{ $sTot }}">{{ $pc($tt['tidak_rilis'], $tt['jadwal_sd']) }}</td>
        <td style="{{ $sTot }}">{{ $v($tt['sesuai']) }}</td><td style="{{ $sTot }}">{{ $pc($tt['sesuai'], $tt['jadwal_sd']) }}</td>
        <td style="{{ $sTot }}">{{ $v($tt['non_arc']) }}</td>
    </tr>
</table>
</div></template>
