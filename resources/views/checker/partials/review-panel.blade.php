{{--
    Panel tinjau manual per kategori (kiri: halaman PDF, kanan: semua kriteria dalam kategori).
    Dipakai bersama oleh checker.blade.php (upload) & bps-detail.blade.php (import API)
    lewat CheckerReview (public/js/checker-review.js) — satu tempat perbaikan untuk keduanya.
--}}
<div id="reviewSection" style="display:none;"
     data-catatan-url="{{ route('checker.hasil.catatan', '__ID__') }}"
     data-catatan-hapus-url="{{ route('checker.catatan.destroy', '__ID__') }}"
     data-catatan-saran-url="{{ route('checker.catatan.saran') }}">
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-1">
                        <i class="ti ti-clipboard-check text-primary me-1"></i>
                        Tinjau Manual: <span id="revFileName">-</span>
                    </h5>
                    <small class="text-muted">File <span id="revFileIdx">1</span> / <span id="revFileTotal">1</span></small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="revToggleDetail"
                            style="display:none;" onclick="CheckerReview.toggleSiblings()">
                        <i class="ti ti-eye me-1"></i> Tampilkan Info Lain
                    </button>
                    <span class="badge bg-primary" id="revStepBadge">Kategori 1/1</span>
                </div>
            </div>
            <div class="progress mb-3" style="height:6px;">
                <div class="progress-bar bg-info" id="revProgressBar" style="width:0%; transition:width .2s;"></div>
            </div>

            {{-- Tab publikasi: muncul bila meninjau beberapa publikasi sekaligus --}}
            <ul class="nav nav-tabs review-file-tabs mb-3" id="revFileTabs" hidden></ul>

            <div class="row g-3">
                {{-- LEFT: contoh yang benar (bila dibuka) di kiri, halaman yang diperiksa di sebelahnya --}}
                <div class="col-lg-8" id="revLeftCol">
                    <div class="d-flex gap-2" style="height:85vh; min-height:750px;">
                        <div id="revCompare" class="border rounded flex-column overflow-hidden" style="display:none; flex:1 1 0; min-width:0;">
                            <div class="d-flex align-items-center gap-1 px-2 py-1 border-bottom bg-green-lt">
                                <i class="ti ti-rosette-discount-check fs-2 me-1"></i>
                                <div class="flex-fill text-truncate small" style="min-width:0;">
                                    <strong>Contoh yang benar</strong>
                                    <span id="revCompareInfo" class="text-secondary"></span>
                                </div>
                                <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon" data-cmp="-1" title="Contoh sebelumnya"><i class="ti ti-chevron-left"></i></button>
                                <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon" data-cmp="1" title="Contoh berikutnya"><i class="ti ti-chevron-right"></i></button>
                                <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon" data-cmp="zoom" title="Perbesar"><i class="ti ti-maximize"></i></button>
                                <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon" data-cmp="close" title="Tutup perbandingan"><i class="ti ti-x"></i></button>
                            </div>
                            <div id="revCompareBody" class="flex-fill d-flex align-items-center justify-content-center overflow-auto" style="min-height:0; background:#525659;"></div>
                        </div>

                        <div class="border rounded overflow-hidden" style="flex:1 1 0; min-width:0; background:#525659;">
                            <iframe id="revPdfFrame" style="width:100%;height:100%;border:none;" allowfullscreen></iframe>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2" id="revPageHint">-</small>
                </div>

                {{-- RIGHT: semua kriteria dalam satu kategori --}}
                <div class="col-lg-4" id="revRightCol">
                    <div class="border rounded p-3 d-flex flex-column" style="height:85vh; min-height:750px;">
                        {{-- Cari kriteria di semua kategori publikasi aktif → lompat ke kategori & kriterianya --}}
                        <div class="position-relative mb-2">
                            <div class="input-icon">
                                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                                <input type="search" class="form-control form-control-sm" id="revCari" autocomplete="off"
                                       placeholder="Cari kriteria di semua kategori (kode/kata)…">
                            </div>
                            <div id="revCariHasil" class="list-group shadow position-absolute w-100 overflow-auto"
                                 style="z-index:1050; max-height:55vh;" hidden></div>
                        </div>

                        <label class="small fw-semibold mb-1" for="revKategoriSelect">Kategori</label>
                        <select class="form-select form-select-sm mb-2" id="revKategoriSelect"
                                title="✓ = sudah disimpan, ○ = belum"></select>

                        {{-- Gambar contoh yang benar untuk kategori ini (diatur admin di Kelola Kriteria) --}}
                        <div id="revContoh" class="mb-2" style="display:none;"></div>

                        {{-- Filter kriteria menurut penilaian (jumlah = seluruh publikasi aktif) --}}
                        <div id="revFilter" class="d-flex flex-wrap gap-1 mb-2"></div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted" id="revItemCount">-</small>
                            {{-- Sisanya Sesuai/Skip: kategori ini saja, atau semua kategori (langsung disimpan) --}}
                            <div class="d-flex gap-3">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-success dropdown-toggle" data-bs-toggle="dropdown"
                                            title="Kriteria yang belum dinilai ditandai Sesuai; yang sudah Tidak Sesuai/Skip tidak diubah">
                                        <i class="ti ti-checks me-1"></i>Sisanya Sesuai
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <button type="button" class="dropdown-item" onclick="CheckerReview.setAll('OK')">
                                            <i class="ti ti-folder me-2"></i>Kategori ini
                                        </button>
                                        <button type="button" class="dropdown-item" onclick="CheckerReview.setAll('OK', 'semua')">
                                            <i class="ti ti-folders me-2"></i>Semua kategori <span class="text-secondary ms-1">(langsung simpan)</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary dropdown-toggle" data-bs-toggle="dropdown"
                                            title="Mis. bagian opsional yang tidak ada: kriteria yang belum dipilih petugas (termasuk pilihan otomatis) ditandai Skip; Tidak Sesuai & pilihan petugas tidak diubah">
                                        <i class="ti ti-player-skip-forward me-1"></i>Sisanya Skip
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <button type="button" class="dropdown-item" onclick="CheckerReview.setAll('TIDAK DIPERIKSA')">
                                            <i class="ti ti-folder me-2"></i>Kategori ini
                                        </button>
                                        <button type="button" class="dropdown-item" onclick="CheckerReview.setAll('TIDAK DIPERIKSA', 'semua')">
                                            <i class="ti ti-folders me-2"></i>Semua kategori <span class="text-secondary ms-1">(langsung simpan)</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="revItems" class="flex-grow-1 overflow-auto pe-1" style="min-height:0;"></div>

                        <div class="alert alert-danger py-2 small mt-2 mb-0" id="revSaveError" style="display:none;"></div>

                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <button class="btn btn-light border btn-sm" id="revBtnPrev" onclick="CheckerReview.prev()">
                                <i class="ti ti-chevron-left"></i> Sebelumnya
                            </button>
                            <button class="btn btn-primary btn-sm flex-grow-1" id="revBtnNext" onclick="CheckerReview.next()">
                                Simpan Kategori &amp; Lanjut <i class="ti ti-chevron-right"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm w-100" id="revBtnExit" onclick="CheckerReview.saveAndExit()"
                                    title="Simpan kategori ini lalu keluar; sisanya bisa dilanjutkan dari menu Riwayat">
                                <i class="ti ti-device-floppy me-1"></i> Simpan &amp; Lanjutkan Nanti
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
