@extends('layouts.app')

@section('title', 'Detail Publikasi BPS')
@section('pretitle', 'Import dari API BPS')
@section('page-title', 'Detail Publikasi')
@section('page-actions')
    <a href="{{ route('checker.bps.index') }}" class="btn">
        <i class="ti ti-arrow-left"></i> Kembali ke Pencarian
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Loading State --}}
        <div id="loadingSection" class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted">Memuat detail publikasi...</p>
            </div>
        </div>

        {{-- Error State --}}
        <div id="errorSection" style="display:none;" class="card border-danger">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <i class="ti ti-alert-circle text-danger" style="font-size:32px;"></i>
                    <div>
                        <h5 class="text-danger mb-2">Gagal Memuat Publikasi</h5>
                        <p class="text-muted mb-3" id="errorMessage"></p>
                        <button class="btn btn-danger btn-sm" onclick="loadPublication()">
                            <i class="ti ti-refresh me-1"></i> Coba Lagi
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detail Content --}}
        <div id="detailSection" style="display:none;">

            {{-- Header Card --}}
            <div class="card mb-3">
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

                            {{-- penugasan pemeriksaan publikasi ini (diisi renderTugas) --}}
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-3" id="tugasBox" hidden>
                                <small class="text-muted">Pemeriksa</small>
                                <span id="tugasBadge"></span>
                                @if($pemeriksa)
                                    <select id="tugasUser" class="form-select form-select-sm w-auto" aria-label="Tugaskan ke pemeriksa">
                                        <option value="">Belum ditugaskan</option>
                                        @foreach($pemeriksa as $u)
                                            <option value="{{ $u['id'] }}">{{ $u['nama'] }}{{ $u['role'] === 'admin' ? ' (admin)' : '' }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-secondary">pilih untuk menugaskan; hanya pemeriksa itu (dan admin) yang bisa memeriksa</small>
                                @endif
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-primary" onclick="viewPDF()" id="btnViewPDF">
                                    <i class="ti ti-eye me-1"></i> Lihat PDF
                                </button>
                                <a href="#" id="btnDownloadPDF" class="btn btn-success" download>
                                    <i class="ti ti-download me-1"></i> Download PDF
                                </a>
                                <a href="#" id="btnOpenNewTab" class="btn btn-outline-primary" target="_blank">
                                    <i class="ti ti-external-link me-1"></i> Buka di Tab Baru
                                </a>
                                @can('periksa')
                                <button class="btn btn-outline-danger" onclick="checkQuality()" id="btnCheckQuality">
                                    <i class="ti ti-rosette-discount-check me-1"></i> Periksa Kualitas PDF
                                </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- PDF Viewer Card --}}
            <div class="card mb-3" id="pdfViewerCard" style="display:none;">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-file-type-pdf text-danger me-2"></i>
                        PDF Viewer
                        <small class="text-muted ms-2" id="pdfPageInfo" style="font-size:.8rem;"></small>
                    </h5>
                    <div class="d-flex gap-2 align-items-center">
                        
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleFullscreen()">
                            <i class="ti ti-maximize" id="fullscreenIcon"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="closePdfViewer()">
                            <i class="ti ti-x"></i>
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
                        <i class="ti ti-alert-circle text-warning mb-3" style="font-size:48px;"></i>
                        <h5 class="mb-2">PDF Tidak Dapat Ditampilkan</h5>
                        <p class="text-muted mb-3">Gunakan tombol di bawah untuk membuka atau mengunduh PDF.</p>
                        <a href="#" id="fallbackDownload" class="btn btn-success me-2" download>
                            <i class="ti ti-download me-1"></i> Download PDF
                        </a>
                        <a href="#" id="fallbackNewTab" class="btn btn-primary" target="_blank">
                            <i class="ti ti-external-link me-1"></i> Buka di Tab Baru
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

            {{-- Pilihan saat Periksa Kualitas PDF bila publikasi punya catatan SIPOTRET (lihat pilihPeriksa) --}}
            <div class="modal fade" id="modalPeriksa" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="ti ti-rosette-discount-check me-1"></i> Periksa Kualitas PDF</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-secondary mb-2">Publikasi ini punya riwayat pemeriksaan di SIPOTRET. Mulai dari mana?</p>
                            <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column gap-2">
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="caraPeriksa" value="lanjut" class="form-selectgroup-input" checked>
                                <div class="form-selectgroup-label d-flex align-items-start p-3 text-start">
                                    <span class="form-selectgroup-check me-3 mt-1"></span>
                                    <span>
                                        <strong>Lanjutkan dari catatan SIPOTRET</strong>
                                        <span class="d-block small text-secondary mt-1">
                                            Catatan periode acuan jadi isian awal tinjauan (Tidak Sesuai + keterangannya),
                                            tinggal diperiksa ulang. Acuan disimpan di Riwayat. Setelah tinjauan selesai,
                                            langsung lanjut ke Kirim ke SIPOTRET.
                                        </span>
                                        <span class="d-flex align-items-center flex-wrap gap-2 mt-2">
                                            <span class="small fw-semibold">Acuan catatan</span>
                                            <select id="mpAcuan" class="form-select form-select-sm w-auto"></select>
                                        </span>
                                        <span class="d-flex align-items-center flex-wrap gap-2 mt-2">
                                            <span class="small fw-semibold">Periode pemeriksaan</span>
                                            <select id="mpBulan" class="form-select form-select-sm w-auto"></select>
                                            <input type="number" id="mpTahun" class="form-control form-control-sm" style="width:90px;" min="2000" max="2100">
                                        </span>
                                    </span>
                                </div>
                            </label>
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="caraPeriksa" value="baru" class="form-selectgroup-input">
                                <div class="form-selectgroup-label d-flex align-items-start p-3 text-start">
                                    <span class="form-selectgroup-check me-3 mt-1"></span>
                                    <span>
                                        <strong>Periksa dari awal</strong>
                                        <span class="d-block small text-secondary mt-1">Tanpa isian awal, semua kriteria dinilai dari nol.</span>
                                    </span>
                                </div>
                            </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn" data-bs-dismiss="modal">Batal</button>
                            <button type="button" class="btn btn-primary" id="mpMulai">
                                <i class="ti ti-player-play me-1"></i> Mulai Periksa
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quality Check Result --}}
            <div id="qualitySection" style="display:none;">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="ti ti-rosette-discount-check text-success me-2"></i>
                            Hasil Pemeriksaan Kualitas
                        </h5>

                        <div class="row row-cards mb-3">
                            <div class="col-sm-6 col-lg-3"><x-stat label="OK"              value-id="statOk"   icon="circle-check"   color="green" /></div>
                            <div class="col-sm-6 col-lg-3"><x-stat label="Perlu Dicek"     value-id="statWarn" icon="alert-triangle" color="yellow" /></div>
                            <div class="col-sm-6 col-lg-3"><x-stat label="Masalah"         value-id="statErr"  icon="circle-x"       color="red" /></div>
                            <div class="col-sm-6 col-lg-3"><x-stat label="Tidak Diperiksa" value-id="statSkip" icon="circle-minus"   color="secondary" /></div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="80">Kode</th>
                                        <th width="150">Kategori</th>
                                        <th>Kriteria Tidak Sesuai</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody id="qualityChecks"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Riwayat pemeriksaan per bulan di SIPOTRET (diisi renderSipotret; disembunyikan bila SIPOTRET tidak diatur) --}}
            <div class="card mb-3" id="sipotretCard" style="display:none;">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-history text-primary me-1"></i> Riwayat Pemeriksaan SIPOTRET
                    </h5>
                    <div class="card-actions"><span class="text-secondary small" id="sipotretRingkas"></span></div>
                </div>
                <div class="list-group list-group-flush" id="sipotretList"></div>
            </div>

            {{-- Additional Info --}}
            <div class="card">
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

