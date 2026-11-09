@extends('layouts.app')

@php
    $bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $param  = array_filter(['tahun' => $tahun, 'bulan' => $bulan, 'status' => $status]);
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

@section('title', "Publikasi Bermasalah — {$nama}")
@section('pretitle', 'SIPOTRET · Pasca Rilis')
@section('page-title', "Publikasi Bermasalah — {$nama}")
@section('page-subtitle', 'Publikasi yang punya catatan Pasca Rilis di SIPOTRET — centang hingga 10 publikasi untuk diperiksa ulang sekaligus')
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
                                    @selected($kode == $wilayah)>{{ $kode }} — {{ $n }}</option>
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
                    </div>

                    @if($daftar)
                        {{-- Pilihan beberapa publikasi (maks. MAX_PUBS) untuk diperiksa sekaligus --}}
                        <div class="alert alert-info d-flex align-items-center flex-wrap gap-2 py-2 mb-3">
                            {{-- Periode pemeriksaan ulang — hasilnya nanti dikirim ke SIPOTRET pada periode ini --}}
                            <div class="d-flex align-items-center flex-wrap gap-2 w-100 pb-2 mb-1 border-bottom">
                                <i class="ti ti-calendar"></i>
                                <label class="small fw-semibold" for="periodeBulan">Periode pemeriksaan</label>
                                <select id="periodeBulan" class="form-select form-select-sm w-auto">
                                    @foreach(range(1, 12) as $b)
                                        <option value="{{ $b }}" @selected($b === $periodeBaru['bulan'])>{{ $bulanNama[$b] }}</option>
                                    @endforeach
                                </select>
                                <input type="number" id="periodeTahun" class="form-control form-control-sm" style="width:90px;"
                                       value="{{ $periodeBaru['tahun'] }}" min="2000" max="2100">
                                <small class="text-secondary">
                                    hasil pemeriksaan ulang dikirim ke SIPOTRET pada periode ini (bawaan: sebulan setelah periode terakhir).
                                    Catatan periode terakhir otomatis jadi isian awal tinjauan — baru masuk SIPOTRET saat dikirim.
                                </small>
                            </div>
                            <i class="ti ti-checkbox"></i>
                            <span class="flex-fill small" id="selectionInfo"></span>
                            <button type="button" class="btn btn-sm btn-ghost-primary" onclick="pilihSemua()">
                                <i class="ti ti-checks"></i> <span>Pilih Semua</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-ghost-secondary" id="btnClearSel" onclick="clearSelection()" hidden>
                                <i class="ti ti-x"></i> <span>Batal Pilih</span>
                            </button>
                            @if($bisaApprove)
                                {{-- Approve beberapa sekaligus: yang dicentang & statusnya menunggu approve --}}
                                <form method="post" action="{{ route('dashboard.sipotret.approve') }}" id="formApprove" class="m-0">
                                    @csrf
                                    <button type="button" class="btn btn-sm btn-success" id="btnApproveSel" onclick="approveTerpilih()" hidden>
                                        <i class="ti ti-circle-check"></i> <span id="btnApproveSelLabel">Approve</span>
                                    </button>
                                </form>
                            @endif
                            <button type="button" class="btn btn-sm btn-primary" id="btnCheckSel" onclick="startCheck()" disabled>
                                <i class="ti ti-rosette-discount-check"></i> <span id="btnCheckSelLabel">Periksa Terpilih</span>
                            </button>
                        </div>
                    @endif

                    @forelse($daftar as $i => $p)
                        <div class="pub-card mb-2" id="pubcard-{{ $i }}">
                            {{-- tidak membuka halaman detail: pemeriksaan harus lewat tombol Periksa di atas supaya periodenya terpilih --}}
                            <div class="d-flex gap-3 align-items-start flex-wrap">
                                {{-- bisa dicentang untuk diperiksa (setelah PDF dari API BPS termuat) atau untuk di-approve --}}
                                @php $bisaDiapprove = $p['tahap'] === 'menunggu' && $bisaApprove; @endphp
                                <label class="form-check m-0 pt-1"
                                       title="{{ $bisaDiapprove || $p['file_rev'] ? 'Pilih untuk diperiksa ulang' . ($bisaDiapprove ? ' atau di-approve' : '') : 'Memuat data publikasi dari API BPS…' }}">
                                    <input type="checkbox" class="form-check-input pub-select" data-index="{{ $i }}" @disabled(!$bisaDiapprove && !$p['file_rev'])
                                           onchange="toggleSelect({{ $i }}, this)">
                                </label>
                                <div class="pub-cover d-flex align-items-center justify-content-center text-muted" id="cover-{{ $i }}" style="font-size:20px;">
                                    <i class="ti ti-file-type-pdf text-danger"></i>
                                </div>
                                {{-- flex-basis 0: lebar tidak ikut isi catatan, jadi tidak turun baris saat toggle dibuka --}}
                                <div class="flex-grow-1" style="flex: 1 1 0; min-width:0;">
                                    <div class="fw-semibold small mb-1" style="word-break: break-word; overflow-wrap: break-word;">{{ $p['judul'] }}</div>

                                    {{-- Yang utama: sudah upload perbaikan atau belum --}}
                                    @php [$warna, $ikon, $label] = $tahapTampil[$p['tahap']]; @endphp
                                    <div class="mb-1">
                                        <span class="badge {{ $warna }} fs-5 px-2 py-1">
                                            <i class="ti ti-{{ $ikon }} me-1"></i>{{ $label }}
                                        </span>
                                        @isset($revLabel[$p['rev']])
                                            <small class="text-secondary ms-1">{{ $revLabel[$p['rev']] }}</small>
                                        @endisset
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <span class="badge bg-azure-lt fw-normal">Pasca Rilis {{ $bulanNama[$p['bulan']] }} {{ $tahun }}</span>
                                        {{-- provinsi mengunggah file perbaikan langsung di SIPOTRET → tanggal & file itu yang dipakai --}}
                                        @if($p['file_rev'])
                                            <small class="fw-semibold text-primary">
                                                <i class="ti ti-upload"></i> Perbaikan diunggah di SIPOTRET{{ $p['diubah_at'] ? ' ' . $tglPendek($p['diubah_at']) : '' }}
                                            </small>
                                        @endif
                                        @unless($p['pub_id_api'])
                                            <small class="text-warning">pub_id tidak tersimpan di SIPOTRET — data portal BPS tidak bisa dimuat</small>
                                        @endunless
                                        <span id="meta-{{ $i }}" class="d-flex flex-wrap gap-2"></span>
                                    </div>

                                    {{-- Catatan masalah & riwayat: disembunyikan, dibuka lewat toggle --}}
                                    <a href="#detail-{{ $i }}" class="small d-inline-block mt-2 text-decoration-none toggle-detail collapsed"
                                       data-bs-toggle="collapse" role="button" aria-expanded="false">
                                        <i class="ti ti-chevron-right"></i> {{ count($p['catatan']) }} catatan masalah{{ $p['riwayat'] ? ' & riwayat' : '' }}
                                    </a>
                                    <div class="collapse" id="detail-{{ $i }}">
                                        <ul class="small mb-0 mt-1 ps-3">
                                            @foreach($p['catatan'] as $c)
                                                <li>{{ $c['catatan'] }}@if($c['keterangan']) <span class="text-secondary">— {{ $c['keterangan'] }}</span>@endif</li>
                                            @endforeach
                                        </ul>

                                        @if($p['riwayat'])
                                            <div class="d-flex flex-wrap align-items-center gap-1 mt-2 small">
                                                <span class="text-secondary me-1"><i class="ti ti-history"></i> Riwayat SIPOTRET:</span>
                                                @foreach($p['riwayat'] as $h)
                                                    <span class="badge {{ !$h['catatan'] ? 'bg-secondary-lt' : $tahapTampil[$h['tahap']][0] }} {{ $h['ini'] ? 'border border-dark' : '' }}"
                                                          title="{{ $h['catatan'] }} catatan · {{ !$h['catatan'] ? 'tanpa temuan' : mb_strtolower($tahapTampil[$h['tahap']][2]) }}">
                                                        {{ \Illuminate\Support\Str::substr($bulanNama[$h['bulan']], 0, 3) }} {{ $h['tahun'] }} · {{ $h['catatan'] }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 align-items-center justify-content-end mt-2 flex-wrap">
                                @if($p['tahap'] === 'menunggu' && $bisaApprove)
                                    <form method="post" action="{{ route('dashboard.sipotret.approve', $p['history_id']) }}" class="m-0"
                                          onsubmit="return confirm({{ Js::from("Approve perbaikan publikasi ini di SIPOTRET?\n\n" . $p['judul'] . ($p['rev'] === 'uploaded_kabkota' ? "\n\nPastikan PDF terbaru di portal BPS sudah diperiksa ulang (centang lalu Periksa)." : '')) }})">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm" title="Setujui file perbaikan (status SIPOTRET → approve)">
                                            <i class="ti ti-circle-check"></i>
                                            <span class="d-none d-md-inline ms-1">Approve</span>
                                        </button>
                                    </form>
                                @endif
                                @if($p['file_rev'])
                                    <a href="{{ route('dashboard.sipotret.file', $p['history_id']) }}" target="_blank" class="btn btn-outline-primary btn-sm"
                                       title="Buka file perbaikan yang diunggah di SIPOTRET">
                                        <i class="ti ti-file-upload"></i><span class="d-none d-md-inline ms-1">File perbaikan</span>
                                    </a>
                                @endif
                                <span id="pdfbadge-{{ $i }}">
                                    @if($p['pub_id_api'])
                                        <span class="badge bg-secondary-lt fw-normal"><i class="ti ti-loader-2 icon-spin me-1"></i>Memuat…</span>
                                    @endif
                                </span>
                                <span id="pdflink-{{ $i }}"></span>
                            </div>
                            @if($p['rev'] === 'uploaded_kabkota' && $bisaApprove)
                                <div class="text-end small text-secondary mt-1">
                                    <i class="ti ti-info-circle"></i>
                                    Perbaikan diunggah kab/kota langsung ke portal publikasi BPS — centang lalu klik <b>Periksa</b> di atas untuk memeriksa ulang PDF terbarunya sebelum approve.
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="ti ti-mood-happy d-block mb-2" style="font-size:36px;opacity:.3;"></i>
                            Tidak ada publikasi bermasalah untuk filter yang dipilih
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Pemeriksaan berjalan: kartu per publikasi berjejer (js/batch-check.js) --}}
            <div id="batchSection" style="display:none;">
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title" id="batchTitle">Memeriksa…</h3>
                        <div class="card-actions">
                            <button type="button" class="btn btn-primary btn-sm" id="btnReviewAll" disabled onclick="beginReview()">
                                <i class="ti ti-player-play"></i> <span>Mulai Tinjauan</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body" id="batchGrid"></div>
                </div>
            </div>

            {{-- Tinjauan per kategori, tab per publikasi --}}
            @include('checker.partials.review-panel')
        @endif

    </div>
