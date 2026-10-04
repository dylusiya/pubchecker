@extends('layouts.app')

@section('title', 'Detail Publikasi BPS')

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Back Button --}}
        <div class="mb-3">
            <a href="{{ route('checker.bps.index') }}" class="btn btn-light btn-sm">
                <i class="mdi mdi-arrow-left me-1"></i> Kembali ke Pencarian
            </a>
        </div>

        {{-- Loading State --}}
        <div id="loadingSection" class="card card-rounded">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted">Memuat detail publikasi...</p>
            </div>
        </div>

        {{-- Error State --}}
        <div id="errorSection" style="display:none;" class="card card-rounded border-danger">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <i class="mdi mdi-alert-circle text-danger" style="font-size:32px;"></i>
                    <div>
                        <h5 class="text-danger mb-2">Gagal Memuat Publikasi</h5>
                        <p class="text-muted mb-3" id="errorMessage"></p>
                        <button class="btn btn-danger btn-sm" onclick="loadPublication()">
                            <i class="mdi mdi-refresh me-1"></i> Coba Lagi
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detail Content --}}
        <div id="detailSection" style="display:none;">

            {{-- Header Card --}}
            <div class="card card-rounded mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <img id="pubCover" src="" alt="Cover" class="img-fluid rounded shadow-sm mb-3"
                                 style="max-height:350px; object-fit:contain;"
                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22280%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22200%22 height=%22280%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2220%22 fill=%22%23999%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22%3ENo Cover%3C/text%3E%3C/svg%3E'">
                        </div>
                        <div class="col-md-9">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h3 class="mb-0" id="pubTitle"></h3>
                                <span class="badge bg-primary" id="pubStatus"></span>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">ISSN / ISBN</small>
                                    <strong id="pubISSN">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Katalog BPS</small>
                                    <strong id="pubKatalog">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Nomor Publikasi</small>
                                    <strong id="pubNo">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Ukuran File</small>
                                    <strong id="pubSize">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Tanggal Rilis</small>
                                    <strong id="pubDate">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Tanggal Jadwal</small>
                                    <strong id="pubSchDate">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Tanggal Update</small>
                                    <strong id="pubUpdtDate">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Jumlah Halaman</small>
                                    <strong id="pubPages">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Domain</small>
                                    <strong id="pubDomain">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Periode</small>
                                    <strong id="pubPeriode">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Bahasa</small>
                                    <strong id="pubBahasa">-</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block mb-1">Revisi</small>
                                    <strong id="pubRevisi">-</strong>
                                </div>
                                <div class="col-md-12">
                                    <small class="text-muted d-block mb-1">Subjek</small>
                                    <strong id="pubSubject">-</strong>
                                </div>
                            </div>

                            <div class="mb-3" id="abstractSection" style="display:none;">
                                <small class="text-muted d-block mb-1">Abstrak</small>
                                <p class="text-muted small" id="pubAbstract"></p>
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-primary" onclick="viewPDF()" id="btnViewPDF">
                                    <i class="mdi mdi-eye me-1"></i> Lihat PDF
                                </button>
                                <a href="#" id="btnDownloadPDF" class="btn btn-success" download>
                                    <i class="mdi mdi-download me-1"></i> Download PDF
                                </a>
                                <a href="#" id="btnOpenNewTab" class="btn btn-outline-primary" target="_blank">
                                    <i class="mdi mdi-open-in-new me-1"></i> Buka di Tab Baru
                                </a>
                                <button class="btn btn-outline-danger" onclick="checkQuality()" id="btnCheckQuality">
                                    <i class="mdi mdi-check-decagram me-1"></i> Periksa Kualitas PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- PDF Viewer Card --}}
            <div class="card card-rounded mb-3" id="pdfViewerCard" style="display:none;">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="mdi mdi-file-pdf-box text-danger me-2"></i>
                        PDF Viewer
                        <small class="text-muted ms-2" id="pdfPageInfo" style="font-size:.8rem;"></small>
                    </h5>
                    <div class="d-flex gap-2 align-items-center">
                        
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleFullscreen()">
                            <i class="mdi mdi-fullscreen" id="fullscreenIcon"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="closePdfViewer()">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </div>

                {{-- Progress bar loading --}}
                <div id="pdfLoadingOverlay" style="padding:40px 0;">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted fw-semibold mb-2">Memuat PDF...</p>
                        <div class="progress mx-auto mb-2" style="width:300px;height:8px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated"
                                id="pdfLoadProgress" style="width:0%"></div>
                        </div>
                        <small class="text-muted" id="pdfLoadStatus">Menginisialisasi...</small>
                    </div>
                </div>

                {{-- Fallback --}}
                <div id="pdfFallbackMsg" style="display:none; padding:60px 0;">
                    <div class="text-center p-4">
                        <i class="mdi mdi-alert-circle-outline text-warning mb-3" style="font-size:48px;"></i>
                        <h5 class="mb-2">PDF Tidak Dapat Ditampilkan</h5>
                        <p class="text-muted mb-3">Gunakan tombol di bawah untuk membuka atau mengunduh PDF.</p>
                        <a href="#" id="fallbackDownload" class="btn btn-success me-2" download>
                            <i class="mdi mdi-download me-1"></i> Download PDF
                        </a>
                        <a href="#" id="fallbackNewTab" class="btn btn-primary" target="_blank">
                            <i class="mdi mdi-open-in-new me-1"></i> Buka di Tab Baru
                        </a>
                    </div>
                </div>

                {{-- iframe --}}
                <div id="pdfFrameWrapper" style="display:none;">
                    <iframe id="pdfFrame"
                            style="width:100%; height:700px; border:none; display:block;"
                            allowfullscreen></iframe>
                </div>
            </div>

            {{-- Step-by-step Review --}}
            @include('checker.partials.review-panel')

            {{-- Quality Check Result --}}
            <div id="qualitySection" style="display:none;">
                <div class="card card-rounded mb-3">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="mdi mdi-check-decagram text-success me-2"></i>
                            Hasil Pemeriksaan Kualitas
                        </h5>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="stat-card-small bg-success-card">
                                    <small class="d-block mb-1">OK</small>
                                    <h4 id="statOk">0</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card-small bg-warning-card">
                                    <small class="d-block mb-1">Perlu Dicek</small>
                                    <h4 id="statWarn">0</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card-small bg-danger-card">
                                    <small class="d-block mb-1">Masalah</small>
                                    <h4 id="statErr">0</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card-small bg-secondary-card">
                                    <small class="d-block mb-1">Tidak Diperiksa</small>
                                    <h4 id="statSkip">0</h4>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="80">Kode</th>
                                        <th width="150">Kategori</th>
                                        <th>Deskripsi</th>
                                        <th width="120">Status</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody id="qualityChecks"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Additional Info --}}
            <div class="card card-rounded">
                <div class="card-body">
                    <h5 class="card-title mb-3">Informasi Tambahan</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted d-block mb-1">Publikasi ID</small>
                            <code id="pubId">-</code>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block mb-1">Link BPS</small>
                            <a href="#" id="pubLink" target="_blank" class="small">Lihat di Portal BPS</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .stat-card-small { border-radius:8px; padding:15px; color:white; text-align:center; }
    .stat-card-small h4 { font-size:2rem; font-weight:bold; margin:0; }
    .stat-card-small small { opacity:.9; font-size:.85rem; }
    .bg-success-card  { background:#28a745; }
    .bg-warning-card  { background:#ffc107; color:#212529 !important; }
    .bg-warning-card small, .bg-warning-card h4 { color:#212529 !important; }
    .bg-danger-card   { background:#dc3545; }
    .bg-secondary-card{ background:#6c757d; }

    #pdfViewerCard.fullscreen-viewer {
        position: fixed !important;
        top: 0; left: 0;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 9999;
        margin: 0 !important;
        border-radius: 0 !important;
        overflow: auto;
    }
    #pdfViewerCard.fullscreen-viewer #pdfFrame {
        height: calc(100vh - 60px) !important;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('pdfjs/build/pdf.mjs') }}" type="module"></script>
<script src="{{ asset('js/checker-review.js') }}?v={{ filemtime(base_path('js/checker-review.js')) }}"></script>
<script src="{{ asset('js/pdf-extract.js') }}?v={{ filemtime(base_path('js/pdf-extract.js')) }}"></script>
<script>
const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
const PDFJS_VIEWER = '{{ asset("pdfjs/web/viewer.html") }}';
CheckerReview.init({ viewerUrl: PDFJS_VIEWER, csrf: CSRF, saveUrl: '{{ route("checker.hasil.review_kategori", "__ID__") }}' });
PdfExtract.init({ pdfjsBuild: '{{ asset("pdfjs/build") }}/' });

let publicationData = null;
let pdfUrl          = null;
let isFullscreen    = false;

// ── URL PARAMS ────────────────────────────────────────────────
function getUrlParams() {
    const p = new URLSearchParams(window.location.search);
    return {
        pub_id: p.get('pub_id'),
        domain: p.get('domain') || '{{ config("services.bps_api.domain", "6300") }}'
    };
}

// ── LOAD PUBLICATION ──────────────────────────────────────────
async function loadPublication() {
    publicationData = null;
    pdfUrl          = null;
    const { pub_id, domain } = getUrlParams();

    if (!pub_id) { showError('Parameter pub_id tidak ditemukan di URL'); return; }

    document.getElementById('loadingSection').style.display = 'block';
    document.getElementById('errorSection').style.display   = 'none';
    document.getElementById('detailSection').style.display  = 'none';

    try {
        const res  = await fetch('{{ route("checker.bps.detail") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ pub_id, domain })
        });
        const data = await res.json();
        if (data.error) { showError(data.error); return; }
        publicationData = data.publication;
        renderPublication(publicationData);
    } catch(e) {
        showError('Gagal memuat data: ' + e.message);
    }
}

// ── RENDER ────────────────────────────────────────────────────
function renderPublication(pub) {
    document.getElementById('loadingSection').style.display = 'none';
    document.getElementById('detailSection').style.display  = 'block';

    document.getElementById('pubTitle').textContent   = pub.title || '-';
    document.getElementById('pubStatus').textContent  = pub.has_pdf ? 'PDF Tersedia' : 'Tanpa PDF';
    document.getElementById('pubStatus').className    = pub.has_pdf ? 'badge bg-success' : 'badge bg-secondary';

    if (pub.cover) document.getElementById('pubCover').src = pub.cover;

    document.getElementById('pubISSN').textContent     = pub.issn        || '-';
    document.getElementById('pubKatalog').textContent  = pub.catalog     || '-';
    document.getElementById('pubNo').textContent       = pub.pub_no      || '-';
    document.getElementById('pubDate').textContent     = pub.rl_date     || '-';
    document.getElementById('pubSchDate').textContent  = pub.sch_date    || '-';
    document.getElementById('pubUpdtDate').textContent = pub.updt_date   || '-';
    document.getElementById('pubSize').textContent     = pub.size        || '-';
    document.getElementById('pubPages').textContent    = pub.pages       || '-';
    document.getElementById('pubDomain').textContent   = pub.domain_name || 'Domain ' + pub.domain;
    document.getElementById('pubPeriode').textContent  = pub.periode     || '-';
    document.getElementById('pubBahasa').textContent   = pub.bahasa      || '-';
    document.getElementById('pubRevisi').textContent   = pub.revisi !== null ? (pub.revisi ? 'Ya' : 'Tidak') : '-';
    document.getElementById('pubSubject').textContent  = Array.isArray(pub.subject_csa) ? pub.subject_csa.join(', ') : (pub.subject_csa || '-');

    if (pub.abstract?.trim()) {
        document.getElementById('abstractSection').style.display = 'block';
        document.getElementById('pubAbstract').textContent = pub.abstract;
    }

    pdfUrl = pub.pdf;
    const hasPdf = pub.has_pdf && pdfUrl;
    document.getElementById('btnDownloadPDF').href      = hasPdf ? getProxiedPdfUrl() : '#';
    document.getElementById('btnOpenNewTab').href       = hasPdf ? pdfUrl : '#';
    document.getElementById('btnViewPDF').disabled      = !hasPdf;
    document.getElementById('btnCheckQuality').disabled = !hasPdf;

    document.getElementById('pubId').textContent = pub.pub_id || '-';

    const baseUrl = pub.domain_url || 'https://www.bps.go.id';
    const date    = pub.rl_date ? pub.rl_date.replace(/-/g, '/') : '';
    const slug    = makeSlug(pub.title || '');
    const pubLink = `${baseUrl}/id/publication/${date}/${pub.pub_id}/${slug}.html`;
    document.getElementById('pubLink').href = pubLink;
}

function makeSlug(title) {
    return title
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-');
}

// ── VIEW PDF ──────────────────────────────────────────────────
let pdfDoc        = null;
let pdfCurrentPage = 1;
let pdfScale      = 1;
let pdfRendering  = false;

function getProxiedPdfUrl() {
    if (!pdfUrl) return null;
    return '{{ route("checker.bps.pdf_proxy") }}?url=' + encodeURIComponent(pdfUrl);
}

function viewPDF() {
    if (!pdfUrl) return;

    const card      = document.getElementById('pdfViewerCard');
    const overlay   = document.getElementById('pdfLoadingOverlay');
    const fallback  = document.getElementById('pdfFallbackMsg');
    const wrapper   = document.getElementById('pdfFrameWrapper');
    const frame     = document.getElementById('pdfFrame');
    const progress  = document.getElementById('pdfLoadProgress');
    const status    = document.getElementById('pdfLoadStatus');

    card.style.display     = 'block';
    overlay.style.display  = 'block';
    fallback.style.display = 'none';
    wrapper.style.display  = 'none';
    frame.src              = '';

    progress.style.width = '30%';
    status.textContent   = 'Memuat viewer...';
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const proxiedUrl = getProxiedPdfUrl();
    const viewerUrl = `{{ asset('pdfjs/web/viewer.html') }}?file=${encodeURIComponent(proxiedUrl)}#pagemode=none&spread=even`;

    const timeout = setTimeout(() => {
        overlay.style.display  = 'none';
        fallback.style.display = 'block';
        document.getElementById('fallbackDownload').href = proxiedUrl;
        document.getElementById('fallbackNewTab').href   = pdfUrl;
    }, 60000);

    frame.onload = function () {
        if (frame.src && frame.src !== 'about:blank') {
            clearTimeout(timeout);
            overlay.style.display = 'none';
            wrapper.style.display = 'block';

            // Inject CSS untuk hide toolbar edit buttons
            try {
                const iframeDoc = frame.contentDocument || frame.contentWindow.document;
                const style = iframeDoc.createElement('style');
                style.textContent = `
                    #editorModeButtons,
                    #editorModeSeparator,
                    #printButton,
                    #downloadButton,
                    #openFileButton,
                    #secondaryOpenFile,
                    #secondaryPrint,
                    #secondaryDownload {
                        display: none !important;
                    }
                `;
                iframeDoc.head.appendChild(style);
            } catch(e) {
                // same-origin harusnya bisa, ignore jika gagal
            }
        }
    };

    setTimeout(() => {
        progress.style.width = '60%';
        status.textContent   = 'Membuka PDF...';
        frame.src = viewerUrl;
    }, 150);
}

async function renderPage(pageNum) {
    if (!pdfDoc) return;
    const page    = await pdfDoc.getPage(pageNum);
    const viewport = page.getViewport({ scale: pdfScale });

    // Buat canvas baru atau pakai yang ada
    let canvas = document.getElementById(`pdf-page-${pageNum}`);
    if (!canvas) {
        canvas = document.createElement('canvas');
        canvas.id = `pdf-page-${pageNum}`;
        canvas.style.cssText = 'box-shadow:0 2px 8px rgba(0,0,0,.4); display:block;';
        document.getElementById('pdfPagesWrapper').appendChild(canvas);
    }

    canvas.width  = viewport.width;
    canvas.height = viewport.height;

    await page.render({
        canvasContext: canvas.getContext('2d'),
        viewport
    }).promise;
}

async function renderRemainingPages() {
    if (!pdfDoc) return;
    for (let i = 2; i <= pdfDoc.numPages; i++) {
        await renderPage(i);
        // Update counter
        document.getElementById('pdfPageInfo').textContent =
            `(${i}/${pdfDoc.numPages} halaman dirender)`;
    }
    document.getElementById('pdfPageInfo').textContent = '';
}

function pdfPrevPage() {
    const container = document.getElementById('pdfCanvasContainer');
    const canvas = document.getElementById(`pdf-page-${Math.max(1, pdfCurrentPage - 1)}`);
    if (canvas) {
        pdfCurrentPage = Math.max(1, pdfCurrentPage - 1);
        canvas.scrollIntoView({ behavior: 'smooth', block: 'start' });
        updatePageControls();
    }
}

function pdfNextPage() {
    if (!pdfDoc) return;
    const next = Math.min(pdfDoc.numPages, pdfCurrentPage + 1);
    const canvas = document.getElementById(`pdf-page-${next}`);
    if (canvas) {
        pdfCurrentPage = next;
        canvas.scrollIntoView({ behavior: 'smooth', block: 'start' });
        updatePageControls();
    }
}

function pdfZoom(scale) {
    pdfScale = parseFloat(scale);
    if (!pdfDoc) return;
    // Re-render semua halaman dengan scale baru
    document.getElementById('pdfPagesWrapper').innerHTML = '';
    for (let i = 1; i <= pdfDoc.numPages; i++) renderPage(i);
}

function updatePageControls() {
    if (!pdfDoc) return;
    document.getElementById('pdfPageCounter').textContent = `${pdfCurrentPage} / ${pdfDoc.numPages}`;
    document.getElementById('btnPrev').disabled = pdfCurrentPage <= 1;
    document.getElementById('btnNext').disabled = pdfCurrentPage >= pdfDoc.numPages;
}

function closePdfViewer() {
    const card = document.getElementById('pdfViewerCard');
    card.style.display = 'none';
    document.getElementById('pdfCanvasContainer').style.display  = 'none';
    document.getElementById('pdfLoadingOverlay').style.display   = 'none';
    document.getElementById('pdfFallbackMsg').style.display      = 'none';
    document.getElementById('pdfPagesWrapper').innerHTML         = '';
    pdfDoc = null;
    if (isFullscreen) toggleFullscreen();
}

function toggleFullscreen() {
    const card = document.getElementById('pdfViewerCard');
    const icon = document.getElementById('fullscreenIcon');
    if (!isFullscreen) {
        card.classList.add('fullscreen-viewer');
        icon.className = 'mdi mdi-fullscreen-exit';
        isFullscreen = true;
    } else {
        card.classList.remove('fullscreen-viewer');
        icon.className = 'mdi mdi-fullscreen';
        isFullscreen = false;
    }
}

// ── CHECK QUALITY ─────────────────────────────────────────────
async function checkQuality() {
    if (!pdfUrl || !publicationData) return;

    const btn = document.getElementById('btnCheckQuality');
    const ori = btn.innerHTML;
    btn.disabled  = true;
    btn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Memeriksa...';
    document.getElementById('qualitySection').style.display = 'none';

    // Baca teks PDF di browser (pdf.js + OCR halaman gambar) — server hosting tidak bisa
    // menjalankan Ghostscript/Tesseract. Jika gagal, server mengunduh & membaca sendiri.
    let extracted = null;
    try {
        extracted = await PdfExtract.extract(getProxiedPdfUrl(), {
            onProgress: msg => { btn.innerHTML = `<i class="mdi mdi-loading mdi-spin me-1"></i> ${msg}`; },
        });
    } catch (e) {
        console.warn('Ekstraksi teks di browser gagal, dibaca di server', e);
    }
    btn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Memeriksa kriteria...';

    try {
        const res  = await fetch('{{ route("checker.bps.run") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({
                publications: [{
                    pub_id: publicationData.pub_id,
                    title:  publicationData.title,
                    pdf:    pdfUrl
                }],
                domain: publicationData.domain,
                extracted: extracted ? JSON.stringify(extracted) : null,
            })
        });
        const data = await res.json();
        if (data.error) { showAlert('danger', data.error); return; }
        const result = data.results?.[0];
        if (result) {
            if (result.checks?.length) {
                CheckerReview.start([{
                    filename: result.filename,
                    checks:   result.checks,
                    hasilId:  result.hasil_id,
                    summary:  result.summary,
                    url:      getProxiedPdfUrl(),
                    ocrLines: extracted?.ocr_lines,
                }], { onFinish: () => renderQualityResults(result) });
            } else {
                renderQualityResults(result);
            }
        }
    } catch(e) {
        showAlert('danger', 'Pemeriksaan gagal: ' + e.message);
    } finally {
        btn.disabled  = false;
        btn.innerHTML = ori;
    }
}