@push('scripts')
<script src="{{ asset('pdfjs/build/pdf.mjs') }}" type="module"></script>
<script src="{{ asset('js/checker-review.js') }}?v={{ filemtime(public_path('js/checker-review.js')) }}"></script>
<script src="{{ asset('js/penugasan.js') }}?v={{ filemtime(public_path('js/penugasan.js')) }}"></script>
<script src="{{ asset('js/pdf-extract.js') }}?v={{ filemtime(public_path('js/pdf-extract.js')) }}"></script>
<script>
const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
const PDFJS_VIEWER = '{{ asset("pdfjs/web/viewer.html") }}';
CheckerReview.init({ viewerUrl: PDFJS_VIEWER, csrf: CSRF, saveUrl: '{{ route("checker.hasil.review_kategori", "__ID__") }}', contohUrl: '{{ route("checker.contoh.json") }}' });
PdfExtract.init({ pdfjsBuild: '{{ asset("pdfjs/build") }}/' });

let publicationData = null;
let pdfUrl          = null;
let pdfJudul        = 'publikasi'; // nama file saat diunduh
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
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ pub_id, domain })
        });
        const data = await res.json();
        if (data.error || !res.ok) { showError(data.error || data.message || ('Server membalas ' + res.status)); return; }
        publicationData = data.publication;
        renderPublication(publicationData);
        sipotretRiwayat = Array.isArray(data.sipotret) ? data.sipotret : null;
        sipotretIsian   = data.sipotret_isian || null;
        renderSipotret(data.sipotret);
        renderTugas(data.tugas || null);
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

    pdfUrl   = pub.pdf;
    pdfJudul = pub.title || 'publikasi';
    const hasPdf = pub.has_pdf && pdfUrl;
    document.getElementById('btnDownloadPDF').href      = hasPdf ? unduhPdfUrl() : '#';
    document.getElementById('btnOpenNewTab').href       = hasPdf ? pdfUrl : '#';
    document.getElementById('btnViewPDF').disabled      = !hasPdf;
    // tombol periksa tidak dirender untuk role 'user' (hanya melihat)
    const btnCheck = document.getElementById('btnCheckQuality');
    if (btnCheck) btnCheck.disabled = !hasPdf;

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
    // origin disamakan dengan halaman: pdf.js menolak file beda origin dari viewer
    return location.origin + '{{ parse_url(route("checker.bps.pdf_proxy"), PHP_URL_PATH) }}?url=' + encodeURIComponent(pdfUrl);
}

