@extends('layouts.app')

@section('title', 'Pemeriksaan Pra Rilis')
@section('pretitle', 'Pra Rilis · draf dari SIPOTRET')
@section('page-title', 'Pemeriksaan Pra Rilis')
@section('page-actions')
    @if(Route::has('checker.sipotret_draf.notifikasi'))
        <a href="{{ route('checker.sipotret_draf.notifikasi') }}" class="btn"><i class="ti ti-bell"></i> Notifikasi</a>
    @endif
    <a href="{{ route('checker.riwayat') }}" class="btn"><i class="ti ti-history"></i> Riwayat</a>
    @if(Route::has('checker.sipotret_draf.tambah') && ($aktif ?? false))
        <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#kartuTambah" aria-expanded="false">
            <i class="ti ti-plus"></i> Tambah publikasi
        </button>
    @endif
@endsection

@section('content')
@php
    $fmtTgl  = fn($t) => $t ? \Carbon\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '-';
    $hariTgl = fn($t) => $t ? \Carbon\Carbon::parse($t)->locale('id')->translatedFormat('D, d M Y') : '-';
    $hariIni = date('Y-m-d');
    $sisa    = fn($t) => $t ? (int) round((strtotime($t) - strtotime($hariIni)) / 86400) : null;
    $hitung  = fn($n) => $n === null ? '' : ($n === 0 ? 'hari ini' : ($n > 0 ? $n . ' hari lagi' : 'lewat ' . abs($n) . ' hari'));

    $tugas    = $tugas ?? [];
    $bolehPub = fn($id) => !isset($tugas[$id]) || $tugas[$id]['boleh'];
    $isAdmin  = auth()->user()?->isAdmin() && !empty($pemeriksa);
    $adaTugasSaya = collect($tugas)->contains('saya', true);

    // satu baris = satu publikasi, dengan langkah berikutnya & kategori antrean
    $baris = collect($publikasi)->map(function ($p) use ($diperiksa, $bolehPub, $tugas) {
        $alur  = $p['langkah'];
        $kini  = collect($alur['langkah'])->firstWhere('status', 'aktif');
        $lewat = collect($alur['langkah'])->where('status', 'terlewat');
        $pdf   = collect($p['versi'])->firstWhere('pdf', true);
        $h     = $pdf ? ($diperiksa[$pdf['tr_id']] ?? null) : null;
        $kategori = match (true) {
            !$kini && $lewat->isEmpty() => 'selesai',
            $kini && $kini['oleh'] === 'PJK' => 'pjk',
            default => 'pemeriksa',
        };
        return $p + [
            'alur' => $alur, 'kini' => $kini, 'lewat_langkah' => $lewat, 'pdf' => $pdf,
            'hasil' => $h, 'tinggal_kirim' => $h && !$h->sipotret_terkirim_at && $kini && $kini['no'] === 3,
            'kategori' => $kategori,
            'telat' => ($kini && $kini['lewat']) || $lewat->isNotEmpty() || (($alur['tenggat']['sisa'] ?? 0) < 0),
            'tenggat_kini' => $alur['tenggat'] ?? null,
            'mepet' => ($t = $alur['tenggat'] ?? null) && $t['sisa'] >= 0 && $t['sisa'] <= 3,
            'boleh' => $bolehPub($p['pub_id']),
            'saya'  => (bool) ($tugas[$p['pub_id']]['saya'] ?? false),
        ];
    })
    // belum selesai dulu (tenggat terdekat), yang selesai di akhir (rilis terbaru dulu)
    ->sortBy(fn($r) => $r['kategori'] === 'selesai'
        ? [1, 9999999999 - strtotime($r['tgl_rilis'] ?? '2000-01-01'), 0]
        : [0, strtotime($r['tenggat_kini']['tgl'] ?? $r['tgl_rilis'] ?? '2999-12-31'), strtotime($r['tgl_rilis'] ?? '2999-12-31')])
    ->values();

    $jml = [
        'pemeriksa' => $baris->where('kategori', 'pemeriksa')->count(),
        'pjk'       => $baris->where('kategori', 'pjk')->count(),
        'telat'     => $baris->where('kategori', '!=', 'selesai')->where('telat', true)->count(),
        'mepet'     => $baris->where('kategori', '!=', 'selesai')->where('mepet', true)->count(),
        'selesai'   => $baris->where('kategori', 'selesai')->count(),
    ];
    $warnaL = ['selesai' => 'success', 'aktif' => 'primary', 'terlewat' => 'danger', 'menunggu' => 'secondary-lt'];
@endphp

@if(!$aktif)
    <div class="alert alert-warning">Koneksi database SIPOTRET belum diatur (<code>SIPOTRET_DB_*</code> di <code>.env</code>).</div>