const statusBadge = CheckerReview.statusBadge;

function renderQualityResults(result) {
    document.getElementById('qualitySection').style.display = 'block';
    document.getElementById('qualitySection').scrollIntoView({ behavior: 'smooth', block: 'start' });

    const checks = result.checks || [];
    document.getElementById('statOk').textContent   = checks.filter(c => c.status === 'OK').length;
    document.getElementById('statWarn').textContent = checks.filter(c => c.status === 'PERLU DICEK').length;
    document.getElementById('statErr').textContent  = checks.filter(c => c.status === 'TIDAK ADA').length;
    document.getElementById('statSkip').textContent = checks.filter(c => c.status === 'TIDAK DIPERIKSA').length;

    document.getElementById('qualityChecks').innerHTML = checks.map(ch => `
        <tr>
            <td class="font-monospace text-muted small">${ch.id || ''}</td>
            <td class="text-muted">${ch.kategori || '-'}</td>
            <td>${ch.deskripsi || '-'}</td>
            <td>${statusBadge(ch.status)}</td>
            <td class="text-muted small">${ch.catatan || '—'}</td>
        </tr>
    `).join('');
}

// ── UTILS ─────────────────────────────────────────────────────
function showError(msg) {
    document.getElementById('loadingSection').style.display = 'none';
    document.getElementById('errorSection').style.display   = 'block';
    document.getElementById('errorMessage').textContent     = msg;
}

function showAlert(type, msg) {
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show`;
    div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.querySelector('.content-wrapper').prepend(div);
    div.scrollIntoView({ behavior: 'smooth', block: 'start' });
    // Pesan error dibiarkan sampai ditutup manual supaya tidak terlewat
    if (type !== 'danger') setTimeout(() => div.remove(), 6000);
}

// ── INIT ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadPublication);

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && isFullscreen) toggleFullscreen();
});
</script>
@endpush