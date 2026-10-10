@extends('layouts.app')

@section('title', 'Pemeriksaan Publikasi')
@section('pretitle', 'Publication Checker')
@section('page-title', 'Pemeriksaan Kover Publikasi')
@section('page-subtitle', 'Upload hingga 5 PDF publikasi BPS, lalu tinjau per kategori berdampingan dengan contoh yang benar')
@section('page-actions')
    <a href="{{ route('checker.riwayat') }}" class="btn btn-primary">
        <i class="ti ti-history"></i> Riwayat
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Upload Card --}}
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-1">Upload File PDF</h5>
                <p class="text-muted small mb-3">Drag &amp; drop atau pilih hingga 5 PDF publikasi untuk diperiksa sekaligus</p>

                <div id="dropzone"
                     class="border border-2 rounded-3 p-5 text-center"
                     style="border-style: dashed !important; border-color: #dee2e6 !important; cursor: pointer; transition: background .2s, border-color .2s;"
                     onclick="document.getElementById('fileInput').click()"
                     ondragover="event.preventDefault(); this.style.background='#f4f5fa'; this.style.borderColor='#667eea !important';"
                     ondragleave="this.style.background=''; this.style.borderColor='';"
                     ondrop="handleDrop(event)">
                    <i class="ti ti-file-upload text-primary" style="font-size: 48px; opacity: .7;"></i>
                    <h6 class="mt-2 mb-1 fw-semibold">Drag &amp; drop file PDF di sini</h6>
                    <p class="text-muted small mb-3">Maksimal 5 PDF per pemeriksaan, 50MB per file</p>
                    <button class="btn btn-primary btn-sm px-4"
                            onclick="event.stopPropagation(); document.getElementById('fileInput').click()">
                        <i class="ti ti-paperclip me-1"></i> Pilih File PDF
                    </button>
                    <input type="file" id="fileInput" accept=".pdf" multiple
                           style="display:none" onchange="addFiles(this.files)">
                </div>
            </div>
        </div>

        {{-- File Queue --}}
        <div id="fileSection" style="display:none;">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="card-title mb-0">
                                <span id="fileCount">0</span> File Dipilih
                            </h5>
                            <small class="text-muted" id="actionNote"></small>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-4" id="fileChips"></div>

                    <div class="d-flex gap-2 align-items-center">
                        <button class="btn btn-primary" id="btnCheck" onclick="startCheck()">
                            <i class="ti ti-player-play me-1"></i> Periksa Semua PDF
                        </button>
                        <button class="btn btn-light border" onclick="clearAll()">
                            <i class="ti ti-refresh me-1"></i> Reset
                        </button>
                    </div>
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

        {{-- Step-by-step Review --}}
        @include('checker.partials.review-panel')

        {{-- Stat Cards --}}
        <div id="statsSection" style="display:none;">
            <div class="row row-cards mb-3">
                <div class="col-sm-6 col-lg-3"><x-stat label="Total Publikasi"   value-id="statTotal" icon="files"          color="blue"   sub="File diperiksa" /></div>
                <div class="col-sm-6 col-lg-3"><x-stat label="Semua Kriteria OK" value-id="statOk"    icon="circle-check"   color="green"  sub="Tidak ada masalah" /></div>
                <div class="col-sm-6 col-lg-3"><x-stat label="Perlu Dicek"       value-id="statWarn"  icon="alert-triangle" color="yellow" sub="Ada catatan" /></div>
                <div class="col-sm-6 col-lg-3"><x-stat label="Ada Masalah"       value-id="statErr"   icon="circle-x"       color="red"    sub="Kriteria tidak terpenuhi" /></div>
            </div>
        </div>

        {{-- Results --}}
        <div id="resultsSection" style="display:none;">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h5 class="card-title mb-0">Hasil Pemeriksaan (setelah ditinjau manual)</h5>
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            <div class="input-group input-group-sm" style="width: 220px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="ti ti-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0"
                                       id="searchInput" placeholder="Cari nama file..."
                                       oninput="renderList()">
                            </div>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-primary"
                                        onclick="setFilter('all',this)">Semua</button>
                                <button type="button" class="btn btn-outline-success"
                                        onclick="setFilter('ok',this)">OK</button>
                                <button type="button" class="btn btn-outline-warning"
                                        onclick="setFilter('warn',this)">Perlu Dicek</button>
                                <button type="button" class="btn btn-outline-danger"
                                        onclick="setFilter('err',this)">Masalah</button>
                            </div>
                        </div>
                    </div>

                    <div id="resultsList"></div>
                </div>
            </div>

            {{-- Export --}}
            <div class="card mb-4">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <i class="ti ti-file-spreadsheet text-success" style="font-size: 32px;"></i>
                    <div class="flex-grow-1">
                        <strong class="d-block">Export Rekap ke Excel</strong>
                        <span class="text-muted small">
                            Sheet <strong>Rekap</strong> + <strong>Detail</strong> per kriteria per publikasi
                        </span>
                    </div>
                    <button class="btn btn-success btn-sm" onclick="exportExcel()">
                        <i class="ti ti-download me-1"></i> Download Excel
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/checker-review.js') }}?v={{ filemtime(public_path('js/checker-review.js')) }}"></script>
<script src="{{ asset('js/pdf-extract.js') }}?v={{ filemtime(public_path('js/pdf-extract.js')) }}"></script>
<script src="{{ asset('js/batch-check.js') }}?v={{ filemtime(public_path('js/batch-check.js')) }}"></script>
<script>
const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
CheckerReview.init({ viewerUrl: '{{ asset("pdfjs/web/viewer.html") }}', csrf: CSRF, saveUrl: '{{ route("checker.hasil.review_kategori", "__ID__") }}', contohUrl: '{{ route("checker.contoh.json") }}' });
PdfExtract.init({ pdfjsBuild: '{{ asset("pdfjs/build") }}/' });

