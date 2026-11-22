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
@section('page-title', $judulHalaman)

@section('content')
@php
    $jmlPasca = $jumlah['semua'];
    $jmlPra   = $praRilis->filter(fn($r) => ($r['tahap']['tahap'] ?? '') !== 'rilis')->count();
    // tab awal: Pra Rilis bila tidak ada tugas pasca rilis (dan tidak sedang menyaring pasca rilis)
    $tabAwal  = !$jmlPasca && $praRilis->isNotEmpty() && !request()->hasAny(['bulan', 'tahun', 'status', 'wilayah', 'pemeriksa']) ? 'pra' : 'pasca';
@endphp
<div class="card mb-3">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item">
                <a href="#tabPasca" class="nav-link {{ $tabAwal === 'pasca' ? 'active' : '' }}" data-bs-toggle="tab" role="tab">
                    <i class="ti ti-world-check me-1"></i> Pasca Rilis · {{ $namaPeriode }} <span class="badge bg-blue-lt ms-2">{{ $jmlPasca }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#tabPra" class="nav-link {{ $tabAwal === 'pra' ? 'active' : '' }}" data-bs-toggle="tab" role="tab">
                    <i class="ti ti-file-pencil me-1"></i> Pra Rilis <span class="badge bg-blue-lt ms-2">{{ $jmlPra }}</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        {{-- ================= Pasca rilis (per periode) ================= --}}
        <div class="tab-pane {{ $tabAwal === 'pasca' ? 'active show' : '' }}" id="tabPasca" role="tabpanel">
            {{-- filter satu baris --}}
            <form method="get" class="card-body border-bottom py-2 d-flex flex-wrap align-items-center gap-2">
                <select name="bulan" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()"
                        title="Periode {{ $namaPeriode }} diperiksa pada {{ $namaPeriksa }}">
                    @foreach(range(1, 12) as $b)
                        <option value="{{ $b }}" @selected($b === $bulan)>{{ $bulanNama[$b] }}</option>
                    @endforeach
                </select>
                <select name="tahun" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    @for($t = date('Y') + 1; $t >= 2024; $t--)
                        <option value="{{ $t }}" @selected($t === $tahun)>{{ $t }}</option>
                    @endfor
                </select>
                @if($pemeriksa)
                    <select name="pemeriksa" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">Semua pemeriksa</option>
                        @foreach($pemeriksa as $u)
                            <option value="{{ $u['id'] }}" @selected($u['id'] === $pemeriksaId)>{{ $u['nama'] }}{{ $u['id'] === auth()->id() ? ' (saya)' : '' }}</option>
                        @endforeach
                    </select>
                @endif
                @if(count($wilayahAda) > 1 || $wilayah)
                    <select name="wilayah" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">Semua wilayah</option>
                        @foreach($wilayahAda as $kode)
                            <option value="{{ $kode }}" @selected((string) $wilayah === $kode)>{{ $namaWilayah($kode) }}</option>
                        @endforeach
                    </select>
                @endif
                @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <a href="{{ $filter($periodeKini) }}" class="btn btn-sm btn-ghost-secondary" title="Periode bulan lalu (diperiksa bulan ini)"><i class="ti ti-calendar-event"></i> Terkini</a>

                {{-- status = filter sekaligus ringkasan jumlah --}}
                <div class="btn-group btn-group-sm ms-md-auto">
                    <a href="{{ $filter(['status' => null]) }}" class="btn {{ !$status ? 'btn-primary' : '' }}">Semua {{ $jumlah['semua'] }}</a>
                    {{-- ringkas: yang perlu dikerjakan + satu "Selesai" (terkirim / tercatat di SIPOTRET / sudah sesuai) --}}
                    @foreach(['belum' => ['Belum diperiksa', null], 'tinjau' => ['Tinjauan', 'Tinjauan belum selesai'],
                              'selesai' => ['Belum kirim', 'Tinjauan selesai, belum dikirim ke SIPOTRET'],
                              'beres' => ['Selesai', 'Terkirim ' . $jumlah['terkirim'] . ' · Tercatat di SIPOTRET ' . $jumlah['sipotret'] . ' · Sudah sesuai ' . $jumlah['ok']]] as $kunci => [$label, $ket])
                        <a href="{{ $filter(['status' => $kunci]) }}" class="btn {{ $status === $kunci || ($kunci === 'beres' && in_array($status, \App\Http\Controllers\PenugasanController::BERES, true)) ? 'btn-primary' : '' }}"
                           @if($ket) title="{{ $ket }}" @endif>{{ $label }} {{ $jumlah[$kunci] }}</a>
                    @endforeach
                </div>
            </form>

            <div class="card-body">
                @if($baris->isNotEmpty())
                    <div class="d-flex mb-2">
                        <button type="button" class="btn btn-sm btn-ghost-secondary ms-auto" onclick="toggleSemuaDetail(this)" title="Buka / tutup semua catatan">
                            <i class="ti ti-list-details"></i> <span>Tampilkan semua catatan</span>
                        </button>
                    </div>
                    @include('checker.partials.pub-pilih-bar')
                @endif

                @php $wilayahSebelum = null; $banyakWilayah = $perWilayah->count() > 1; @endphp
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

                    {{-- judul kelompok per wilayah (hanya bila lebih dari satu wilayah) --}}
                    @if($banyakWilayah && $kodeWilayah !== $wilayahSebelum)
                        @php $wilayahSebelum = $kodeWilayah; @endphp
                        <div class="d-flex align-items-center gap-2 {{ $i ? 'mt-4' : '' }} mb-2 pb-1 border-bottom pub-grup-wilayah">
                            <i class="ti ti-map-pin text-primary"></i>
                            <strong>{{ $namaWilayah($kodeWilayah) }}</strong>
                            <span class="badge bg-primary-lt">{{ $perWilayah[$kodeWilayah] ?? 0 }}</span>
                        </div>
                    @endif

                    @include('checker.partials.pub-card', ['p' => $r['kartu'], 'progres' => $progres])
                @empty
                    <div class="text-center py-4 text-secondary">
                        <i class="ti ti-user-check d-block mb-2" style="font-size:36px;opacity:.3;"></i>
                        {{ $status ? 'Tidak ada tugas dengan status ini.' : 'Belum ada tugas periode ini.' }}
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ================= Pra rilis (draf SIPOTRET, tidak per periode) — tampilan sama dengan Pasca Rilis ================= --}}
        <div class="tab-pane {{ $tabAwal === 'pra' ? 'active show' : '' }}" id="tabPra" role="tabpanel">
            @php
                // tahap → [label, warna badge, ikon]
                $tahapPraInfo = [
                    'menunggu_periksa' => ['Perlu diperiksa', 'bg-purple-lt', 'eye-search'],
                    'belum_upload'     => ['Belum upload draf', 'bg-red-lt', 'file-off'],
                    'revisi'           => ['Revisi PJK', 'bg-yellow-lt', 'pencil'],
                    'menunggu_sprp'    => ['Menunggu SPRP', 'bg-blue-lt', 'file-certificate'],
                    'siap_rilis'       => ['Siap rilis', 'bg-green-lt', 'rocket'],
                    'rilis'            => ['Sudah rilis', 'bg-green-lt', 'circle-check'],
                ];
                $warnaLevel = ['danger' => 'danger', 'warning' => 'warning', 'info' => 'secondary', 'ok' => 'success'];
                $hitungPra = fn($n) => $n === null ? '' : ($n === 0 ? 'hari ini' : ($n > 0 ? $n . ' hari lagi' : 'lewat ' . abs($n) . ' hari'));
                $fmtPra    = fn($t) => $t ? \Carbon\Carbon::parse($t)->locale('id')->translatedFormat('D, d M Y') : '-';
                $tglPra    = fn($r) => $r['tahap']['tgl_rilis'] ?? $r['tugas']->tgl_rilis?->format('Y-m-d');
                $bulanPra  = $praRilis->map($tglPra)->filter()->map(fn($t) => (int) substr($t, 5, 2))->unique()->sort()->values();
                $jmlTahap  = $praRilis->countBy(fn($r) => $r['tahap']['tahap'] ?? '-');
            @endphp

            {{-- filter satu baris (seperti Pasca Rilis): status = tombol berjumlah di kanan --}}
            <div class="card-body border-bottom py-2 d-flex flex-wrap align-items-center gap-2" id="saringPra">
                <div class="input-group input-group-sm input-group-flat flex-fill" style="min-width:200px; max-width:360px;">
                    <span class="input-group-text"><i class="ti ti-search"></i></span>
                    <input type="search" class="form-control form-control-sm" data-f="cari" placeholder="Cari judul / PJK…">
                </div>
                <select class="form-select form-select-sm" style="width:auto;" data-f="bulan">
                    <option value="">Semua bulan rilis</option>
                    @foreach($bulanPra as $b)<option value="{{ $b }}">{{ $bulanNama[$b] }}</option>@endforeach
                </select>
                @if($pemeriksa)
                    <select class="form-select form-select-sm" style="width:auto;" data-f="pemeriksa">
                        <option value="">Semua pemeriksa</option>
                        @foreach($pemeriksa as $u)<option value="{{ $u['id'] }}">{{ $u['nama'] }}{{ $u['id'] === auth()->id() ? ' (saya)' : '' }}</option>@endforeach
                    </select>
                @endif
                <div class="btn-group btn-group-sm ms-md-auto" id="praStatus">
                    <button type="button" class="btn btn-primary" data-tahap="">Belum rilis {{ $jmlPra }}</button>
                    @foreach($tahapPraInfo as $k => [$nm])
                        @if($k !== 'rilis' && $jmlTahap->get($k))<button type="button" class="btn" data-tahap="{{ $k }}">{{ $nm }} {{ $jmlTahap->get($k) }}</button>@endif
                    @endforeach
                    <button type="button" class="btn" data-tahap="rilis">Sudah rilis {{ $jmlTahap->get('rilis', 0) }}</button>
                </div>
            </div>

            <div class="card-body">
                @if($praRilis->isEmpty())
                    <div class="text-center py-4 text-secondary">
                        <i class="ti ti-file-pencil d-block mb-2" style="font-size:36px;opacity:.3;"></i> Belum ada tugas pra rilis.
                    </div>
                @else
                    {{-- bar pilihan (seperti Pasca Rilis) --}}
                    {{-- sticky-top & tombol tidak pernah disembunyikan (hanya nonaktif) supaya tidak bergeser --}}
                    <div class="alert alert-info d-flex align-items-center flex-wrap gap-2 py-2 mb-3 sticky-top" id="barPra">
                        <i class="ti ti-checkbox"></i>
                        <span class="small text-nowrap" id="praInfo"></span>
                        <div class="btn-list ms-auto">
                        <button type="button" class="btn btn-sm btn-ghost-primary" id="praSemua"><i class="ti ti-checks"></i> <span>Pilih Semua</span></button>
                        <button type="button" class="btn btn-sm btn-ghost-secondary" id="praBatal" disabled><i class="ti ti-x"></i> <span>Batal</span></button>
                        @if($pemeriksa && Route::has('checker.sipotret_draf.tugaskan'))
                            <div class="input-group input-group-sm w-auto" id="praTugasBar">
                                <select id="praPemeriksa" class="form-select form-select-sm w-auto" aria-label="Pemeriksa" disabled>
                                    <option value="">Pilih pemeriksa…</option>
                                    @foreach($pemeriksa as $u)<option value="{{ $u['id'] }}">{{ $u['nama'] }}{{ $u['role'] === 'admin' ? ' (admin)' : '' }}</option>@endforeach
                                </select>
                                <button type="button" class="btn btn-sm btn-purple" id="praTugaskan" title="Tugaskan publikasi yang dicentang ke pemeriksa ini" disabled>
                                    <i class="ti ti-user-check"></i> <span id="praTugasLabel">Tugaskan</span>
                                </button>
                                <button type="button" class="btn btn-sm" id="praLepas" title="Lepas penugasan publikasi yang dicentang" disabled><i class="ti ti-user-off"></i></button>
                            </div>
                        @endif
                        <button type="button" class="btn btn-sm btn-primary" id="praPeriksa" disabled>
                            <i class="ti ti-rosette-discount-check"></i> <span id="praPeriksaLabel">Periksa</span>
                        </button>
                        </div>
                    </div>

                    <div id="daftarPra">
                        @foreach($praRilis as $r)
                            @php
                                $t = $r['tugas']; $s = $r['tahap'];
                                [$nmTahap, $clsTahap, $ikTahap] = $s ? $tahapPraInfo[$s['tahap']] : ['Tidak terbaca', 'bg-secondary-lt', 'question-mark'];
                                $w = $s ? $warnaLevel[$s['level']] : 'secondary';
                                $detail = route('checker.sipotret_draf.show', $t->sipotret_pub_id);
                                $judul  = $s['judul'] ?? $t->judul ?? 'Publikasi #' . $t->sipotret_pub_id;
                                $trId   = ($s['tr_id'] ?? null) && ($s['tahap'] ?? '') !== 'rilis' ? $s['tr_id'] : null;
                            @endphp
                            <div class="pub-card mb-2" data-pra data-tahap="{{ $s['tahap'] ?? '' }}" data-bulan="{{ ($tg = $tglPra($r)) ? (int) substr($tg, 5, 2) : '' }}"
                                 data-pemeriksa="{{ $t->user_id }}" data-pub="{{ $t->sipotret_pub_id }}" data-judul="{{ $judul }}" data-rilis-tgl="{{ $tg }}" data-tr="{{ $trId }}"
                                 data-teks="{{ mb_strtolower($judul . ' ' . ($s['pjk'] ?? '')) }}">
                                {{-- klik kotak → centang / batal centang; judul & tombol Detail → halaman detail --}}
                                <div class="d-flex gap-3 align-items-start flex-wrap pub-klik"
                                     onclick="if (!event.target.closest('label, a, button, input')) this.querySelector('.pra-pilih').click()">
                                    <label class="form-check m-0 pt-1" title="{{ $trId ? 'Pilih untuk diperiksa / ditugaskan' : 'Pilih untuk ditugaskan' }}">
                                        <input type="checkbox" class="form-check-input pra-pilih">
                                    </label>
                                    <div class="pub-cover d-flex align-items-center justify-content-center text-muted" style="font-size:20px;">
                                        <i class="ti ti-file-pencil text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1" style="flex: 1 1 0; min-width:0;">
                                        <div class="fw-semibold small mb-1" style="word-break: break-word;"><a href="{{ $detail }}" class="text-reset pub-judul" title="Buka detail publikasi">{{ $judul }}</a></div>
                                        <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                            <span class="badge {{ $clsTahap }} fs-5 px-2 py-1"><i class="ti ti-{{ $ikTahap }} me-1"></i>{{ $nmTahap }}</span>
                                            @if($s && $s['tahap'] !== 'rilis')
                                                <small class="text-{{ $w }} fw-semibold" title="{{ $s['batas_label'] }} · {{ $fmtPra($s['batas']) }}"><i class="ti ti-calendar-due"></i> {{ $hitungPra($s['sisa']) }}</small>
                                                <small class="text-secondary">{{ $s['batas_label'] }} {{ $fmtPra($s['batas']) }}</small>
                                            @endif
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 align-items-center small text-secondary">
                                            <span class="badge bg-azure-lt fw-normal">Rilis {{ $fmtPra($tg) }}</span>
                                            <span><i class="ti ti-user"></i> {{ $s['pjk'] ?? '-' }}</span>
                                            @if($pemeriksa)<span class="badge bg-purple-lt"><i class="ti ti-user-check me-1"></i>{{ $t->user?->full_name ?? '-' }}</span>@endif
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 align-items-center justify-content-end mt-2 flex-wrap">
                                    @if(($s['tahap'] ?? null) === 'menunggu_periksa' && $trId)
                                        <a href="{{ route('checker.index', ['draf' => $trId]) }}" class="btn btn-sm btn-primary"><i class="ti ti-player-play"></i><span class="ms-1">Periksa</span></a>
                                    @endif
                                    <a href="{{ $detail }}" class="btn btn-sm"><i class="ti ti-eye"></i><span class="ms-1">Detail</span></a>
                                </div>
                            </div>
                        @endforeach
                        <div class="text-center py-4 text-secondary" id="praKosong" style="display:none;">Tidak ada yang cocok.</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($baris->isNotEmpty())
    @include('checker.partials.pub-batch')
