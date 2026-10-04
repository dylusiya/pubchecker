@extends('layouts.app')

@section('title', 'Import dari API BPS')

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Header --}}
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="card-title mb-1">
                            <i class="mdi mdi-cloud-download-outline text-primary me-2"></i>
                            Import Publikasi dari API BPS
                        </h4>
                        <p class="text-muted mb-0 small">
                            Cari publikasi dari portal BPS, pilih, lalu periksa PDF-nya otomatis
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('checker.index') }}" class="btn btn-light btn-sm border">
                            <i class="mdi mdi-upload me-1"></i> Upload Manual
                        </a>
                        <a href="{{ route('checker.riwayat') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-history me-1"></i> Riwayat
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- API tidak terkonfigurasi --}}
        @if(!$configured)
        <div class="card card-rounded mb-3 border-danger">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <i class="mdi mdi-alert-circle text-danger" style="font-size:32px; flex-shrink:0;"></i>
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
                                webapi.bps.go.id/developer <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Progress --}}
        <div id="progressSection" style="display:none;">
            <div class="card card-rounded mb-3 border-primary">
                <div class="card-body">

                    {{-- Progress bar publikasi (antar publikasi) --}}
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h5 class="card-title mb-0 small fw-semibold" id="progressText">Memproses...</h5>
                        <span class="badge bg-primary" id="progressPct">0%</span>
                    </div>
                    <div class="progress mb-1" style="height:5px;">
                        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated"
                            id="progressBar" style="width:0%; transition:width .4s;"></div>
                    </div>
                    <small class="text-muted d-block mb-3" id="progressDetail"></small>

                    {{-- Panel fase: download / parse / kriteria --}}
                    <div id="phasePanel">

                        {{-- Fase: Mengunduh --}}
                        <div id="phaseDownload" class="phase-block" style="display:none;">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="mdi mdi-cloud-download-outline text-primary"></i>
                                <small class="fw-semibold text-muted text-uppercase" style="letter-spacing:.05em; font-size:10px;">Mengunduh PDF</small>
                            </div>
                            <div class="progress mb-1" style="height:3px;">
                                <div class="progress-bar bg-info progress-bar-striped progress-bar-animated" style="width:100%;"></div>
                            </div>
                            <small class="text-muted" id="downloadMsg">Menghubungi server BPS...</small>
                        </div>

                        {{-- Fase: Membaca halaman --}}
                        <div id="phasePages" style="display:none;">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="mdi mdi-file-search-outline text-warning"></i>
                                    <small class="fw-semibold text-muted text-uppercase" style="letter-spacing:.05em; font-size:10px;">Membaca Halaman PDF</small>
                                </div>
                                <small class="text-muted" id="pagesMeta">0 / ?</small>
                            </div>
                            <div class="progress mb-1" style="height:5px;">
                                <div class="progress-bar bg-warning" id="pagesBar" style="width:0%; transition:width .15s;"></div>
                            </div>
                            <small class="text-muted" id="pagesMsg">Memulai...</small>
                        </div>

                        {{-- Fase: Memeriksa kriteria --}}
                        <div id="phaseKriteria" style="display:none;">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="mdi mdi-clipboard-check-outline text-success"></i>
                                    <small class="fw-semibold text-muted text-uppercase" style="letter-spacing:.05em; font-size:10px;">Memeriksa Kriteria</small>
                                </div>
                                <small class="text-muted" id="kriteriaMeta">0 kriteria</small>
                            </div>
                            <div class="progress mb-2" style="height:4px;">
                                <div class="progress-bar bg-success" id="kriteriaBar" style="width:0%; transition:width .2s;"></div>
                            </div>
                            <div id="kriteriaGrid"
                                style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:5px; max-height:200px; overflow-y:auto;">
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- Form Pencarian --}}
        <div class="card card-rounded mb-3">
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
                            <i class="mdi mdi-magnify"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hasil Pencarian --}}
        <div id="searchSection" style="display:none;">
            <div class="card card-rounded mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="card-title mb-0">
                            Hasil Pencarian
                            <span class="badge bg-primary ms-1" id="searchTotal">0</span>
                        </h5>
                        <div class="d-flex gap-2 align-items-center">
                            <small class="text-muted" id="searchMeta"></small>
                            <button class="btn btn-outline-primary btn-sm"
                                    id="btnSelectAll" onclick="toggleSelectAll()">
                                <i class="mdi mdi-checkbox-multiple-marked-outline me-1"></i>
                                Pilih Semua
                            </button>
                            <button class="btn btn-success btn-sm" id="btnCheckSelected"
                                    onclick="checkSelected()" style="display:none;">
                                <i class="mdi mdi-play me-1"></i>
                                Periksa <span id="selectedCount">0</span> PDF
                            </button>
                        </div>
                    </div>

                    <div id="searchResults"></div>

                    {{-- Pagination --}}
                    <div id="paginationBar" class="d-flex justify-content-center gap-2 mt-3"
                         style="display:none !important;"></div>
                </div>
            </div>
        </div>

        {{-- Stat Cards --}}
        <div id="statsSection" style="display:none;">
            <div class="row mb-3">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card bg-primary-card">
                        <p>Total Diperiksa</p>
                        <h3 id="statTotal">0</h3>
                        <p>Publikasi</p>
                        <i class="mdi mdi-file-multiple-outline icon"></i>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card bg-success-card">
                        <p>Semua OK</p>
                        <h3 id="statOk">0</h3>
                        <p>Tidak ada masalah</p>
                        <i class="mdi mdi-check-circle-outline icon"></i>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card bg-warning-card">
                        <p>Perlu Dicek</p>
                        <h3 id="statWarn">0</h3>
                        <p>Ada catatan</p>
                        <i class="mdi mdi-alert-outline icon"></i>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card bg-danger-card">
                        <p>Ada Masalah</p>
                        <h3 id="statErr">0</h3>
                        <p>Kriteria tidak terpenuhi</p>
                        <i class="mdi mdi-close-circle-outline icon"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hasil Pemeriksaan --}}
        <div id="resultsSection" style="display:none;">
            <div class="card card-rounded mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">Hasil Pemeriksaan</h5>
                    <div id="resultsList"></div>
                </div>
            </div>

            <div class="card card-rounded mb-4">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <i class="mdi mdi-microsoft-excel text-success" style="font-size:32px;"></i>
                    <div class="flex-grow-1">
                        <strong class="d-block">Export Rekap ke Excel</strong>
                        <span class="text-muted small">Sheet Rekap + Detail per kriteria</span>
                    </div>
                    <button class="btn btn-success btn-sm" onclick="exportExcel()">
                        <i class="mdi mdi-download me-1"></i> Download Excel
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>


