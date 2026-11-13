@extends('layouts.app')

@section('title', 'Import dari API BPS')
@section('pretitle', 'Publication Checker')
@section('page-title', 'Import Publikasi dari API BPS')
@section('page-subtitle', 'Cari publikasi dari portal BPS, centang hingga 10 publikasi untuk diperiksa sekaligus')
@section('page-actions')
    @can('periksa')
    <a href="{{ route('checker.index') }}" class="btn">
        <i class="ti ti-upload"></i> Upload Manual
    </a>
    @endcan
    <a href="{{ route('checker.riwayat') }}" class="btn btn-primary">
        <i class="ti ti-history"></i> Riwayat
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- API tidak terkonfigurasi --}}
        @if(!$configured)
        <div class="card mb-3 border-danger">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <i class="ti ti-alert-circle text-danger" style="font-size:32px; flex-shrink:0;"></i>
                    <div>
                        <h5 class="text-danger mb-1">API Key BPS Belum Dikonfigurasi</h5>
                        <p class="text-muted small mb-2">
                            Tambahkan baris berikut ke file <code>.env</code> kamu, lalu jalankan
                            <code>php artisan config:clear</code>:
                        </p>
                        <pre class="bg-light rounded p-2 small mb-2">BPS_API_KEY=isi_api_key_kamu_di_sini
