@extends('layouts.app')

@section('title', 'Detail Pra Rilis')
@section('pretitle', 'Pra Rilis · draf dari SIPOTRET')
@section('page-title', $p['judul'])
@section('page-subtitle', $p['judul_en'] ?? '')
@section('page-actions')
    <a href="{{ route('checker.sipotret_draf.index', ['tahun' => $p['tahun']]) }}" class="btn">
        <i class="ti ti-arrow-left"></i> Kembali
    </a>
    <a href="{{ rtrim((string) config('services.sipotret.file_url'), '/') . '/publikasi/upload/' . $p['pub_id'] }}" target="_blank" rel="noopener" class="btn">
        <i class="ti ti-external-link"></i> Buka di SIPOTRET
    </a>
@endsection

@section('content')
@php
    $tgl    = fn($t) => $t ? \Carbon\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '-';
    $waktu  = fn($t) => $t ? \Carbon\Carbon::parse($t)->format('d/m/Y H:i') : '-';
    $isi    = fn($v) => filled($v) ? e($v) : '<span class="text-secondary">-</span>';
    $hariIni = date('Y-m-d');
    $sisaHari = fn($t) => $t ? (int) round((strtotime($t) - strtotime($hariIni)) / 86400) : null;
    $hitung = fn($n) => $n === null ? '' : ($n === 0 ? 'hari ini' : ($n > 0 ? $n . ' hari lagi' : 'lewat ' . abs($n) . ' hari'));
    $levelWarna = ['MAYOR' => 'bg-red-lt', 'MINOR' => 'bg-yellow-lt'];

    $boleh       = $boleh ?? true;
    $isAdmin     = (bool) auth()->user()?->isAdmin();
    $master      = $master ?? ['katalog' => [], 'issn' => []];
    $bolehEdit   = $boleh && Route::has('checker.sipotret_draf.kelengkapan');
    $bolehAbs    = $boleh && Route::has('checker.sipotret_draf.abstraksi');
    $linkSipotret = rtrim((string) config('services.sipotret.file_url'), '/') . '/publikasi/upload/' . $p['pub_id'];
    $linkPortal  = config('services.sipotret.portal_publikasi_url');
    $versiFile   = collect($p['versi'])->where('ada_file', true)->values();
    $drafPeriksa = collect($p['versi'])->firstWhere('pdf', true);
    $versiFinal  = collect($p['versi'])->firstWhere('final', true) ?? $drafPeriksa;
    $sprp        = collect($p['kelengkapan']['berkas'])->firstWhere('jenis', 'spr');
    $tg          = $p['tenggat'];

    $alur  = $p['langkah'];
    // langkah 3 dari SIPOTRET hanya tahu "belum diperiksa"; bila versi terbaru sudah diperiksa di pubchecker tapi belum dikirim,
    // keterangannya diganti supaya tidak bertentangan dengan daftar versi
    $hasilTerbaru = $drafPeriksa ? $hasil->where('sipotret_tr_id', $drafPeriksa['tr_id'])->sortByDesc('created_at')->first() : null;
    $tinggalKirim = $hasilTerbaru && !$hasilTerbaru->sipotret_terkirim_at && $alur['langkah'][2]['status'] !== 'selesai';
    if ($tinggalKirim) $alur['langkah'][2]['ket'] = $drafPeriksa['flag'] . ' sudah diperiksa di pubchecker, tinggal kirim ke SIPOTRET';
    $L     = collect($alur['langkah'])->keyBy('no');
    $lewat = collect($alur['langkah'])->where('status', 'terlewat');
    // panel yang dibuka: error validasi form kelengkapan → 1; lainnya langkah berikutnya / yang terlewat / terakhir
    $buka  = (isset($errors) && $errors->any()) ? 1 : ($alur['sekarang'] ?? $lewat->first()['no'] ?? 6);
    $gaya  = [
        'selesai'  => ['success',   'ti-check',          'Selesai'],
        'aktif'    => ['primary',   null,                'Sekarang'],
        'terlewat' => ['danger',    'ti-alert-triangle', 'Terlewat'],
        'menunggu' => ['secondary', null,                'Belum'],
    ];
@endphp

