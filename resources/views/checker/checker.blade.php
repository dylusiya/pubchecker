@extends('layouts.app')

@section('title', 'Pemeriksaan Publikasi')

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Header --}}
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-1">
                            <i class="mdi mdi-file-search-outline text-primary me-2"></i>
                            Pemeriksaan Kover Publikasi
                        </h4>
                        <p class="text-muted mb-0 small">Upload satu atau banyak PDF publikasi BPS, lalu tinjau step-by-step: kiri halaman yang diperiksa, kanan kriteria yang harus dicek</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm border">
                            <i class="mdi mdi-home"></i> Dashboard
                        </a>
                        <a href="{{ route('checker.riwayat') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-history"></i> Riwayat
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Upload Card --}}
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <h5 class="card-title mb-1">Upload File PDF</h5>
                <p class="text-muted small mb-3">Drag &amp; drop atau pilih satu / banyak PDF — diproses per batch 5 file</p>

                <div id="dropzone"
                     class="border border-2 rounded-3 p-5 text-center"
                     style="border-style: dashed !important; border-color: #dee2e6 !important; cursor: pointer; transition: background .2s, border-color .2s;"
                     onclick="document.getElementById('fileInput').click()"
                     ondragover="event.preventDefault(); this.style.background='#f4f5fa'; this.style.borderColor='#667eea !important';"
                     ondragleave="this.style.background=''; this.style.borderColor='';"
                     ondrop="handleDrop(event)">
                    <i class="mdi mdi-file-upload-outline text-primary" style="font-size: 48px; opacity: .7;"></i>
                    <h6 class="mt-2 mb-1 fw-semibold">Drag &amp; drop file PDF di sini</h6>
                    <p class="text-muted small mb-3">Maksimal 20 file, 20MB per file</p>
                    <button class="btn btn-primary btn-sm px-4"
                            onclick="event.stopPropagation(); document.getElementById('fileInput').click()">
                        <i class="mdi mdi-paperclip me-1"></i> Pilih File PDF
                    </button>
                    <input type="file" id="fileInput" multiple accept=".pdf"
                           style="display:none" onchange="addFiles(this.files)">
                </div>
            </div>
        </div>

        {{-- File Queue --}}
        <div id="fileSection" style="display:none;">
            <div class="card card-rounded mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="card-title mb-0">
                                <span id="fileCount">0</span> File Dipilih
                            </h5>
                            <small class="text-muted" id="actionNote"></small>
                        </div>
                        <button class="btn btn-light btn-sm border" onclick="clearAll()">
                            <i class="mdi mdi-close me-1"></i> Hapus Semua
                        </button>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-4" id="fileChips"></div>

                    <div class="d-flex gap-2 align-items-center">
                        <button class="btn btn-primary" id="btnCheck" onclick="startCheck()">
                            <i class="mdi mdi-play me-1"></i> Periksa Semua PDF
                        </button>
                        <button class="btn btn-light border" onclick="clearAll()">
                            <i class="mdi mdi-refresh me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Progress --}}
        <div id="progressSection" style="display:none;">
            <div class="card card-rounded mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-loading mdi-spin text-primary me-1"></i>
                            <span id="progressText">Memproses...</span>
                        </h5>
                        <span class="badge bg-primary" id="progressPct">0%</span>
                    </div>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated"
                             id="progressBar" style="width: 0%; transition: width .3s;"></div>
                    </div>
                    <small class="text-muted" id="progressDetail"></small>
                </div>
            </div>
        </div>

        {{-- Step-by-step Review --}}
        @include('checker.partials.review-panel')

        {{-- Stat Cards --}}
        <div id="statsSection" style="display:none;">
            <div class="row mb-3">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card bg-primary-card">
                        <p>Total Publikasi</p>
                        <h3 id="statTotal">0</h3>
                        <p>File diperiksa</p>
                        <i class="mdi mdi-file-multiple-outline icon"></i>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card bg-success-card">
                        <p>Semua Kriteria OK</p>
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

        {{-- Results --}}
        <div id="resultsSection" style="display:none;">
            <div class="card card-rounded mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h5 class="card-title mb-0">Hasil Pemeriksaan (setelah ditinjau manual)</h5>
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            <div class="input-group input-group-sm" style="width: 220px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="mdi mdi-magnify text-muted"></i>
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
            <div class="card card-rounded mb-4">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <i class="mdi mdi-microsoft-excel text-success" style="font-size: 32px;"></i>
                    <div class="flex-grow-1">
                        <strong class="d-block">Export Rekap ke Excel</strong>
                        <span class="text-muted small">
                            Sheet <strong>Rekap</strong> + <strong>Detail</strong> per kriteria per publikasi
                        </span>
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
    .stat-card {
        border-radius: 10px;
        padding: 20px;
        color: white;
        margin-bottom: 0;
        position: relative;
        overflow: hidden;
    }
    .stat-card h3 {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .stat-card p {
        margin-bottom: 0;
        opacity: 0.9;
        font-size: 0.9rem;
    }
    .stat-card .icon {
        font-size: 3rem;
        opacity: 0.25;
        position: absolute;
        right: 20px;
        bottom: 15px;
    }
    .bg-primary-card  { background: #667eea; }
    .bg-success-card  { background: #28a745; }
    .bg-warning-card  { background: #ffc107; color: #212529 !important; }
    .bg-warning-card p, .bg-warning-card h3 { color: #212529 !important; }
    .bg-danger-card   { background: #dc3545; }

    /* File chips */
    .file-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        background: #f4f5fa;
        border: 1px solid #e8e9f0;
        font-size: 12px;
    }

    /* Result rows */
    .result-row-header {
        cursor: pointer;
        transition: background .15s;
    }
    .result-row-header:hover { background: #f8f9fa !important; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/checker-review.js') }}"></script>
<script>
const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
CheckerReview.init({ viewerUrl: '{{ asset("pdfjs/web/viewer.html") }}', csrf: CSRF });

let selectedFiles = [];
let allResults    = [];
let sesiId        = null;
let currentFilter = 'all';

function addFiles(files) {
    [...files].forEach(f => {
        if (f.name.toLowerCase().endsWith('.pdf') &&
            !selectedFiles.find(s => s.name === f.name && s.size === f.size)) {
            selectedFiles.push(f);
        }
    });
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
    document.getElementById('actionNote').textContent = selectedFiles.length + ' PDF siap diperiksa';
    chips.innerHTML = selectedFiles.map((f, i) => `
        <span class="file-chip">
            <i class="mdi mdi-file-pdf-box text-danger"></i>
            <span class="text-truncate" style="max-width:160px;" title="${f.name}">${f.name}</span>
            <small class="text-muted">${(f.size/1024).toFixed(0)} KB</small>
            <i class="mdi mdi-close text-muted" style="cursor:pointer;" onclick="removeFile(${i})"></i>
        </span>`).join('');
}

function removeFile(i) { selectedFiles.splice(i, 1); renderQueue(); }
function clearAll()    { selectedFiles = []; renderQueue(); }

async function startCheck() {
    if (!selectedFiles.length) return;
    const btn = document.getElementById('btnCheck');
    btn.disabled = true;
    btn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Memproses...';
    document.getElementById('progressSection').style.display = 'block';
    document.getElementById('statsSection').style.display    = 'none';
    document.getElementById('resultsSection').style.display  = 'none';
    allResults = []; sesiId = null;

    const batchSize = 5, total = selectedFiles.length;
    let done = 0;
    for (let i = 0; i < total; i += batchSize) {
        const batch = selectedFiles.slice(i, i + batchSize);
        const fd = new FormData();
        batch.forEach(f => fd.append('files[]', f));
        if (sesiId) fd.append('sesi_id', sesiId);
        fd.append('_token', CSRF);
        setProgress(done, total, 'Memproses: ' + batch[0].name);
        try {
            const res  = await fetch('{{ route("checker.check") }}', { method:'POST', body:fd });
            const text = await res.text();
            console.log('Status:', res.status);
            console.log('Response:', text);
            
            // Coba parse
            const data = JSON.parse(text);
            if (data.error) { showAlert('danger', data.error); break; }
            sesiId = data.sesi_id ?? sesiId;
            allResults.push(...(data.results ?? []));
        } catch (err) { showAlert('danger', 'Request gagal: ' + err.message); }
        done += batch.length;
    }

    setProgress(total, total, 'Selesai! Memulai tinjauan manual...');
    document.getElementById('progressSection').style.display = 'none';
    btn.disabled = false;
    btn.innerHTML = '<i class="mdi mdi-play me-1"></i> Periksa Ulang';
    beginReview();
}

function setProgress(done, total, text) {
    const pct = total ? Math.round(done / total * 100) : 0;
    document.getElementById('progressBar').style.width    = pct + '%';
    document.getElementById('progressText').textContent   = text;
    document.getElementById('progressPct').textContent    = pct + '%';
    document.getElementById('progressDetail').textContent = done + ' / ' + total + ' file';
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
                <i class="mdi mdi-file-search-outline d-block mb-2" style="font-size:40px;opacity:.3;"></i>
                Tidak ada hasil yang cocok
            </div>`;
        return;
    }

    list.innerHTML = filtered.map((r, i) => {
        const hasErr  = r.summary.tidak_ada > 0;
        const hasWarn = r.summary.perlu_dicek > 0;
        const iconCls = hasErr  ? 'mdi-close-circle-outline text-danger'
                      : hasWarn ? 'mdi-alert-outline text-warning'
                      :           'mdi-check-circle-outline text-success';
        return `
        <div class="border rounded mb-2" id="pub-${i}">
            <div class="d-flex align-items-center p-3 gap-2 bg-white rounded result-row-header"
                 onclick="togglePub(${i})">
                <i class="mdi ${iconCls}" style="font-size:18px;flex-shrink:0;"></i>
                <span class="flex-grow-1 fw-semibold small text-truncate" title="${r.filename}">${r.filename}</span>
                <div class="d-flex gap-1 flex-shrink-0">
                    ${r.summary.ok          ? `<span class="badge bg-success">${r.summary.ok} OK</span>` : ''}
                    ${r.summary.perlu_dicek ? `<span class="badge bg-warning text-dark">${r.summary.perlu_dicek} Dicek</span>` : ''}
                    ${r.summary.tidak_ada   ? `<span class="badge bg-danger">${r.summary.tidak_ada} Masalah</span>` : ''}
                    ${r.summary.tidak_diperiksa ? `<span class="badge bg-secondary">${r.summary.tidak_diperiksa} Skip</span>` : ''}
                </div>
                <small class="text-muted flex-shrink-0 ms-1">${r.total_pages ?? '?'} hal.</small>
                <i class="mdi mdi-chevron-down text-muted ms-1" id="chev-${i}" style="transition:transform .2s;flex-shrink:0;"></i>
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
function beginReview() {
    const entries = allResults
        .map((r, i) => {
            const file = selectedFiles[i];
            if (!file || !r.checks?.length) return null;
            const url = URL.createObjectURL(file);
            return {
                filename: r.filename,
                checks:   r.checks,
                hasilId:  r.hasil_id,
                summary:  r.summary,
                url,
                cleanup: () => URL.revokeObjectURL(url),
            };
        })
        .filter(Boolean);

    CheckerReview.start(entries, { onFinish: finishReview });
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