@elseif($error)
    <div class="alert alert-danger">{{ $error }}</div>
@endif

{{-- ============ Tambah publikasi baru ke SIPOTRET ============ --}}
@if(Route::has('checker.sipotret_draf.tambah') && $aktif && !$error)
    @php $master = $master ?? ['katalog' => [], 'issn' => [], 'users' => []]; $bukaTambah = session('buka_tambah') || (isset($errors) && $errors->any()); @endphp
    <div class="collapse {{ $bukaTambah ? 'show' : '' }} mb-3" id="kartuTambah">
        <div class="card border-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-plus me-1"></i> Tambah publikasi ke SIPOTRET</h3>
                <div class="card-actions"><button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#kartuTambah" aria-label="Tutup"></button></div>
            </div>
            <form method="post" action="{{ route('checker.sipotret_draf.tambah') }}" id="formTambah">
                @csrf
                <div class="card-body">
                    @if(isset($errors) && $errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="tbJudul">Judul publikasi <span class="text-danger">*</span></label>
                            <input type="text" name="pub_judul" id="tbJudul" maxlength="500" class="form-control" required value="{{ old('pub_judul') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tbRilis">Rencana rilis <span class="text-danger">*</span></label>
                            <input type="date" name="pub_tgl_rilis" id="tbRilis" class="form-control" required value="{{ old('pub_tgl_rilis') }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="tbJudulEn">Judul (EN)</label>
                            <input type="text" name="pub_judul_en" id="tbJudulEn" maxlength="500" class="form-control" value="{{ old('pub_judul_en') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tbJenis">Jenis</label>
                            <select name="pub_status" id="tbJenis" class="form-select">
                                <option value="non_arc" @selected(old('pub_status') !== 'arc')>Non ARC</option>
                                <option value="arc" @selected(old('pub_status') === 'arc')>ARC</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">PJK <span class="text-danger">*</span></label>
                            @if($master['users'])
                                @include('checker.partials.combo', ['name' => 'us_id', 'value' => (string) old('us_id'), 'data' => $master['users'],
                                    'placeholder' => 'Ketik nama atau email…', 'kode' => false, 'wajib' => true])
                            @else
                                <div class="text-warning small">Daftar user SIPOTRET tidak terbaca.</div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ketua tim</label>
                            @if($master['users'])
                                @include('checker.partials.combo', ['name' => 'spv_id', 'value' => (string) old('spv_id'), 'data' => $master['users'],
                                    'placeholder' => 'Ketik nama atau email…', 'kode' => false, 'kosong' => 'Kosongkan'])
                            @endif
                        </div>
                    </div>

                    <details class="mt-3" @if(old('pub_no') || old('pub_no_katalog') || old('pub_issn') || old('pub_volume')) open @endif>
                        <summary class="text-secondary small">Nomor, volume, katalog, ISSN (opsional)</summary>
                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label class="form-label" for="tbNo">Nomor publikasi</label>
                                <input type="text" name="pub_no" id="tbNo" maxlength="100" class="form-control font-monospace" value="{{ old('pub_no') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="tbVol">Volume</label>
                                <input type="text" name="pub_volume" id="tbVol" maxlength="50" class="form-control" value="{{ old('pub_volume') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ISSN</label>
                                @if($master['issn'])
                                    @include('checker.partials.combo', ['name' => 'pub_issn', 'value' => (string) old('pub_issn'), 'data' => $master['issn'],
                                        'placeholder' => 'Ketik nomor atau judul ISSN…', 'kosong' => 'Kosongkan (tidak punya ISSN)'])
                                @else
                                    <input type="text" name="pub_issn" maxlength="50" class="form-control font-monospace" value="{{ old('pub_issn') }}">
                                @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label">Nomor katalog</label>
                                @if($master['katalog'])
                                    @include('checker.partials.combo', ['name' => 'pub_no_katalog', 'value' => (string) old('pub_no_katalog'), 'data' => $master['katalog'],
                                        'placeholder' => 'Ketik nomor atau nama katalog…', 'kosong' => 'Kosongkan (belum ada nomor katalog)'])
                                @else
                                    <input type="text" name="pub_no_katalog" maxlength="100" class="form-control font-monospace" value="{{ old('pub_no_katalog') }}">
                                @endif
                            </div>
                        </div>
                    </details>
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                    <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Simpan ke SIPOTRET</button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- ============ Ringkasan antrean (sekaligus filter) ============ --}}
<div class="row row-cards mb-3" id="ubin">
    @foreach([
        ['pemeriksa', 'Tugas pemeriksa', 'ti-user-check', 'primary'],
        ['pjk',       'Menunggu PJK',    'ti-hourglass', 'orange'],
        ['mepet',     'Tenggat ≤ 3 hari', 'ti-bell-ringing', 'warning'],
        ['telat',     'Lewat batas',     'ti-alert-triangle', 'danger'],
        ['selesai',   'Selesai',         'ti-circle-check', 'success'],
    ] as [$k, $label, $ikon, $w])
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="card card-sm card-link ubin w-100 text-start" data-ubin="{{ $k }}">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar bg-{{ $w }}-lt"><i class="ti {{ $ikon }} text-{{ $w }} fs-2"></i></span>
                    <div class="lh-sm">
                        <div class="fs-2 fw-bold {{ $jml[$k] && in_array($k, ['telat', 'mepet'], true) ? 'text-' . $w : '' }}">{{ $jml[$k] }}</div>
                        <div class="fw-semibold small">{{ $label }}</div>
                    </div>
                </div>
            </button>
        </div>
    @endforeach