@if(isset($errors) && $errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(!$boleh)
    <div class="alert alert-warning"><i class="ti ti-lock me-1"></i> {{ $tugas->pesanTolak() }} Halaman ini hanya bisa dilihat.</div>
@endif

{{-- ============ Ringkasan: langkah berikutnya + info utama ============ --}}
@php
    $kini = $alur['sekarang'] ? $L[$alur['sekarang']] : null;
    $warnaKini = !$kini ? ($lewat->isNotEmpty() ? 'danger' : 'success') : ($kini['lewat'] ? 'danger' : 'primary');
@endphp
<div class="card mb-3" id="kartuAlur">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-lg">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-lg rounded-circle bg-{{ $warnaKini }} text-white flex-shrink-0">
                        @if($kini)<span class="fs-2 fw-bold">{{ $kini['no'] }}</span>
                        @else<i class="ti {{ $lewat->isNotEmpty() ? 'ti-alert-triangle' : 'ti-circle-check' }} fs-1"></i>@endif
                    </span>
                    <div>
                        <div class="text-uppercase text-secondary fw-semibold" style="font-size:11px; letter-spacing:.04em;">
                            @if($kini) Langkah berikutnya · {{ $alur['selesai'] }}/6 selesai
                            @elseif($lewat->isNotEmpty()) Hampir selesai · ada yang terlewat
                            @else Semua langkah selesai @endif
                        </div>
                        <div class="fs-2 fw-bold lh-sm">
                            {{ $kini ? $kini['judul'] : ($lewat->isNotEmpty() ? 'Lengkapi: ' . $lewat->pluck('judul')->implode(', ') : 'Publikasi sudah rilis & tertaut') }}
                        </div>
                        @if($kini)
                            <div class="text-secondary">
                                <span class="badge {{ $kini['oleh'] === 'PJK' ? 'bg-orange-lt' : 'bg-blue-lt' }} me-1">{{ $kini['oleh'] === 'PJK' ? 'Menunggu PJK' : 'Tugas pemeriksa' }}</span>
                                @if($kini['batas'])
                                    <span class="{{ $kini['lewat'] ? 'text-danger fw-semibold' : '' }}"><i class="ti ti-calendar-due"></i> {{ $tgl($kini['batas']) }} ({{ $hitung($sisaHari($kini['batas'])) }})</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-auto d-flex flex-wrap gap-2">
                {{-- satu tombol utama sesuai langkah berikutnya --}}
                @php $nKini = $kini['no'] ?? $lewat->first()['no'] ?? null; @endphp
                @if($nKini === 3 && $boleh && $tinggalKirim)
                    <button type="button" class="btn btn-purple btn-lg" data-buka-langkah="3" data-fokus="#kirim-v{{ $drafPeriksa['tr_id'] }}"><i class="ti ti-send me-1"></i> Kirim {{ $drafPeriksa['flag'] }} ke SIPOTRET</button>
                @elseif($nKini === 3 && $boleh && $drafPeriksa)
                    <a href="{{ route('checker.index', ['draf' => $drafPeriksa['tr_id']]) }}" class="btn btn-primary btn-lg"><i class="ti ti-player-play me-1"></i> Periksa {{ $drafPeriksa['flag'] }}</a>
                @elseif($nKini === 2)
                    <a href="{{ $linkSipotret }}" target="_blank" rel="noopener" class="btn btn-lg"><i class="ti ti-external-link me-1"></i> Ingatkan PJK</a>
                    @if($versiFile->isNotEmpty() && $bolehAbs && (!$p['abstraksi'] || !$p['abstraksi_en']))
                        <button type="button" class="btn btn-primary btn-lg" data-buka-langkah="2" data-fokus="#formAbstraksi"><i class="ti ti-pencil me-1"></i> Lengkapi abstraksi</button>
                    @elseif($isAdmin && !$p['rilis'])<button type="button" class="btn btn-primary btn-lg" data-buka-langkah="2" data-fokus="#formUpload"><i class="ti ti-upload me-1"></i> Upload atas nama PJK</button>@endif
                @elseif($nKini === 4 && $boleh && $isAdmin && $sprp && in_array($sprp['hasil'], [null, 'ditolak'], true))
                    <button type="button" class="btn btn-primary btn-lg" data-buka-langkah="4" data-fokus="#formSprp"><i class="ti ti-file-upload me-1"></i> Upload SPRP atas nama PJK</button>
                @elseif($nKini)
                    @php $teksCta = [1 => ['ti-pencil', 'Isi kelengkapan'], 4 => ['ti-file-certificate', 'Putuskan SPRP'], 5 => ['ti-upload', 'Upload ke portal'], 6 => ['ti-rocket', 'Tandai rilis']][$nKini] ?? null; @endphp
                    @if($teksCta && $boleh)
                        <button type="button" class="btn btn-{{ $nKini === 6 ? 'success' : 'primary' }} btn-lg" data-buka-langkah="{{ $nKini }}"><i class="ti {{ $teksCta[0] }} me-1"></i> {{ $teksCta[1] }}</button>
                    @endif
                @endif
            </div>
        </div>
    </div>
    <div class="progress progress-sm rounded-0">
        <div class="progress-bar bg-{{ $lewat->isNotEmpty() ? 'danger' : 'success' }}" style="width: {{ round($alur['selesai'] / 6 * 100) }}%"></div>
    </div>
    {{-- garis waktu tenggat: H-10 → H-7 → H-3 → H --}}
    @include('checker.partials.pra-rilis-tenggat', ['p' => $p, 'L' => $L])
    {{-- info utama satu baris --}}
    <div class="card-footer py-2">
        <div class="d-flex flex-wrap align-items-center gap-3 small">
            <span><span class="text-secondary">PJK</span> {{ $p['pjk'] ?? '-' }}</span>
            <span><span class="text-secondary">Ketua tim</span> {{ $p['ketua_tim'] ?? '-' }}</span>
            <span>{{ $p['status'] === 'arc' ? 'ARC' : 'Non ARC' }} · {{ $p['tahun'] }}</span>
            @if($bolehEdit)
                <button type="button" class="btn btn-sm btn-ghost-primary py-0 px-1" data-buka-langkah="1" data-fokus="#isianIdentitas" title="Ubah judul, jenis ARC, PJK & ketua tim">
                    <i class="ti ti-pencil"></i> ubah
                </button>
            @endif
            @if($p['rilis'])<span class="badge bg-green-lt">Sudah rilis</span>@endif
            {{-- penugasan pemeriksa --}}
            @if($pemeriksa || $tugas)
                <span class="ms-lg-auto d-flex align-items-center gap-2" id="kartuTugas">
                    <span class="text-secondary"><i class="ti ti-user-check"></i> Pemeriksa</span>
                    <span id="tugasBadge">
                        @if($tugasRingkas)
                            <span class="badge {{ $tugasRingkas['saya'] ? 'bg-green-lt' : 'bg-purple-lt' }}" title="{{ $tugas->penugas ? 'ditugaskan ' . $tugas->penugas->full_name . ' · ' . $tugas->updated_at?->format('d/m/Y') : '' }}">{{ $tugasRingkas['saya'] ? 'Tugas saya' : $tugasRingkas['nama'] }}</span>
                        @else
                            <span class="text-secondary">belum ditugaskan</span>
                        @endif
                    </span>
                    @if($pemeriksa)
                        <span class="input-group input-group-sm" style="width:auto;">
                            <select class="form-select form-select-sm" id="tugasPilih">
                                <option value="">Tugaskan ke…</option>
                                @foreach($pemeriksa as $u)
                                    <option value="{{ $u['id'] }}" @selected($tugasRingkas && $tugasRingkas['user_id'] === $u['id'])>{{ $u['nama'] }}{{ $u['role'] === 'admin' ? ' (admin)' : '' }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn" id="tugasSimpan" title="Simpan penugasan"><i class="ti ti-check"></i></button>
                            @if($tugasRingkas)<button type="button" class="btn" id="tugasLepas" title="Lepas penugasan"><i class="ti ti-user-x"></i></button>@endif
                        </span>
                    @endif
                </span>
            @endif
        </div>
    </div>
</div>

{{-- ============ Stepper (kiri) + panel kerja langkah (kanan) ============ --}}
<div class="row g-3 mb-3">
    <div class="col-lg-4 col-xl-3">
        <div class="card stepper-wrap">
            <div class="list-group list-group-flush" id="navLangkah">
                @foreach($alur['langkah'] as $l)
                    @php [$w, $ik, $nm] = $gaya[$l['status']]; @endphp
                    <a href="#langkah-{{ $l['no'] }}" class="list-group-item list-group-item-action stepper-item stepper-{{ $l['status'] }} {{ $l['no'] === $buka ? 'active' : '' }}" data-langkah-nav="{{ $l['no'] }}">
                        <div class="d-flex gap-2">
                            <span class="avatar avatar-sm rounded-circle flex-shrink-0 bg-{{ $w }}{{ $l['status'] === 'menunggu' ? '-lt' : ' text-white' }}">
                                @if($ik)<i class="ti {{ $ik }}"></i>@else{{ $l['no'] }}@endif
                            </span>
                            <div class="flex-fill lh-sm">
                                <div class="d-flex justify-content-between gap-1">
                                    <span class="fw-semibold">{{ $l['judul'] }}</span>
                                    @if($l['status'] !== 'menunggu')<span class="text-{{ $w }} small text-nowrap">{{ $nm }}</span>@endif
                                </div>
                                <div class="small text-secondary mt-1">{{ $l['oleh'] }}</div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-8 col-xl-9">
        {{-- ---------- 1. Kelengkapan ---------- --}}
        @php $l = $L[1]; @endphp
        <div class="card panel-langkah {{ $buka === 1 ? '' : 'd-none' }}" id="langkah-1" data-panel="1">
            @include('checker.partials.pra-rilis-panel-head', ['l' => $l, 'gaya' => $gaya, 'tgl' => $tgl])
            <div class="card-body">
                @if($bolehEdit)
                    <form method="post" action="{{ route('checker.sipotret_draf.kelengkapan', $p['pub_id']) }}">
                        @csrf
                        @php $val = fn($k, $v) => old($k, $v); @endphp
                        {{-- identitas publikasi (seperti form edit publikasi SIPOTRET) --}}
                        <div class="text-uppercase text-secondary fw-semibold mb-2" style="font-size:11px; letter-spacing:.04em;">Identitas publikasi</div>
                        <div class="row g-3 mb-4" id="isianIdentitas">
                            <div class="col-md-9">
                                <label class="form-label" for="isiJudul">Judul publikasi <span class="text-danger">*</span></label>
                                <input type="text" name="pub_judul" id="isiJudul" maxlength="500" class="form-control" required value="{{ $val('pub_judul', $p['judul']) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="isiJenis">Jenis</label>
                                <select name="pub_status" id="isiJenis" class="form-select">
                                    <option value="arc" @selected($val('pub_status', $p['status']) === 'arc')>ARC</option>
                                    <option value="non_arc" @selected($val('pub_status', $p['status']) !== 'arc')>Non ARC</option>
                                </select>
                            </div>
                            @if($master['users'] ?? [])
                                <div class="col-md-6">
                                    <label class="form-label">PJK <span class="text-danger">*</span></label>
                                    @include('checker.partials.combo', ['name' => 'us_id', 'value' => (string) $val('us_id', $p['pjk_id']), 'data' => $master['users'],
                                        'placeholder' => 'Ketik nama atau email…', 'kode' => false, 'wajib' => true])
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ketua tim</label>
                                    @include('checker.partials.combo', ['name' => 'spv_id', 'value' => (string) $val('spv_id', $p['ketua_tim_id']), 'data' => $master['users'],
                                        'placeholder' => 'Ketik nama atau email…', 'kode' => false, 'kosong' => 'Kosongkan (belum ada ketua tim)'])
                                </div>
                            @endif
                        </div>

                        <div class="text-uppercase text-secondary fw-semibold mb-2" style="font-size:11px; letter-spacing:.04em;">Kelengkapan</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Nomor publikasi <span class="text-danger">*</span></label>
                                <input type="text" name="pub_no" maxlength="100" class="form-control font-monospace" value="{{ $val('pub_no', $p['nomor']) }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Volume</label>
                                <input type="text" name="pub_volume" maxlength="50" class="form-control" value="{{ $val('pub_volume', $p['volume']) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ISSN <span class="text-secondary fw-normal">· opsional</span></label>
                                @if($master['issn'])
                                    @include('checker.partials.combo', ['name' => 'pub_issn', 'value' => $val('pub_issn', $p['issn']), 'data' => $master['issn'],
                                        'placeholder' => 'Ketik nomor atau judul ISSN…', 'kosong' => 'Kosongkan (tidak punya ISSN)'])
                                @else
                                    <input type="text" name="pub_issn" maxlength="50" class="form-control font-monospace" value="{{ $val('pub_issn', $p['issn']) }}">
                                @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label">Nomor katalog <span class="text-danger">*</span></label>
                                @if($master['katalog'])
                                    @include('checker.partials.combo', ['name' => 'pub_no_katalog', 'value' => $val('pub_no_katalog', $p['katalog']), 'data' => $master['katalog'],
                                        'placeholder' => 'Ketik nomor atau nama katalog…', 'kosong' => 'Kosongkan (belum ada nomor katalog)'])
                                @else
                                    <input type="text" name="pub_no_katalog" maxlength="100" class="form-control font-monospace" value="{{ $val('pub_no_katalog', $p['katalog']) }}">
                                @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label">Judul (EN) <span class="text-danger">*</span></label>
                                <input type="text" name="pub_judul_en" maxlength="500" class="form-control" value="{{ $val('pub_judul_en', $p['judul_en']) }}">
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Simpan ke SIPOTRET</button>
                        </div>
                    </form>
                @else
                    <div class="datagrid">
                        <div class="datagrid-item"><div class="datagrid-title">Nomor publikasi</div><div class="datagrid-content font-monospace">{!! $isi($p['nomor']) !!}</div></div>
                        <div class="datagrid-item"><div class="datagrid-title">Volume</div><div class="datagrid-content">{!! $isi($p['volume']) !!}</div></div>
                        <div class="datagrid-item"><div class="datagrid-title">Nomor katalog</div><div class="datagrid-content font-monospace">{!! $isi($p['katalog']) !!}</div></div>
                        <div class="datagrid-item"><div class="datagrid-title">ISSN</div><div class="datagrid-content font-monospace">{!! $isi($p['issn']) !!}</div></div>
                    </div>
                @endif
                @if($p['nama_katalog'] || ($p['issn_link'] && preg_match('#^https?://#i', $p['issn_link'])))
                    <div class="small text-secondary mt-3 pt-3 border-top">
                        @if($p['nama_katalog'])<div><i class="ti ti-book"></i> Katalog: {{ $p['nama_katalog'] }}@if($p['nama_katalog_en']) <span class="fst-italic">/ {{ $p['nama_katalog_en'] }}</span>@endif</div>@endif
                        @if($p['issn_link'] && preg_match('#^https?://#i', $p['issn_link']))<div><a href="{{ $p['issn_link'] }}" target="_blank" rel="noopener"><i class="ti ti-external-link"></i> Halaman ISSN</a></div>@endif
                    </div>
                @endif
            </div>
        </div>

        {{-- ---------- 2. Draf & abstraksi (PJK) ---------- --}}
        @php $l = $L[2]; @endphp
        <div class="card panel-langkah {{ $buka === 2 ? '' : 'd-none' }}" id="langkah-2" data-panel="2">
            @include('checker.partials.pra-rilis-panel-head', ['l' => $l, 'gaya' => $gaya, 'tgl' => $tgl])
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="fw-semibold mb-2"><i class="ti ti-files me-1"></i> Draf diupload</div>
                        @if($versiFile->isEmpty())
                            <div class="text-secondary small">Belum ada draf.</div>
                        @else
                            <div class="list-group list-group-flush border rounded">
                                @foreach($versiFile as $v)
                                    <div class="list-group-item py-2 d-flex align-items-center gap-2">
                                        <span class="badge {{ $v['final'] ? 'bg-green-lt' : 'bg-primary-lt' }}">{{ $v['flag'] ?? '-' }}</span>
                                        <span class="small text-secondary flex-fill">{{ $waktu($v['tanggal']) }}@if($v['catatan_pjk'])<span class="d-block text-truncate" style="max-width:260px;" title="{{ $v['catatan_pjk'] }}">{{ $v['catatan_pjk'] }}</span>@endif</span>
                                        @if($v['pdf'])<a href="{{ route('checker.sipotret_draf.pdf', $v['tr_id']) }}" target="_blank" class="btn btn-sm btn-icon btn-ghost-secondary" title="Lihat PDF"><i class="ti ti-eye"></i></a>@endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <div class="fw-semibold mb-2"><i class="ti ti-align-left me-1"></i> Abstraksi</div>
                        @if($bolehAbs)
                            <form method="post" action="{{ route('checker.sipotret_draf.abstraksi', $p['pub_id']) }}" id="formAbstraksi">
                                @csrf
                                @foreach([['abstraksi_id', 'Abstraksi', $p['abstraksi'], 'Isi abstraksi bahasa Indonesia…'], ['abstraksi_en', 'Abstract (EN)', $p['abstraksi_en'], 'Fill abstract in English…']] as [$nm, $lbl, $teks, $ph])
                                    <label class="form-label small mb-1" for="{{ $nm }}">
                                        @if($teks)<i class="ti ti-circle-check text-success"></i>@else<i class="ti ti-circle-x text-danger"></i>@endif
                                        {{ $lbl }} {{ $teks ? '' : '· belum diisi' }}
                                    </label>
                                    <textarea name="{{ $nm }}" id="{{ $nm }}" rows="5" class="form-control form-control-sm mb-2 {{ $nm === 'abstraksi_en' ? 'fst-italic' : '' }}"
                                              placeholder="{{ $ph }}" style="text-align:justify;" data-asli="{{ $teks }}">{{ old($nm, $teks) }}</textarea>
                                @endforeach
                                <button class="btn btn-sm btn-primary" id="absSimpan" disabled><i class="ti ti-device-floppy me-1"></i> Simpan abstraksi ke SIPOTRET</button>
                                <span class="small text-secondary ms-1" id="absInfo"></span>
                            </form>
                        @else
                            @foreach([['Abstraksi', $p['abstraksi'], ''], ['Abstract (EN)', $p['abstraksi_en'], 'fst-italic']] as [$lbl, $teks, $cls])
                                <details class="border rounded p-2 mb-2" @if(!$teks) open @endif>
                                    <summary class="small">
                                        @if($teks)<i class="ti ti-circle-check text-success"></i>@else<i class="ti ti-circle-x text-danger"></i>@endif
                                        {{ $lbl }} {{ $teks ? '' : '· belum diisi PJK' }}
                                    </summary>
                                    @if($teks)<div class="small mt-2 {{ $cls }}" style="white-space:pre-line; text-align:justify; max-height:220px; overflow:auto;">{{ $teks }}</div>@endif
                                </details>
                            @endforeach
                        @endif
                    </div>
                </div>

                {{-- admin: upload atas nama PJK (mis. PJK telat) --}}
                @if(!$p['rilis'] && $isAdmin)
                    <details class="mt-3 border rounded" id="kartuUpload" @if($versiFile->isEmpty() && $tg && $tg['h7'] < $hariIni) open @endif>
                        <summary class="p-2 fw-semibold"><i class="ti ti-upload me-1"></i> Upload draf atas nama PJK</summary>
                        <div class="p-3 pt-0">
                            <form method="post" action="{{ route('checker.sipotret_draf.upload', $p['pub_id']) }}" enctype="multipart/form-data" id="formUpload"
                                  data-confirm="Upload draf ini ke SIPOTRET atas nama PJK? Tercatat sebagai versi baru dan PJK diberi notifikasi." data-confirm-ok="Upload">
                                @csrf
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">File draf (PDF, maks. 30 MB)</label>
                                        <input type="file" name="file" accept="application/pdf,.pdf" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">Catatan (opsional)</label>
                                        <input type="text" name="catatan" maxlength="2000" class="form-control form-control-sm" placeholder="mis. dikirim PJK lewat WA">
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary btn-sm" name="periksa" value="1"><i class="ti ti-player-play me-1"></i> Upload &amp; Periksa</button>
                                    <button class="btn btn-sm" name="periksa" value="0"><i class="ti ti-upload me-1"></i> Upload saja</button>
                                </div>
                            </form>
                        </div>
                    </details>
                @endif
            </div>
        </div>

        {{-- ---------- 3. Periksa draf: satu baris per versi (status, temuan, aksi) ---------- --}}
        @php
            $l = $L[3];
            // per versi: hasil pubchecker terbaru + jumlah pemeriksaan; temuan dari pubchecker, atau dari SIPOTRET bila diperiksa langsung di sana
            $sudahFinal = $L[3]['status'] === 'selesai' && ($p['rilis'] || $L[4]['status'] === 'selesai' || collect($p['versi'])->contains('final', true));
            $barisVersi = collect($p['versi'])->where('ada_file', true)->values()->map(function ($v) use ($hasil, $sudahFinal) {
                $hs = $hasil->where('sipotret_tr_id', $v['tr_id'])->sortByDesc('created_at')->values();
                $h  = $hs->first();
                $status = match (true) {
                    $h && $h->sipotret_terkirim_at => ['success', 'ti-send', 'Terkirim ke SIPOTRET'],
                    (bool) $h                      => ['warning', 'ti-send-off', 'Sudah diperiksa, belum dikirim'],
                    $v['tanggal_ipds'] || $v['ada_revisi'] => ['success', 'ti-circle-check', 'Diperiksa di SIPOTRET'],
                    $v['ada_versi_baru']           => ['secondary', 'ti-minus', 'Tidak diperiksa (ada versi baru)'],
                    $sudahFinal                    => ['secondary', 'ti-minus', 'Tidak diperiksa (sudah final)'],
                    default                        => ['primary', 'ti-clock', 'Belum diperiksa'],
                };
                return ['v' => $v, 'h' => $h, 'n' => $hs->count(), 'status' => $status];
            });
            $versiTerbaru = $barisVersi->first();
            $versiLama    = $barisVersi->slice(1);
        @endphp
        <div class="card panel-langkah {{ $buka === 3 ? '' : 'd-none' }}" id="langkah-3" data-panel="3">
            @include('checker.partials.pra-rilis-panel-head', ['l' => $l, 'gaya' => $gaya, 'tgl' => $tgl])
            @if(!$versiTerbaru)
                <div class="card-body text-secondary">Belum ada draf untuk diperiksa.</div>
            @else
                <div class="list-group list-group-flush">
                    @include('checker.partials.pra-rilis-versi', ['bv' => $versiTerbaru, 'terbaru' => true])
                </div>
                @if($versiLama->isNotEmpty())
                    <details class="border-top">
                        <summary class="px-3 py-2 small text-secondary">Versi sebelumnya ({{ $versiLama->count() }})</summary>
                        <div class="list-group list-group-flush">
                            @foreach($versiLama as $bv)
                                @include('checker.partials.pra-rilis-versi', ['bv' => $bv, 'terbaru' => false])
                            @endforeach
                        </div>
                    </details>
                @endif
            @endif
        </div>

        {{-- ---------- 4. SPRP ---------- --}}
        @php $l = $L[4]; @endphp
        <div class="card panel-langkah {{ $buka === 4 ? '' : 'd-none' }}" id="langkah-4" data-panel="4">
            @include('checker.partials.pra-rilis-panel-head', ['l' => $l, 'gaya' => $gaya, 'tgl' => $tgl])
            <div class="card-body">
                @foreach($p['kelengkapan']['berkas'] as $b)
                    @php
                        [$cls, $ikon, $teks] = match ($b['hasil']) {
                            'diterima' => ['success', 'circle-check', 'Diterima'],
                            'ditolak'  => ['danger', 'circle-x', 'Ditolak, menunggu PJK upload ulang'],
                            'menunggu' => ['warning', 'clock', 'Menunggu keputusan'],
                            default    => ['secondary', 'minus', 'Belum diupload PJK'],
                        };
                        $utama = $b['jenis'] === 'spr';
                    @endphp
                    <div class="d-flex flex-wrap align-items-center gap-3 p-3 rounded border mb-2">
                        <span class="avatar bg-{{ $cls }}-lt"><i class="ti ti-{{ $ikon }} text-{{ $cls }}"></i></span>
                        <div class="flex-fill">
                            <div class="fw-semibold">{{ $b['label'] }}</div>
                            <div class="small text-{{ $cls === 'secondary' ? 'secondary' : $cls }}">{{ $teks }}</div>
                        </div>
                        @if($b['ada_file'])
                            <a href="{{ route('checker.sipotret_draf.berkas', [$p['pub_id'], $b['jenis']]) }}" target="_blank" class="btn btn-sm">
                                <i class="ti ti-{{ $b['ext'] === 'pdf' ? 'eye' : 'download' }} me-1"></i>{{ $b['ext'] === 'pdf' ? 'Lihat' : 'Unduh' }}
                            </a>
                        @endif
                        @if($utama && $b['ada_file'] && $boleh)
                            @if($b['hasil'] !== 'diterima')
                                <form method="post" action="{{ route('checker.sipotret_draf.sprp', $p['pub_id']) }}"
                                      data-confirm="Terima berkas SPRP? Publikasi ditandai siap rilis di SIPOTRET dan PJK diberi notifikasi." data-confirm-ok="Terima">
                                    @csrf <input type="hidden" name="aksi" value="terima">
                                    <button class="btn btn-sm btn-success"><i class="ti ti-check me-1"></i>Terima &amp; finalkan</button>
                                </form>
                            @endif
                            @if($b['hasil'] !== 'ditolak')
                                <form method="post" action="{{ route('checker.sipotret_draf.sprp', $p['pub_id']) }}"
                                      data-confirm="Tolak berkas SPRP? PJK diberi notifikasi untuk memperbaiki." data-confirm-ok="Tolak" data-confirm-danger>
                                    @csrf <input type="hidden" name="aksi" value="tolak">
                                    <button class="btn btn-sm btn-outline-danger"><i class="ti ti-x me-1"></i>Tolak</button>
                                </form>
                            @endif
                        @endif
                    </div>
                @endforeach

                {{-- admin: upload SPRP atas nama PJK (selama belum diterima) --}}
                @if($isAdmin && $boleh && $sprp && $sprp['hasil'] !== 'diterima' && Route::has('checker.sipotret_draf.sprp_upload'))
                    <details class="mt-3 border rounded" id="kartuSprp" @if(!$sprp['ada_file'] || $sprp['hasil'] === 'ditolak') open @endif>
                        <summary class="p-2 fw-semibold"><i class="ti ti-file-upload me-1"></i> {{ $sprp['ada_file'] ? 'Ganti' : 'Upload' }} SPRP atas nama PJK</summary>
                        <div class="p-3 pt-0">
                            <form method="post" action="{{ route('checker.sipotret_draf.sprp_upload', $p['pub_id']) }}" enctype="multipart/form-data" id="formSprp"
                                  data-confirm="Upload berkas SPRP ini ke SIPOTRET atas nama PJK?{{ $sprp['ada_file'] ? ' File SPRP yang lama diganti.' : '' }} PJK diberi notifikasi." data-confirm-ok="Upload">
                                @csrf
                                <div class="d-flex flex-wrap align-items-end gap-2">
                                    <div class="flex-fill" style="min-width:240px;">
                                        <label class="form-label small mb-1">File SPRP (PDF/JPG/PNG, maks. 30 MB)</label>
                                        <input type="file" name="file" accept="application/pdf,.pdf,image/jpeg,image/png" class="form-control form-control-sm" required>
                                    </div>
                                    <button class="btn btn-sm btn-primary"><i class="ti ti-upload me-1"></i> Upload ke SIPOTRET</button>
                                </div>
                            </form>
                        </div>
                    </details>
                @endif
            </div>
        </div>

        {{-- ---------- 5. Upload ke portal publikasi BPS ---------- --}}
        @php $l = $L[5]; @endphp
        <div class="card panel-langkah {{ $buka === 5 ? '' : 'd-none' }}" id="langkah-5" data-panel="5">
            @include('checker.partials.pra-rilis-panel-head', ['l' => $l, 'gaya' => $gaya, 'tgl' => $tgl])
            <div class="card-body">
                <ol class="langkah-cek mb-3">
                    <li>
                        <div class="fw-semibold">Siapkan berkas</div>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            {{-- PDF final --}}
                            <div class="border rounded p-2 d-flex align-items-center gap-2">
                                <span class="avatar avatar-sm bg-red-lt"><i class="ti ti-file-type-pdf text-red"></i></span>
                                <div class="lh-sm me-2">
                                    <div class="small fw-semibold">PDF final</div>
                                    <div class="small text-secondary">{{ $versiFinal ? $versiFinal['flag'] . ' · ' . $waktu($versiFinal['tanggal']) : 'belum ada draf' }}</div>
                                </div>
                                @if($versiFinal)
                                    <a href="{{ route('checker.sipotret_draf.pdf', $versiFinal['tr_id']) }}" target="_blank" class="btn btn-sm btn-icon" title="Lihat"><i class="ti ti-eye"></i></a>
                                    <a href="{{ route('checker.sipotret_draf.pdf', [$versiFinal['tr_id'], 'unduh' => 1]) }}" class="btn btn-sm btn-primary"><i class="ti ti-download me-1"></i>Unduh</a>
                                @endif
                            </div>
                            {{-- SPRP --}}
                            <div class="border rounded p-2 d-flex align-items-center gap-2">
                                <span class="avatar avatar-sm bg-{{ ($sprp['hasil'] ?? null) === 'diterima' ? 'green' : 'yellow' }}-lt"><i class="ti ti-file-certificate"></i></span>
                                <div class="lh-sm me-2">
                                    <div class="small fw-semibold">SPRP</div>
                                    <div class="small {{ ($sprp['hasil'] ?? null) === 'diterima' ? 'text-success' : 'text-warning' }}">
                                        {{ match ($sprp['hasil'] ?? null) { 'diterima' => 'diterima', 'menunggu' => 'belum diputuskan', 'ditolak' => 'ditolak', default => 'belum diupload' } }}</div>
                                </div>
                                @if($sprp && $sprp['ada_file'])
                                    @if($sprp['ext'] === 'pdf')<a href="{{ route('checker.sipotret_draf.berkas', [$p['pub_id'], 'spr']) }}" target="_blank" class="btn btn-sm btn-icon" title="Lihat"><i class="ti ti-eye"></i></a>@endif
                                    <a href="{{ route('checker.sipotret_draf.berkas', [$p['pub_id'], 'spr', 'unduh' => 1]) }}" class="btn btn-sm"><i class="ti ti-download me-1"></i>Unduh</a>
                                @else
                                    <button type="button" class="btn btn-sm" data-buka-langkah="4">Langkah 4</button>
                                @endif
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold">Isi metadata di portal publikasi BPS</span>
                            @if($linkPortal)
                                <a href="{{ $linkPortal }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary ms-auto"><i class="ti ti-external-link me-1"></i> Buka portal publikasi</a>
                            @endif
                        </div>
                        @php
                            // [label, nilai disalin, nilai tampil (opsional), langkah tempat mengisinya, wajib?]
                            $meta = [
                                ['Judul', $p['judul'], null, 1, true],
                                ['Judul (EN)', $p['judul_en'], null, 1, true],
                                ['Nomor publikasi', $p['nomor'], null, 1, true],
                                ['Volume', $p['volume'], null, 1, false],
                                ['Nomor katalog', $p['katalog'], $p['katalog'] && $p['nama_katalog'] ? $p['katalog'] . ' · ' . $p['nama_katalog'] : null, 1, true],
                                ['ISSN', $p['issn'], null, 1, false],
                                ['Tahun', $p['tahun'] ? (string) $p['tahun'] : null, null, null, false],
                                ['Tanggal rilis', $p['tgl_rilis'] ? \Carbon\Carbon::parse($p['tgl_rilis'])->format('d/m/Y') : null,
                                    $p['tgl_rilis'] ? \Carbon\Carbon::parse($p['tgl_rilis'])->locale('id')->translatedFormat('l, d F Y') : null, null, true],
                                ['Jenis', $p['status'] === 'arc' ? 'ARC' : 'Non ARC', null, 1, false],
                                ['Abstraksi', $p['abstraksi'], null, 2, true],
                                ['Abstract (EN)', $p['abstraksi_en'], null, 2, true],
                            ];
                        @endphp
                        <div class="border rounded meta-portal">
                            @foreach($meta as [$lbl, $nilai, $tampil, $diLangkah, $wajib])
                                <div class="d-flex align-items-start gap-2 px-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                    <div class="small text-secondary flex-shrink-0" style="width:8.5rem;">{{ $lbl }}</div>
                                    <div class="flex-fill small {{ in_array($lbl, ['Abstraksi', 'Abstract (EN)'], true) ? 'meta-panjang' : '' }} {{ $lbl === 'Abstract (EN)' || $lbl === 'Judul (EN)' ? 'fst-italic' : '' }}">
                                        @if(filled($nilai))
                                            <span class="{{ in_array($lbl, ['Nomor publikasi', 'Nomor katalog', 'ISSN'], true) ? 'font-monospace' : '' }}">{{ $tampil ?? $nilai }}</span>
                                        @elseif($wajib)
                                            <span class="text-danger"><i class="ti ti-alert-circle"></i> belum diisi</span>
                                            @if($diLangkah)<button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" data-buka-langkah="{{ $diLangkah }}">isi di langkah {{ $diLangkah }}</button>@endif
                                        @else
                                            <span class="text-secondary">- {{ $lbl === 'ISSN' ? '(tidak punya ISSN)' : '' }}</span>
                                        @endif
                                    </div>
                                    @if(filled($nilai))
                                        <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary flex-shrink-0" data-salin="{{ $nilai }}" title="Salin {{ $lbl }}"><i class="ti ti-copy"></i></button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </li>
                    <li>
                        <div class="fw-semibold">Centang bila sudah diupload</div>
                        @if($p['portal'])
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-1 p-2 rounded bg-green-lt">
                                <i class="ti ti-square-check text-success fs-2"></i>
                                <div class="flex-fill small">
                                    <strong>Sudah diupload ke portal publikasi BPS</strong>
                                    <div class="text-secondary">ditandai {{ $p['portal']['oleh'] ?? '-' }} · {{ $waktu($p['portal']['waktu']) }}</div>
                                </div>
                                @if($boleh && Route::has('checker.sipotret_draf.portal'))
                                    <form method="post" action="{{ route('checker.sipotret_draf.portal', $p['pub_id']) }}"
                                          data-confirm="Batalkan tanda sudah diupload ke portal?" data-confirm-ok="Batalkan tanda">
                                        @csrf <input type="hidden" name="aksi" value="batal">
                                        <button class="btn btn-sm btn-ghost-secondary"><i class="ti ti-square me-1"></i> Batalkan tanda</button>
                                    </form>
                                @endif
                            </div>
                        @elseif(!\App\Models\PraRilisPortal::aktif())
                            <div class="small text-warning mt-1"><i class="ti ti-database-off me-1"></i> Ceklis belum tersedia: tabel <code>pra_rilis_portal</code> belum dibuat (jalankan migrasi / file SQL-nya).</div>
                        @elseif($boleh)
                            <form method="post" action="{{ route('checker.sipotret_draf.portal', $p['pub_id']) }}" class="mt-1"
                                  data-confirm="PDF final publikasi ini sudah diupload ke portal publikasi BPS?" data-confirm-ok="Sudah diupload">
                                @csrf <input type="hidden" name="aksi" value="tandai">
                                <button class="btn btn-primary"><i class="ti ti-square-check me-1"></i> Tandai sudah diupload ke portal</button>
                            </form>
                        @else
                            <div class="small text-secondary mt-1"><i class="ti ti-lock me-1"></i> Khusus pemeriksa yang ditugaskan &amp; admin.</div>
                        @endif
                    </li>
                    <li>
                        <div class="fw-semibold">Setelah tayang di Web BPS, tautkan di langkah 6</div>
                        <button type="button" class="btn btn-sm mt-1" data-buka-langkah="6"><i class="ti ti-search me-1"></i> Cari di Web BPS</button>
                    </li>
                </ol>
            </div>
        </div>

        {{-- ---------- 6. Finalkan & tautkan ---------- --}}
        @php $l = $L[6]; @endphp
        <div class="card panel-langkah {{ $buka === 6 ? '' : 'd-none' }}" id="langkah-6" data-panel="6">
            @include('checker.partials.pra-rilis-panel-head', ['l' => $l, 'gaya' => $gaya, 'tgl' => $tgl])
            <div class="card-body">
                @if($p['rilis'])
                    <div class="d-flex align-items-center gap-3 p-3 rounded bg-green-lt mb-3">
                        <i class="ti ti-rocket fs-1 text-success"></i>
                        <div class="flex-fill">
                            <div class="fw-semibold">Sudah ditandai rilis di SIPOTRET</div>
                            <div class="small">{{ $p['pasca'] ? 'Tertaut ke ' . count($p['pasca']) . ' publikasi Web BPS dan masuk Pasca Rilis.' : 'Belum tertaut ke publikasi Web BPS.' }}</div>
                        </div>
                        @foreach($p['pasca'] as $ps)
                            @if($ps['domain'])
                                <a href="{{ route('checker.bps.detail.show', ['pub_id' => $ps['pub_id_api'], 'domain' => $ps['domain']]) }}" class="btn btn-sm"><i class="ti ti-world me-1"></i> Detail Web BPS</a>
                            @endif
                        @endforeach
                    </div>
                    @php $hasilPasca = $hasil->whereNull('sipotret_tr_id'); @endphp
                    @if($hasilPasca->isNotEmpty())
                        <div class="small mb-3"><i class="ti ti-world-check me-1"></i> Diperiksa pasca rilis di pubchecker {{ $hasilPasca->count() }}×:
                            @foreach($hasilPasca->take(5) as $hp)
                                <a href="{{ route('checker.riwayat.detail', $hp->sesi_id) }}" class="badge bg-info-lt text-decoration-none">{{ $waktu($hp->created_at) }}</a>
                            @endforeach
                        </div>
                    @endif
                    @if($p['pasca'] && $pascaRiwayat)
                        <div class="fw-semibold mb-2">Pemeriksaan pasca rilis</div>
                        <div class="list-group list-group-flush border rounded mb-3">
                            @foreach($pascaRiwayat as $r)
                                @php
                                    [$cls, $teks] = match ($r['tahap']) {
                                        'bersih'   => ['bg-green-lt', 'Tidak ada temuan'],
                                        'approve'  => ['bg-green-lt', 'Perbaikan disetujui'],
                                        'menunggu' => ['bg-yellow-lt', 'Perbaikan menunggu persetujuan'],
                                        default    => ['bg-red-lt', 'Belum diperbaiki'],
                                    };
                                @endphp
                                <div class="list-group-item py-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <strong class="small">{{ \Carbon\Carbon::create($r['tahun'], $r['bulan'], 1)->locale('id')->translatedFormat('F Y') }}</strong>
                                        <span class="badge {{ $cls }}">{{ $teks }}</span>
                                        @if($r['catatan'])<span class="small text-secondary">{{ count($r['catatan']) }} temuan</span>@endif
                                    </div>
                                    @if($r['catatan'])
                                        <ul class="small mb-0 mt-1 ps-3">
                                            @foreach($r['catatan'] as $c)
                                                <li>@if($c['kategori'])<span class="text-secondary">{{ $c['kategori'] }} ·</span>@endif {{ $c['catatan'] }}@if($c['keterangan'])<span class="text-danger"> · {{ $c['keterangan'] }}</span>@endif</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- batalkan rilis: disembunyikan di "opsi lanjutan", konfirmasi ganda --}}
                    @if($boleh)
                        @php $ph = $pascaHapus ?? ['pasca' => 0, 'periode' => 0, 'catatan' => 0, 'pub_id_api' => []]; @endphp
                        <details class="border border-danger rounded" id="kartuBatalRilis">
                            <summary class="p-2 text-danger small fw-semibold"><i class="ti ti-arrow-back-up me-1"></i> Batalkan rilis{{ $p['pasca'] ? '' : ' (mis. untuk menautkan ulang ke Web BPS)' }}</summary>
                            <div class="p-3 pt-0">
                                <form method="post" action="{{ route('checker.sipotret_draf.batal_rilis', $p['pub_id']) }}" id="formBatalRilis"
                                      data-confirm="Batalkan status rilis publikasi ini di SIPOTRET? PJK diberi notifikasi." data-confirm-ok="Batalkan rilis" data-confirm-danger>
                                    @csrf
                                    <p class="small mb-2">Status rilis di SIPOTRET dikembalikan ke belum rilis. Versi final dan hasil pemeriksaan tidak berubah.</p>
                                    <label class="form-check small mb-2">
                                        <input type="checkbox" class="form-check-input" name="hapus_pasca" value="1" id="batalHapusPasca" @checked($ph['pasca'] > 0) @disabled($ph['pasca'] === 0)>
                                        <span class="form-check-label">Hapus juga data Pasca Rilis
                                            @if($ph['pasca'])
                                                · <strong class="text-danger">{{ $ph['pasca'] }} publikasi Web API ({{ implode(', ', $ph['pub_id_api']) ?: '-' }}), {{ $ph['periode'] }} periode, {{ $ph['catatan'] }} catatan</strong> terhapus permanen
                                            @else
                                                <span class="text-secondary">(tidak ada)</span>
                                            @endif
                                        </span>
                                    </label>
                                    <div class="input-group input-group-sm mb-2" style="max-width:380px;">
                                        <span class="input-group-text">Ketik <strong class="ms-1">BATALKAN</strong></span>
                                        <input type="text" name="konfirmasi" id="batalKonfirmasi" class="form-control" autocomplete="off" required>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger" id="batalBtn" disabled><i class="ti ti-arrow-back-up me-1"></i> Batalkan Rilis</button>
                                </form>
                            </div>
                        </details>
                    @endif
                @elseif($versiFile->isEmpty())
                    <div class="text-secondary">Belum ada draf.</div>
                @elseif(!$boleh)
                    <div class="text-secondary"><i class="ti ti-lock me-1"></i> Khusus pemeriksa yang ditugaskan &amp; admin.</div>
                @else
                    @if($L[4]['status'] !== 'selesai')
                        <div class="alert alert-warning py-2 small"><i class="ti ti-alert-triangle me-1"></i> SPRP belum diterima.</div>
                    @endif
                    @php
                        $wilayah = ['6300' => 'Provinsi Kalimantan Selatan', '6301' => 'Kab. Tanah Laut', '6302' => 'Kab. Kotabaru', '6303' => 'Kab. Banjar',
                                    '6304' => 'Kab. Barito Kuala', '6305' => 'Kab. Tapin', '6306' => 'Kab. Hulu Sungai Selatan', '6307' => 'Kab. Hulu Sungai Tengah',
                                    '6308' => 'Kab. Hulu Sungai Utara', '6309' => 'Kab. Tabalong', '6310' => 'Kab. Tanah Bumbu', '6311' => 'Kab. Balangan',
                                    '6371' => 'Kota Banjarmasin', '6372' => 'Kota Banjarbaru'];
                        $tahunCari = $p['tgl_rilis'] ? (int) substr($p['tgl_rilis'], 0, 4) : ((int) $p['tahun'] ?: (int) date('Y'));
                    @endphp
                    {{-- Tandai rilis = 2 langkah: (A) pilih publikasinya di Web BPS (dicari otomatis), (B) cek ringkasan lalu simpan --}}
                    <form method="post" action="{{ route('checker.sipotret_draf.rilis', $p['pub_id']) }}" id="formRilis"
                          data-judul-sipotret="{{ $p['judul'] }}"
                          data-url-detail="{{ route('checker.bps.detail.show', ['pub_id' => '__ID__', 'domain' => '__DOM__']) }}">
                        @csrf
                        <input type="hidden" name="judul_api" id="rilisJudulApi">
                        <input type="hidden" name="rilis_api" id="rilisTglApi">
                        <input type="hidden" name="pub_id_api" id="rilisPubId">
                        <input type="hidden" name="domain" id="rilisDomainKirim" value="6300">

                        {{-- A. pilih publikasi Web BPS --}}
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="avatar avatar-xs rounded-circle bg-primary text-white">A</span>
                            <span class="fw-semibold">Pilih publikasinya di Web BPS</span>
                        </div>
                        <div class="small text-secondary mb-2" id="rilisRingkasCari">
                            Mencari <strong id="rilisRingkasKata">{{ \Illuminate\Support\Str::limit($p['judul'], 60, '') }}</strong>
                            di <span id="rilisRingkasWil">{{ $wilayah['6300'] }}</span>, tahun <span id="rilisRingkasThn">{{ $tahunCari }}</span>
                            · <a href="#" id="rilisUbahCari">ubah pencarian</a>
                        </div>
                        <div class="border rounded p-2 mb-2 bg-body-tertiary" id="rilisPanelCari" hidden>
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small mb-1" for="rilisDomain">Wilayah</label>
                                    <select id="rilisDomain" class="form-select form-select-sm">
                                        @foreach($wilayah as $kode => $nama)<option value="{{ $kode }}">{{ $nama }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-1" for="rilisCari">Kata kunci judul</label>
                                    <input type="text" id="rilisCari" class="form-control form-control-sm" value="{{ \Illuminate\Support\Str::limit($p['judul'], 60, '') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1" for="rilisTahunApi">Tahun</label>
                                    <input type="number" id="rilisTahunApi" class="form-control form-control-sm" value="{{ $tahunCari }}" placeholder="semua">
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary mt-2" id="rilisBtnCari"><i class="ti ti-search me-1"></i> Cari</button>
                        </div>
                        <div id="rilisHasil" class="list-group mb-2" style="max-height:320px; overflow:auto;">
                            <div class="list-group-item small text-secondary">Mencari…</div>
                        </div>
                        <details class="small mb-3" id="rilisManual">
                            <summary class="text-secondary">Tidak ketemu?</summary>
                            <div class="border rounded p-2 mt-2">
                                <label class="form-label small mb-1" for="rilisPubIdManual">Isi pub_id Web BPS manual</label>
                                <div class="input-group input-group-sm mb-2" style="max-width:360px;">
                                    <input type="text" id="rilisPubIdManual" class="form-control font-monospace" placeholder="mis. 6a5c1c0d1e2f3a4b5c6d7e8f">
                                    <button type="button" class="btn" id="rilisPakaiManual">Pakai</button>
                                </div>
                                <label class="form-check mb-0">
                                    <input type="checkbox" class="form-check-input" id="rilisTanpaApi">
                                    <span class="form-check-label">Tandai rilis <strong>tanpa</strong> tautan Web BPS</span>
                                </label>
                            </div>
                        </details>

                        {{-- B. ringkasan & simpan --}}
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="avatar avatar-xs rounded-circle bg-primary text-white">B</span>
                            <span class="fw-semibold">Cek lalu simpan</span>
                        </div>
                        <div class="border rounded p-3 mb-3">
                            <div class="row g-3 align-items-end">
                                <div class="col-12">
                                    <div class="form-label small mb-1">Ditautkan ke</div>
                                    <div id="rilisTerpilih" class="text-secondary small"><i class="ti ti-arrow-up me-1"></i> Pilih publikasinya di atas dulu.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1" for="rilisVersi">Versi final</label>
                                    <select name="tr_id" id="rilisVersi" class="form-select form-select-sm">
                                        @foreach($versiFile as $v)
                                            <option value="{{ $v['tr_id'] }}" @selected($versiFinal && $versiFinal['tr_id'] === $v['tr_id'])>{{ $v['flag'] ?? 'v?' }} · diupload {{ $waktu($v['tanggal']) }}{{ $loop->first ? ' (terbaru)' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Periode pasca rilis <span class="text-secondary fw-normal" id="rilisPeriodeKet"></span></label>
                                    <div class="input-group input-group-sm">
                                        <select name="bulan" id="rilisBulan" class="form-select form-select-sm">
                                            @foreach(range(1, 12) as $b)
                                                <option value="{{ $b }}" @selected($b === (int) date('n'))>{{ \Carbon\Carbon::create(2000, $b, 1)->locale('id')->translatedFormat('F') }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="tahun" id="rilisTahun" value="{{ date('Y') }}" min="2000" max="2100" class="form-control form-control-sm" style="max-width:90px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-success btn-lg" id="rilisBtnSimpan" disabled><i class="ti ti-rocket me-1"></i> <span id="rilisTeksSimpan">Pilih publikasi Web BPS dulu</span></button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ============ Riwayat notifikasi (informasi pendukung) ============ --}}
@php $jmlPeran = collect($notif['items'])->countBy(fn($n) => \Illuminate\Support\Str::slug($n['peran'] ?? 'lain')); @endphp
<details class="card mb-3" id="kartuNotif">
    <summary class="card-header">
        <h3 class="card-title"><i class="ti ti-bell me-1"></i> Riwayat Notifikasi</h3>
        <span class="card-subtitle ms-2">{{ $notif['total'] }} notifikasi di SIPOTRET</span>
        <div class="card-actions"><a href="{{ route('checker.sipotret_draf.notifikasi') }}" class="btn btn-sm btn-ghost-secondary">Semua publikasi</a></div>
    </summary>
    @if(!$notif['items'])
        <div class="card-body text-secondary">Belum ada notifikasi untuk publikasi ini.</div>
    @else
        <div class="card-body py-2 d-flex flex-wrap gap-2" id="saringNotif">
            <button type="button" class="btn btn-sm active" data-peran="">Semua <span class="badge bg-secondary-lt ms-1">{{ count($notif['items']) }}</span></button>
            @foreach(['pjk' => 'Ke PJK', 'ketua-tim' => 'Ke ketua tim', 'admin' => 'Ke admin', 'lain' => 'Lainnya'] as $k => $label)
                @if($jmlPeran[$k] ?? 0)
                    <button type="button" class="btn btn-sm" data-peran="{{ $k }}">{{ $label }} <span class="badge bg-secondary-lt ms-1">{{ $jmlPeran[$k] }}</span></button>
                @endif
            @endforeach
        </div>
        <div class="list-group list-group-flush" id="daftarNotif" style="max-height:420px; overflow:auto;">
            @foreach($notif['items'] as $n)
                @include('checker.partials.notif-item', ['n' => $n])
            @endforeach
        </div>
        @if($notif['total'] > count($notif['items']))
            <div class="card-footer small text-secondary">Menampilkan {{ count($notif['items']) }} notifikasi terbaru.</div>
        @endif
    @endif
</details>
@endsection

@push('styles')
<style>
    .stepper-wrap { position: sticky; top: 1rem; }
    .stepper-item { position: relative; border-left: 3px solid transparent; }
    .stepper-item, .stepper-item:hover, .stepper-item:focus, .stepper-item * { text-decoration: none !important; } /* link: tanpa garis bawah saat hover */
    .stepper-item { cursor: pointer; transition: background-color .12s, border-color .12s; }
    .stepper-item:hover:not(.active) { background: rgba(var(--tblr-primary-rgb, 32, 107, 196), .10); border-left-color: rgba(var(--tblr-primary-rgb, 32, 107, 196), .55); }
    .stepper-item.active { border-left-color: var(--tblr-primary); background: var(--tblr-primary-lt); }
    .stepper-terlewat:not(.active) { background: var(--tblr-danger-lt); }
    .stepper-terlewat:hover:not(.active) { background: rgba(var(--tblr-danger-rgb, 214, 57, 57), .18); border-left-color: var(--tblr-danger); }
    .stepper-menunggu:hover:not(.active) { opacity: 1; }
    .stepper-menunggu:not(.active) { opacity: .7; }
    .panel-langkah { min-height: 100%; }
    .langkah-cek { counter-reset: cek; list-style: none; padding-left: 0; }
    .langkah-cek > li { counter-increment: cek; position: relative; padding: 0 0 1rem 2.5rem; border-left: 2px dashed var(--tblr-border-color); margin-left: .9rem; }
    .langkah-cek > li:last-child { border-left-color: transparent; padding-bottom: 0; }
    .langkah-cek > li::before { content: counter(cek); position: absolute; left: -.95rem; top: 0; width: 1.8rem; height: 1.8rem; border-radius: 50%;
        background: var(--tblr-bg-surface); border: 2px solid var(--tblr-primary); color: var(--tblr-primary); font-weight: 600; display: flex; align-items: center; justify-content: center; }
    .meta-panjang { white-space: pre-line; text-align: justify; max-height: 160px; overflow: auto; }
    details > summary { cursor: pointer; }
    details.card > summary { list-style: none; }
    details.card > summary::-webkit-details-marker { display: none; }
</style>
@endpush

@push('scripts')
<script>
(() => {
    // stepper: buka panel langkah (klik di kiri, tombol data-buka-langkah, atau #langkah-N di URL)
    const nav = document.querySelectorAll('[data-langkah-nav]');
    const buka = (n, fokus) => {
        n = String(n);
        nav.forEach(a => a.classList.toggle('active', a.dataset.langkahNav === n));
        document.querySelectorAll('[data-panel]').forEach(el => el.classList.toggle('d-none', el.dataset.panel !== n));
        history.replaceState(null, '', '#langkah-' + n);
        const panel = document.getElementById('langkah-' + n);
        const target = fokus ? document.querySelector(fokus) : panel;
        if (target?.tagName === 'DETAILS' || target?.closest('details')) (target.closest('details') || target).open = true;
        if (target?.classList.contains('collapse')) target.classList.add('show');
        if (window.innerWidth < 992 || fokus) (target || panel)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    nav.forEach(a => a.addEventListener('click', e => { e.preventDefault(); buka(a.dataset.langkahNav); }));
    document.querySelectorAll('[data-buka-langkah]').forEach(b => b.addEventListener('click', () => {
        buka(b.dataset.bukaLangkah, b.dataset.fokus); // langkah 6 mencari di Web BPS sendiri begitu panelnya terbuka
    }));
    const m = location.hash.match(/^#langkah-([1-6])$/);
    if (m) buka(m[1]);
    else if (location.hash === '#kartuUpload') buka(2, '#kartuUpload'); // tombol Upload dari panel Segera Rilis
})();
(() => {
    // penugasan pemeriksaan pra rilis (admin), endpoint sama dengan halaman daftar
    const sel = document.getElementById('tugasPilih');
    if (!sel) return;
    const kirim = async userId => {
        const lepas = !userId;
        const ok = await Dialog.confirm(lepas ? 'Lepas penugasan publikasi ini?'
            : `Tugaskan pemeriksaan pra rilis publikasi ini ke ${sel.selectedOptions[0].text}?`, { okText: lepas ? 'Lepas' : 'Tugaskan' });
        if (!ok) return;
        try {
            const res = await fetch('{{ Route::has('checker.sipotret_draf.tugaskan') ? route('checker.sipotret_draf.tugaskan') : '' }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
                body: JSON.stringify({ pubs: [{ pub_id: {{ $p['pub_id'] }}, judul: @json($p['judul']), domain: @json($p['tgl_rilis']) }], user_id: userId || null }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || `Server membalas ${res.status}`);
            location.reload();
        } catch (e) {
            Dialog.alert('Penugasan gagal: ' + e.message);
        }
    };
    document.getElementById('tugasSimpan').addEventListener('click', () => sel.value ? kirim(sel.value) : Dialog.alert('Pilih pemeriksa dulu.'));
    document.getElementById('tugasLepas')?.addEventListener('click', () => kirim(null));
})();
(() => {
    // Riwayat notifikasi: saring per penerima
    const chips = document.getElementById('saringNotif');
    chips?.addEventListener('click', e => {
        const b = e.target.closest('button[data-peran]');
        if (!b) return;
        chips.querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
        document.querySelectorAll('#daftarNotif [data-peran]').forEach(el => {
            el.style.display = !b.dataset.peran || el.dataset.peran === b.dataset.peran ? '' : 'none';
        });
    });
})();
(() => {
    // tombol salin metadata (langkah 5). Clipboard API butuh https/localhost → cadangan execCommand untuk http intranet
    const salin = async teks => {
        try { await navigator.clipboard.writeText(teks); return true; } catch {}
        const t = Object.assign(document.createElement('textarea'), { value: teks });
        t.style.cssText = 'position:fixed;opacity:0;';
        document.body.appendChild(t); t.select();
        let ok = false; try { ok = document.execCommand('copy'); } catch {}
        t.remove(); return ok;
    };
    document.addEventListener('click', async e => {
        const b = e.target.closest('[data-salin]');
        if (!b) return;
        const ok = await salin(b.dataset.salin);
        const ikon = b.querySelector('i');
        ikon.className = 'ti ' + (ok ? 'ti-check text-success' : 'ti-x text-danger');
        b.title = ok ? 'Tersalin' : 'Gagal menyalin';
        setTimeout(() => { ikon.className = 'ti ti-copy'; }, 1500);
    });
})();
(() => {
    // Abstraksi: tombol simpan aktif bila isinya beda dengan yang tersimpan di SIPOTRET
    const form = document.getElementById('formAbstraksi');
    if (!form) return;
    const area = [...form.querySelectorAll('textarea[data-asli]')];
    const btn = document.getElementById('absSimpan'), info = document.getElementById('absInfo');
    const cek = () => {
        const ubah = area.some(t => t.value.trim() !== (t.dataset.asli || '').trim());
        btn.disabled = !ubah;
        info.textContent = ubah ? 'belum disimpan' : '';
        info.classList.toggle('text-warning', ubah);
    };
    area.forEach(t => t.addEventListener('input', cek));
    cek();
})();
(() => {
    // Batalkan rilis: tombol aktif setelah mengetik BATALKAN (konfirmasi kedua setelah dialog)
    const ketik = document.getElementById('batalKonfirmasi');
    if (!ketik) return;
    const btn = document.getElementById('batalBtn');
    ketik.addEventListener('input', () => { btn.disabled = ketik.value.trim().toUpperCase() !== 'BATALKAN'; });
})();
(() => {
    // Langkah 6: (A) cari publikasi di Web BPS otomatis saat panel dibuka → pilih (judul sama persis dipilih otomatis),
    // (B) periode pasca rilis ikut bulan tayang, tombol menyebut apa yang akan disimpan, konfirmasi berisi ringkasan.
    const form = document.getElementById('formRilis');
    if (!form) return;
    const $ = id => document.getElementById(id);
    const CARI_URL = '{{ route('checker.bps.search') }}';
    const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const norm = s => String(s ?? '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
    const judulSip = norm(form.dataset.judulSipotret);
    const tglId = t => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(t || ''); return m ? `${+m[3]} ${BULAN[+m[2] - 1].slice(0, 3)} ${m[1]}` : '-'; };
    const urlDetail = (id, dom) => form.dataset.urlDetail.replace('__ID__', encodeURIComponent(id)).replace('__DOM__', encodeURIComponent(dom));
    let pilihan = null, sudahCari = false;

    const perbarui = () => {
        const tanpa = $('rilisTanpaApi').checked;
        const btn = $('rilisBtnSimpan');
        btn.disabled = !tanpa && !pilihan;
        $('rilisTeksSimpan').textContent = tanpa ? 'Tandai final & rilis (tanpa Web BPS)'
            : pilihan ? 'Tandai final & rilis, tautkan ke Web BPS' : 'Pilih publikasi Web BPS dulu';
        $('rilisPubId').value = !tanpa && pilihan ? pilihan.id : '';
        $('rilisJudulApi').value = !tanpa && pilihan ? pilihan.judul : '';
        $('rilisTglApi').value = !tanpa && pilihan && /^\d{4}-\d{2}-\d{2}/.test(pilihan.rilis) ? pilihan.rilis.slice(0, 10) : '';
        $('rilisDomainKirim').value = pilihan?.domain || $('rilisDomain').value;
        $('rilisTerpilih').innerHTML = tanpa
            ? '<span class="text-warning"><i class="ti ti-unlink me-1"></i> Tanpa tautan Web BPS</span>'
            : pilihan
                ? `<div class="d-flex align-items-center gap-2 p-2 rounded bg-green-lt">
                       <i class="ti ti-circle-check text-success fs-2"></i>
                       <div class="flex-fill lh-sm"><div class="fw-semibold">${esc(pilihan.judul || '(judul belum diketahui)')}</div>
                       <div class="small text-secondary">pub_id <span class="font-monospace">${esc(pilihan.id)}</span>${pilihan.rilis ? ' · rilis ' + tglId(pilihan.rilis) : ''}</div></div>
                       <a href="${esc(urlDetail(pilihan.id, pilihan.domain))}" target="_blank" class="btn btn-sm"><i class="ti ti-external-link me-1"></i>Lihat</a>
                   </div>`
                : '<i class="ti ti-arrow-up me-1"></i> Pilih publikasinya di atas dulu.';
    };
    const pilih = p => {
        pilihan = p;
        $('rilisTanpaApi').checked = false;
        $('rilisHasil').querySelectorAll('[data-id]').forEach(a => a.classList.toggle('active', !!p && a.dataset.id === p.id));
        // periode pasca rilis = bulan publikasi tayang di Web BPS
        const m = /^(\d{4})-(\d{2})/.exec(p?.rilis || '');
        if (m) {
            $('rilisBulan').value = String(+m[2]); $('rilisTahun').value = m[1];
            $('rilisPeriodeKet').textContent = '· ikut tanggal rilis';
        }
        perbarui();
    };

    async function cari(tanpaTahun = false) {
        sudahCari = true;
        const box = $('rilisHasil'), dom = $('rilisDomain').value;
        const tahun = tanpaTahun ? null : (+$('rilisTahunApi').value || null);
        $('rilisRingkasKata').textContent = $('rilisCari').value.trim() || '(semua)';
        $('rilisRingkasWil').textContent = $('rilisDomain').selectedOptions[0].text;
        $('rilisRingkasThn').textContent = tahun || 'semua';
        box.innerHTML = '<div class="list-group-item small text-secondary"><i class="ti ti-loader-2 icon-spin me-1"></i> Mencari di Web BPS…</div>';
        try {
            const res = await fetch(CARI_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ domain: dom, keyword: $('rilisCari').value.trim() || null, year: tahun }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.error) throw new Error(data.error || data.message || ('Server membalas ' + res.status));
            const items = (data.items || []).map(it => ({ id: String(it.pub_id), judul: it.title || '', rilis: it.rl_date || '', cover: it.cover || '', domain: dom,
                                                          sama: norm(it.title) === judulSip }));
            if (!items.length && tahun) return cari(true); // coba lagi tanpa tahun
            items.sort((a, b) => b.sama - a.sama);
            box.innerHTML = items.length ? items.map(it => `
                <a href="#" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2" data-id="${esc(it.id)}">
                    ${it.cover ? `<img src="${esc(it.cover)}" alt="" loading="lazy" style="width:34px;height:46px;object-fit:cover;" class="rounded border flex-shrink-0">` : '<span class="avatar avatar-sm flex-shrink-0"><i class="ti ti-book"></i></span>'}
                    <div class="flex-fill lh-sm">
                        <div class="small fw-semibold">${esc(it.judul)} ${it.sama ? '<span class="badge bg-green-lt ms-1">judul sama</span>' : ''}</div>
                        <div class="small text-secondary">rilis ${tglId(it.rilis)} · pub_id <span class="font-monospace">${esc(it.id)}</span></div>
                    </div>
                    <span class="btn btn-sm btn-outline-primary flex-shrink-0 pilih-teks">Pilih</span>
                </a>`).join('')
                + (tanpaTahun ? '<div class="list-group-item small text-secondary">Ditampilkan semua tahun.</div>' : '')
                : `<div class="list-group-item small text-secondary"><i class="ti ti-mood-empty me-1"></i> Belum ada di Web BPS · <a href="#" data-ubah>ubah pencarian</a></div>`;
            box._items = items;
            const sama = items.filter(i => i.sama);
            if (sama.length === 1 && !pilihan) pilih(sama[0]);      // satu judul sama persis → langsung terpilih
            else if (pilihan) pilih(items.find(i => i.id === pilihan.id) || pilihan);
        } catch (e) {
            box.innerHTML = `<div class="list-group-item small text-danger"><i class="ti ti-alert-triangle me-1"></i> ${esc(e.message)}
                <a href="#" data-ulang class="ms-1">Coba lagi</a></div>`;
        }
    }
    const bukaUbah = () => { $('rilisPanelCari').hidden = false; $('rilisCari').focus(); };
    $('rilisUbahCari').addEventListener('click', e => { e.preventDefault(); $('rilisPanelCari').hidden ? bukaUbah() : ($('rilisPanelCari').hidden = true); });
    $('rilisBtnCari').addEventListener('click', () => cari());
    $('rilisCari').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); cari(); } });
    $('rilisDomain').addEventListener('change', () => cari());
    $('rilisHasil').addEventListener('click', e => {
        if (e.target.closest('[data-ubah]')) { e.preventDefault(); return bukaUbah(); }
        if (e.target.closest('[data-ulang]')) { e.preventDefault(); return cari(); }
        const a = e.target.closest('[data-id]');
        if (!a) return;
        e.preventDefault();
        pilih(($('rilisHasil')._items || []).find(i => i.id === a.dataset.id));
    });
    $('rilisPakaiManual').addEventListener('click', () => {
        const id = $('rilisPubIdManual').value.trim();
        if (!id) return Dialog.alert('Isi pub_id dulu.');
        pilih({ id, judul: '', rilis: '', domain: $('rilisDomain').value });
    });
    $('rilisTanpaApi').addEventListener('change', () => {
        if ($('rilisTanpaApi').checked) {
            pilihan = null;
            $('rilisHasil').querySelectorAll('.active').forEach(a => a.classList.remove('active'));
        }
        perbarui();
    });

    // konfirmasi berisi ringkasan yang akan disimpan
    form.addEventListener('submit', async e => {
        e.preventDefault();
        const versi = $('rilisVersi').selectedOptions[0]?.text.split(' · ')[0] || '';
        const periode = `${BULAN[+$('rilisBulan').value - 1]} ${$('rilisTahun').value}`;
        const ok = await Dialog.confirm(`Tandai publikasi ini final & sudah rilis di SIPOTRET?\n\n• Versi final: ${versi}\n• `
            + (pilihan ? `Tautkan ke Web BPS: ${pilihan.judul || 'pub_id ' + pilihan.id}\n• Pasca rilis periode ${periode}` : 'Tanpa tautan Web BPS')
            + '\n\nPJK diberi notifikasi.', { okText: 'Tandai rilis' });
        if (ok) { $('rilisBtnSimpan').disabled = true; form.submit(); }
    });

    // cari otomatis begitu panel langkah 6 terlihat (dibuka dari stepper, tombol, atau #langkah-6)
    const panel = $('langkah-6');
    const cekTampil = () => { if (!sudahCari && !panel.classList.contains('d-none')) cari(); };
    new MutationObserver(cekTampil).observe(panel, { attributes: true, attributeFilter: ['class'] });
    cekTampil();
    perbarui();
})();
</script>
@endpush
