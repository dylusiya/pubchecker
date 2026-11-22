{{-- Garis waktu tenggat pra rilis H-10 → H-7 → H-3 → H supaya pengguna sadar & ingat, masing-masing dengan kapan
     terpenuhinya (tepat waktu / terlambat, dari $p['peristiwa']). Butuh $p (detail publikasi), $L (langkah per nomor).
     H-x dihitung hari kerja (libur dilewati) oleh SipotretDrafService::tenggat(). --}}
@php
    $tg = $p['tenggat'];
    $hariIniT = date('Y-m-d');
    $hari = fn($t) => \Carbon\Carbon::parse($t)->locale('id')->translatedFormat('l, d M Y');
    $hariPendek = fn($t) => \Carbon\Carbon::parse($t)->locale('id')->translatedFormat('D, d M Y');
    $peristiwa = collect($p['peristiwa'] ?? []);
    $adaDraf = collect($p['versi'])->where('ada_file', true)->isNotEmpty();

    // realisasi tiap tenggat: tanggal kejadian yang memenuhinya
    $drafPertama = $peristiwa->firstWhere('kode', 'draf')['waktu'] ?? null;
    $sprpTerima  = $peristiwa->where('kode', 'sprp_terima')->last()['waktu'] ?? null;
    // H-3 terpenuhi saat yang terakhir dari keduanya: SPRP diterima & upload portal dicentang
    $portalWkt   = $p['portal']['waktu'] ?? null;
    $realH3      = ['SPRP & portal beres', $sprpTerima && $portalWkt ? max($sprpTerima, $portalWkt) : null]; // salah satu tak tercatat → "tidak tercatat"
    $tglRilis    = $peristiwa->where('kode', 'rilis')->last()['waktu'] ?? $peristiwa->firstWhere('kode', 'web')['waktu'] ?? null;

    $milestone = $tg ? [
        // kode, judul, keterangan, tanggal, terpenuhi?, wajib?, [label realisasi, tanggal realisasi]
        ['H-10', 'Awal kirim draf', 'PJK mulai bisa kirim draf', $tg['h10'], $adaDraf, false, null],
        ['H-7',  'Batas kirim draf', 'PJK upload draf', $tg['h7'], $adaDraf, true, ['Draf pertama', $drafPertama]],
        ['H-3',  'Final, SPRP & upload portal', 'SPRP diterima & upload portal dicentang', $tg['h3'], $L[4]['status'] === 'selesai' && $L[5]['status'] === 'selesai', true, $realH3],
        ['H',    'Rilis', 'Tandai rilis & tautkan ke Web BPS', $p['tgl_rilis'], $p['rilis'], true, ['Dirilis', $tglRilis]],
    ] : [];
    // satu titik "berikutnya": tenggat pertama yang belum terpenuhi dan belum lewat
    $berikut = null;
    foreach ($milestone as $i => $m) if (!$m[4] && $m[5] && $m[3] >= $hariIniT) { $berikut = $i; break; }
    $sisaT = fn($t) => (int) round((strtotime(substr($t, 0, 10)) - strtotime($hariIniT)) / 86400);
    $teksSisa = fn($n) => $n === 0 ? 'hari ini' : ($n === 1 ? 'besok' : ($n > 0 ? $n . ' hari lagi' : 'lewat ' . abs($n) . ' hari'));
    $telat = fn($real, $batas) => (int) round((strtotime(substr($real, 0, 10)) - strtotime($batas)) / 86400);

@endphp
@if(!$tg)
    <div class="card-body border-top py-2 small text-warning"><i class="ti ti-calendar-off me-1"></i> Belum ada rencana rilis.</div>
@else
<div class="card-body border-top py-3">
    <div class="tenggat-garis" title="H-10, H-7, H-3 = hari kerja sebelum rilis (Sabtu, Minggu &amp; libur dilewati)">
        @foreach($milestone as $i => [$kode, $judul, $ket, $t, $beres, $wajib, $real])
            @php
                $n = $sisaT($t);
                $st = match (true) {
                    $beres                    => 'beres',
                    $i === $berikut           => 'berikut',
                    $t < $hariIniT && $wajib  => 'lewat',
                    $t < $hariIniT            => 'lalu',
                    default                   => 'nanti',
                };
                [$w, $ik] = ['beres' => ['success', 'ti-check'], 'berikut' => [$n <= 3 ? 'warning' : 'primary', 'ti-bell-ringing'],
                             'lewat' => ['danger', 'ti-alert-triangle'], 'lalu' => ['secondary', 'ti-point'], 'nanti' => ['secondary', 'ti-point']][$st];
            @endphp
            <div class="tenggat-titik tenggat-{{ $st }}">
                <span class="tenggat-ikon bg-{{ $w }}{{ in_array($st, ['nanti', 'lalu'], true) ? '-lt' : ' text-white' }}"><i class="ti {{ $ik }}"></i></span>
                <div class="text-uppercase fw-bold text-{{ $w }}" style="font-size:11px; letter-spacing:.05em;">{{ $kode }} · {{ $judul }}</div>
                <div class="fw-bold {{ $st === 'berikut' ? 'fs-3' : '' }}">{{ $hari($t) }}</div>
                <div class="small">
                    @if($beres)
                        @if($real && $real[1])
                            @php $d = $telat($real[1], $t); @endphp
                            <span class="text-success"><i class="ti ti-check"></i> {{ $real[0] }} {{ $hariPendek($real[1]) }}</span>
                            <span class="d-block {{ $d > 0 ? 'text-danger' : 'text-success' }}">{{ $d > 0 ? 'terlambat ' . $d . ' hari' : 'tepat waktu' }}</span>
                        @else
                            <span class="text-success"><i class="ti ti-check"></i> terpenuhi</span>
                        @endif
                    @elseif($st === 'lewat')<span class="text-danger fw-semibold">{{ $teksSisa($n) }}, belum terpenuhi</span>
                    @elseif($st === 'berikut')<span class="badge bg-{{ $w }} text-white">{{ $teksSisa($n) }}</span>
                    @elseif($st === 'lalu')<span class="text-secondary">sudah lewat</span>
                    @else<span class="text-secondary">{{ $teksSisa($n) }}</span>@endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

@once
    @push('styles')
    <style>
        .tenggat-garis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; position: relative; }
        .tenggat-garis::before { content: ''; position: absolute; top: .95rem; left: 1rem; right: 1rem; border-top: 2px dashed var(--tblr-border-color); }
        .tenggat-titik { position: relative; padding: 0 .25rem; }
        .tenggat-ikon { position: relative; display: inline-flex; width: 2rem; height: 2rem; border-radius: 50%; align-items: center; justify-content: center; margin-bottom: .35rem; font-size: 1rem; }
        .tenggat-berikut { background: var(--tblr-bg-surface-secondary, rgba(0,0,0,.02)); border-radius: 6px; padding: .25rem .5rem .5rem; outline: 2px solid var(--tblr-warning-lt); }
        .tenggat-nanti, .tenggat-lalu { opacity: .75; }
        @media (max-width: 767.98px) {
            .tenggat-garis { grid-template-columns: 1fr 1fr; }
            .tenggat-garis::before { display: none; }
        }
    </style>
    @endpush
@endonce