</div>
@endsection

@push('styles')
<style>
    .toggle-detail .ti-chevron-right { display: inline-block; transition: transform .15s; }
    .toggle-detail:not(.collapsed) .ti-chevron-right { transform: rotate(90deg); }
</style>
@endpush

@if(!$error && $daftar)
@push('scripts')
<script src="{{ asset('js/checker-review.js') }}?v={{ filemtime(public_path('js/checker-review.js')) }}"></script>
<script src="{{ asset('js/pdf-extract.js') }}?v={{ filemtime(public_path('js/pdf-extract.js')) }}"></script>
<script src="{{ asset('js/batch-check.js') }}?v={{ filemtime(public_path('js/batch-check.js')) }}"></script>
<script>
const CSRF     = document.querySelector('meta[name=csrf-token]')?.content ?? '';
const MAX_PUBS = 10; // batas publikasi per pemeriksaan (sama dengan Import API BPS)
const DOMAIN   = {{ Js::from((string) $wilayah) }};
// Publikasi dari SIPOTRET; detail (PDF, kover, ISSN) dilengkapi dari API BPS lewat pub_id
// file_url: file perbaikan yang diunggah di SIPOTRET — bila ada, file inilah yang diperiksa (bukan PDF portal)
const items    = {{ Js::from(array_map(fn($p) => [
    'pub_id'     => $p['pub_id_api'],
    'title'      => $p['judul'],
    'history_id' => $p['history_id'],
    'tahap'      => $p['tahap'],
    'file_url'   => $p['file_rev'] ? route('dashboard.sipotret.file', $p['history_id']) : null,
], $daftar)) }};
const BISA_APPROVE = {{ Js::from($bisaApprove) }};
// catatan periode SIPOTRET terakhir per pub_id → isian awal tinjauan (baru masuk SIPOTRET saat dikirim)
const ISIAN    = {{ Js::from((object) $isian) }};
const BULAN_PENDEK = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const selected = new Map(); // kunci publikasi → pub
// publikasi yang sama di beberapa bulan = satu pilihan; tanpa pub_id dibedakan per periode
const kunciPub = p => p.pub_id ? String(p.pub_id) : 'h' + p.history_id;
let batchOutcomes = [];
let allResults    = [];
let sesiId        = null;
let detailSelesai = false; // detail API BPS semua publikasi sudah dimuat