/** Unduh lewat proxy dengan nama file = judul publikasi. */
function unduhPdfUrl() {
    return pdfUrl ? getProxiedPdfUrl() + '&nama=' + encodeURIComponent(pdfJudul) : null;
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

    const timeout = setTimeout(() => {
        overlay.style.display  = 'none';
        fallback.style.display = 'block';
        document.getElementById('fallbackDownload').href = unduhPdfUrl();
        document.getElementById('fallbackNewTab').href   = pdfUrl;
    }, 60000);

    // Viewer dimuat lewat CheckerReview.mountViewer (srcdoc) — server memblokir <iframe src> ke viewer.html
    progress.style.width = '60%';
    status.textContent   = 'Membuka PDF...';
    CheckerReview.mountViewer(frame, proxiedUrl, {
        afterOpen: win => {
            clearTimeout(timeout);
            overlay.style.display = 'none';
            wrapper.style.display = 'block';

            // Sembunyikan tombol edit/cetak/unduh di toolbar viewer
            try {
                const style = win.document.createElement('style');
                style.textContent = `
                    #editorModeButtons, #editorModeSeparator, #printButton, #downloadButton,
                    #openFileButton, #secondaryOpenFile, #secondaryPrint, #secondaryDownload {
                        display: none !important;
                    }`;
                win.document.head.appendChild(style);
            } catch (e) { /* abaikan */ }
        },
    });
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
        icon.className = 'ti ti-minimize';
        isFullscreen = true;
    } else {
        card.classList.remove('fullscreen-viewer');
        icon.className = 'ti ti-maximize';
        isFullscreen = false;
    }
}

// ── PENUGASAN PEMERIKSAAN ─────────────────────────────────────
Penugasan.init({ url: '{{ route("checker.penugasan.simpan") }}', csrf: CSRF });
let tugasPub = null;

/** Badge pemeriksa yang ditugaskan; publikasi milik pemeriksa lain tidak bisa diperiksa (tombol dikunci). */
function renderTugas(t) {
    tugasPub = t;
    const box = document.getElementById('tugasBox');
    const sel = document.getElementById('tugasUser');
    box.hidden = !t && !sel;
    document.getElementById('tugasBadge').innerHTML = t ? Penugasan.badge(t) : '<span class="text-secondary small">belum ditugaskan</span>';
    if (sel) sel.value = t ? String(t.user_id) : '';
    const btn = document.getElementById('btnCheckQuality');
    if (btn && !Penugasan.boleh(t)) {
        btn.disabled = true;
        btn.title = `Ditugaskan ke ${t.nama}. Hanya pemeriksa yang ditugaskan (dan admin) yang bisa memeriksa.`;
    }
}