</div>

<div class="card">
    {{-- ============ Filter ============ --}}
    <div class="card-header flex-wrap gap-2">
        <form method="get" class="d-flex gap-2" id="formTahun">
            <select name="tahun" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()" title="Tahun publikasi">
                <option value="semua" @selected($tahun === 'semua')>Semua tahun</option>
                @foreach($daftarTahun as $t)
                    <option value="{{ $t }}" @selected($t === $tahun)>{{ $t }}</option>
                @endforeach
            </select>
        </form>
        <div class="input-group input-group-sm input-group-flat flex-fill" style="min-width:200px; max-width:360px;">
            <span class="input-group-text"><i class="ti ti-search"></i></span>
            <input type="search" id="cari" class="form-control form-control-sm" placeholder="Cari judul / PJK…" value="{{ $cari }}">
        </div>
        @php
            // bulan rencana rilis → jumlah publikasi (pilihan filter bulan)
            $jmlBulan = $baris->filter(fn($r) => $r['tgl_rilis'])->countBy(fn($r) => (int) substr($r['tgl_rilis'], 5, 2));
        @endphp
        <select id="saringBulan" class="form-select form-select-sm" style="width:auto;" title="Bulan rencana rilis">
            <option value="">Semua bulan rilis</option>
            @foreach(range(1, 12) as $b)
                <option value="{{ $b }}" @disabled(!$jmlBulan->get($b))>{{ \Carbon\Carbon::create(2000, $b, 1)->locale('id')->translatedFormat('F') }} ({{ $jmlBulan->get($b, 0) }})</option>
            @endforeach
            @if($baris->contains(fn($r) => !$r['tgl_rilis']))<option value="0">Belum ada rencana rilis</option>@endif
        </select>
        <select id="saringJenis" class="form-select form-select-sm" style="width:auto;" title="Jenis publikasi">
            <option value="">ARC &amp; Non ARC</option>
            <option value="arc">ARC</option>
            <option value="non_arc">Non ARC</option>
        </select>
        <select id="saringRilis" class="form-select form-select-sm" style="width:auto;" title="Status rilis">
            <option value="">Sudah &amp; belum rilis</option>
            <option value="0">Belum rilis</option>
            <option value="1">Sudah rilis</option>
        </select>
        <select id="urutan" class="form-select form-select-sm" style="width:auto;" title="Urutan">
            <option value="tenggat">Urut: tenggat terdekat</option>
            <option value="rilis">Urut: tanggal rilis</option>
        </select>
        @if($adaTugasSaya)
            <label class="form-check form-switch mb-0 ms-1">
                <input class="form-check-input" type="checkbox" id="hanyaSaya">
                <span class="form-check-label small">Tugas saya <span class="badge bg-green-lt">{{ collect($tugas)->where('saya', true)->count() }}</span></span>
            </label>
        @endif
        <div class="ms-auto small text-secondary" id="infoSaring"></div>
    </div>

    @php $nomorBermasalah = $baris->filter(fn($r) => $r['cek_nomor'] ?? null); @endphp
    @if($nomorBermasalah->isNotEmpty())
        <div class="card-body py-2 border-bottom bg-warning-lt small d-flex flex-wrap align-items-center gap-2">
            <i class="ti ti-sort-ascending-numbers text-warning fs-3"></i>
            <span><strong>{{ $nomorBermasalah->count() }} nomor</strong> tidak urut / ganda</span>
            <a href="#" id="lihatNomor" class="ms-auto">Urutkan per tanggal rilis</a>
        </div>
    @endif

    {{-- ============ Antrean ============ --}}
    <div class="table-responsive">
        <table class="table table-vcenter card-table" id="tblDraf">
            <thead>
                <tr>
                    <th class="w-1"><input type="checkbox" class="form-check-input" id="pilihSemua" aria-label="Pilih semua yang tampil" title="Pilih semua yang tampil"></th>
                    <th>Publikasi</th>
                    <th>Tenggat</th>
                    <th>Langkah berikutnya</th>
                    <th class="w-1 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($baris as $r)
                @php
                    $kini = $r['kini']; $detail = route('checker.sipotret_draf.show', $r['pub_id']);
                    $bisaPilih = ($r['pdf'] && $r['boleh']) || $isAdmin;
                @endphp
                <tr data-pub="{{ $r['pub_id'] }}" data-judul="{{ $r['judul'] }}" data-rilis="{{ $r['tgl_rilis'] }}"
                    data-tr="{{ $r['pdf'] && $r['boleh'] ? $r['pdf']['tr_id'] : '' }}"
                    data-kategori="{{ $r['kategori'] }}" data-telat="{{ $r['telat'] && $r['kategori'] !== 'selesai' ? 1 : 0 }}" data-mepet="{{ $r['mepet'] && $r['kategori'] !== 'selesai' ? 1 : 0 }}"
                    data-langkah="{{ $kini['no'] ?? '' }}" data-saya="{{ $r['saya'] ? 1 : 0 }}"
                    data-jenis="{{ $r['status'] === 'arc' ? 'arc' : 'non_arc' }}" data-sudah-rilis="{{ $r['rilis'] ? 1 : 0 }}"
                    data-urut-tenggat="{{ $loop->index }}" data-tgl-rilis="{{ $r['tgl_rilis'] ?? '9999-12-31' }}"
                    data-urut-nomor="{{ \App\Services\SipotretDrafService::urutNomor($r['nomor'] ?? null) ?? 999999 }}"
                    data-teks="{{ mb_strtolower($r['judul'] . ' ' . ($r['judul_en'] ?? '') . ' ' . ($r['pjk'] ?? '') . ' ' . ($r['nomor'] ?? '') . ' ' . ($r['volume'] ?? '')) }}"
                    class="{{ $r['kategori'] === 'selesai' ? 'baris-selesai' : '' }}">
                    <td>@if($bisaPilih)<input type="checkbox" class="form-check-input pilih" aria-label="Pilih">@endif</td>
                    <td style="min-width:260px;">
                        <a href="{{ $detail }}" class="fw-semibold text-reset d-block lh-sm">{{ $r['judul'] }}</a>
                        {{-- baris 2: nomor & volume (diisi admin/pemeriksa, harus urut tanggal rilis) --}}
                        @php $cn = $r['cek_nomor'] ?? null; @endphp
                        <div class="small mt-1 d-flex flex-wrap align-items-center gap-3 baris-nomor">
                            <span title="{{ $cn ? $cn['pesan'] : 'Nomor publikasi' }}">
                                <span class="text-secondary">Nomor</span>
                                @if($r['nomor'])
                                    <strong class="font-monospace {{ $cn ? 'text-warning' : '' }}">@if($cn)<i class="ti ti-alert-triangle"></i> @endif{{ $r['nomor'] }}</strong>
                                @else
                                    <a href="{{ $detail }}#langkah-1" class="text-danger fw-semibold">belum ada</a>
                                @endif
                            </span>
                            <span>
                                <span class="text-secondary">Volume</span>
                                <strong>{{ $r['volume'] ?? '-' }}</strong>
                            </span>
                        </div>
                        {{-- baris 3: tahun, PJK, jenis, versi, penugasan --}}
                        <div class="small text-secondary mt-1 d-flex flex-wrap align-items-center gap-1">
                            @if($tahun === 'semua' && $r['tahun'])<span class="badge bg-blue-lt">{{ $r['tahun'] }}</span>@endif
                            <span><i class="ti ti-user"></i> {{ $r['pjk'] ?? '-' }}</span>
                            <span class="badge {{ $r['status'] === 'arc' ? 'bg-azure-lt' : 'bg-secondary-lt' }}">{{ $r['status'] === 'arc' ? 'ARC' : 'Non ARC' }}</span>
                            @if($r['pdf'])<span class="badge bg-secondary-lt">{{ $r['pdf']['flag'] }}</span>@endif
                            <span class="tugas-badge" data-pub="{{ $r['pub_id'] }}"></span>
                        </div>
                    </td>
                    <td class="text-nowrap" style="min-width:260px;">
                        @php $tk = $r['tenggat_kini']; @endphp
                        @if($r['rilis'])
                            <div class="small text-success fw-semibold"><i class="ti ti-rocket"></i> Sudah rilis</div>
                            <div class="small text-secondary">Rencana rilis {{ $hariTgl($r['tgl_rilis']) }}</div>
                        @elseif(!$r['tenggat'])
                            <span class="small text-warning"><i class="ti ti-calendar-off"></i> Belum ada rencana rilis</span>
                        @else
                            {{-- kotak hitung mundur tenggat yang sedang dikejar + daftar semua tenggat dengan hari & tanggal --}}
                            @php
                                $adaDraf = !empty($r['versi']);
                                $semua = array_values(array_filter([
                                    $adaDraf ? null : ['H-10', 'Mulai draf', $r['tenggat']['h10'], false, false],
                                    ['H-7', 'Kirim draf', $r['tenggat']['h7'], $adaDraf, true],
                                    ['H-3', 'SPRP & portal', $r['tenggat']['h3'], $r['alur']['langkah'][3]['status'] === 'selesai' && $r['alur']['langkah'][4]['status'] === 'selesai', true],
                                    ['H', 'Rilis', $r['tgl_rilis'], false, true],
                                ]));
                            @endphp
                            <div class="d-flex align-items-start gap-2">
                                @if($tk)
                                    <span class="badge bg-{{ $tk['level'] }} text-white tenggat-sisa" title="{{ $tk['kode'] }} · {{ $tk['label'] }}">
                                        @if($tk['sisa'] < 0)<span>{{ abs($tk['sisa']) }}</span><small>hr lewat</small>
                                        @elseif($tk['sisa'] === 0)<span>!</span><small>hari ini</small>
                                        @else<span>{{ $tk['sisa'] }}</span><small>hari lagi</small>@endif
                                    </span>
                                @endif
                                <table class="tenggat-tabel">
                                    @foreach($semua as [$kd, $nm, $tg, $ok, $wajib])
                                        @php
                                            $kejar = $tk && $tk['kode'] === $kd;
                                            $kls = match (true) {
                                                $ok => 'text-success',
                                                $kejar => 'fw-bold text-' . ($tk['level'] === 'primary' ? 'body' : $tk['level']),
                                                $wajib && $tg < $hariIni => 'text-danger',
                                                default => 'text-secondary',
                                            };
                                        @endphp
                                        <tr class="{{ $kls }} {{ $kejar ? 'tenggat-kejar' : '' }}">
                                            <td class="pe-1">@if($ok)<i class="ti ti-check"></i>@elseif($kejar)<i class="ti ti-caret-right"></i>@elseif($wajib && $tg < $hariIni)<i class="ti ti-alert-triangle"></i>@endif</td>
                                            <td class="pe-2" title="{{ $kd }}">{{ $nm }}</td>
                                            <td>{{ \Carbon\Carbon::parse($tg)->locale('id')->translatedFormat('D, d M') }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        @endif
                    </td>
                    <td style="min-width:230px;">
                        <div class="d-flex gap-1 mb-1" style="max-width:220px;">
                            @foreach($r['alur']['langkah'] as $l)
                                <span class="flex-fill rounded bg-{{ $warnaL[$l['status']] }}" style="height:6px;" title="{{ $l['no'] }}. {{ $l['judul'] }} ({{ $l['oleh'] }}): {{ $l['ket'] }}"></span>
                            @endforeach
                        </div>
                        @if($kini)
                            <div class="small lh-sm">
                                <strong>{{ $kini['no'] }} · {{ $kini['judul'] }}</strong>
                                <span class="badge {{ $kini['oleh'] === 'PJK' ? 'bg-orange-lt' : 'bg-blue-lt' }} ms-1">{{ $kini['oleh'] }}</span>
                                @if($r['tinggal_kirim'])<div class="text-purple">tinggal kirim hasil</div>@endif
                            </div>
                        @elseif($r['kategori'] === 'selesai')
                            <div class="small text-success"><i class="ti ti-circle-check"></i> Selesai</div>
                        @endif
                        @if($r['lewat_langkah']->isNotEmpty())
                            <div class="small text-danger"><i class="ti ti-alert-triangle"></i> Terlewat: langkah {{ $r['lewat_langkah']->pluck('no')->implode(', ') }}</div>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if($r['pdf'])
                            <a href="{{ route('checker.sipotret_draf.pdf', $r['pdf']['tr_id']) }}" target="_blank" class="btn btn-sm btn-icon btn-ghost-secondary" title="Lihat PDF {{ $r['pdf']['flag'] }}"><i class="ti ti-eye"></i></a>
                        @endif
                        {{-- satu tombol utama sesuai langkah berikutnya --}}
                        @php $n = $kini['no'] ?? null; @endphp
                        @if(!$r['boleh'] && $r['kategori'] !== 'selesai')
                            <a href="{{ $detail }}" class="btn btn-sm" title="Ditugaskan ke {{ $tugas[$r['pub_id']]['nama'] ?? 'pemeriksa lain' }}"><i class="ti ti-lock me-1"></i>Lihat</a>
                        @elseif($r['tinggal_kirim'])
                            <a href="{{ $detail }}#langkah-3" class="btn btn-sm btn-purple"><i class="ti ti-send me-1"></i>Kirim hasil</a>
                        @elseif($n === 3 && $r['pdf'])
                            <a href="{{ route('checker.index', ['draf' => $r['pdf']['tr_id']]) }}" class="btn btn-sm btn-primary"><i class="ti ti-player-play me-1"></i>Periksa {{ $r['pdf']['flag'] }}</a>
                        @elseif($n === 1)
                            <a href="{{ $detail }}#langkah-1" class="btn btn-sm btn-primary"><i class="ti ti-pencil me-1"></i>Isi kelengkapan</a>
                        @elseif($n === 4 && ($kini['aksi'] ?? null) === 'sprp')
                            <a href="{{ $detail }}#langkah-4" class="btn btn-sm btn-primary"><i class="ti ti-file-certificate me-1"></i>Putuskan SPRP</a>
                        @elseif($n === 4 && auth()->user()?->isAdmin())
                            {{-- PJK belum upload / SPRP ditolak: admin bisa upload atas nama PJK --}}
                            <a href="{{ $detail }}#langkah-4" class="btn btn-sm btn-outline-primary" title="Upload SPRP atas nama PJK"><i class="ti ti-file-upload me-1"></i>Upload SPRP</a>
                        @elseif($n === 5)
                            <a href="{{ $detail }}#langkah-5" class="btn btn-sm btn-primary"><i class="ti ti-upload me-1"></i>Upload portal</a>
                        @elseif($n === 6)
                            <a href="{{ $detail }}#langkah-6" class="btn btn-sm btn-success"><i class="ti ti-rocket me-1"></i>Tandai rilis</a>
                        @elseif($n === 2 && !$r['pdf'] && auth()->user()?->isAdmin() && $kini['lewat'])
                            <a href="{{ $detail }}#kartuUpload" class="btn btn-sm btn-outline-danger" title="PJK lewat batas kirim draf: upload atas nama PJK"><i class="ti ti-upload me-1"></i>Upload</a>
                        @elseif($n === 2 && !empty($r['versi']) && $r['boleh'])
                            {{-- draf sudah ada, tinggal abstraksi: pemeriksa boleh melengkapi --}}
                            <a href="{{ $detail }}#langkah-2" class="btn btn-sm btn-outline-primary" title="Abstraksi belum lengkap; bisa dilengkapi pemeriksa"><i class="ti ti-pencil me-1"></i>Abstraksi</a>
                        @elseif($n === 2 || $n === 4)
                            <a href="{{ $detail }}#langkah-{{ $n }}" class="btn btn-sm btn-ghost-secondary" title="Menunggu PJK"><i class="ti ti-hourglass me-1"></i>Menunggu PJK</a>
                        @else
                            <a href="{{ $detail }}" class="btn btn-sm btn-ghost-secondary">Detail</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-secondary py-5">Tidak ada publikasi SIPOTRET{{ $tahun === 'semua' ? '' : ' untuk tahun ' . $tahun }}.</td></tr>
            @endforelse
                <tr id="kosong" style="display:none;"><td colspan="5" class="text-center text-secondary py-5">
                    <i class="ti ti-filter-off fs-1 d-block mb-2"></i>Tidak ada publikasi yang cocok dengan filter.
                    <a href="#" id="resetSaring" class="d-block mt-1">Tampilkan semua</a>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ============ Bar aksi massal: muncul saat ada yang dipilih ============ --}}