let selectedFiles = [];
let allResults    = [];
let batchOutcomes = []; // hasil per kartu (urutan sama dengan file), { result } atau { error }
let sesiId        = null;
let currentFilter = 'all';

// Maksimal PDF per pemeriksaan: teks & OCR dibaca di browser (±30–40 detik per PDF) dan
// tinjauan per kategori dilakukan berurutan per file — 5 masih nyaman untuk sekali duduk.
const MAX_FILES = 5;

function addFiles(files) {
    const pdfs = [...files].filter(f => f.name.toLowerCase().endsWith('.pdf'))
        .filter(f => !selectedFiles.some(s => s.name === f.name && s.size === f.size));
    if (!pdfs.length) return;

    const sisa = MAX_FILES - selectedFiles.length;
    if (pdfs.length > sisa) {
        showAlert('warning', `Maksimal ${MAX_FILES} PDF per pemeriksaan — ${pdfs.length - sisa} file tidak ditambahkan.`);
    }
    selectedFiles = selectedFiles.concat(pdfs.slice(0, Math.max(0, sisa)));
    document.getElementById('fileInput').value = '';
    renderQueue();
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropzone').style.background = '';
    addFiles([...e.dataTransfer.files]);
}

function renderQueue() {
    const sec   = document.getElementById('fileSection');
    const chips = document.getElementById('fileChips');
    if (!selectedFiles.length) { sec.style.display = 'none'; return; }
    sec.style.display = 'block';
    document.getElementById('fileCount').textContent = selectedFiles.length;
    document.getElementById('actionNote').textContent = selectedFiles.length >= MAX_FILES
        ? `${selectedFiles.length} PDF siap diperiksa (maksimal ${MAX_FILES})`
        : `${selectedFiles.length} PDF siap diperiksa — bisa tambah ${MAX_FILES - selectedFiles.length} lagi`;
    chips.innerHTML = selectedFiles.map((f, i) => `
        <span class="file-chip">
            <i class="ti ti-file-type-pdf text-danger"></i>
            <span class="text-truncate" style="max-width:160px;" title="${f.name}">${f.name}</span>
            <small class="text-muted">${(f.size/1024).toFixed(0)} KB</small>
            <i class="ti ti-x text-muted" style="cursor:pointer;" onclick="removeFile(${i})"></i>
        </span>`).join('');
}