document.getElementById('tugasUser')?.addEventListener('change', async e => {
    const sel = e.target;
    const nama = sel.selectedOptions[0]?.textContent.trim();
    if (!confirm(sel.value ? `Tugaskan publikasi ini ke ${nama}?` : 'Lepas penugasan publikasi ini?')) {
        sel.value = tugasPub ? String(tugasPub.user_id) : '';
        return;
    }
    try {
        const r = await Penugasan.simpan([{ pub_id: String(publicationData.pub_id), judul: publicationData.title, domain: String(publicationData.domain || '') }], sel.value || null);
        renderTugas(r.tugas[String(publicationData.pub_id)] ?? null);
        showAlert('success', r.pesan);
    } catch (err) {
        sel.value = tugasPub ? String(tugasPub.user_id) : '';
        showAlert('danger', 'Gagal menyimpan penugasan: ' + esc(err.message));
    }
});

// ── CHECK QUALITY ─────────────────────────────────────────────
let sipotretRiwayat = null; // riwayat per bulan di SIPOTRET (null = SIPOTRET tidak diatur)
let sipotretIsian   = null; // catatan periode terakhir (isianTerakhir): hanya untuk acuan bawaan di pilihan

/** Publikasi punya catatan SIPOTRET → tawarkan lanjutkan dari catatan periode acuan (dipilih) atau periksa dari awal. */
function checkQuality() {
    if (!pdfUrl || !publicationData || !Penugasan.boleh(tugasPub)) return;
    // periode SIPOTRET yang punya catatan, terbaru dulu
    const pilihan = (sipotretRiwayat || []).filter(r => r.catatan.length);
    if (!pilihan.length) { jalankanPeriksa({ lanjut: false }); return; }

    const p = sipotretIsian?.periode || pilihan[0];
    document.getElementById('mpAcuan').innerHTML = pilihan.map(r =>
        `<option value="${r.bulan}-${r.tahun}" ${r.bulan === p.bulan && r.tahun === p.tahun ? 'selected' : ''}>${BULAN[r.bulan]} ${r.tahun} (${r.catatan.length} catatan)</option>`).join('');
    // bawaan: sebulan setelah periode SIPOTRET terakhir publikasi ini (seperti Publikasi Bermasalah)
    const akhir = sipotretRiwayat?.[0] || p;
    const n = akhir.tahun * 12 + akhir.bulan; // bulan berikutnya, 0-based
    const sel = document.getElementById('mpBulan');
    sel.innerHTML = BULAN.slice(1).map((b, i) => `<option value="${i + 1}">${b}</option>`).join('');
    sel.value = String(n % 12 + 1);
    document.getElementById('mpTahun').value = Math.floor(n / 12);
    document.querySelector('input[name=caraPeriksa][value=lanjut]').checked = true;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPeriksa')).show();
}

document.getElementById('mpMulai')?.addEventListener('click', () => {
    const lanjut = document.querySelector('input[name=caraPeriksa]:checked')?.value === 'lanjut';
    const bulan  = parseInt(document.getElementById('mpBulan').value, 10);
    const tahun  = parseInt(document.getElementById('mpTahun').value, 10);
    if (lanjut && !(bulan >= 1 && bulan <= 12 && tahun >= 2000 && tahun <= 2100)) {
        document.getElementById('mpTahun').focus();
        return;
    }
    const [aBulan, aTahun] = (document.getElementById('mpAcuan').value || '').split('-').map(Number);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPeriksa')).hide();
    jalankanPeriksa({ lanjut, periode: lanjut ? { bulan, tahun } : null, acuan: lanjut && aBulan ? { bulan: aBulan, tahun: aTahun } : null });
});