BPS_DOMAIN=6300</pre>
                        <p class="text-muted small mb-0">
                            Daftar dan dapatkan API Key di
                            <a href="https://webapi.bps.go.id/developer" target="_blank">
                                webapi.bps.go.id/developer <i class="ti ti-external-link"></i>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- API BPS tidak bisa dihubungi saat halaman dibuka --}}
        @if($configured && ($apiError ?? false))
        <div class="alert alert-warning" role="alert">
            <div class="d-flex">
                <i class="ti ti-plug-connected-x alert-icon"></i>
                <div>
                    <h4 class="alert-title">API BPS tidak bisa dihubungi</h4>
                    <div class="text-secondary">
                        Server <code>webapi.bps.go.id</code> tidak merespons dari jaringan ini (timeout), jadi daftar
                        kabupaten/kota belum bisa dimuat. Pencarian tetap bisa dicoba untuk Provinsi Kalimantan Selatan,
                        atau muat ulang halaman ini beberapa saat lagi.
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Form Pencarian --}}
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3">Cari Publikasi BPS</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Domain / Kantor BPS</label>
                        <select id="searchDomain" class="form-select form-select-sm text-black">
                            @foreach($domains as $d)
                                <option value="{{ $d['domain_id'] }}"
                                    {{ $d['domain_id'] === $domain ? 'selected' : '' }}>
                                    {{ $d['domain_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Kata Kunci</label>
                        <input type="text" id="searchKeyword" class="form-control form-control-sm text-black"
                               placeholder="Contoh: statistik daerah">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">Tahun</label>
                        <select id="searchYear" class="form-select form-select-sm text-black">
                            <option value="">Semua</option>
                            @for($y = now()->year; $y >= 2015; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">Bulan</label>
                        <select id="searchMonth" class="form-select form-select-sm text-black">
                            <option value="">Semua</option>
                            @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-primary btn-sm w-100" onclick="doSearch(1)"
                                id="btnSearch" {{ !$configured ? 'disabled' : '' }}>
                            <i class="ti ti-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hasil Pencarian --}}
        <div id="searchSection" style="display:none;">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="card-title mb-0">
                            Hasil Pencarian
                            <span class="badge bg-primary ms-1" id="searchTotal">0</span>
                        </h5>
                        <div class="d-flex gap-2 align-items-center">
                            <small class="text-muted" id="searchMeta"></small>
                        </div>
                    </div>

                    {{-- Pilihan beberapa publikasi (maks. MAX_PUBS) untuk diperiksa sekaligus --}}
                    <div class="alert alert-info d-flex align-items-center flex-wrap gap-2 py-2 mb-3" id="selectionBar" @cannot('periksa') hidden @endcannot>
                        <i class="ti ti-checkbox"></i>
                        <span class="flex-fill small" id="selectionInfo"></span>
                        <button type="button" class="btn btn-sm btn-ghost-secondary" id="btnClearSel" onclick="clearSelection()" hidden>
                            <i class="ti ti-x"></i> <span>Batal Pilih</span>
                        </button>
                        @if($pemeriksa)
                            {{-- admin: tugaskan publikasi yang dicentang ke satu pemeriksa --}}
                            <div class="d-flex align-items-center gap-1" id="tugasBar" hidden>
                                <select id="tugasUser" class="form-select form-select-sm w-auto" aria-label="Pemeriksa">
                                    <option value="">Pilih pemeriksa…</option>
                                    @foreach($pemeriksa as $u)
                                        <option value="{{ $u['id'] }}">{{ $u['nama'] }}{{ $u['role'] === 'admin' ? ' (admin)' : '' }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-sm btn-purple" onclick="tugaskanTerpilih()" title="Tugaskan publikasi yang dicentang ke pemeriksa ini">
                                    <i class="ti ti-user-check"></i> <span id="btnTugasLabel">Tugaskan</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="tugaskanTerpilih(true)" title="Lepas penugasan publikasi yang dicentang">
                                    <i class="ti ti-user-off"></i>
                                </button>
                            </div>
                        @endif
                        <button type="button" class="btn btn-sm btn-primary" id="btnCheckSel" onclick="startCheck()" disabled>
                            <i class="ti ti-rosette-discount-check"></i> <span id="btnCheckSelLabel">Periksa Terpilih</span>
                        </button>
                    </div>

                    <div id="searchResults"></div>

                    {{-- Pagination --}}
                    <div id="paginationBar" class="d-flex justify-content-center gap-2 mt-3"
                         style="display:none !important;"></div>
                </div>
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

    </div>
</div>


@endsection

@push('scripts')
<script src="{{ asset('js/checker-review.js') }}?v={{ filemtime(public_path('js/checker-review.js')) }}"></script>
<script src="{{ asset('js/penugasan.js') }}?v={{ filemtime(public_path('js/penugasan.js')) }}"></script>
<script src="{{ asset('js/pdf-extract.js') }}?v={{ filemtime(public_path('js/pdf-extract.js')) }}"></script>
<script src="{{ asset('js/batch-check.js') }}?v={{ filemtime(public_path('js/batch-check.js')) }}"></script>
<script>
const CSRF      = document.querySelector('meta[name=csrf-token]')?.content ?? '';
const CAN_PERIKSA = @json(Gate::allows('periksa')); // role 'user': hanya melihat
const MAX_PUBS  = 10; // batas publikasi per pemeriksaan (sama dengan Upload Manual)
const ADMIN_TUGAS = @json((bool) $pemeriksa); // admin: bisa menugaskan publikasi ke pemeriksa
Penugasan.init({ url: '{{ route("checker.penugasan.simpan") }}', csrf: CSRF });
let currentPage = 1;
let searchItems = []; // hasil search terakhir
const selected  = new Map(); // pub_id → pub; tetap tersimpan saat pindah halaman pencarian
let batchOutcomes = [];
let allResults    = [];
let sesiId        = null;

CheckerReview.init({ viewerUrl: '{{ asset("pdfjs/web/viewer.html") }}', csrf: CSRF, saveUrl: '{{ route("checker.hasil.review_kategori", "__ID__") }}', contohUrl: '{{ route("checker.contoh.json") }}' });
PdfExtract.init({ pdfjsBuild: '{{ asset("pdfjs/build") }}/' });

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function proxied(pdf) {
    return '{{ route("checker.bps.pdf_proxy") }}?url=' + encodeURIComponent(pdf);
}

/** Unduh lewat proxy (bisa walau BPS tak terjangkau dari jaringan pengguna), nama file = judul. */
function unduhUrl(pub) {
    return proxied(pub.pdf) + '&nama=' + encodeURIComponent(pub.title || 'publikasi');
}

// ── PILIH BEBERAPA PUBLIKASI ──────────────────────────────────
function toggleSelect(i, cb) {
    const pub = searchItems[i];
    if (!pub) return;
    if (cb.checked) {
        if (selected.size >= MAX_PUBS) {
            cb.checked = false;
            showAlert('warning', `Maksimal ${MAX_PUBS} publikasi sekali periksa.`);
            return;
        }
        selected.set(String(pub.pub_id), pub);
    } else {
        selected.delete(String(pub.pub_id));
    }
    document.getElementById('pubcard-' + i)?.classList.toggle('border-primary', cb.checked);
    renderSelection();
}

function clearSelection() {
    selected.clear();
    document.querySelectorAll('.pub-select').forEach(cb => {
        cb.checked = false;
        cb.closest('.pub-card')?.classList.remove('border-primary');
    });
    renderSelection();
}

function renderSelection() {
    const n = selected.size;
    document.getElementById('selectionInfo').innerHTML = n
        ? `<strong>${n}</strong> dari maks. ${MAX_PUBS} publikasi dipilih: ${[...selected.values()].map(p => esc(p.title)).join(' · ')}`
        : `Centang hingga ${MAX_PUBS} publikasi ber-PDF untuk diperiksa sekaligus, atau buka satu publikasi untuk melihat detailnya.`;
    document.getElementById('btnCheckSel').disabled = !n;
    document.getElementById('btnCheckSelLabel').textContent = n ? `Periksa (${n})` : 'Periksa Terpilih';
    document.getElementById('btnClearSel').hidden = !n;
    if (ADMIN_TUGAS) {
        document.getElementById('tugasBar').hidden = !n;
        document.getElementById('btnTugasLabel').textContent = n ? `Tugaskan (${n})` : 'Tugaskan';
    }
}

// ── PENUGASAN PEMERIKSAAN (admin) ─────────────────────────────
async function tugaskanTerpilih(lepas = false) {
    const pubs = [...selected.values()];
    if (!pubs.length) return;
    const sel = document.getElementById('tugasUser');
    if (!lepas && !sel.value) { sel.focus(); showAlert('warning', 'Pilih pemeriksa dulu.'); return; }
    const nama = sel.selectedOptions[0]?.textContent.trim();
    if (!confirm(lepas ? `Lepas penugasan ${pubs.length} publikasi?` : `Tugaskan ${pubs.length} publikasi ke ${nama}?

Selama ditugaskan, hanya pemeriksa itu (dan admin) yang bisa memeriksa, meninjau, dan mengirim ke SIPOTRET.`)) return;
    try {
        const r = await Penugasan.simpan(pubs.map(p => ({ pub_id: String(p.pub_id), judul: p.title, domain: String(p.domain || p._domain || '') })), lepas ? null : sel.value);
        pubs.forEach(p => { p.tugas = r.tugas[String(p.pub_id)] ?? null; });
        searchItems.forEach((p, i) => {
            if (!selected.has(String(p.pub_id))) return;
            p.tugas = r.tugas[String(p.pub_id)] ?? null;
            const box = document.getElementById('tugas-' + i);
            if (box) box.innerHTML = Penugasan.badge(p.tugas);
        });
        showAlert('success', r.pesan);
    } catch (e) {
        showAlert('danger', 'Gagal menyimpan penugasan: ' + e.message);
    }
}

// ── SEARCH ────────────────────────────────────────────────────
async function doSearch(page = 1) {
    currentPage = page;
    const btn = document.getElementById('btnSearch');
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2 icon-spin"></i>';

    const body = {
        domain:  document.getElementById('searchDomain').value,
        keyword: document.getElementById('searchKeyword').value || null,
        year:    document.getElementById('searchYear').value    || null,
        month:   document.getElementById('searchMonth').value   || null,
        page,
        _token: CSRF,
    };

    try {
        const res  = await fetch('{{ route("checker.bps.search") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(body),
        });
        const data = await res.json();

        if (data.error || !res.ok) { showAlert('danger', data.error || data.message || ('Server membalas ' + res.status)); return; }

        // domain pencarian diingat per item (pilihan bisa lintas halaman/domain)
        searchItems = (data.items ?? []).map(p => ({ ...p, _domain: body.domain }));
        renderSearchResults(data.meta, searchItems);
    } catch(e) {
        showAlert('danger', 'Pencarian gagal: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-search"></i>';
    }
}

function renderSearchResults(meta, items) {
    document.getElementById('searchSection').style.display = 'block';
    document.getElementById('searchTotal').textContent = meta.total ?? items.length;
    document.getElementById('searchMeta').textContent =
        `Hal. ${meta.page ?? 1} / ${meta.pages ?? 1} (${meta.per_page ?? 10} per halaman)`;

    const box = document.getElementById('searchResults');

    if (!items.length) {
        box.innerHTML = `<div class="text-center py-4 text-muted">
            <i class="ti ti-file-search d-block mb-2" style="font-size:36px;opacity:.3;"></i>
            Tidak ada publikasi ditemukan</div>`;
        renderPagination(meta);
        return;
    }

    box.innerHTML = items.map((pub, i) => {
        // Klik kartu → detail publikasi; centang → ikut diperiksa bersama (maks. MAX_PUBS)
        const detailUrl = `{{ route('checker.bps.detail.show') }}?pub_id=${pub.pub_id}&domain=${pub.domain || document.getElementById('searchDomain').value}`;
        const isSel = selected.has(String(pub.pub_id));
        return `
    <div class="pub-card mb-2 ${isSel ? 'border-primary' : ''}" id="pubcard-${i}">
        <div class="d-flex gap-3 align-items-start flex-wrap" style="cursor:pointer;"
             onclick="window.location='${detailUrl}'">
            ${!CAN_PERIKSA ? '' : !Penugasan.boleh(pub.tugas)
                ? `<label class="form-check m-0 pt-1" onclick="event.stopPropagation()" title="Ditugaskan ke ${esc(pub.tugas.nama)}">
                       <input type="checkbox" class="form-check-input" disabled>
                   </label>`
                : pub.has_pdf || ADMIN_TUGAS
                ? `<label class="form-check m-0 pt-1" onclick="event.stopPropagation()" title="Pilih untuk diperiksa sekaligus${ADMIN_TUGAS ? ' atau ditugaskan' : ''}">
                       <input type="checkbox" class="form-check-input pub-select" ${isSel ? 'checked' : ''}
                              onchange="toggleSelect(${i}, this)">
                   </label>`
                : `<label class="form-check m-0 pt-1" onclick="event.stopPropagation()" title="Tidak ada PDF">
                       <input type="checkbox" class="form-check-input" disabled>
                   </label>`
            }
            ${pub.cover
                ? `<img src="${pub.cover}" class="pub-cover" alt="cover" onerror="this.style.display='none'">`
                : `<div class="pub-cover d-flex align-items-center justify-content-center text-muted" style="font-size:20px;"><i class="ti ti-file-type-pdf text-danger"></i></div>`
            }
            <div class="flex-grow-1 min-w-0" style="min-width:0;">
                <div class="fw-semibold small mb-1" style="word-break: break-word; overflow-wrap: break-word;" title="${pub.title}">
                    ${pub.title}
                </div>
                <div class="d-flex flex-wrap gap-2">
                    ${pub.issn ? `<span class="badge bg-secondary fw-normal">${pub.issn}</span>` : ''}
                    ${pub.rl_date ? `<small class="text-muted">${pub.rl_date}</small>` : ''}
                    ${pub.size ? `<small class="text-muted">${pub.size}</small>` : ''}
                    <span id="tugas-${i}">${Penugasan.badge(pub.tugas)}</span>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center justify-content-end mt-2 flex-wrap">
            ${pub.has_pdf ? '' : `<span class="badge bg-secondary fw-normal">Tanpa PDF</span>`}
            <a href="${detailUrl}" class="btn btn-primary btn-sm" title="Buka detail publikasi">
                <i class="ti ti-${CAN_PERIKSA ? 'rosette-discount-check' : 'eye'}"></i>
                <span class="d-none d-md-inline ms-1">${CAN_PERIKSA ? 'Buka &amp; Periksa' : 'Lihat Detail'}</span>
            </a>
            ${pub.has_pdf
                ? `<a href="${esc(unduhUrl(pub))}" download class="btn btn-outline-success btn-sm" onclick="event.stopPropagation()" title="Download PDF">
                    <i class="ti ti-download"></i>
                    <span class="d-none d-md-inline ms-1">PDF</span>
                </a>`
                : ''
            }
        </div>
    </div>`
    }).join('');

    renderSelection();
    renderPagination(meta);
}

// ── PERIKSA BEBERAPA PUBLIKASI SEKALIGUS ──────────────────────
async function startCheck() {
    const pubs = [...selected.values()].filter(p => p.has_pdf && p.pdf);
    if (!pubs.length) return;
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

    // Satu publikasi per request; teks dibaca di browser lewat proxy (pdf.js + OCR kover)
    const jobs = pubs.map(pub => ({
        title:  pub.title,
        cover:  pub.cover || null,
        source: proxied(pub.pdf),
        send:   async extracted => {
            const res = await fetch('{{ route("checker.bps.run") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    publications: [{
                        pub_id:  String(pub.pub_id ?? ''),
                        title:   pub.title,
                        pdf:     pub.pdf,
                        domain:  String(pub.domain || pub._domain || ''),
                        issn:    pub.issn || null,
                        catalog: pub.catalog || null, // cadangan cek sama_api bila detail Web API gagal diambil server
                        pub_no:  pub.pub_no || null,
                        rl_date: pub.rl_date || null,
                    }],
                    extracted_b64: PdfExtract.kemas(extracted), // base64: lihat PdfExtract.kemas
                    sesi_id: sesiId,
                }),
            });
            const data = await res.json().catch(() => { throw new Error(res.status === 403 ? 'Ditolak server (403) sebelum sampai ke aplikasi, kemungkinan firewall hosting. Coba lagi; bila tetap gagal, hubungi pengelola server.' : 'Server membalas ' + res.status + ' (bukan JSON)'); });
            if (data.error || !res.ok) throw new Error(data.error || data.message || ('Server membalas ' + res.status));
            const r = data.results?.[0];
            if (!r || r.error) throw new Error(r?.error || 'Tidak ada hasil pemeriksaan');
            sesiId ??= data.sesi_id;
            r._pdfUrl = proxied(pub.pdf); // PDF untuk viewer — jangan mengandalkan urutan
            r._pub = pub; // pub_id & domain: data publikasi di judul panel tinjauan diambil dari API BPS
            return r;
        },
    }));

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

/** @param {number} [jobIndex] kartu yang diklik "Tinjau" — publikasi itu dibuka pertama */
function beginReview(jobIndex) {
    // termasuk publikasi yang baru berhasil lewat "Coba lagi" setelah pemeriksaan awal selesai
    allResults = batchOutcomes.filter(o => o?.result).map(o => o.result);
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
            pubId:     r._pub?.pub_id ?? null,
            pubDomain: r._pub?.domain || r._pub?._domain || null,
        }));
    if (!entries.length) return;

    const startIndex = target ? entries.findIndex(e => e.hasilId === target.hasil_id) : -1;
    CheckerReview.start(entries, { onFinish: finishReview, startIndex: startIndex >= 0 ? startIndex : undefined });
}

