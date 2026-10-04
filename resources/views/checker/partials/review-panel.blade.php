{{--
    Panel tinjau manual step-by-step (kiri: halaman PDF, kanan: kriteria).
    Dipakai bersama oleh checker.blade.php (upload) & bps-detail.blade.php (import API)
    lewat CheckerReview (public/js/checker-review.js) — satu tempat perbaikan untuk keduanya.
--}}
<div id="reviewSection" style="display:none;">
    <div class="card card-rounded mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-1">
                        <i class="mdi mdi-clipboard-check-outline text-primary me-1"></i>
                        Tinjau Manual: <span id="revFileName">-</span>
                    </h5>
                    <small class="text-muted">File <span id="revFileIdx">1</span> / <span id="revFileTotal">1</span></small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="revToggleDetail"
                            style="display:none;" onclick="CheckerReview.toggleSiblings()">
                        <i class="mdi mdi-eye-outline me-1"></i> Tampilkan Info Lain
                    </button>
                    <span class="badge bg-primary" id="revStepBadge">Kriteria 1/1</span>
                </div>
            </div>
            <div class="progress mb-3" style="height:6px;">
                <div class="progress-bar bg-info" id="revProgressBar" style="width:0%; transition:width .2s;"></div>
            </div>

            <div class="row g-3">
                {{-- LEFT: halaman yang diperiksa --}}
                <div class="col-lg-8">
                    <div class="border rounded overflow-hidden" style="height:85vh; min-height:750px; background:#525659;">
                        <iframe id="revPdfFrame" style="width:100%;height:100%;border:none;" allowfullscreen></iframe>
                    </div>
                    <small class="text-muted d-block mt-2" id="revPageHint">-</small>
                </div>

                {{-- RIGHT: apa yang harus diperiksa --}}
                <div class="col-lg-4">
                    <div class="border rounded p-3 d-flex flex-column" style="min-height:750px;">
                        <div class="mb-2 d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-light text-dark border font-monospace" id="revKode">-</span>
                            <span class="badge bg-secondary" id="revKategori">-</span>
                        </div>
                        <h6 class="fw-semibold mb-1" id="revDeskripsi">-</h6>
                        <small class="text-muted mb-3" id="revAreaHint" style="display:none;">
                            <i class="mdi mdi-crosshairs-gps me-1"></i><span></span>
                        </small>

                        <div class="alert alert-light border py-2 px-3 mb-3">
                            <small class="text-muted d-block mb-1">Hasil Cek Otomatis</small>
                            <span class="badge" id="revAutoStatus">-</span>
                            <div class="small text-muted mt-1" id="revAutoCatatan"></div>
                        </div>

                        <label class="small fw-semibold mb-2">Verifikasi Anda</label>
                        <div class="btn-group w-100 mb-3" role="group" id="revStatusButtons">
                            <button type="button" class="btn btn-outline-success btn-sm" data-status="OK">OK</button>
                            <button type="button" class="btn btn-outline-warning btn-sm" data-status="PERLU DICEK">Perlu Dicek</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-status="TIDAK ADA">Tidak Ada</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-status="TIDAK DIPERIKSA">Skip</button>
                        </div>

                        <textarea class="form-control form-control-sm mb-3" id="revCatatan" rows="3" placeholder="Catatan (opsional)"></textarea>

                        <div class="mt-auto d-flex gap-2">
                            <button class="btn btn-light border btn-sm" id="revBtnPrev" onclick="CheckerReview.prev()">
                                <i class="mdi mdi-chevron-left"></i> Sebelumnya
                            </button>
                            <button class="btn btn-primary btn-sm flex-grow-1" id="revBtnNext" onclick="CheckerReview.next()">
                                Simpan &amp; Lanjut <i class="mdi mdi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