/** Isian awal dari catatan SIPOTRET periode acuan (null bila gagal / tidak ada catatan). */
async function ambilIsian(acuan) {
    try {
        const res = await fetch('{{ route("checker.sipotret.isian") }}?' + new URLSearchParams({ pub_id: publicationData.pub_id, ...acuan }),
            { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || ('Server membalas ' + res.status));
        return data.isian;
    } catch (e) {
        showAlert('warning', 'Catatan SIPOTRET acuan tidak bisa dimuat (' + esc(e.message) + '), pemeriksaan dilanjutkan tanpa isian awal.');
        return null;
    }
}

/**
 * opsi.lanjut: catatan SIPOTRET terakhir jadi isian awal, lalu setelah tinjauan langsung ke
 * Kirim ke SIPOTRET pada opsi.periode ({ bulan, tahun }). Jangan tulis kurung kurawal ganda di
 * komentar ini: Blade membacanya sebagai echo PHP.
 */
async function jalankanPeriksa({ lanjut, periode = null, acuan = null }) {
    if (!pdfUrl || !publicationData) return;
    // catatan periode acuan yang dipilih (bukan otomatis periode terakhir); disimpan di hasil lewat acuan_bulan/tahun
    const isianAcuan = lanjut && acuan ? await ambilIsian(acuan) : null;

    const btn = document.getElementById('btnCheckQuality');
    const ori = btn.innerHTML;
    btn.disabled  = true;
    btn.innerHTML = '<i class="ti ti-loader-2 icon-spin me-1"></i> Memeriksa...';
    document.getElementById('qualitySection').style.display = 'none';

    // Baca teks PDF di browser (pdf.js + OCR halaman gambar) — server hosting tidak bisa
    // menjalankan Ghostscript/Tesseract. Jika gagal, server mengunduh & membaca sendiri.
    let extracted = null;
    try {
        extracted = await PdfExtract.extract(getProxiedPdfUrl(), {
            onProgress: msg => { btn.innerHTML = `<i class="ti ti-loader-2 icon-spin me-1"></i> ${msg}`; },
        });
    } catch (e) {
        console.warn('Ekstraksi teks di browser gagal, dibaca di server', e);
    }
    btn.innerHTML = '<i class="ti ti-loader-2 icon-spin me-1"></i> Memeriksa kriteria...';

    try {
        const res  = await fetch('{{ route("checker.bps.run") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({
                publications: [{
                    pub_id:  publicationData.pub_id,
                    title:   publicationData.title,
                    pdf:     pdfUrl,
                    domain:  publicationData.domain ? String(publicationData.domain) : null,
                    issn:    publicationData.issn || null,
                    catalog: publicationData.catalog || null, // cadangan cek sama_api bila detail Web API gagal diambil server
                    pub_no:  publicationData.pub_no || null,
                    rl_date: publicationData.rl_date || null,
                    acuan_bulan: isianAcuan ? acuan.bulan : null,
                    acuan_tahun: isianAcuan ? acuan.tahun : null,
                    periode_bulan: periode ? periode.bulan : null,
                    periode_tahun: periode ? periode.tahun : null,
                }],
                domain: publicationData.domain,
                extracted_b64: PdfExtract.kemas(extracted), // base64: lihat PdfExtract.kemas
            })
        });
        const data = await res.json();
        if (data.error || !res.ok) { showAlert('danger', data.error || data.message || ('Server membalas ' + res.status)); return; }
        const result = data.results?.[0];
        if (result) {
            if (isianAcuan) CheckerReview.terapkanIsian(result, isianAcuan);
            // lanjutan SIPOTRET: setelah tinjauan → Riwayat, modal Kirim ke SIPOTRET terbuka dengan periode terpilih
            const keKirim = lanjut && data.sesi_id
                ? '{{ route("checker.riwayat.detail", "__ID__") }}'.replace('__ID__', data.sesi_id)
                    + '?' + new URLSearchParams({ sipotret: 1, ...periode })
                : null;
            if (result.checks?.length) {
                CheckerReview.start([{
                    filename: result.filename,
                    checks:   result.checks,
                    hasilId:  result.hasil_id,
                    summary:  result.summary,
                    url:      getProxiedPdfUrl(),
                    ocrLines: extracted?.ocr_lines,
                    usulanCatatan: result._usulanCatatan ?? null,
                    isianInfo: result.isianInfo ?? null,
                    pubId:     publicationData.pub_id,
                    meta:      publicationData, // ISSN, revisi terakhir, dll. sudah dimuat di halaman ini
                }], { onFinish: () => keKirim ? (window.location = keKirim) : renderQualityResults(result) });
            } else if (keKirim) {
                window.location = keKirim;
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

// ── RIWAYAT SIPOTRET ──────────────────────────────────────────
// riwayat: null = SIPOTRET tidak diatur/tidak terjangkau (kartu disembunyikan), [] = belum pernah diperiksa
const BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const TAHAP_SIPOTRET = {
    bersih:   ['bg-green-lt',  'circle-check', 'Tidak ada temuan'],
    belum:    ['bg-red-lt',    'alert-circle', 'Belum diperbaiki'],
    menunggu: ['bg-yellow-lt', 'clock',        'Menunggu approve'],
    approve:  ['bg-green-lt',  'check',        'Sudah approve'],
};

function renderSipotret(riwayat) {
    const card = document.getElementById('sipotretCard');
    if (!Array.isArray(riwayat)) { card.style.display = 'none'; return; }
    card.style.display = 'block';

    const list = document.getElementById('sipotretList');
    document.getElementById('sipotretRingkas').textContent = riwayat.length ? `${riwayat.length} periode` : '';

    if (!riwayat.length) {
        list.innerHTML = `<div class="list-group-item text-secondary small">
            <i class="ti ti-info-circle me-1"></i> Publikasi ini belum pernah tercatat di Pasca Rilis SIPOTRET.</div>`;
        return;
    }

    list.innerHTML = riwayat.map((r, i) => {
        const [cls, icon, label] = TAHAP_SIPOTRET[r.tahap] || TAHAP_SIPOTRET.belum;
        const head = `
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <strong>${BULAN[r.bulan] || r.bulan} ${r.tahun}</strong>
                <span class="badge ${cls}"><i class="ti ti-${icon} me-1"></i>${label}</span>
                ${r.catatan.length ? `<span class="text-secondary small ms-auto">${r.catatan.length} catatan</span>` : ''}
            </div>`;
        if (!r.catatan.length) return `<div class="list-group-item">${head}</div>`;

        // periode terbaru langsung terbuka, sisanya bisa dibuka
        return `<details class="list-group-item" ${i === 0 ? 'open' : ''}>
            <summary class="list-unstyled" style="cursor:pointer;">${head}</summary>
            <ol class="small mt-2 mb-0 ps-3">
                ${r.catatan.map(c => `<li class="mb-1">
                    <div class="text-secondary">${c.kode ? `<span class="font-monospace">${esc(c.kode)}</span> · ` : ''}${esc(c.kategori || 'Item lainnya')}</div>
                    <div>${esc(c.catatan)}</div>
                    ${c.keterangan ? `<div class="text-danger">${esc(c.keterangan)}</div>` : ''}
                </li>`).join('')}
            </ol>
        </details>`;
    }).join('');
}

const statusBadge = CheckerReview.statusBadge;

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function renderQualityResults(result) {
    document.getElementById('qualitySection').style.display = 'block';
    document.getElementById('qualitySection').scrollIntoView({ behavior: 'smooth', block: 'start' });

    const checks = result.checks || [];
    document.getElementById('statOk').textContent   = checks.filter(c => c.status === 'OK').length;
    document.getElementById('statWarn').textContent = checks.filter(c => c.status === 'PERLU DICEK').length;
    document.getElementById('statErr').textContent  = checks.filter(c => c.status === 'TIDAK ADA' || c.status === 'TIDAK SESUAI').length;
    document.getElementById('statSkip').textContent = checks.filter(c => c.status === 'TIDAK DIPERIKSA').length;

    // Ringkasan hanya kriteria Tidak Sesuai (hasil verifikasi petugas) beserta keterangannya
    const tidakSesuai = checks.filter(c => c.status === 'TIDAK SESUAI');
    document.getElementById('qualityChecks').innerHTML = tidakSesuai.length
        ? tidakSesuai.map(ch => `
            <tr>
                <td class="font-monospace text-muted small">${esc(ch.id)}</td>
                <td class="text-muted">${esc(ch.kategori || '-')}</td>
                <td>${esc(ch.deskripsi || '-')}</td>
                <td class="text-danger">${esc(ch.keterangan || '-')}</td>
            </tr>`).join('')
        : `<tr><td colspan="4" class="text-center text-secondary py-3">Tidak ada kriteria Tidak Sesuai.</td></tr>`;
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