function removeFile(i) { selectedFiles.splice(i, 1); renderQueue(); }
function clearAll()    { selectedFiles = []; renderQueue(); }

async function startCheck() {
    if (!selectedFiles.length) return;
    const btn = document.getElementById('btnCheck');
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2 icon-spin me-1"></i> Memproses...';
    const total = selectedFiles.length;
    document.getElementById('batchSection').style.display    = 'block';
    document.getElementById('statsSection').style.display    = 'none';
    document.getElementById('resultsSection').style.display  = 'none';
    document.getElementById('batchTitle').textContent        = `Memeriksa ${total} publikasi…`;
    document.getElementById('btnReviewAll').disabled         = true;
    allResults = []; sesiId = null;

    // Sesi dibuat dulu supaya semua file (diproses paralel) masuk ke sesi yang sama
    try {
        const res = await fetch('{{ route("checker.sesi.create") }}', {
            method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        sesiId = (await res.json()).sesi_id ?? null;
    } catch (e) { sesiId = null; }

    // Satu file per request (batas upload server per request). Teks PDF dibaca di browser
    // (pdf.js + OCR) — server hosting tidak bisa menjalankan Ghostscript/Tesseract.
    const jobs = selectedFiles.map(file => ({
        title:  file.name,
        cover:  null,
        source: file,
        send:   async extracted => {
            const fd = new FormData();
            fd.append('files[]', file);
            if (sesiId) fd.append('sesi_id', sesiId);
            if (extracted) fd.append('extracted', JSON.stringify(extracted));
            fd.append('_token', CSRF);
            const res  = await fetch('{{ route("checker.check") }}', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.error || !res.ok) throw new Error(data.error || data.message || ('Server membalas ' + res.status));
            const r = data.results?.[0];
            if (!r || r.error) throw new Error(r?.error || 'Tidak ada hasil pemeriksaan');
            sesiId ??= data.sesi_id;
            r._file = file; // PDF asli untuk viewer — jangan mengandalkan urutan
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
    btn.innerHTML = '<i class="ti ti-player-play me-1"></i> Periksa Ulang';

    // Satu publikasi: langsung masuk tinjauan seperti sebelumnya
    if (total === 1 && allResults.length === 1) beginReview();
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
    const q    = (document.getElementById('searchInput').value || '').toLowerCase();
    const list = document.getElementById('resultsList');
    const filtered = allResults.filter(r => {
        if (!r.filename.toLowerCase().includes(q)) return false;
        if (currentFilter === 'ok')   return !r.summary.tidak_ada && !r.summary.perlu_dicek;
        if (currentFilter === 'warn') return  r.summary.perlu_dicek > 0 && !r.summary.tidak_ada;
        if (currentFilter === 'err')  return  r.summary.tidak_ada > 0;
        return true;
    });

    if (!filtered.length) {
        list.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="ti ti-file-search d-block mb-2" style="font-size:40px;opacity:.3;"></i>
                Tidak ada hasil yang cocok
            </div>`;
        return;
    }

    list.innerHTML = filtered.map((r, i) => {
        const hasErr  = r.summary.tidak_ada > 0;
        const hasWarn = r.summary.perlu_dicek > 0;
        const iconCls = hasErr  ? 'ti-circle-x text-danger'
                      : hasWarn ? 'ti-alert-triangle text-warning'
                      :           'ti-circle-check text-success';
        return `
        <div class="border rounded mb-2" id="pub-${i}">
            <div class="d-flex align-items-center p-3 gap-2 bg-white rounded result-row-header"
                 onclick="togglePub(${i})">
                <i class="ti ${iconCls}" style="font-size:18px;flex-shrink:0;"></i>
                <span class="flex-grow-1 fw-semibold small text-truncate" title="${r.filename}">${r.filename}</span>
                <div class="d-flex gap-1 flex-shrink-0">
                    ${r.summary.ok          ? `<span class="badge bg-success">${r.summary.ok} OK</span>` : ''}
                    ${r.summary.perlu_dicek ? `<span class="badge bg-warning text-dark">${r.summary.perlu_dicek} Dicek</span>` : ''}
                    ${r.summary.tidak_ada   ? `<span class="badge bg-danger">${r.summary.tidak_ada} Masalah</span>` : ''}
                    ${r.summary.tidak_diperiksa ? `<span class="badge bg-secondary">${r.summary.tidak_diperiksa} Skip</span>` : ''}
                </div>
                <small class="text-muted flex-shrink-0 ms-1">${r.total_pages ?? '?'} hal.</small>
                <i class="ti ti-chevron-down text-muted ms-1" id="chev-${i}" style="transition:transform .2s;flex-shrink:0;"></i>
            </div>
            <div id="detail-${i}" style="display:none;" class="p-3 border-top bg-light rounded-bottom">
                ${r.error ? `<div class="alert alert-danger py-2 small mb-3">${r.error}</div>` : ''}
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered mb-0 bg-white" style="font-size:12px;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:65px;">Kode</th>
                                <th style="width:140px;">Kategori</th>
                                <th>Deskripsi</th>
                                <th style="width:130px;">Status</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${r.checks.map(ch => `
                            <tr>
                                <td class="font-monospace text-muted" style="font-size:11px;">${ch.id ?? ''}</td>
                                <td class="text-muted small">${ch.kategori}</td>
                                <td>${ch.deskripsi}</td>
                                <td>${statusBadge(ch.status)}</td>
                                <td class="text-muted small">${ch.catatan || '—'}</td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;
    }).join('');
}

const statusBadge = CheckerReview.statusBadge;

// ── STEP-BY-STEP REVIEW (lihat js/checker-review.js) ─────────────
/** @param {number} [jobIndex] kartu yang diklik "Tinjau" — publikasi itu dibuka pertama */
function beginReview(jobIndex) {
    const target = Number.isInteger(jobIndex) ? batchOutcomes[jobIndex]?.result : null;
    const entries = allResults
        .map(r => {
            const file = r._file;
            if (!file || !r.checks?.length) return null;
            const url = URL.createObjectURL(file);
            return {
                filename: r.filename,
                checks:   r.checks,
                hasilId:  r.hasil_id,
                ocrLines: r.ocr_lines,
                summary:  r.summary,
                url,
                cleanup: () => URL.revokeObjectURL(url),
            };
        })
        .filter(Boolean);

    const startIndex = target ? entries.findIndex(e => e.hasilId === target.hasil_id) : undefined;
    CheckerReview.start(entries, { onFinish: finishReview, startIndex: startIndex >= 0 ? startIndex : undefined });
}

function finishReview() {
    renderStats(); renderList();
    document.getElementById('statsSection').style.display   = 'block';
    document.getElementById('resultsSection').style.display = 'block';
    document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function togglePub(i) {
    const d = document.getElementById('detail-' + i);
    const c = document.getElementById('chev-' + i);
    const open = d.style.display === 'none';
    d.style.display = open ? 'block' : 'none';
    c.style.transform = open ? 'rotate(180deg)' : '';
}

function setFilter(f, btn) {
    currentFilter = f;
    document.querySelectorAll('.btn-group .btn').forEach(b => {
        b.className = b.className.replace(/\bbtn-(primary|success|warning|danger)\b/g, match => 'btn-outline-' + match.split('-')[1]);
    });
    const map = { all:'primary', ok:'success', warn:'warning', err:'danger' };
    btn.className = btn.className.replace('btn-outline-' + map[f], 'btn-' + map[f]);
    renderList();
}

async function exportExcel() {
    if (!allResults.length) return;
    try {
        const res  = await fetch('{{ route("checker.export") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ results: allResults }),
        });
        const blob = await res.blob();
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = 'rekap_pemeriksaan_bps.xlsx'; a.click();
        URL.revokeObjectURL(url);
    } catch (err) {
        showAlert('danger', 'Export gagal: ' + err.message);
    }
}

function showAlert(type, msg) {
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show`;
    div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.querySelector('.content-wrapper').prepend(div);
    setTimeout(() => div.remove(), 5000);
}
</script>
@endpush