@endsection

@push('styles')
<style>
    .stat-card { border-radius:10px; padding:20px; color:white; position:relative; overflow:hidden; }
    .stat-card h3 { font-size:2.5rem; font-weight:bold; margin-bottom:5px; }
    .stat-card p  { margin-bottom:0; opacity:.9; font-size:.9rem; }
    .stat-card .icon { font-size:3rem; opacity:.25; position:absolute; right:20px; bottom:15px; }
    .bg-primary-card { background:#667eea; }
    .bg-success-card  { background:#28a745; }
    .bg-warning-card  { background:#ffc107; color:#212529 !important; }
    .bg-warning-card p, .bg-warning-card h3 { color:#212529 !important; }
    .bg-danger-card   { background:#dc3545; }

    .pub-card { 
        border:1px solid #e8e9f0; 
        border-radius:8px; 
        padding:12px; 
        background:#fff; 
        transition:border-color .15s;
    }
    .pub-card:hover { border-color:#667eea; }
    .pub-card.selected { border-color:#667eea; background:#f4f5ff; }
    .pub-card .pub-cover { 
        width:50px; 
        height:70px; 
        object-fit:cover; 
        border-radius:4px; 
        background:#f0f0f0; 
        flex-shrink:0; 
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .pub-card {
            flex-direction: column !important;
            gap: 10px !important;
        }
        
        .pub-card .pub-cover {
            width: 100%;
            height: 120px;
            max-width: 150px;
            margin: 0 auto;
        }
        
        .pub-card .flex-grow-1 {
            min-width: 100% !important;
        }
        
        .pub-card .fw-semibold {
            white-space: normal !important;
            text-overflow: initial !important;
            overflow: visible !important;
        }
        
        .pub-card .flex-shrink-0 {
            width: 100%;
            justify-content: center !important;
            flex-wrap: wrap;
        }
        
        .pub-card .form-check-input {
            position: static !important;
            margin: 0 auto 10px !important;
        }
    }
    
    /* Tablet */
    @media (min-width: 769px) and (max-width: 1024px) {
        .pub-card .fw-semibold {
            font-size: 0.85rem;
        }
        
        .pub-card .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
    }
    
    .result-row-header { cursor:pointer; transition:background .15s; }
    .result-row-header:hover { background:#f8f9fa !important; }

    .phase-block + .phase-block { margin-top: 14px; padding-top: 14px; border-top: 1px solid #f0f0f0; }

    .krit-live {
        display: flex; align-items: center; gap: 7px;
        padding: 4px 8px; border-radius: 5px; font-size: 11.5px;
        border: 1px solid #e9ecef; background: #f8f9fa;
        opacity: 0; transform: translateX(-6px);
        transition: opacity .18s ease, transform .18s ease;
    }
    .krit-live.show          { opacity: 1; transform: translateX(0); }
    .krit-live.s-ok          { background:#f0fdf4; border-color:#bbf7d0; }
    .krit-live.s-warn        { background:#fffbeb; border-color:#fde68a; }
    .krit-live.s-err         { background:#fef2f2; border-color:#fecaca; }
    .krit-live.s-skip        { background:#f8f9fa; border-color:#e2e8f0; opacity:.55; }
    .krit-live .kl-label     { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .krit-live .kl-badge     { font-size:10px; font-weight:600; flex-shrink:0; padding:1px 5px; border-radius:3px; }
    .kl-badge.ok   { background:#dcfce7; color:#166534; }
    .kl-badge.warn { background:#fef9c3; color:#854d0e; }
    .kl-badge.err  { background:#fee2e2; color:#991b1b; }
    .kl-badge.skip { background:#f1f5f9; color:#94a3b8; }
</style>
@endpush

@push('scripts')
<script>
const CSRF      = document.querySelector('meta[name=csrf-token]')?.content ?? '';
let currentPage = 1;
let allResults  = [];
let searchItems = []; // hasil search terakhir
let selected    = new Set();

// ── SEARCH ────────────────────────────────────────────────────
async function doSearch(page = 1) {
    currentPage = page;
    const btn = document.getElementById('btnSearch');
    btn.disabled = true;
    btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i>';

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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(body),
        });
        const data = await res.json();

        if (data.error) { showAlert('danger', data.error); return; }

        searchItems = data.items ?? [];
        selected.clear();
        renderSearchResults(data.meta, searchItems);
    } catch(e) {
        showAlert('danger', 'Pencarian gagal: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="mdi mdi-magnify"></i>';
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
            <i class="mdi mdi-file-search-outline d-block mb-2" style="font-size:36px;opacity:.3;"></i>
            Tidak ada publikasi ditemukan</div>`;
        renderPagination(meta);
        return;
    }

    box.innerHTML = items.map((pub, i) => {
       
        return `
    <div class="pub-card mb-2 ${selected.has(pub.pub_id) ? 'selected' : ''}"
         id="pubcard-${i}">
        <div class="d-flex gap-3 align-items-start flex-wrap" onclick="toggleSelect(${i})">
            <input type="checkbox" class="form-check-input mt-1 flex-shrink-0"
                   id="chk-${i}" ${selected.has(pub.pub_id) ? 'checked' : ''}
                   onclick="event.stopPropagation(); toggleSelect(${i})">
            ${pub.cover
                ? `<img src="${pub.cover}" class="pub-cover" alt="cover" onerror="this.style.display='none'">`
                : `<div class="pub-cover d-flex align-items-center justify-content-center text-muted" style="font-size:20px;"><i class="mdi mdi-file-pdf-box text-danger"></i></div>`
            }
            <div class="flex-grow-1 min-w-0" style="min-width:0;">
                <div class="fw-semibold small mb-1" style="word-break: break-word; overflow-wrap: break-word;" title="${pub.title}">
                    ${pub.title}
                </div>
                <div class="d-flex flex-wrap gap-2">
                    ${pub.issn ? `<span class="badge bg-secondary fw-normal">${pub.issn}</span>` : ''}
                    ${pub.rl_date ? `<small class="text-muted">${pub.rl_date}</small>` : ''}
                    ${pub.size ? `<small class="text-muted">${pub.size}</small>` : ''}
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center justify-content-end mt-2 flex-wrap">
            ${pub.has_pdf
                ? `<span class="badge bg-success fw-normal"><i class="mdi mdi-file-pdf-box me-1"></i>PDF</span>`
                : `<span class="badge bg-secondary fw-normal">Tanpa PDF</span>`
            }
            <a href="{{ route('checker.bps.detail.show') }}?pub_id=${pub.pub_id}&domain=${pub.domain || document.getElementById('searchDomain').value}" 
               class="btn btn-primary btn-sm" 
               onclick="event.stopPropagation()" 
               title="Lihat Detail Lengkap">
                <i class="mdi mdi-information-outline"></i>
                <span class="d-none d-md-inline ms-1">Detail</span>
            </a>
            ${pub.has_pdf
                ? `<a href="${pub.pdf}" download class="btn btn-outline-success btn-sm" onclick="event.stopPropagation()" title="Download PDF">
                    <i class="mdi mdi-download"></i>
                    <span class="d-none d-md-inline ms-1">PDF</span>
                </a>`
                : ''
            }
        </div>
    </div>`
    }).join('');

    renderPagination(meta);
    updateSelectedUI();
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

function toggleSelect(i) {
    const pub  = searchItems[i];
    if (!pub?.has_pdf) return; // skip yang tidak ada PDF
    const card = document.getElementById('pubcard-' + i);
    const chk  = document.getElementById('chk-' + i);
    if (selected.has(pub.pub_id)) {
        selected.delete(pub.pub_id);
        card.classList.remove('selected');
        chk.checked = false;
    } else {
        selected.add(pub.pub_id);
        card.classList.add('selected');
        chk.checked = true;
    }
    updateSelectedUI();
}

function toggleSelectAll() {
    const hasPdf = searchItems.filter(p => p.has_pdf);
    const allSel = hasPdf.every(p => selected.has(p.pub_id));
    hasPdf.forEach((pub, _) => {
        const i = searchItems.indexOf(pub);
        if (allSel) {
            selected.delete(pub.pub_id);
            document.getElementById('pubcard-'+i)?.classList.remove('selected');
            if(document.getElementById('chk-'+i)) document.getElementById('chk-'+i).checked = false;
        } else {
            selected.add(pub.pub_id);
            document.getElementById('pubcard-'+i)?.classList.add('selected');
            if(document.getElementById('chk-'+i)) document.getElementById('chk-'+i).checked = true;
        }
    });
    updateSelectedUI();
}

function updateSelectedUI() {
    const n   = selected.size;
    const btn = document.getElementById('btnCheckSelected');
    document.getElementById('selectedCount').textContent = n;
    btn.style.display = n > 0 ? 'inline-block' : 'none';
}

let activeSource = null; // track EventSource aktif

async function checkSelected() {
    if (!selected.size) return;

    const publications = searchItems
        .filter(p => selected.has(p.pub_id) && p.has_pdf)
        .map(p => ({ pub_id: p.pub_id, title: p.title, pdf: p.pdf }));

    if (!publications.length) {
        showAlert('warning', 'Tidak ada publikasi dengan PDF yang dipilih.');
        return;
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
    await new Promise(r => setTimeout(r, 300));

    document.getElementById('progressSection').style.display = 'block';
    document.getElementById('statsSection').style.display    = 'none';
    document.getElementById('resultsSection').style.display  = 'none';
    document.getElementById('btnCheckSelected').disabled     = true;
    allResults = [];

    const total = publications.length;
    let done    = 0;

    for (const pub of publications) {
        setProgress(done, total, `📥 Mengunduh: ${pub.title.substring(0, 55)}...`);
        
        try {
            await streamOnePub(pub, done, total);
        } catch (e) {
            console.error(e);
            allResults.push({
                pub_id: pub.pub_id, filename: pub.title, error: e.message,
                summary: { ok:0, perlu_dicek:0, tidak_ada:0, tidak_diperiksa:0, status_akhir:'ERROR' },
                checks: []
            });
        }

        done++;
        setProgress(done, total, done === total ? '🎉 Selesai!' : `✅ ${pub.title.substring(0, 40)}`);
        await new Promise(r => setTimeout(r, 300));
    }

    // Selesai semua
    setTimeout(() => {
        document.getElementById('progressSection').style.display = 'none';
        showPhase(null); // reset semua fase
        if (allResults.length) {
            renderStats();
            renderList();
            document.getElementById('statsSection').style.display   = 'block';
            document.getElementById('resultsSection').style.display = 'block';
            document.getElementById('statsSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        document.getElementById('btnCheckSelected').disabled = false;
    }, 800);
}

/**
 * Stream SSE satu publikasi.
 * Event dari server:
 *   heartbeat  — update download progress
 *   parse_start — mulai baca halaman, kirim total
 *   parse_page  — halaman ke-N selesai dibaca
 *   parse_done  — semua halaman selesai
 *   check       — satu kriteria selesai dievaluasi
 *   done        — semua selesai, result lengkap
 *   error       — ada error
 */
function streamOnePub(pub, doneCount, total) {
    return new Promise((resolve, reject) => {
        // Reset semua fase
        showPhase(null);
        document.getElementById('kriteriaGrid').innerHTML = '';
        document.getElementById('kriteriaMeta').textContent = '0 kriteria';
        document.getElementById('kriteriaBar').style.width  = '0%';

        // Tampilkan fase download dulu
        showPhase('download');
        document.getElementById('downloadMsg').textContent = 'Menghubungi server BPS...';

        const url = `{{ route('checker.bps.stream') }}?`
            + `pdf_url=${encodeURIComponent(pub.pdf)}`
            + `&title=${encodeURIComponent(pub.title)}`
            + `&domain=${encodeURIComponent(document.getElementById('searchDomain').value)}`;

        if (activeSource) activeSource.close();
        const es = new EventSource(url);
        activeSource = es;

        let totalPages   = 0;
        let doneKriteria = 0;
        let totalKriteria = 0; // akan diketahui setelah semua check masuk

        // ── heartbeat: progress download ──────────────────
        es.addEventListener('heartbeat', e => {
            const d = JSON.parse(e.data);
            document.getElementById('downloadMsg').textContent = d.msg ?? '...';
            document.getElementById('progressText').textContent = `⏳ ${d.msg}`;
        });

        // ── parse_start: PDF mulai dibaca ─────────────────
        es.addEventListener('parse_start', e => {
            const d = JSON.parse(e.data);
            totalPages = d.total_pages;
            showPhase('pages');
            document.getElementById('pagesMeta').textContent = `0 / ${totalPages} hal.`;
            document.getElementById('pagesMsg').textContent  = 'Membaca halaman...';
            document.getElementById('pagesBar').style.width  = '0%';
            document.getElementById('progressText').textContent = `📄 Membaca ${totalPages} halaman...`;
        });

        // ── parse_page: satu halaman selesai dibaca ───────
        es.addEventListener('parse_page', e => {
            const d   = JSON.parse(e.data);
            const pct = totalPages ? Math.round(d.current / totalPages * 100) : 0;
            document.getElementById('pagesBar').style.width  = pct + '%';
            document.getElementById('pagesMeta').textContent = `${d.current} / ${d.total} hal.`;
            document.getElementById('pagesMsg').textContent  = `Halaman ${d.current} selesai dibaca`;
        });

        // ── parse_done: semua halaman sudah dibaca ────────
        es.addEventListener('parse_done', e => {
            const d = JSON.parse(e.data);
            document.getElementById('pagesBar').style.width  = '100%';
            document.getElementById('pagesMeta').textContent = `${d.total_pages} / ${d.total_pages} hal. ✓`;
            document.getElementById('progressText').textContent = `🔍 Memeriksa kriteria...`;
            // Transisi ke fase kriteria
            setTimeout(() => showPhase('kriteria'), 300);
        });

        // ── check: satu kriteria selesai dievaluasi ───────
        es.addEventListener('check', e => {
            const ch = JSON.parse(e.data);
            doneKriteria++;

            const statusMap = {
                'OK':              { cls:'s-ok',   badge:'ok',   icon:'mdi-check-circle text-success',  label:'OK'    },
                'PERLU DICEK':     { cls:'s-warn',  badge:'warn', icon:'mdi-alert text-warning',         label:'Dicek' },
                'TIDAK ADA':       { cls:'s-err',   badge:'err',  icon:'mdi-close-circle text-danger',   label:'Gagal' },
                'TIDAK DIPERIKSA': { cls:'s-skip',  badge:'skip', icon:'mdi-minus-circle text-muted',    label:'Skip'  },
            };
            const s  = statusMap[ch.status] ?? statusMap['TIDAK DIPERIKSA'];
            const el = document.createElement('div');
            el.className = `krit-live ${s.cls}`;
            el.innerHTML = `
                <i class="mdi ${s.icon}" style="font-size:13px; flex-shrink:0;"></i>
                <span class="kl-label" title="${ch.deskripsi}">${ch.deskripsi}</span>
                <span class="kl-badge ${s.badge}">${s.label}</span>`;

            const grid = document.getElementById('kriteriaGrid');
            grid.appendChild(el);
            requestAnimationFrame(() => el.classList.add('show'));
            grid.scrollTop = grid.scrollHeight;

            document.getElementById('kriteriaMeta').textContent = `${doneKriteria} kriteria diperiksa`;
            // Update bar kriteria — estimasi dari jumlah yang masuk
            // bar akan 100% saat done
        });

        // ── done: semua selesai ───────────────────────────
        es.addEventListener('done', e => {
            const d = JSON.parse(e.data);
            allResults.push(d.result);

            document.getElementById('kriteriaBar').style.width  = '100%';
            document.getElementById('kriteriaMeta').textContent = `${doneKriteria} / ${doneKriteria} ✓`;
            document.getElementById('progressText').textContent = `✅ Selesai: ${pub.title.substring(0, 50)}`;

            clearTimeout(timeout);
            es.close();
            activeSource = null;
            resolve(d.result);
        });

        // ── error: ada yang gagal ─────────────────────────
        es.addEventListener('error', e => {
            let msg = 'SSE error';
            try { msg = JSON.parse(e.data).message; } catch {}
            clearTimeout(timeout);
            es.close();
            activeSource = null;
            reject(new Error(msg));
        });

        // Timeout 10 menit
        const timeout = setTimeout(() => {
            es.close();
            activeSource = null;
            reject(new Error('Timeout: PDF terlalu besar atau koneksi bermasalah'));
        }, 600_000);
    });
}

/**
 * Tampilkan salah satu fase, sembunyikan sisanya.
 * phase: 'download' | 'pages' | 'kriteria' | null
 */
function showPhase(phase) {
    document.getElementById('phaseDownload').style.display  = phase === 'download'  ? 'block' : 'none';
    document.getElementById('phasePages').style.display     = phase === 'pages'     ? 'block' : 'none';
    document.getElementById('phaseKriteria').style.display  = phase === 'kriteria'  ? 'block' : 'none';
}

function setProgress(done, total, text) {
    const pct = total ? Math.round(done / total * 100) : 0;
    document.getElementById('progressBar').style.width    = pct + '%';
    document.getElementById('progressText').textContent   = text;
    document.getElementById('progressPct').textContent    = pct + '%';
    document.getElementById('progressDetail').textContent = done + ' / ' + total + ' publikasi';
}

function renderStats() {
    const ok   = allResults.filter(r => !r.summary.tidak_ada && !r.summary.perlu_dicek).length;
    const warn = allResults.filter(r =>  r.summary.perlu_dicek > 0 && !r.summary.tidak_ada).length;
    const err  = allResults.filter(r =>  r.summary.tidak_ada > 0).length;
    document.getElementById('statTotal').textContent = allResults.length;
    document.getElementById('statOk').textContent    = ok;
    document.getElementById('statWarn').textContent  = warn;
    document.getElementById('statErr').textContent   = err;
}

function renderList() {
    const list = document.getElementById('resultsList');
    if (!allResults.length) {
        list.innerHTML = '<p class="text-muted text-center py-3">Tidak ada hasil</p>';
        return;
    }
    list.innerHTML = allResults.map((r, i) => {
        const hasErr  = r.summary.tidak_ada > 0;
        const hasWarn = r.summary.perlu_dicek > 0;
        const iconCls = hasErr  ? 'mdi-close-circle-outline text-danger'
                      : hasWarn ? 'mdi-alert-outline text-warning'
                      :           'mdi-check-circle-outline text-success';
        return `
        <div class="border rounded mb-2">
            <div class="d-flex align-items-center p-3 gap-2 bg-white rounded result-row-header"
                 onclick="toggleResult(${i}, this)">
                <i class="mdi ${iconCls}" style="font-size:18px;flex-shrink:0;"></i>
                <span class="flex-grow-1 fw-semibold small text-truncate" title="${r.filename}">${r.filename}</span>
                <div class="d-flex gap-1 flex-shrink-0">
                    ${r.summary.ok          ? `<span class="badge bg-success">${r.summary.ok} OK</span>` : ''}
                    ${r.summary.perlu_dicek ? `<span class="badge bg-warning text-dark">${r.summary.perlu_dicek} Dicek</span>` : ''}
                    ${r.summary.tidak_ada   ? `<span class="badge bg-danger">${r.summary.tidak_ada} Masalah</span>` : ''}
                </div>
                <small class="text-muted ms-1">${r.total_pages ?? '?'} hal.</small>
                <i class="mdi mdi-chevron-down text-muted ms-1 chev-icon" style="transition:transform .2s;"></i>
            </div>
            <div class="detail-body p-3 border-top bg-light rounded-bottom" style="display:none;">
                ${r.error ? `<div class="alert alert-danger py-2 small mb-2">${r.error}</div>` : ''}
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered mb-0 bg-white" style="font-size:12px;">
                        <thead class="table-light">
                            <tr><th>Kode</th><th>Kategori</th><th>Deskripsi</th><th>Status</th><th>Catatan</th></tr>
                        </thead>
                        <tbody>
                        ${(r.checks||[]).map(ch => `<tr>
                            <td class="font-monospace text-muted" style="font-size:11px;">${ch.id??''}</td>
                            <td class="text-muted small">${ch.kategori}</td>
                            <td style="font-size:12px;">${ch.deskripsi}</td>
                            <td>${statusBadge(ch.status)}</td>
                            <td class="text-muted small">${ch.catatan||'—'}</td>
                        </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;
    }).join('');
}

function toggleResult(i, header) {
    const body = header.closest('.border').querySelector('.detail-body');
    const chev = header.querySelector('.chev-icon');
    const open = body.style.display === 'none';
    body.style.display   = open ? 'block' : 'none';
    chev.style.transform = open ? 'rotate(180deg)' : '';
}

function statusBadge(s) {
    const map = { 'OK':'bg-success', 'PERLU DICEK':'bg-warning text-dark', 'TIDAK ADA':'bg-danger', 'TIDAK DIPERIKSA':'bg-secondary' };
    return `<span class="badge ${map[s]??'bg-secondary'}">${s}</span>`;
}

async function exportExcel() {
    if (!allResults.length) return;
    const res  = await fetch('{{ route("checker.export") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ results: allResults }),
    });
    const blob = await res.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'rekap_bps_import.xlsx'; a.click();
    URL.revokeObjectURL(url);
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