CheckerReview.init({ viewerUrl: '{{ asset("pdfjs/web/viewer.html") }}', csrf: CSRF, saveUrl: '{{ route("checker.hasil.review_kategori", "__ID__") }}', contohUrl: '{{ route("checker.contoh.json") }}' });
PdfExtract.init({ pdfjsBuild: '{{ asset("pdfjs/build") }}/' });

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function proxied(pdf) {
    return '{{ route("checker.bps.pdf_proxy") }}?url=' + encodeURIComponent(pdf);
}

/** "2026-03-10" / "2026-03-10 14:05:00" → "10 Mar 2026"; format lain ditampilkan apa adanya. */
function tanggal(s) {
    const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${+m[3]} ${BULAN_PENDEK[+m[2]]} ${m[1]}` : s;
}

// ── DETAIL DARI API BPS (kover, PDF, ISSN, tanggal revisi) ────
async function loadDetail(pubId) {
    const res  = await fetch('{{ route("checker.bps.detail") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ pub_id: pubId, domain: DOMAIN }),
    });
    const data = await res.json();
    if (!res.ok || !data.publication) throw new Error(data.error || ('Server membalas ' + res.status));
    return data.publication;
}

function renderDetail(i, pub, err) {
    const cb = document.querySelector(`.pub-select[data-index="${i}"]`);
    const badge = document.getElementById('pdfbadge-' + i);
    if (err) {
        cb.closest('label').title = 'Detail publikasi tidak bisa dimuat dari API BPS';
        badge.innerHTML = `<span class="badge bg-warning-lt fw-normal" title="${esc(err.message)}"><i class="ti ti-alert-triangle me-1"></i>API BPS gagal</span>`;
        return;
    }
    Object.assign(items[i], pub); // pub.cover sudah lewat cover-proxy (BpsImportController::detail)
    if (pub.cover) {
        // ikon PDF tetap tampil bila sampul gagal dimuat
        const ikon = document.getElementById('cover-' + i);
        const img  = Object.assign(new Image(), { className: 'pub-cover', alt: 'cover', src: pub.cover });
        img.onload = () => ikon.replaceWith(img);
    }
    // tanggal revisi terakhir di portal BPS (updt_date) — petunjuk apakah kab/kota sudah mengganti file.
    // Yang punya file perbaikan di SIPOTRET sudah menampilkan tanggal unggahnya sendiri.
    const fileSipotret = !!items[i].file_url;
    document.getElementById('meta-' + i).innerHTML =
        (pub.issn ? `<span class="badge bg-secondary fw-normal">${esc(pub.issn)}</span>` : '') +
        (pub.rl_date ? `<small class="text-muted">Rilis ${esc(tanggal(pub.rl_date))}</small>` : '') +
        (pub.updt_date && !fileSipotret ? `<small class="fw-semibold"><i class="ti ti-refresh"></i> Revisi terakhir ${esc(tanggal(pub.updt_date))}</small>` : '') +
        (pub.size && !fileSipotret ? `<small class="text-muted">${esc(pub.size)}</small>` : '');
    // hanya yang perlu diperhatikan yang diberi tanda: publikasi tanpa PDF tidak bisa diperiksa
    badge.innerHTML = pub.has_pdf || fileSipotret ? '' : `<span class="badge bg-secondary fw-normal">Tanpa PDF</span>`;
    if (pub.has_pdf) {
        cb.disabled = false;
        cb.closest('label').title = items[i].tahap === 'menunggu' && BISA_APPROVE
            ? 'Pilih untuk di-approve atau diperiksa ulang' : 'Pilih untuk diperiksa sekaligus';
        document.getElementById('pdflink-' + i).innerHTML =
            `<a href="${esc(proxied(pub.pdf) + '&nama=' + encodeURIComponent(items[i].title || 'publikasi'))}" download class="btn btn-outline-success btn-sm" title="Download PDF">
                <i class="ti ti-download"></i><span class="d-none d-md-inline ms-1">PDF</span></a>`;
    } else if (cb.disabled) {
        cb.closest('label').title = 'Tidak ada PDF';
    }
}

// Satu publikasi bisa muncul di beberapa bulan — detailnya cukup diambil sekali
async function loadAllDetails() {
    const perPub = new Map();
    items.forEach((it, i) => {
        if (!it.pub_id) return;
        if (!perPub.has(it.pub_id)) perPub.set(it.pub_id, []);
        perPub.get(it.pub_id).push(i);
    });
    const antrian = [...perPub.entries()];
    const pekerja = async () => {
        for (let job; (job = antrian.shift());) {
            const [pubId, idx] = job;
            try {
                const pub = await loadDetail(pubId);
                idx.forEach(i => renderDetail(i, pub));
            } catch (e) {
                idx.forEach(i => renderDetail(i, null, e));
            }
        }
    };
    await Promise.all(Array.from({ length: 4 }, pekerja)); // 4 permintaan paralel
    detailSelesai = true;
    renderSelection();
}

/** Pilih semua publikasi yang bisa dicentang (ber-PDF, atau menunggu approve). */
function pilihSemua() {
    document.querySelectorAll('.pub-select:not(:disabled)').forEach(cb => {
        const p = items[cb.dataset.index];
        selected.set(kunciPub(p), p);
    });
    syncCheckboxes();
    renderSelection();
}

/** history_id yang dicentang & statusnya menunggu approve (satu publikasi bisa punya beberapa periode). */
function historyMenunggu() {
    return [...new Set(items.filter(p => p.tahap === 'menunggu' && selected.has(kunciPub(p))).map(p => p.history_id))];
}

function approveTerpilih() {
    const ids = historyMenunggu();
    if (!ids.length) return;
    if (!confirm(`Approve perbaikan ${ids.length} publikasi di SIPOTRET?\n\nPerbaikan kab/kota diunggah ke portal publikasi BPS — pastikan PDF terbarunya sudah diperiksa ulang.`)) return;
    const form = document.getElementById('formApprove');
    ids.forEach(id => form.insertAdjacentHTML('beforeend', `<input type="hidden" name="history_ids[]" value="${id}">`));
    form.submit();
}

function periodeDipilih() {
    const bulan = parseInt(document.getElementById('periodeBulan').value, 10);
    const tahun = parseInt(document.getElementById('periodeTahun').value, 10);
    return bulan >= 1 && bulan <= 12 && tahun >= 2000 && tahun <= 2100 ? { bulan, tahun } : null;
}

// ── PILIH BEBERAPA PUBLIKASI ──────────────────────────────────
function toggleSelect(i, cb) {
    const pub = items[i];
    if (cb.checked) selected.set(kunciPub(pub), pub);
    else selected.delete(kunciPub(pub));
    syncCheckboxes();
    renderSelection();
}

/** Publikasi yang sama di bulan lain ikut tercentang/terlepas. */
function syncCheckboxes() {
    document.querySelectorAll('.pub-select').forEach(cb => {
        const on = selected.has(kunciPub(items[cb.dataset.index]));
        cb.checked = on;
        cb.closest('.pub-card')?.classList.toggle('border-primary', on);
    });
}

function clearSelection() {
    selected.clear();
    syncCheckboxes();
    renderSelection();
}

function renderSelection() {
    const n        = selected.size;
    const nPeriksa = [...selected.values()].filter(bisaDiperiksa).length;
    const nApprove = historyMenunggu().length;
    document.getElementById('selectionInfo').innerHTML = n
        ? `<strong>${n}</strong> publikasi dipilih: ${[...selected.values()].map(p => esc(p.title)).join(' · ')}`
        : `Centang publikasi untuk diperiksa ulang (maks. ${MAX_PUBS} ber-PDF sekali periksa)`
          + (BISA_APPROVE ? ' atau untuk di-approve' : '')
          + (detailSelesai ? '.' : ' — data PDF masih dimuat dari API BPS…');
    document.getElementById('btnCheckSel').disabled = !nPeriksa;
    document.getElementById('btnCheckSelLabel').textContent = nPeriksa ? `Periksa (${nPeriksa})` : 'Periksa Terpilih';
    document.getElementById('btnClearSel').hidden = !n;
    const btnApprove = document.getElementById('btnApproveSel');
    if (btnApprove) {
        btnApprove.hidden = !nApprove;
        document.getElementById('btnApproveSelLabel').textContent = `Approve (${nApprove})`;
    }
}

// ── PERIKSA BEBERAPA PUBLIKASI SEKALIGUS (sama dengan Import API BPS) ──
/** Bisa diperiksa: ada file perbaikan di SIPOTRET, atau PDF di portal BPS. */
function bisaDiperiksa(p) {
    return !!p.file_url || !!(p.has_pdf && p.pdf);
}

async function startCheck() {
    const pubs = [...selected.values()].filter(bisaDiperiksa);
    if (!pubs.length) return;
    if (pubs.length > MAX_PUBS) {
        showAlert('warning', `${pubs.length} publikasi ber-PDF dipilih — maksimal ${MAX_PUBS} sekali periksa. Kurangi pilihannya dulu.`);
        return;
    }
    if (!periodeDipilih()) {
        showAlert('warning', 'Pilih periode pemeriksaan (bulan & tahun) dulu — hasilnya akan dikirim ke SIPOTRET pada periode itu.');
        document.getElementById('periodeBulan').focus();
        return;
    }
    const btn = document.getElementById('btnCheckSel');
    btn.disabled = true;

    const total = pubs.length;
    document.getElementById('batchSection').style.display = 'block';
    document.getElementById('reviewSection').style.display = 'none';
    document.getElementById('batchTitle').textContent = `Memeriksa ${total} publikasi…`;
    document.getElementById('btnReviewAll').disabled = true;
    document.getElementById('batchSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    allResults = []; sesiId = null;

    // Sesi dibuat dulu supaya semua publikasi (diproses paralel) masuk ke sesi yang sama
    try {
        const res = await fetch('{{ route("checker.sesi.create") }}', {
            method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        sesiId = (await res.json()).sesi_id ?? null;
    } catch (e) { sesiId = null; }

    const jobs = pubs.map(pub => {
      // file perbaikan di SIPOTRET (provinsi) diutamakan; selain itu PDF portal BPS lewat proxy
      const sumber = pub.file_url || proxied(pub.pdf);
      return {
        title:  pub.title,
        cover:  pub.cover || null,
        source: sumber,
        send:   async extracted => {
            const res = await fetch('{{ route("checker.bps.run") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    publications: [{
                        pub_id:  String(pub.pub_id ?? ''),
                        title:   pub.title,
                        // tetap URL portal bila ada (dipakai Riwayat & kiriman SIPOTRET); teks sudah dibaca dari `sumber`
                        pdf:     pub.pdf || new URL(pub.file_url, location.href).href,
                        domain:  DOMAIN,
                        issn:    pub.issn || null,
                        rl_date: pub.rl_date || null,
                    }],
                    extracted: extracted ? JSON.stringify(extracted) : null,
                    sesi_id: sesiId,
                }),
            });
            const data = await res.json();
            if (data.error || !res.ok) throw new Error(data.error || data.message || ('Server membalas ' + res.status));
            const r = data.results?.[0];
            if (!r || r.error) throw new Error(r?.error || 'Tidak ada hasil pemeriksaan');
            sesiId ??= data.sesi_id;
            r._pdfUrl = sumber; // viewer tinjauan menampilkan file yang diperiksa
            terapkanIsian(r, ISIAN[String(pub.pub_id)]);
            return r;
        },
      };
    });

    batchOutcomes = await BatchCheck.run(document.getElementById('batchGrid'), jobs, {
        onReview: i => beginReview(i),
    });
    allResults = batchOutcomes.filter(o => o.result).map(o => o.result);

    const gagal = total - allResults.length;
    document.getElementById('batchTitle').textContent =
        `${allResults.length} dari ${total} publikasi selesai diperiksa` + (gagal ? ` · ${gagal} gagal` : '');
    document.getElementById('btnReviewAll').disabled = !allResults.length;
    btn.disabled = false;
    clearSelection();

    if (total === 1 && allResults.length === 1) beginReview();
}

/**
 * Isi awal tinjauan dari catatan SIPOTRET periode terakhir: kriteria yang tercatat → pilihan "Tidak Sesuai"
 * + keterangannya (belum tersimpan sampai kategori disimpan); item lainnya → usulan catatan tambahan.
 * Dipasang sekali saat hasil datang, supaya membuka ulang tinjauan tidak menimpa yang sudah disimpan.
 */
function terapkanIsian(r, isian) {
    if (!isian) return;
    const asal = `Catatan SIPOTRET ${BULAN_PENDEK[isian.periode.bulan]} ${isian.periode.tahun}`;
    (r.checks || []).forEach(c => {
        if (!(c.id in isian.kriteria)) return;
        const ket = isian.kriteria[c.id];
        c.selStatus     = 'TIDAK SESUAI';
        c.selKet        = ket;
        c.isianSipotret = ket ? `${asal}: ${ket}` : `${asal} (tanpa keterangan — isi dulu)`;
    });
    r._usulanCatatan = isian.lainnya.map(u => ({ ...u }));
}

/** @param {number} [jobIndex] kartu yang diklik "Tinjau" — publikasi itu dibuka pertama */
function beginReview(jobIndex) {
    const target  = Number.isInteger(jobIndex) ? batchOutcomes[jobIndex]?.result : null;
    const entries = allResults
        .filter(r => r.checks?.length)
        .map(r => ({
            filename: r.filename,
            checks:   r.checks,
            hasilId:  r.hasil_id,
            ocrLines: r.ocr_lines,
            summary:  r.summary,
            url:      r._pdfUrl,
            usulanCatatan: r._usulanCatatan ?? null,
        }));
    if (!entries.length) return;

    const startIndex = target ? entries.findIndex(e => e.hasilId === target.hasil_id) : -1;
    CheckerReview.start(entries, { onFinish: finishReview, startIndex: startIndex >= 0 ? startIndex : undefined });
}

function finishReview() {
    document.getElementById('batchSection').style.display = 'block';
    if (sesiId) {
        // lanjut ke Riwayat: modal "Kirim ke SIPOTRET" langsung terbuka dengan periode yang dipilih di awal
        const periode = periodeDipilih() ?? {};
        const url = '{{ route("checker.riwayat.detail", "__ID__") }}'.replace('__ID__', sesiId)
            + '?' + new URLSearchParams({ sipotret: 1, ...periode });
        document.getElementById('batchTitle').innerHTML =
            `Tinjauan tersimpan · <a href="${url}">Kirim hasil ke SIPOTRET</a>`;
        window.location = url;
        return;
    }
    document.getElementById('batchSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** Buka/tutup catatan masalah semua publikasi sekaligus. */
function toggleSemuaDetail(btn) {
    const buka = btn.dataset.terbuka !== '1';
    document.querySelectorAll('.pub-card .collapse').forEach(el => bootstrap.Collapse.getOrCreateInstance(el, { toggle: false })[buka ? 'show' : 'hide']());
    btn.dataset.terbuka = buka ? '1' : '';
    btn.querySelector('span').textContent = buka ? 'Sembunyikan semua catatan' : 'Tampilkan semua catatan';
}

function showAlert(type, msg) {
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show`;
    div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.querySelector('.content-wrapper').prepend(div);
    setTimeout(() => div.remove(), 6000);
}

document.addEventListener('DOMContentLoaded', () => {
    renderSelection();
    loadAllDetails();
});
</script>
@endpush
@endif