@endif

@endsection

@push('scripts')
<script>
(() => {
    // Tab Pra Rilis: filter (cari, bulan, pemeriksa, status) + pilih & aksi, sama dengan tab Pasca Rilis
    const box = document.getElementById('saringPra');
    const kartu = [...document.querySelectorAll('#daftarPra [data-pra]')];
    if (!box || !kartu.length) return;
    const $ = id => document.getElementById(id);
    const f = k => box.querySelector(`[data-f="${k}"]`);
    const MAKS = 10; // sama dengan batas halaman Pemeriksaan
    let tahap = ''; // '' = semua yang belum rilis

    const terpilih = () => kartu.filter(c => c.querySelector('.pra-pilih').checked);
    const bisaPeriksa = () => terpilih().filter(c => c.dataset.tr);
    const tampil = () => kartu.filter(c => c.style.display !== 'none');

    const perbarui = () => {
        const n = terpilih().length, nPdf = bisaPeriksa().length;
        // info singkat (judul cukup di tooltip); tombol hanya aktif / nonaktif supaya tidak bergeser
        $('praInfo').innerHTML = n ? `<strong>${n}</strong> dipilih` : `${tampil().length} publikasi`;
        $('praInfo').title = terpilih().map(c => c.dataset.judul).join('\n');
        $('praBatal').disabled = !n;
        $('praTugasBar')?.querySelectorAll('select, button').forEach(el => { el.disabled = !n; });
        if ($('praTugasLabel')) $('praTugasLabel').textContent = n ? `Tugaskan (${n})` : 'Tugaskan';
        $('praPeriksa').disabled = !nPdf || nPdf > MAKS;
        $('praPeriksa').title = nPdf > MAKS ? `Maks. ${MAKS} draf sekali periksa` : (nPdf < n ? `${n - nPdf} belum punya draf` : '');
        $('praPeriksaLabel').textContent = nPdf ? `Periksa (${nPdf})` : 'Periksa';
        kartu.forEach(c => c.classList.toggle('border-primary', c.querySelector('.pra-pilih').checked));
    };
    const terapkan = () => {
        const q = f('cari').value.trim().toLowerCase(), bulan = f('bulan').value, pem = f('pemeriksa')?.value || '';
        let n = 0;
        kartu.forEach(c => {
            const d = c.dataset;
            const ok = (!q || d.teks.includes(q)) && (!bulan || d.bulan === bulan) && (!pem || d.pemeriksa === pem)
                && (tahap === '' ? d.tahap !== 'rilis' : d.tahap === tahap);
            c.style.display = ok ? '' : 'none';
            if (!ok) c.querySelector('.pra-pilih').checked = false; // yang tersaring tidak ikut terpilih
            n += ok;
        });
        $('praKosong').style.display = n ? 'none' : '';
        perbarui();
    };

    box.addEventListener('input', terapkan);
    box.addEventListener('change', terapkan);
    $('praStatus').addEventListener('click', e => {
        const b = e.target.closest('[data-tahap]');
        if (!b) return;
        tahap = b.dataset.tahap;
        $('praStatus').querySelectorAll('[data-tahap]').forEach(x => x.classList.toggle('btn-primary', x === b));
        terapkan();
    });
    $('daftarPra').addEventListener('change', e => { if (e.target.matches('.pra-pilih')) perbarui(); });
    $('praSemua').addEventListener('click', () => { tampil().forEach(c => { c.querySelector('.pra-pilih').checked = true; }); perbarui(); });
    $('praBatal').addEventListener('click', () => { kartu.forEach(c => { c.querySelector('.pra-pilih').checked = false; }); perbarui(); });
    $('praPeriksa').addEventListener('click', () => {
        location.href = '{{ route('checker.index') }}?draf=' + bisaPeriksa().map(c => c.dataset.tr).join(',');
    });

    // penugasan pra rilis: endpoint sama dengan halaman Pra Rilis ("domain" = rencana rilis)
    const tugaskan = async lepas => {
        const pilih = terpilih(), sel = $('praPemeriksa');
        if (!pilih.length) return;
        if (!lepas && !sel.value) return Dialog.alert('Pilih pemeriksa dulu.');
        const ok = await Dialog.confirm(lepas ? `Lepas penugasan ${pilih.length} publikasi?`
            : `Tugaskan ${pilih.length} publikasi ke ${sel.selectedOptions[0].text}?`, { okText: lepas ? 'Lepas' : 'Tugaskan' });
        if (!ok) return;
        try {
            const res = await fetch('{{ Route::has('checker.sipotret_draf.tugaskan') ? route('checker.sipotret_draf.tugaskan') : '' }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
                body: JSON.stringify({ pubs: pilih.map(c => ({ pub_id: Number(c.dataset.pub), judul: c.dataset.judul, domain: c.dataset.rilisTgl || null })),
                                       user_id: lepas ? null : sel.value }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || `Server membalas ${res.status}`);
            location.hash = 'pra';
            location.reload();
        } catch (e) {
            Dialog.alert('Penugasan gagal: ' + e.message);
        }
    };
    $('praTugaskan')?.addEventListener('click', () => tugaskan(false));
    $('praLepas')?.addEventListener('click', () => tugaskan(true));

    if (location.hash === '#pra') document.querySelector('a[href="#tabPra"]')?.click();
    terapkan();
})();
</script>
@endpush

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