<div class="bar-massal shadow-lg" id="barMassal" hidden>
    <div class="container-xl d-flex flex-wrap align-items-center gap-2 py-2">
        <strong id="jmlPilih"></strong>
        <span class="text-secondary small" id="jmlPdf"></span>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" id="btnPeriksaPilih"><i class="ti ti-player-play me-1"></i> Periksa terpilih</button>
            @if($isAdmin)
                <div class="input-group input-group-sm" style="width:auto;">
                    <select class="form-select form-select-sm" id="pilihPemeriksa">
                        <option value="">Tugaskan ke…</option>
                        @foreach($pemeriksa as $u)
                            <option value="{{ $u['id'] }}">{{ $u['nama'] }}{{ $u['role'] === 'admin' ? ' (admin)' : '' }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn" id="btnTugaskan" disabled><i class="ti ti-user-check me-1"></i>Tugaskan</button>
                    <button type="button" class="btn" id="btnLepas" title="Lepas penugasan"><i class="ti ti-user-x"></i></button>
                </div>
            @endif
            <button type="button" class="btn btn-sm btn-ghost-secondary" id="btnBatalPilih">Batal</button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .ubin { border: 1px solid var(--tblr-border-color); }
    .ubin.active { border-color: var(--tblr-primary); box-shadow: 0 0 0 2px var(--tblr-primary-lt); }
    .tenggat-sisa { min-width: 3rem; display: inline-flex; flex-direction: column; align-items: center; line-height: 1.1; padding: .3rem .35rem; }
    .tenggat-sisa span { font-size: 1.1rem; font-weight: 700; }
    .tenggat-tabel { font-size: 12px; line-height: 1.35; border-collapse: collapse; }
    .tenggat-tabel td { padding: 1px 0; white-space: nowrap; }
    .tenggat-tabel td:first-child { width: 1rem; }
    .tenggat-kejar td { background: var(--tblr-bg-surface-secondary, rgba(0,0,0,.03)); }
    .tenggat-sisa small { font-size: .55rem; font-weight: 500; text-transform: uppercase; white-space: nowrap; }
    .bar-massal { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030; background: var(--tblr-bg-surface); border-top: 1px solid var(--tblr-border-color); }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/penugasan.js') }}?v={{ filemtime(public_path('js/penugasan.js')) }}"></script>
<script>
(() => {
    const MAKS = 10; // sama dengan batas halaman Pemeriksaan
    const CEK_URL = '{{ route('checker.index') }}';
    const ADMIN = {{ $isAdmin ? 'true' : 'false' }};
    const tbl = document.getElementById('tblDraf');
    const rows = [...tbl.querySelectorAll('tbody tr[data-pub]')];
    const $ = id => document.getElementById(id);

    // ---------- filter: ubin ringkasan + cari + langkah + tugas saya ----------
    let ubin = '';
    const terapkan = () => {
        const q = $('cari').value.trim().toLowerCase();
        const saya = $('hanyaSaya')?.checked;
        const jenis = $('saringJenis').value, rilis = $('saringRilis').value, bulan = $('saringBulan').value;
        let n = 0;
        rows.forEach(tr => {
            const d = tr.dataset;
            // bawaan: semua tampil (yang selesai di urutan akhir, tidak disembunyikan)
            const cocokUbin = ubin === '' || ubin === 'semua' ? true
                : ubin === 'telat' ? d.telat === '1' : ubin === 'mepet' ? d.mepet === '1' : d.kategori === ubin;
            const tampil = cocokUbin && (!q || d.teks.includes(q)) && (!saya || d.saya === '1')
                && (!jenis || d.jenis === jenis) && (rilis === '' || d.sudahRilis === rilis)
                && (bulan === '' || (bulan === '0' ? d.rilis === '' : d.rilis !== '' && +d.rilis.slice(5, 7) === +bulan));
            tr.style.display = tampil ? '' : 'none';
            if (!tampil) { const c = tr.querySelector('.pilih'); if (c) c.checked = false; }
            n += tampil;
        });
        $('kosong').style.display = rows.length && !n ? '' : 'none';
        $('infoSaring').textContent = n === rows.length ? `${n} publikasi` : `${n} dari ${rows.length} publikasi`;
        perbarui();
    };
    document.querySelectorAll('[data-ubin]').forEach(b => b.addEventListener('click', () => {
        ubin = ubin === b.dataset.ubin ? '' : b.dataset.ubin;   // klik lagi = lepas filter
        document.querySelectorAll('[data-ubin]').forEach(x => x.classList.toggle('active', x.dataset.ubin === ubin));
        terapkan();
    }));
    // dari Dashboard: #telat, #mepet, … langsung membuka saringan ubin itu
    document.querySelector(`[data-ubin="${CSS.escape(location.hash.slice(1))}"]`)?.click();
    $('cari').addEventListener('input', terapkan);
    $('saringJenis').addEventListener('change', terapkan);
    $('saringBulan').addEventListener('change', terapkan);
    $('saringRilis').addEventListener('change', terapkan);
    // urutan: tenggat terdekat (bawaan server) atau tanggal rilis lalu nomor, untuk mengecek nomor urut
    const tbody = tbl.querySelector('tbody');
    const urutkan = () => {
        const perRilis = $('urutan').value === 'rilis';
        [...rows].sort((a, b) => perRilis
            ? a.dataset.tglRilis.localeCompare(b.dataset.tglRilis) || (+a.dataset.urutNomor - +b.dataset.urutNomor)
            : +a.dataset.urutTenggat - +b.dataset.urutTenggat)
            .forEach(tr => tbody.insertBefore(tr, $('kosong')));
    };
    $('urutan').addEventListener('change', urutkan);
    $('lihatNomor')?.addEventListener('click', e => { e.preventDefault(); $('urutan').value = 'rilis'; urutkan(); });
    $('hanyaSaya')?.addEventListener('change', terapkan);
    $('resetSaring').addEventListener('click', e => {
        e.preventDefault();
        ubin = 'semua'; $('cari').value = ''; $('saringJenis').value = ''; $('saringRilis').value = ''; $('saringBulan').value = '';
        if ($('hanyaSaya')) $('hanyaSaya').checked = false;
        document.querySelectorAll('[data-ubin]').forEach(x => x.classList.remove('active'));
        terapkan();
    });

    // ---------- pilih & aksi massal ----------
    const terpilih = () => rows.filter(tr => tr.querySelector('.pilih:checked'));
    const bisaPeriksa = () => terpilih().filter(tr => tr.dataset.tr);
    function perbarui() {
        const n = terpilih().length, nPdf = bisaPeriksa().length;
        $('barMassal').hidden = !n;
        document.body.style.paddingBottom = n ? '64px' : '';
        $('jmlPilih').textContent = `${n} dipilih`;
        $('jmlPdf').textContent = nPdf > MAKS ? `maks. ${MAKS} untuk diperiksa sekaligus` : (nPdf < n ? `${nPdf} punya draf PDF untuk diperiksa` : '');
        $('btnPeriksaPilih').disabled = !nPdf || nPdf > MAKS;
        if ($('btnTugaskan')) $('btnTugaskan').disabled = !n || !$('pilihPemeriksa').value;
        const tampil = rows.filter(tr => tr.style.display !== 'none' && tr.querySelector('.pilih'));
        $('pilihSemua').checked = tampil.length > 0 && tampil.every(tr => tr.querySelector('.pilih').checked);
    }
    tbl.addEventListener('change', e => { if (e.target.matches('.pilih')) perbarui(); });
    $('pilihSemua').addEventListener('change', e => {
        rows.filter(tr => tr.style.display !== 'none').forEach(tr => { const c = tr.querySelector('.pilih'); if (c) c.checked = e.target.checked; });
        perbarui();
    });
    $('btnBatalPilih').addEventListener('click', () => { rows.forEach(tr => { const c = tr.querySelector('.pilih'); if (c) c.checked = false; }); perbarui(); });
    $('btnPeriksaPilih').addEventListener('click', () => { location.href = CEK_URL + '?draf=' + bisaPeriksa().map(tr => tr.dataset.tr).join(','); });
    $('pilihPemeriksa')?.addEventListener('change', perbarui);

    // ---------- penugasan pra rilis (js/penugasan.js; field "domain" dipakai untuk rencana rilis) ----------
    const TUGAS = @json((object) $tugas);
    Penugasan.init({ url: '{{ Route::has('checker.sipotret_draf.tugaskan') ? route('checker.sipotret_draf.tugaskan') : '' }}', csrf: document.querySelector('meta[name=csrf-token]')?.content ?? '' });
    const tampilTugas = () => document.querySelectorAll('.tugas-badge[data-pub]').forEach(el => { el.innerHTML = Penugasan.badge(TUGAS[el.dataset.pub]); });
    tampilTugas();
    const tugaskan = async lepas => {
        const pilih = terpilih();
        const sel = $('pilihPemeriksa');
        if (!pilih.length || (!lepas && !sel.value)) return;
        const ok = await Dialog.confirm(lepas ? `Lepas penugasan ${pilih.length} publikasi?`
            : `Tugaskan pemeriksaan pra rilis ${pilih.length} publikasi ke ${sel.selectedOptions[0].text}?`, { okText: lepas ? 'Lepas' : 'Tugaskan' });
        if (!ok) return;
        try {
            const r = await Penugasan.simpan(pilih.map(tr => ({ pub_id: Number(tr.dataset.pub), judul: tr.dataset.judul, domain: tr.dataset.rilis || null })),
                lepas ? null : sel.value);
            pilih.forEach(tr => { delete TUGAS[tr.dataset.pub]; });
            Object.assign(TUGAS, r.tugas || {});
            rows.forEach(tr => { tr.dataset.saya = TUGAS[tr.dataset.pub]?.saya ? '1' : '0'; });
            tampilTugas();
            pilih.forEach(tr => { tr.querySelector('.pilih').checked = false; });
            perbarui();
            Dialog.alert(r.pesan);
        } catch (e) {
            Dialog.alert('Penugasan gagal: ' + e.message);
        }
    };
    $('btnTugaskan')?.addEventListener('click', () => tugaskan(false));
    $('btnLepas')?.addEventListener('click', () => tugaskan(true));

    terapkan();
})();
</script>
@endpush
