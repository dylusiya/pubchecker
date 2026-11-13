{{--
    Panel tinjau manual per kategori (kiri: halaman PDF, kanan: semua kriteria dalam kategori).
    Dipakai bersama oleh checker.blade.php (upload) & bps-detail.blade.php (import API)
    lewat CheckerReview (public/js/checker-review.js) — satu tempat perbaikan untuk keduanya.
--}}
<div id="reviewSection" style="display:none;"
     data-catatan-url="{{ route('checker.hasil.catatan', '__ID__') }}"
     data-catatan-hapus-url="{{ route('checker.catatan.destroy', '__ID__') }}"
     data-catatan-saran-url="{{ route('checker.catatan.saran') }}"
     data-pin-url="{{ route('checker.hasil.pin', '__ID__') }}"
     data-acuan-url="{{ route('checker.hasil.acuan_sipotret', '__ID__') }}"
     data-detail-url="{{ route('checker.bps.detail') }}"
     data-detail-show-url="{{ route('checker.bps.detail.show') }}"
     data-scan-url="{{ route('checker.hasil.scan_ulang', '__ID__') }}"
     {{-- panjang maksimal nama item catatan tambahan (kolom item pubchecker) --}}
     data-max-item="{{ \App\Models\CatatanTambahan::MAX_NAMA }}"
     {{-- kriteria bertipe manual ("Perlu diperiksa manual…"): tidak ikut daftar Konfirmasi temuan otomatis --}}
     data-kriteria-manual="{{ json_encode(\App\Models\KriteriaPemeriksaan::where('tipe_cek', 'manual')->pluck('kode')) }}">
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div style="min-width:0; flex:1 1 320px;">
                    <h5 class="card-title mb-1">
                        <i class="ti ti-clipboard-check text-primary me-1"></i>
                        Tinjau Manual: <span id="revFileName">-</span>
                    </h5>
                    <small class="text-muted d-flex align-items-center flex-wrap gap-1">
                        <span>File <span id="revFileIdx">1</span> / <span id="revFileTotal">1</span></span>
                        {{-- Data publikasi dari API BPS: ISSN & revisi terakhir (lengkapnya lewat "Info publikasi") — CheckerReview.renderMeta --}}
                        <span id="revMeta"></span>
                        {{-- buka/tutup detail publikasi lengkap (+ bagian halaman yang disembunyikan selama meninjau) --}}
                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-decoration-none" id="revToggleDetail"
                                style="display:none;" onclick="CheckerReview.toggleSiblings()">
                            <i class="ti ti-info-circle me-1"></i>Info publikasi
                        </button>
                        {{-- jalankan lagi cek otomatis dengan kriteria terbaru; penilaian petugas tidak ditimpa (CheckerReview.scanUlang) --}}
                        <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-decoration-none" id="revScanUlang" hidden
                                onclick="CheckerReview.scanUlang()"
                                title="Periksa ulang otomatis publikasi ini dengan kriteria terbaru. Kriteria yang sudah dinilai & disimpan tidak berubah.">
                            <i class="ti ti-refresh me-1"></i><span id="revScanUlangLabel">Scan ulang</span>
                        </button>
                        {{-- publikasi sudah tidak bermasalah: semua kriteria Sesuai, catatan tambahan dihapus, langsung disimpan
                             (Tugas Saya: "Sudah sesuai" untuk periode ini) — CheckerReview.semuaSesuai --}}
                        <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-success text-decoration-none" id="revSemuaSesuai" hidden
                                onclick="CheckerReview.semuaSesuai()"
                                title="Publikasi ini sudah tidak bermasalah: tandai SEMUA kriteria Sesuai, hapus catatan tambahan, lalu simpan">
                            <i class="ti ti-rosette-discount-check me-1"></i>Tandai semua sesuai
                        </button>
                    </small>
                    {{-- Acuan isian awal: catatan SIPOTRET periode berapa yang dipakai untuk kriteria yang belum ditinjau (satu baris).
                         style display: atribut hidden dikalahkan kelas d-flex (display:flex !important), jadi flex dipasang lewat CSS di bawah --}}
                    <div id="revIsianInfo" class="small align-items-center flex-wrap gap-1 mt-1" hidden></div>
                    <style>#revIsianInfo:not([hidden]) { display: flex; }</style>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    {{-- Satu menu untuk pengaturan yang jarang diganti: cara meninjau & tampilan publikasi.
                         Label tombol menunjukkan pilihan aktif (CheckerReview.applyCaraUi / applyModeUi). --}}
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"
                                data-bs-auto-close="true" aria-expanded="false" title="Ganti cara meninjau atau tampilan publikasi">
                            <i class="ti ti-adjustments-horizontal me-1"></i><span id="revTampilanLabel">Pilih kriteria bermasalah</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-1" style="min-width:320px;">
                            {{-- Cara meninjau (mode Per Publikasi) --}}
                            <div id="revCaraToggle">
                                <h6 class="dropdown-header">Cara meninjau</h6>
                                <button type="button" class="dropdown-item d-block text-wrap active" data-cara="pilih">
                                    <i class="ti ti-search me-1"></i><strong>Pilih kriteria bermasalah</strong>
                                    <div class="small opacity-75 ms-4">Cari & tandai yang tidak sesuai saja; sisanya Sesuai saat selesai.</div>
                                </button>
                                <button type="button" class="dropdown-item d-block text-wrap" data-cara="urut">
                                    <i class="ti ti-list-numbers me-1"></i><strong>Berurutan</strong>
                                    <div class="small opacity-75 ms-4">Nilai semua kriteria satu per satu, kategori demi kategori.</div>
                                </button>
                            </div>
                            {{-- Tampilan publikasi (hanya bila meninjau lebih dari satu publikasi) --}}
                            <div id="revModeToggle" hidden>
                                <div class="dropdown-divider"></div>
                                <h6 class="dropdown-header">Tampilan publikasi</h6>
                                <button type="button" class="dropdown-item d-block text-wrap active" data-mode="tab">
                                    <i class="ti ti-layout-navbar me-1"></i><strong>Per publikasi</strong>
                                    <div class="small opacity-75 ms-4">Satu publikasi per tab.</div>
                                </button>
                                <button type="button" class="dropdown-item d-block text-wrap" data-mode="jejer">
                                    <i class="ti ti-layout-columns me-1"></i><strong>Jejer semua</strong>
                                    <div class="small opacity-75 ms-4">Semua publikasi berdampingan untuk satu kriteria.</div>
                                </button>
                            </div>
                        </div>
                    </div>
                    <span class="badge bg-primary-lt" id="revStepBadge">Kategori 1/1</span>
                </div>
            </div>
            <div class="progress mb-3" style="height:6px;">
                <div class="progress-bar bg-info" id="revProgressBar" style="width:0%; transition:width .2s;"></div>
            </div>

            {{-- Detail publikasi lengkap dari API BPS (seperti halaman detail), dibuka lewat "Tampilkan Info Lain" --}}
            <div id="revInfoLain" class="border rounded p-3 mb-3 bg-light" hidden></div>

            {{-- ── Mode Jejer Semua: semua publikasi berdampingan untuk satu kriteria ── --}}
            <div id="revModeJejer" hidden>
                <div class="position-relative mb-2">
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="search" class="form-control" id="revJejerCari" autocomplete="off"
                               placeholder="Cari kriteria (kode/kategori/kata), lalu pilih…">
                    </div>
                    <div id="revJejerHasil" class="list-group shadow position-absolute w-100 overflow-auto"
                         style="z-index:1050; max-height:55vh;" hidden></div>
                </div>

                <div id="revJejerKriteria" class="border rounded p-2 mb-2 bg-light" hidden></div>
                <div id="revJejerMsg" class="alert py-2 small mb-2" style="display:none;"></div>

                {{-- Satu kolom per publikasi, digeser ke samping --}}
                <div id="revJejerStrip" class="review-jejer-strip"></div>
            </div>

            <div id="revModeTab">
            {{-- Tab publikasi: muncul bila meninjau beberapa publikasi sekaligus --}}
            <ul class="nav nav-tabs review-file-tabs mb-3" id="revFileTabs" hidden></ul>

            <div class="row g-3">
                {{-- LEFT: contoh yang benar (bila dibuka) di kiri, halaman yang diperiksa di sebelahnya --}}
                <div class="col-lg-8" id="revLeftCol">
                    {{-- tinggi = kolom kriteria di kanan dikurangi baris tombol di bawah PDF --}}
                    <div class="d-flex gap-2" style="height:calc(100vh - 190px); min-height:600px;">
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
                    <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                        <small class="text-muted" id="revPageHint">-</small>
                        {{-- Klik area di halaman PDF → catatan tambahan dengan penanda di posisi itu --}}
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-outline-warning btn-sm" id="revBtnPin" hidden
                                    title="Aktifkan, lalu klik area di halaman PDF untuk memberi komentar di posisi itu">
                                <i class="ti ti-message-plus me-1"></i> Komentar di PDF
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm active" id="revBtnIsiKomentar" hidden
                                    title="Tampilkan/sembunyikan isi komentar di samping penanda pada PDF">
                                <i class="ti ti-message-2"></i> Isi komentar
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="revBtnUnduhPdf" hidden
                                    title="Unduh PDF dengan kriteria Tidak Sesuai & catatan tambahan tertanam sebagai komentar (sticky note) di halamannya">
                                <i class="ti ti-download me-1"></i> Unduh PDF + Komentar
                            </button>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: semua kriteria dalam satu kategori --}}
                <div class="col-lg-4" id="revRightCol">
                    <div class="border rounded p-2 d-flex flex-column" style="height:calc(100vh - 150px); min-height:640px;">
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

                        {{-- Hasil cek sistem (Tidak Ada / Perlu Dicek, bukan kriteria manual) dari semua kategori:
                             dikonfirmasi petugas di satu tempat (CheckerReview.renderKonfirmasi) --}}
                        <div id="revKonfirmasi" class="mb-2"></div>

                        {{-- Daftar kriteria Tidak Sesuai publikasi ini (semua kategori); klik untuk membuka --}}
                        <div id="revMasalah" class="mb-2"></div>

                        <select class="form-select form-select-sm mb-2 rev-urut-only" id="revKategoriSelect" aria-label="Kategori"
                                title="Kategori · ✓ = sudah disimpan, ○ = belum"></select>

                        {{-- Gambar contoh yang benar untuk kategori ini (diatur admin di Kelola Kriteria) --}}
                        <div id="revContoh" class="mb-2" style="display:none;"></div>

                        {{-- Filter kriteria menurut penilaian (jumlah = seluruh publikasi aktif) --}}
                        <div id="revFilter" class="d-flex flex-wrap gap-1 mb-2 rev-urut-only"></div>

                        <div class="d-flex justify-content-between align-items-center mb-2 rev-urut-only">
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

                        {{-- Mode Pilih Kriteria Bermasalah, sebelum ada kriteria yang dibuka --}}
                        <div id="revPilihHint" class="text-center text-secondary small border rounded border-dashed p-3 mb-2" hidden>
                            <i class="ti ti-search d-block mb-1" style="font-size:28px; opacity:.5;"></i>
                            Cari kriteria yang bermasalah di kotak pencarian di atas, lalu tandai <strong>Tidak Sesuai</strong>
                            dan isi keterangannya. Kriteria yang tidak ditandai dianggap <strong>Sesuai</strong> saat klik Selesai.
                        </div>

                        <div id="revItems" class="flex-grow-1 overflow-auto pe-1" style="min-height:0;"></div>

                        <div class="alert alert-danger py-2 small mt-2 mb-0" id="revSaveError" style="display:none;"></div>

                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <button class="btn btn-light border btn-sm" id="revBtnPrev" onclick="CheckerReview.prev()">
                                <i class="ti ti-chevron-left"></i> Sebelumnya
                            </button>
                            <button class="btn btn-outline-primary btn-sm flex-grow-1" id="revBtnSimpanFokus" hidden
                                    onclick="CheckerReview.simpanFokus()" title="Simpan penilaian kriteria yang sedang dibuka">
                                <i class="ti ti-device-floppy me-1"></i> Simpan Kriteria Ini
                            </button>
                            <button class="btn btn-primary btn-sm flex-grow-1" id="revBtnNext" onclick="CheckerReview.next()">
                                Simpan Kategori &amp; Lanjut <i class="ti ti-chevron-right"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="revBtnExit" onclick="CheckerReview.saveAndExit()"
                                    title="Simpan & lanjutkan nanti: simpan yang sedang dibuka lalu keluar; sisanya bisa dilanjutkan dari menu Riwayat">
                                <i class="ti ti-device-floppy me-1"></i> Nanti
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            </div>{{-- /revModeTab --}}
        </div>
    </div>
</div>