function finishReview() {
    document.getElementById('batchSection').style.display = 'block';
    if (sesiId) {
        const url = '{{ route("checker.riwayat.detail", "__ID__") }}'.replace('__ID__', sesiId);
        document.getElementById('batchTitle').innerHTML =
            `Tinjauan tersimpan · <a href="${url}">Lihat hasil di Riwayat</a>`;
    }
    document.getElementById('batchSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderPagination(meta) {
    const bar   = document.getElementById('paginationBar');
    const pages = meta.pages ?? 1;
    const page  = meta.page  ?? 1;
    if (pages <= 1) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    bar.innerHTML = '';
    const start = Math.max(1, page - 2);
    const end   = Math.min(pages, page + 2);
    if (page > 1) bar.innerHTML += `<button class="btn btn-sm btn-outline-primary" onclick="doSearch(${page-1})">‹</button>`;
    for (let p = start; p <= end; p++) {
        bar.innerHTML += `<button class="btn btn-sm ${p===page?'btn-primary':'btn-outline-primary'}" onclick="doSearch(${p})">${p}</button>`;
    }
    if (page < pages) bar.innerHTML += `<button class="btn btn-sm btn-outline-primary" onclick="doSearch(${page+1})">›</button>`;
}

function showAlert(type, msg) {
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show`;
    div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.querySelector('.content-wrapper').prepend(div);
    setTimeout(() => div.remove(), 6000);
}

// Auto-search jika API sudah dikonfigurasi
@if($configured)
document.addEventListener('DOMContentLoaded', () => doSearch(1));
@endif
</script>
@endpush