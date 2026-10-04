{{--
    Panel tinjau manual per kategori (kiri: halaman PDF, kanan: semua kriteria dalam kategori).
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
                    <span class="badge bg-primary" id="revStepBadge">Kategori 1/1</span>
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

                {{-- RIGHT: semua kriteria dalam satu kategori --}}
                <div class="col-lg-4">
                    <div class="border rounded p-3 d-flex flex-column" style="height:85vh; min-height:750px;">
                        <label class="small fw-semibold mb-1" for="revKategoriSelect">Kategori</label>
                        <select class="form-select form-select-sm mb-2" id="revKategoriSelect"
                                title="✓ = sudah disimpan, ○ = belum"></select>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted" id="revItemCount">-</small>
                            <button type="button" class="btn btn-link btn-sm p-0 text-success"
                                    onclick="CheckerReview.setAll('OK')">
                                <i class="mdi mdi-check-all me-1"></i>Tandai semua OK
                            </button>
                        </div>

                        <div id="revItems" class="flex-grow-1 overflow-auto pe-1" style="min-height:0;"></div>

                        <div class="alert alert-danger py-2 small mt-2 mb-0" id="revSaveError" style="display:none;"></div>

                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <button class="btn btn-light border btn-sm" id="revBtnPrev" onclick="CheckerReview.prev()">
                                <i class="mdi mdi-chevron-left"></i> Sebelumnya
                            </button>
                            <button class="btn btn-primary btn-sm flex-grow-1" id="revBtnNext" onclick="CheckerReview.next()">
                                Simpan Kategori &amp; Lanjut <i class="mdi mdi-chevron-right"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm w-100" id="revBtnExit" onclick="CheckerReview.saveAndExit()"
                                    title="Simpan kategori ini lalu keluar; sisanya bisa dilanjutkan dari menu Riwayat">
                                <i class="mdi mdi-content-save-outline me-1"></i> Simpan &amp; Lanjutkan Nanti
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
