/**
 * CheckerReview — panel tinjau manual per kategori (split view: kiri halaman PDF, kanan daftar kriteria).
 * Dipakai bersama oleh halaman upload (checker.blade.php), detail import API (bps-detail.blade.php)
 * dan lanjutan tinjauan dari riwayat (tinjau.blade.php), lewat markup partials/review-panel.blade.php.
 *
 * Satu langkah = satu kategori: semua kriteria dalam kategori itu tampil bersamaan dan disimpan
 * sekaligus (PATCH /checker/hasil/{id}/review-kategori). Kriteria yang sudah disimpan ditandai
 * `reviewed`, sehingga tinjauan bisa dilanjutkan dari kategori pertama yang belum selesai.
 *
 * Entry shape yang diterima start():
 *   {
 *     filename: string,
 *     checks:   array (item: { id, kategori, deskripsi, status, catatan, target, area, reviewed? }),
 *     hasilId:  number|null,   // untuk PATCH ke saveUrl (route checker.hasil.review_kategori)
 *     summary:  object|null,   // referensi ke result.summary, di-update di tempat setelah tiap simpan
 *     url:      string,        // URL PDF untuk viewer (blob: lokal, proxied remote, atau /checker/hasil/{id}/pdf)
 *     cleanup:  function|null, // dipanggil saat pindah/selesai (mis. revoke blob URL)
 *     usulanCatatan: array|null, // opsional: { item, keterangan, flag_level } — usulan catatan tambahan
 *                                // (mis. item lainnya dari SIPOTRET periode lalu), dipakai lewat tombol "Pakai"
 *   }
 * Item checks boleh membawa isian awal: selStatus/selKet (belum tersimpan sampai kategori disimpan)
 * dan isianSipotret (teks asal isian, ditampilkan di kartu kriteria).
 */
const CheckerReview = (() => {
    const STATUS_CLASS = {
        'OK':              'bg-success',
        'PERLU DICEK':     'bg-warning text-dark',
        'TIDAK ADA':       'bg-danger',
        'TIDAK SESUAI':    'bg-danger',
        'TIDAK DIPERIKSA': 'bg-secondary',
        'MENUNGGU':        'bg-azure-lt', // draf: cek otomatis belum selesai
    };

    // Pilihan verifikasi petugas. Hasil cek otomatis "Perlu Dicek"/"Tidak Ada" tidak dipilihkan:
    // petugas harus menilai sendiri; "Tidak Sesuai" wajib disertai keterangan.
    const TIDAK_SESUAI   = 'TIDAK SESUAI';
    const STATUS_BUTTONS = [
        ['OK',              'Sesuai',       'success'],
        [TIDAK_SESUAI,      'Tidak Sesuai', 'danger'],
        ['TIDAK DIPERIKSA', 'Skip',         'secondary'],
    ];
    const STATUS_VERIFIKASI = STATUS_BUTTONS.map(([val]) => val);

    // Filter kriteria saat meninjau: [kunci, label]. Penilaian = pilihan di layar atau yang tersimpan.
    const FILTERS = [
        ['all',             'Semua'],
        ['belum',           'Belum dinilai'],
        ['OK',              'Sesuai'],
        [TIDAK_SESUAI,      'Tidak Sesuai'],
        ['TIDAK DIPERIKSA', 'Skip'],
        ['auto',            'Temuan otomatis'], // hasil cek sistem Perlu Dicek / Tidak Ada (bukan kriteria manual)
    ];

    const PAGE_LABEL = {
        cover: 'Halaman 1 (Kover)',
        page2: 'Halaman 2',
        front: 'Bagian awal publikasi',
        tim_penyusun: 'Halaman Tim Penyusun',
        katalog: 'Halaman Katalog',
        daftar_isi: 'Halaman Daftar Isi',
        kata_pengantar: 'Halaman Kata Pengantar',
        kata_sambutan: 'Halaman Kata Sambutan',
        abstraksi: 'Halaman Abstraksi',
        daftar_tabel: 'Halaman Daftar Tabel',
        daftar_gambar: 'Halaman Daftar Gambar',
        daftar_lampiran: 'Halaman Daftar Lampiran',
        daftar_pustaka: 'Halaman Daftar Pustaka',
        glosarium: 'Halaman Glosarium',
        indeks: 'Halaman Indeks',
        lampiran: 'Halaman Lampiran',
        last:  'Halaman terakhir (Kover Belakang)',
        all:   'Semua Halaman',
    };

    const ID = {
        section:      'reviewSection',
        fileName:     'revFileName',
        fileIdx:      'revFileIdx',
        fileTotal:    'revFileTotal',
        fileTabs:     'revFileTabs',
        stepBadge:    'revStepBadge',
        progressBar:  'revProgressBar',
        frame:        'revPdfFrame',
        pageHint:     'revPageHint',
        btnPin:       'revBtnPin',
        btnUnduhPdf:  'revBtnUnduhPdf',
        btnIsiKomentar: 'revBtnIsiKomentar',
        kategoriSel:  'revKategoriSelect',
        itemCount:    'revItemCount',
        contoh:       'revContoh',
        compare:      'revCompare',
        compareInfo:  'revCompareInfo',
        compareBody:  'revCompareBody',
        leftCol:      'revLeftCol',
        rightCol:     'revRightCol',
        items:        'revItems',
        filter:       'revFilter',
        saveError:    'revSaveError',
        btnPrev:      'revBtnPrev',
        btnNext:      'revBtnNext',
        btnExit:      'revBtnExit',
        btnSimpanFokus: 'revBtnSimpanFokus',
        caraToggle:   'revCaraToggle',
        masalah:      'revMasalah',
        konfirmasi:   'revKonfirmasi',
        isianInfo:    'revIsianInfo',
        meta:         'revMeta',
        infoLain:     'revInfoLain',
        pilihHint:    'revPilihHint',
        toggleDetail: 'revToggleDetail',
        modeToggle:   'revModeToggle',
        modeTab:      'revModeTab',
        modeJejer:    'revModeJejer',
        jejerCari:    'revJejerCari',
        jejerHasil:   'revJejerHasil',
        jejerKriteria:'revJejerKriteria',
        jejerMsg:     'revJejerMsg',
        jejerStrip:   'revJejerStrip',
    };

    const SHOW_LABEL = '<i class="ti ti-info-circle me-1"></i>Info publikasi';
    const HIDE_LABEL = '<i class="ti ti-chevron-up me-1"></i>Tutup info publikasi';

    let viewerBaseUrl = null;
    let csrfToken     = null;
    let saveUrlTpl    = null; // route('checker.hasil.review_kategori', '__ID__') — app bisa berada di subfolder
    let contohUrl     = null; // route('checker.contoh.json') — gambar contoh yang benar per kategori
    let contohMap     = {};   // { kategori: [{ url, keterangan }] }
    let contohLoaded  = null; // Promise, dimuat sekali per halaman
    let compare       = null; // contoh yang sedang dibandingkan di samping PDF: { kategori, idx }
    let compareWanted = false; // pilihan petugas (buka/tutup contoh) — bertahan walau pindah kategori/tab publikasi

    let entries         = [];
    let fileIndex       = 0;
    let groupIndex      = 0;
    let saving          = false;
    let filter          = 'all'; // filter kriteria aktif (FILTERS) — bertahan saat pindah kategori/tab
    let onFinishCb      = null;
    let hiddenSiblings  = [];
    let siblingsVisible = false;

    // Cara meninjau (mode tab): 'pilih' (bawaan) = cari & tandai yang bermasalah saja, sisanya Sesuai
    // saat Selesai; 'urut' = semua kriteria per kategori. Pilihan petugas diingat di browser ini.
    const CARA_KEY = 'pubchecker.caraTinjau';
    let cara    = (() => { try { return localStorage.getItem(CARA_KEY) === 'urut' ? 'urut' : 'pilih'; } catch { return 'pilih'; } })();
    let fokusId = null; // mode 'pilih': kode kriteria yang sedang dibuka
    // Daftar "Kriteria Tidak Sesuai" bisa dilipat. null = bawaan per cara: terbuka di mode pilih,
    // terlipat di mode urut (supaya daftar kriteria per kategori tetap lega).
    let masalahBuka = null;
    // panel Konfirmasi temuan otomatis: null = bawaan per cara meninjau (Pilih: terbuka; Berurutan: terlipat supaya
    // daftar kriteria di bawahnya tetap lega), true/false = pilihan petugas lewat klik judulnya
    let konfirmasiBuka = null;
    let kodeManualSet = null;
    let lihatCatatan = false; // mode pilih: kotak catatan tambahan dibuka dari daftar "Item lainnya"

    // Mode "Jejer Semua" (lihat bagian di bawah)
    let mode        = 'tab';      // 'tab' = satu publikasi per tab; 'jejer' = semua berdampingan per kriteria
    let jejerId     = null;       // kode kriteria yang sedang dibuka
    let jejerObs    = null;       // IntersectionObserver: viewer dimuat saat kolomnya mulai terlihat
    let jejerSaving = false;
    const jejerDraft = new Map(); // isian yang belum disimpan: `${index publikasi}|${kode}` → { ts, ket }

    function el(key) { return document.getElementById(ID[key]); }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function statusBadgeClass(s) { return STATUS_CLASS[s] || 'bg-secondary'; }
    function statusBadge(s) { return `<span class="badge ${statusBadgeClass(s)}">${s}</span>`; }

    /**
     * Sembunyikan sementara card/elemen lain yang sejajar dengan panel review
     * (header, metadata, dll) supaya panel tampil penuh & fokus tanpa scroll —
     * berlaku generik di halaman manapun tempat partial ini di-include.
     * Tetap bisa dibuka lagi sewaktu-waktu lewat toggleSiblings() tanpa keluar dari tinjauan.
     */
    function setFocusMode(on) {
        const section = el('section');
        if (!section?.parentElement) return;

        // Selama tinjauan pakai lebar penuh layar supaya PDF & contoh berdampingan tidak sempit
        const container = section.closest('.content-wrapper');
        container?.classList.toggle('container-xl', !on);
        container?.classList.toggle('container-fluid', on);

        if (on) {
            hiddenSiblings = [...section.parentElement.children]
                .filter(node => node !== section && node.tagName !== 'SCRIPT')
                .map(node => ({ node, display: node.style.display, visible: getComputedStyle(node).display !== 'none' }));
            siblingsVisible = false;
            applySiblingsVisibility();

            // tombol "Info publikasi" diatur per publikasi oleh syncToggleDetail (hanya bila ada data API BPS)
            const toggleBtn = el('toggleDetail');
            if (toggleBtn) toggleBtn.innerHTML = SHOW_LABEL;
        } else {
            hiddenSiblings.forEach(({ node, display }) => { node.style.display = display; });
            hiddenSiblings  = [];
            siblingsVisible = false;

            const toggleBtn = el('toggleDetail');
            if (toggleBtn) toggleBtn.style.display = 'none';
        }
    }

    function applySiblingsVisibility() {
        hiddenSiblings.forEach(({ node, display }) => {
            node.style.display = siblingsVisible ? display : 'none';
        });
    }

    /**
     * "Info publikasi": buka/tutup detail publikasi lengkap dari API BPS. Bagian halaman lain (mis. info sesi
     * di halaman tinjau) tetap tersembunyi selama meninjau — tidak dibutuhkan saat memeriksa.
     */
    function toggleSiblings() {
        siblingsVisible = !siblingsVisible;
        const toggleBtn = el('toggleDetail');
        if (toggleBtn) toggleBtn.innerHTML = siblingsVisible ? HIDE_LABEL : SHOW_LABEL;
        renderInfoLain();
    }

    /** Tombol "Info publikasi" tampil hanya bila publikasi punya data API BPS. */
    function syncToggleDetail() {
        const toggleBtn = el('toggleDetail');
        if (!toggleBtn) return;
        const entry = entries[fileIndex];
        const ada = !!(entry && (entry.meta || entry.pubId));
        toggleBtn.style.display = ada ? '' : 'none';
        toggleBtn.innerHTML = siblingsVisible ? HIDE_LABEL : SHOW_LABEL;
    }

    /** Detail publikasi lengkap (data API BPS) publikasi aktif — tampil selama "Info Lain" dibuka. */
    function renderInfoLain() {
        const box = el('infoLain');
        if (!box) return;
        const entry = entries[fileIndex];
        const m = entry?.meta;
        box.hidden = !siblingsVisible || !entry || (!m && !entry.pubId);
        if (box.hidden) return;
        if (!m) { box.innerHTML = '<div class="small text-secondary"><i class="ti ti-loader-2 icon-spin"></i> Memuat detail publikasi dari API BPS...</div>'; return; }
        if (!m.title && !m.issn && !m.abstract) { box.innerHTML = '<div class="small text-secondary">Detail publikasi tidak bisa dimuat dari API BPS.</div>'; return; }

        const subjek = Array.isArray(m.subject_csa) ? m.subject_csa.join(', ') : (m.subject_csa || '');
        const kolom = [
            ['ISSN / ISBN', m.issn], ['Katalog BPS', m.catalog], ['Nomor Publikasi', m.pub_no], ['Ukuran File', m.size],
            ['Tanggal Rilis', m.rl_date && tglMeta(m.rl_date)], ['Tanggal Jadwal', m.sch_date && tglMeta(m.sch_date)],
            ['Revisi Terakhir', m.updt_date && tglMeta(m.updt_date)], ['Jumlah Halaman', m.pages],
            ['Wilayah', m.domain_name || (m.domain ? 'Domain ' + m.domain : '')], ['Periode', m.periode],
            ['Bahasa', m.bahasa], ['Revisi', m.revisi === null || m.revisi === undefined ? '' : (m.revisi ? 'Ya' : 'Tidak')],
        ].filter(([, v]) => v !== null && v !== undefined && v !== '');
        const detailUrl = m.pub_id && catUrl('detailShowUrl')
            ? `${catUrl('detailShowUrl')}?${new URLSearchParams({ pub_id: m.pub_id, domain: m.domain || entry.pubDomain || '' })}` : null;

        box.innerHTML = `
            <div class="d-flex gap-3 align-items-start flex-wrap">
                ${m.cover ? `<img src="${esc(m.cover)}" alt="cover" class="rounded border flex-shrink-0" style="width:110px; max-height:155px; object-fit:contain; background:#fff;" onerror="this.remove()">` : ''}
                <div class="flex-grow-1" style="min-width:0; flex:1 1 300px;">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="fw-semibold">${esc(m.title || entry.filename)}</div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            ${detailUrl ? `<a href="${esc(detailUrl)}" target="_blank" class="btn btn-sm btn-outline-primary py-0"><i class="ti ti-external-link me-1"></i>Detail</a>` : ''}
                            ${m.pdf ? `<a href="${esc(m.pdf)}" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="ti ti-file-type-pdf me-1"></i>PDF portal</a>` : ''}
                        </div>
                    </div>
                    <div class="row g-2 small">
                        ${kolom.map(([k, v]) => `
                            <div class="col-6 col-md-4 col-xl-3">
                                <div class="text-secondary">${esc(k)}</div>
                                <div class="fw-semibold text-break">${esc(v)}</div>
                            </div>`).join('')}
                        ${subjek ? `<div class="col-12"><div class="text-secondary">Subjek</div><div class="fw-semibold">${esc(subjek)}</div></div>` : ''}
                    </div>
                    ${m.abstract ? `
                    <div class="small mt-2">
                        <div class="text-secondary">Abstrak</div>
                        <div class="text-wrap" style="white-space:pre-line; max-height:220px; overflow:auto;">${esc(m.abstract)}</div>
                    </div>` : ''}
                </div>
            </div>`;
    }

    function init({ viewerUrl, csrf, saveUrl, contohUrl: cUrl }) {
        viewerBaseUrl = asalSama(viewerUrl); // viewer & PDF harus satu origin (lihat asalSama)
        csrfToken     = csrf;
        saveUrlTpl    = saveUrl;
        contohUrl     = cUrl || null;

        // Klik thumbnail contoh → tampil di samping PDF yang sedang diperiksa
        el('fileTabs')?.addEventListener('click', e => {
            const tab = e.target.closest('[data-file]');
            if (!tab) return;
            e.preventDefault();
            switchFile(parseInt(tab.dataset.file, 10));
        });

        el('contoh')?.addEventListener('click', e => {
            // tombol Tampilkan/Sembunyikan
            if (e.target.closest('[data-contoh-toggle]')) {
                if (compare) { compareWanted = false; hideCompare(); }
                else { compareWanted = true; openCompare(kategoriAktif(), 0); }
                return;
            }
            const img = e.target.closest('[data-contoh-idx]');
            if (!img) return;
            const idx = parseInt(img.dataset.contohIdx, 10);
            // klik contoh yang sedang tampil = tutup (toggle)
            if (compare && compare.kategori === img.dataset.kategori && compare.idx === idx) {
                compareWanted = false;
                hideCompare();
            } else {
                compareWanted = true;
                openCompare(img.dataset.kategori, idx);
            }
        });

        el('compare')?.addEventListener('click', e => {
            const act = e.target.closest('[data-cmp]')?.dataset.cmp;
            if (!act || !compare) return;
            if (act === 'close') { compareWanted = false; hideCompare(); }
            else if (act === 'zoom') openContoh(compare.kategori, compare.idx);
            else openCompare(compare.kategori, compare.idx + parseInt(act, 10));
        });

        el('items')?.addEventListener('click', e => {
            if (klikCatatan(e)) return;
            if (klikPinKriteria(e)) return;
            const loc = e.target.closest('button[data-hal]');
            if (loc) { goToLokasi(parseInt(loc.dataset.hal, 10), loc.dataset.teks); return; }
            const apply = e.target.closest('button[data-apply-all]');
            if (apply) { applyToAll(apply.closest('[data-idx]'), apply); return; }
            const btn = e.target.closest('button[data-status]');
            if (!btn) return;
            const card = btn.closest('[data-idx]');
            setItemSelection(card, btn.dataset.status);
            card.dataset.dipilih = '1'; // dipilih petugas — tidak ditimpa "Tandai sisanya Skip"
            if (btn.dataset.status === TIDAK_SESUAI) card.querySelector('.rev-ket')?.focus();
            // jumlah diperbarui, tapi kartu tidak langsung disembunyikan (mis. keterangan masih diisi)
            collectGroup();
            renderFilter();
            renderMasalah();
            gambarSemuaPin(); // penanda kriteria hanya tampil selama Tidak Sesuai
        });

        el('filter')?.addEventListener('click', e => {
            const b = e.target.closest('[data-filter]');
            if (b) { filter = b.dataset.filter; applyFilter(); return; }
            const go = e.target.closest('[data-filter-goto]');
            if (go) {
                collectGroup();
                groupIndex = parseInt(go.dataset.filterGoto, 10);
                renderGroup();
            }
        });

        let tundaBalon = null;
        el('items')?.addEventListener('input', e => {
            if (!e.target.matches('.rev-ket')) return;
            e.target.classList.remove('is-invalid');
            // balon komentar di PDF ikut keterangan yang sedang diketik
            clearTimeout(tundaBalon);
            tundaBalon = setTimeout(() => { collectGroup(); gambarSemuaPin(); renderMasalah(); }, 400);
        });

        // Acuan isian awal dari SIPOTRET (tinjauan dari Riwayat): pilih/ganti periode
        el('isianInfo')?.addEventListener('change', e => {
            if (e.target.matches('[data-acuan]')) gantiAcuan(e.target.value);
        });
        el('isianInfo')?.addEventListener('click', e => {
            if (e.target.closest('[data-acuan-simpan]')) simpanSemuaAcuan();
            if (e.target.closest('[data-acuan-cek]')) cekOtomatisAcuan();
            if (e.target.closest('[data-acuan-beda]')) terapkanBedaAcuan();
        });

        // Cara meninjau: Berurutan / Pilih Kriteria Bermasalah
        el('caraToggle')?.addEventListener('click', e => {
            const b = e.target.closest('[data-cara]');
            if (b) setCara(b.dataset.cara);
        });

        // Konfirmasi temuan otomatis: pilih Sesuai/Tidak Sesuai/Skip, buka kriteria, simpan sekaligus
        el('konfirmasi')?.addEventListener('click', klikKonfirmasi);
        el('konfirmasi')?.addEventListener('input', ketikBarisNilai);
        el('masalah')?.addEventListener('input', ketikBarisNilai);

        // Daftar kriteria Tidak Sesuai → buka kriterianya
        el('masalah')?.addEventListener('click', e => {
            if (e.target.closest('[data-masalah-toggle]')) { masalahBuka = !(masalahBuka ?? cara === 'pilih'); renderMasalah(); return; }
            if (e.target.closest('[data-mas-simpan]')) { simpanMasalah(); return; }
            const lain = e.target.closest('[data-masalah-lain]');
            if (lain) { bukaMasalahLain(lain.dataset.masalahLain); return; }
            // baris kriteria: Sesuai / Tidak Sesuai / Skip, buka kriteria, halaman temuan (sama dengan Konfirmasi temuan otomatis)
            klikBarisNilai(e, 'mas');
        });

        // Cari kriteria lintas kategori
        const cari = document.getElementById('revCari');
        const cariHasil = document.getElementById('revCariHasil');
        if (cari && cariHasil) {
            cari.addEventListener('input', () => renderCari(cari.value));
            cari.addEventListener('focus', () => { if (cari.value.trim()) renderCari(cari.value); });
            cari.addEventListener('keydown', e => {
                if (e.key === 'Escape') { cari.value = ''; cariHasil.hidden = true; }
                if (e.key === 'Enter') { e.preventDefault(); cariHasil.querySelector('[data-cari]')?.click(); }
            });
            cariHasil.addEventListener('click', e => {
                const b = e.target.closest('[data-cari]');
                if (!b) return;
                const [g, n] = b.dataset.cari.split(':').map(Number);
                cariHasil.hidden = true;
                if (cara === 'pilih') cari.value = ''; // siap mencari kriteria bermasalah berikutnya
                lompatKeKriteria(g, n);
            });
            // klik di luar → tutup daftar hasil
            document.addEventListener('click', e => {
                if (!e.target.closest('#revCari, #revCariHasil')) cariHasil.hidden = true;
            });
        }

        el('btnPin')?.addEventListener('click', () => setPinMode(!pinMode));
        el('btnUnduhPdf')?.addEventListener('click', unduhPdfKomentar);
        el('btnIsiKomentar')?.addEventListener('click', () => {
            isiKomentar = !isiKomentar;
            el('btnIsiKomentar').classList.toggle('active', isiKomentar);
            try { el('frame').contentWindow.document.body.classList.toggle('pc-tanpa-isi', !isiKomentar); } catch { /* viewer belum siap */ }
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && pinMode) setPinMode(false); });

        el('kategoriSel')?.addEventListener('change', e => {
            collectGroup();
            groupIndex = parseInt(e.target.value, 10) || 0;
            renderGroup();
        });

        initJejer();
    }

    // ── Pengelompokan per kategori (urutan mengikuti urutan kriteria) ──────
    function buildGroups(checks) {
        const map = new Map();
        checks.forEach((c, i) => {
            const key = c.kategori || 'Lainnya';
            if (!map.has(key)) map.set(key, []);
            map.get(key).push(i);
        });
        return [...map.entries()].map(([kategori, idx]) => ({ kategori, idx }));
    }

    function isGroupReviewed(entry, g) {
        return g.idx.every(i => entry.checks[i].reviewed);
    }

    function firstUnreviewedGroup(entry) {
        const i = entry.groups.findIndex(g => !isGroupReviewed(entry, g));
        return i === -1 ? null : i;
    }

    /** @param {{onFinish?: Function, startIndex?: number}} opts startIndex: publikasi yang dibuka pertama */
    function start(list, { onFinish, startIndex } = {}) {
        entries = (list || []).filter(e => e.checks && e.checks.length && e.url);
        entries.forEach(e => {
            e.groups = buildGroups(e.checks);
            // hasil cek otomatis disimpan terpisah supaya tetap terlihat walau catatan diubah petugas
            e.checks.forEach(c => {
                // dari riwayat, status kriteria yang sudah ditinjau adalah hasil petugas, bukan otomatis
                c.autoLabel   ??= c.reviewed ? 'Tersimpan' : 'Otomatis';
                c.autoStatus  ??= c.status;
                c.autoCatatan ??= c.catatan;
            });
        });
        onFinishCb = onFinish || (() => {});
        resetJejer(); // tinjauan baru selalu mulai di mode tab

        if (!entries.length) { finish(); return; }

        loadContoh();

        // Buka publikasi yang diminta, atau lanjutkan dari publikasi & kategori pertama yang belum ditinjau
        fileIndex = Number.isInteger(startIndex) && entries[startIndex]
            ? startIndex
            : Math.max(0, entries.findIndex(e => firstUnreviewedGroup(e) !== null));

        setFocusMode(true);
        el('section').style.display = 'block';
        el('section').scrollIntoView({ behavior: 'smooth', block: 'start' });
        loadFile(firstUnreviewedGroup(entries[fileIndex]) ?? 0);
    }

    /**
     * Hasil cek otomatis yang datang belakangan (draf ditinjau sambil diproses, lihat PeriksaTerpilih):
     * status otomatis mengisi kriteria; pilihan & keterangan petugas (tersimpan maupun belum) tidak diubah.
     * @param {object} entry entry tinjauan (objek yang sama dengan yang diberikan ke start)
     * @param {object} r     hasil pemeriksaan dari server (checks, summary, ocr_lines)
     */
    function gabungHasil(entry, r) {
        if (!entry || !r?.checks) return;
        const per = new Map((entry.checks || []).map(c => [c.id, c]));
        r.checks.forEach(nc => {
            const c = per.get(nc.id);
            if (!c) {
                entry.checks.push({ ...nc, autoLabel: 'Otomatis', autoStatus: nc.status, autoCatatan: nc.catatan });
                return;
            }
            Object.assign(c, { catatan: nc.catatan, lokasi: nc.lokasi, target: nc.target, area: nc.area,
                autoLabel: 'Otomatis', autoStatus: nc.status, autoCatatan: nc.catatan });
            if (!c.reviewed) c.status = nc.status; // yang sudah disimpan petugas tetap penilaian petugas
        });
        entry.groups   = buildGroups(entry.checks);
        entry.ocrLines = r.ocr_lines ?? entry.ocrLines;
        entry.summary  = r.summary ?? entry.summary;
        entry.menunggu = false;
        if (!entries.includes(entry)) return;
        renderFileTabs();
        if (entry === entries[fileIndex]) {
            renderMeta(entry);
            renderGroup();
        }
    }

    function loadFile(startGroup) {
        const entry = entries[fileIndex];

        el('fileName').textContent  = entry.filename;
        el('fileIdx').textContent   = fileIndex + 1;
        el('fileTotal').textContent = entries.length;

        fokusId    = null; // publikasi lain: kriteria yang dibuka (mode pilih) dikosongkan
        lihatCatatan = false;
        groupIndex = startGroup;
        try {
            renderMeta(entry);
            syncToggleDetail();
            renderInfoLain();
            renderGroup(); // panel contoh ikut kategori aktif, tetap terbuka/tertutup sesuai pilihan petugas

            setPinMode(false);
            if (el('btnPin')) el('btnPin').hidden = !(entry.hasilId && catUrl('catatanUrl'));
            if (el('btnUnduhPdf')) el('btnUnduhPdf').hidden = !(entry.hasilId && catUrl('catatanUrl'));
            if (el('btnIsiKomentar')) el('btnIsiKomentar').hidden = !(entry.hasilId && catUrl('catatanUrl'));
            // scan ulang: hasil tersimpan & cek otomatis awalnya sudah selesai (draf yang masih diproses menunggu dulu)
            const scan = document.getElementById('revScanUlang');
            if (scan) scan.hidden = !(entry.hasilId && catUrl('scanUrl')) || !!entry.menunggu;
            const semua = document.getElementById('revSemuaSesuai');
            if (semua) semua.hidden = !entry.hasilId;
        } catch (e) {
            // data satu publikasi bermasalah jangan sampai PDF-nya ikut tidak terbuka
            console.error('Panel tinjauan gagal digambar', e);
            pesanSimpan('alert-danger', 'Sebagian panel tinjauan gagal ditampilkan (' + e.message + '). PDF tetap dibuka; muat ulang halaman, bila tetap muncul laporkan pesan ini.');
        } finally {
            bukaPdf(entry);
        }
    }

    /**
     * Tampilkan PDF publikasi di viewer. Selama proses pemeriksaan masih mengunduh PDF yang sama
     * (entry.pdfTunggu, tinjau sambil diproses), viewer menunggu unduhan itu supaya file tidak diunduh
     * dua kali bersamaan — di koneksi lemah keduanya saling berebut dan viewer tampak tidak termuat.
     */
    function bukaPdf(entry) {
        const frame = el('frame');
        if (entry.pdfTunggu && !entry.pdfTunggu.selesai) {
            frame.dataset.viewerToken = 'tunggu'; // batalkan pemuatan viewer sebelumnya
            frame.srcdoc = '<body style="margin:0;background:#525659"></body>';
            tungguPdf(entry);
            return;
        }
        tungguPdf(null);
        const hasOcr = entry.ocrLines && Object.keys(entry.ocrLines).length > 0;
        // Teks OCR dipasang sebelum PDF dibuka, supaya kait terpasang sebelum halaman pertama dirender
        // (lihat injectOcrText). URL absolut: open() me-resolve URL relatif terhadap lokasi viewer.
        mountViewer(frame, asalSama(entry.url), {
            beforeOpen: win => { renderTajam(win); if (hasOcr) injectOcrText(win, entry.ocrLines); pasangPin(win); },
            afterOpen:  ()  => { setTimeout(goToPage, 300); gambarSemuaPin(); },
        });
    }

    /** Lapisan "PDF sedang diunduh… n%" di atas viewer; entry null = sembunyikan. */
    function tungguPdf(entry) {
        const wadah = el('frame').parentElement;
        let box = document.getElementById('revPdfTunggu');
        if (!entry) { box?.remove(); return; }
        wadah.style.position = 'relative';
        if (!box) {
            box = document.createElement('div');
            box.id = 'revPdfTunggu';
            box.className = 'position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white text-center p-4';
            box.addEventListener('click', e => {
                if (!e.target.closest('[data-pdf-langsung]')) return;
                const aktif = entries[fileIndex];
                if (aktif) { aktif.pdfTunggu = null; bukaPdf(aktif); } // buka dari sumbernya walau unduhan belum selesai
            });
            wadah.appendChild(box);
        }
        const persen = Math.round((entry.pdfTunggu.persen || 0) * 100);
        const teks = persen > 0 ? `PDF sedang diunduh… ${persen}%` : 'Menunggu giliran unduh PDF…';
        // progres: cukup ganti teksnya (innerHTML ulang tiap progres membuat tombol susah diklik)
        const label = box.querySelector('.pdf-tunggu-teks');
        if (label) { label.textContent = teks; return; }
        box.innerHTML = `
            <i class="ti ti-loader-2 icon-spin mb-2" style="font-size:36px;"></i>
            <div class="fw-semibold pdf-tunggu-teks">${teks}</div>
            <div class="small opacity-75 mt-1" style="max-width:420px;">
                Memakai unduhan proses pemeriksaan supaya PDF tidak diunduh dua kali. Penilaian &amp; catatan sudah bisa diisi;
                PDF terbuka sendiri begitu selesai diunduh.
            </div>
            <button type="button" class="btn btn-sm btn-light mt-3" data-pdf-langsung>
                <i class="ti ti-external-link me-1"></i> Buka langsung tanpa menunggu
            </button>`;
    }

    /**
     * Jalankan lagi cek otomatis publikasi aktif dengan kriteria terbaru. Teks PDF yang sedang ditampilkan dibaca
     * di browser (termasuk OCR kover) lalu dikirim; gagal dibaca → server membaca sendiri. Hasil digabung seperti
     * hasil yang datang belakangan (gabungHasil): penilaian & pilihan petugas tidak berubah.
     */
    let sedangScan = false;
    async function scanUlang() {
        const entry = entries[fileIndex];
        const url = catUrl('scanUrl');
        if (!entry?.hasilId || !url || sedangScan) return;
        if (!confirm('Periksa ulang otomatis publikasi ini dengan kriteria terbaru?\n\nKriteria yang sudah dinilai & disimpan tidak berubah; yang belum dinilai mendapat hasil cek otomatis baru.')) return;
        const btn = document.getElementById('revScanUlang');
        const label = document.getElementById('revScanUlangLabel');
        const tulis = t => { if (label) label.textContent = t; };
        sedangScan = true;
        if (btn) btn.disabled = true;
        collectGroup(); // pilihan yang belum disimpan tetap ada setelah digabung
        try {
            let extracted = null, alasanBrowser = '';
            // PdfExtract dideklarasikan dengan const (bukan properti window): cek dengan typeof
            const adaPembaca = typeof PdfExtract !== 'undefined';
            if (adaPembaca && entry.url) {
                try {
                    tulis('Mengunduh PDF…');
                    const res = await fetch(asalSama(entry.url));
                    if (!res.ok) throw new Error('PDF ' + res.status);
                    const data = await res.arrayBuffer();
                    extracted = await PdfExtract.extract(data, {
                        onProgress: (msg, frac) => tulis(frac ? `Membaca PDF… ${Math.round(frac * 100)}%` : 'Membaca PDF…'),
                    });
                } catch (e) {
                    console.warn('Teks PDF gagal dibaca di browser, dibaca di server', e);
                    alasanBrowser = e.message;
                    extracted = null;
                }
            } else {
                alasanBrowser = adaPembaca ? 'PDF tidak tersedia' : 'pembaca PDF browser tidak dimuat di halaman ini';
            }
            tulis('Memeriksa kriteria…');
            const res = await fetch(url.replace('__ID__', entry.hasilId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(extracted ? { extracted_b64: PdfExtract.kemas(extracted) } : {}),
            });
            const json = await res.json().catch(() => { throw new Error(res.status === 403 ? 'Ditolak server (403)' : 'Server membalas ' + res.status); });
            if (!res.ok || json.error) {
                throw new Error((json.error || json.message || 'Server membalas ' + res.status)
                    + (alasanBrowser ? ` (pembacaan di browser gagal: ${alasanBrowser})` : ''));
            }
            const sebelum = new Map(entry.checks.map(c => [c.id, c.autoStatus]));
            gabungHasil(entry, json.result);
            const berubah = entry.checks.filter(c => !c.reviewed && sebelum.get(c.id) !== c.autoStatus).length;
            renderGroupTetap();
            pesanSimpan('alert-success', `Scan ulang selesai: hasil cek otomatis ${berubah} kriteria yang belum dinilai berubah. Penilaian yang sudah disimpan tidak diubah.`);
        } catch (e) {
            pesanSimpan('alert-danger', 'Scan ulang gagal: ' + e.message);
        } finally {
            sedangScan = false;
            if (btn) btn.disabled = false;
            tulis('Scan ulang');
        }
    }

    /**
     * Publikasi sudah tidak bermasalah: semua kriteria Sesuai (termasuk yang Tidak Sesuai, mis. dari acuan),
     * catatan tambahan dihapus & usulan item lainnya diabaikan, lalu langsung disimpan. Di Tugas Saya publikasi ini
     * jadi "Sudah sesuai" untuk periode pemeriksaannya (tidak ada yang perlu dikirim ke SIPOTRET).
     */
    async function semuaSesuai() {
        const entry = entries[fileIndex];
        if (!entry?.hasilId || saving) return;
        collectGroup();
        await loadCatatan(entry); // catatan tambahan dimuat bila belum
        const nTs  = entry.checks.filter(c => pilihanAwal(c) === TIDAK_SESUAI).length;
        const nCat = (entry.catatan || []).length;
        const nUsul = (entry.usulanCatatan || []).filter(u => usulanBelumDipakai(entry, u)).length;
        const hilang = [nTs ? `${nTs} kriteria Tidak Sesuai jadi Sesuai` : '', nCat ? `${nCat} catatan tambahan DIHAPUS` : '',
                        nUsul ? `${nUsul} usulan item lainnya dari acuan diabaikan` : ''].filter(Boolean);
        if (!confirm(`Tandai SEMUA ${entry.checks.length} kriteria "${entry.filename}" Sesuai lalu simpan?\n\n`
            + (hilang.length ? hilang.map(t => '• ' + t).join('\n') + '\n\n' : '')
            + 'Publikasi ini dianggap sudah sesuai untuk periode pemeriksaan ini (tidak ada yang dikirim ke SIPOTRET).')) return;

        saving = true;
        const btn = document.getElementById('revSemuaSesuai');
        if (btn) btn.disabled = true;
        try {
            for (const c of [...(entry.catatan || [])]) {
                const res = await fetch(catUrl('catatanHapusUrl').replace('__ID__', c.id), {
                    method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                if (!res.ok) throw new Error(`catatan "${c.item}" gagal dihapus (${res.status})`);
                entry.catatan = entry.catatan.filter(x => x.id !== c.id);
            }
            entry.checks.forEach(c => { c.selStatus = 'OK'; c.selKet = ''; c.dipilih = true; delete c.konfirmasi; });
            await simpanItems(entry, entry.checks);
            (entry.usulanCatatan || []).forEach(u => { u.dipakai = true; });
            renderGroupTetap();
            renderCatatanList();
            gambarSemuaPin();
            pesanSimpan('alert-success', `Semua ${entry.checks.length} kriteria tersimpan Sesuai${nCat ? `, ${nCat} catatan tambahan dihapus` : ''}. Publikasi ini tercatat sudah sesuai untuk periode ini.`);
        } catch (e) {
            pesanSimpan('alert-danger', 'Gagal menandai semua sesuai: ' + e.message);
        } finally {
            saving = false;
            if (btn) btn.disabled = false;
        }
    }

    /** Proses pemeriksaan selesai mengunduh PDF entry (url blob; null = gagal → buka dari sumbernya). */
    function pdfSiap(entry, url) {
        if (!entry) return;
        const menunggu = !!entry.pdfTunggu; // (penanda selesai-nya sudah diisi pemanggil)
        if (url) entry.url = url;
        entry.pdfTunggu = null;
        // hanya publikasi yang sedang tampil & masih menunggu yang dibuka ulang (yang sudah terbuka tidak diganggu)
        if (menunggu && entry === entries[fileIndex]) bukaPdf(entry);
    }

    /** Progres unduhan PDF entry yang sedang ditunggu viewer. */
    function progresPdf(entry) {
        // entry kosong (tinjauan belum dibuka) jangan sampai cocok dengan entries[fileIndex] yang juga kosong
        if (entry && entry === entries[fileIndex] && entry.pdfTunggu && !entry.pdfTunggu.selesai) tungguPdf(entry);
    }

    /**
     * Render halaman minimal 2× resolusi layar. Sel tabel yang tersusun dari kotak warna bersebelahan
     * menimbulkan garis putih tipis di sambungannya (anti-aliasing tiap kotak di pdf.js — Adobe/Chrome
     * tidak); di resolusi lebih tinggi celah itu jauh lebih tipis/samar. pdf.js membaca
     * devicePixelRatio saat merender, jadi cukup dinaikkan sebelum PDF dibuka. Batas memori kanvas
     * tetap dijaga pdf.js lewat maxCanvasPixels.
     */
    function renderTajam(win) {
        const asli = win.devicePixelRatio || 1;
        if (asli >= 2) return;
        try { Object.defineProperty(win, 'devicePixelRatio', { get: () => 2, configurable: true }); } catch { /* abaikan */ }
    }

    /**
     * Sisipkan baris hasil OCR ke lapisan teks viewer pdf.js, supaya teks di gambar (mis. judul kover JPG)
     * bisa dicari dengan Ctrl+F dan disorot. File PDF-nya sendiri tidak diubah.
     *
     * pencarian (getTextContent) dan lapisan teks (streamTextContent) sama-sama membaca
     * PDFPageProxy.streamTextContent, jadi cukup itu yang dibungkus: aliran aslinya diteruskan,
     * lalu ditambah satu bagian berisi baris OCR. Satu item per baris + hasEOL, karena pencarian
     * menyambung teks antar item tanpa spasi.
     */
    function injectOcrText(win, ocrLines) {
        const app  = win.PDFViewerApplication;
        const FONT = 'pubchecker-ocr';
        const origLoad = app.load;

        app.load = function (pdfDocument) {
            const origGetPage = pdfDocument.getPage.bind(pdfDocument);
            pdfDocument.getPage = n => origGetPage(n).then(page => {
                const lines = ocrLines[n];
                if (lines?.length && !page.__ocrInjected) {
                    page.__ocrInjected = true;
                    const origStream = page.streamTextContent.bind(page);
                    const extra = {
                        items: lines.map(l => ({
                            str: l.s, dir: 'ltr', width: l.w, height: l.h,
                            transform: [l.h, 0, 0, l.h, l.x, l.y], fontName: FONT, hasEOL: true,
                        })),
                        styles: { [FONT]: { fontFamily: 'sans-serif', ascent: 0.8, descent: -0.2, vertical: false } },
                        lang: null,
                    };
                    page.streamTextContent = params => {
                        const reader = origStream(params).getReader();
                        // ReadableStream milik iframe: lapisan teks memeriksa `instanceof ReadableStream`
                        return new win.ReadableStream({
                            async pull(ctrl) {
                                const { value, done } = await reader.read();
                                if (!done) { ctrl.enqueue(value); return; }
                                ctrl.enqueue(extra);
                                ctrl.close();
                            },
                        });
                    };
                }
                return page;
            });
            return origLoad.call(this, pdfDocument);
        };
    }

    // ── Data publikasi dari API BPS di judul panel (ISSN, rilis, revisi terakhir, ukuran, halaman) ──
    // entry.meta dari halaman pemanggil bila sudah ada; selain itu diambil sekali lewat entry.pubId (mis. dari Riwayat).
    const BULAN_META = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const tglMeta = s => {
        const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${+m[3]} ${BULAN_META[+m[2]]} ${m[1]}` : (s || '');
    };

    async function renderMeta(entry) {
        const box = el('meta');
        if (!box) return;
        if (!entry.meta && entry.pubId && catUrl('detailUrl')) {
            box.title = '';
            box.innerHTML = ' &middot; <i class="ti ti-loader-2 icon-spin"></i> memuat data publikasi...';
            try {
                const res = await fetch(catUrl('detailUrl'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ pub_id: String(entry.pubId), domain: entry.pubDomain || null }),
                });
                const data = await res.json();
                entry.meta = data.publication || {};
            } catch { entry.meta = {}; }
            if (entry !== entries[fileIndex]) return; // sudah pindah publikasi
            renderInfoLain(); // detail lengkap ikut tampil bila "Info Lain" sedang dibuka
        }
        const m = entry.meta || {};
        // yang tampil cukup ISSN & revisi terakhir (satu baris dengan "File x/y"); lengkapnya di tooltip
        const tampil = [
            m.issn      ? `ISSN ${esc(m.issn)}` : '',
            m.updt_date ? `<span class="fw-semibold text-body"><i class="ti ti-refresh"></i> revisi ${esc(tglMeta(m.updt_date))}</span>` : '',
        ].filter(Boolean);
        const lengkap = [
            m.issn      ? `ISSN/ISBN: ${m.issn}` : '',
            m.rl_date   ? `Rilis: ${tglMeta(m.rl_date)}` : '',
            m.updt_date ? `Revisi terakhir: ${tglMeta(m.updt_date)}` : '',
            m.size      ? `Ukuran: ${m.size}` : '',
            m.pages     ? `Halaman: ${m.pages}` : '',
            m.catalog   ? `Katalog: ${m.catalog}` : '',
        ].filter(Boolean);
        box.title = lengkap.join('\n');
        box.style.cursor = lengkap.length ? 'help' : '';
        box.innerHTML = (tampil.length ? '&middot; ' + tampil.join(' &middot; ') : '')
            + (entry.menunggu ? ' &middot; <span class="badge bg-azure-lt" title="PDF masih diunduh/dibaca/dicek. Penilaian & catatan sudah bisa diisi dan disimpan; hasil cek otomatis menyusul tanpa mengubah pilihan petugas."><i class="ti ti-loader-2 icon-spin"></i> cek otomatis berjalan</span>' : '');
    }

    function currentGroupItems() {
        const entry = entries[fileIndex];
        return entry.groups[groupIndex].idx.map(i => entry.checks[i]);
    }

    function goToPage() {
        const items = entries[fileIndex] ? currentGroupItems() : [];
        // mode pilih: halaman kriteria yang sedang dibuka, bukan kriteria pertama kategorinya
        const first = (cara === 'pilih' && fokusId && items.find(c => c.id === fokusId)) || items[0] || null;
        const win   = el('frame')?.contentWindow;
        if (!first || !win?.PDFViewerApplication) return;
        try {
            const app   = win.PDFViewerApplication;
            const total = app.pagesCount || 1;
            const page  = first.target === 'page2' ? 2
                        : first.target === 'last'  ? total
                        : first.target === 'cover' ? 1
                        : null; // front/all: biarkan petugas menggulir sendiri
            if (page) app.page = Math.min(page, total);
        } catch (e) { /* ignore */ }
    }

    // ── Cari kriteria di semua kategori publikasi aktif ──
    const LABEL_PILIHAN = { OK: ['Sesuai', 'bg-green-lt'], [TIDAK_SESUAI]: ['Tidak Sesuai', 'bg-red-lt'], 'TIDAK DIPERIKSA': ['Skip', 'bg-secondary-lt'] };

    /** Teks di-escape lalu kata kunci pencarian ditandai <mark>, supaya terlihat kenapa kriteria cocok. */
    function sorot(teks, kata) {
        const s = esc(teks);
        const pola = (kata || []).map(k => esc(k).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).filter(Boolean);
        return pola.length ? s.replace(new RegExp(`(${pola.join('|')})`, 'gi'), '<mark class="p-0">$1</mark>') : s;
    }

    function renderCari(q) {
        const box = document.getElementById('revCariHasil');
        const entry = entries[fileIndex];
        const kata = q.trim().toLowerCase().split(/\s+/).filter(Boolean);
        if (!box || !entry || !kata.length) { if (box) box.hidden = true; return; }

        collectGroup(); // pilihan di layar ikut terbaca
        const hasil = [];
        entry.groups.forEach((g, gi) => g.idx.forEach((ci, n) => {
            const c = entry.checks[ci];
            const teks = [c.id, c.kategori, c.deskripsi, c.autoCatatan, c.keterangan, c.selKet].join(' ').toLowerCase();
            if (kata.every(k => teks.includes(k))) hasil.push({ gi, n, c });
        }));

        box.hidden = false;
        if (!hasil.length) {
            box.innerHTML = '<div class="list-group-item small text-secondary">Tidak ada kriteria yang cocok.</div>';
            return;
        }
        box.innerHTML = hasil.slice(0, 40).map(({ gi, n, c }) => {
            const [lbl, cls] = LABEL_PILIHAN[pilihanAwal(c)] || ['Belum dinilai', 'bg-yellow-lt'];
            return `
                <button type="button" class="list-group-item list-group-item-action py-2 small" data-cari="${gi}:${n}">
                    <div class="d-flex justify-content-between gap-2">
                        <span><span class="font-monospace text-secondary">${sorot(c.id, kata)}</span> · <span class="text-secondary">${sorot(c.kategori, kata)}</span></span>
                        <span class="badge ${cls} flex-shrink-0">${lbl}</span>
                    </div>
                    <div class="text-wrap text-break">${sorot(c.deskripsi, kata)}</div>
                </button>`;
        }).join('') + (hasil.length > 40 ? `<div class="list-group-item small text-secondary">… ${hasil.length - 40} lainnya, perjelas kata kunci</div>` : '');
    }

    /** Buka kategori `g` lalu sorot kriteria ke-`n` di kategori itu. */
    function lompatKeKriteria(g, n) {
        const entry = entries[fileIndex];
        if (cara === 'pilih') {
            // hanya kriteria ini yang ditampilkan (lihat applyFilter)
            collectGroup();
            fokusId = entry.checks[entry.groups[g].idx[n]]?.id ?? null;
        }
        if (g !== groupIndex) {
            if (cara !== 'pilih') collectGroup();
            groupIndex = g;
            renderGroup();
        } else if (cara === 'pilih') {
            applyFilter();
        }
        // PDF langsung ke halaman kriteria ini (perkiraan bila tidak ada temuan berposisi)
        keHalamanKriteria(entry.checks[entry.groups[g].idx[n]]);
        let card = el('items').querySelector(`[data-idx="${n}"]`);
        if (card?.hidden) { filter = 'all'; applyFilter(); card = el('items').querySelector(`[data-idx="${n}"]`); }
        if (!card) return;
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.classList.add('border-primary');
        card.style.boxShadow = '0 0 0 3px rgba(var(--tblr-primary-rgb), .35)';
        setTimeout(() => { card.style.boxShadow = ''; card.classList.remove('border-primary'); }, 2000);
    }

    // ── Filter kriteria (Belum dinilai / Sesuai / Tidak Sesuai / Skip / hasil otomatis) ──
    function cocokFilter(c, f = filter) {
        if (f === 'all')   return true;
        if (f === 'belum') return pilihanAwal(c) === null;
        if (f === 'auto')  return temuanSistem(c);
        return pilihanAwal(c) === f;
    }

    /** Kategori berikutnya (berputar) di publikasi aktif yang punya kriteria cocok dengan filter. */
    function nextFilterGroup() {
        const entry = entries[fileIndex];
        for (let k = 1; k < entry.groups.length; k++) {
            const j = (groupIndex + k) % entry.groups.length;
            if (entry.groups[j].idx.some(i => cocokFilter(entry.checks[i]))) return j;
        }
        return null;
    }

    /** Tombol filter + jumlahnya (seluruh publikasi aktif), dan pesan bila kategori ini kosong. */
    function renderFilter() {
        const box = el('filter');
        const entry = entries[fileIndex];
        if (!box || !entry) return;
        const visible = el('items').querySelectorAll('[data-idx]:not([hidden])').length;
        const goto = filter !== 'all' && !visible ? nextFilterGroup() : null;
        const label = FILTERS.find(([k]) => k === filter)?.[1] ?? '';

        box.innerHTML = FILTERS.map(([key, lbl]) => {
            const n = entry.checks.filter(c => cocokFilter(c, key)).length;
            if (key !== 'all' && key !== filter && !n) return '';
            return `<button type="button" class="btn btn-sm py-0 px-2 ${key === filter ? 'btn-primary' : 'btn-outline-secondary'}"
                            data-filter="${key}">${esc(lbl)} <span class="badge ${key === filter ? 'bg-white text-primary' : 'bg-secondary-lt'} ms-1">${n}</span></button>`;
        }).join('') + (filter !== 'all' && !visible ? `
            <div class="w-100 small text-secondary mt-1">
                Tidak ada kriteria "${esc(label)}" di kategori ini.
                ${goto !== null
                    ? `<a href="#" data-filter-goto="${goto}" onclick="event.preventDefault()">Ke ${esc(entry.groups[goto].kategori)} <i class="ti ti-arrow-right"></i></a>`
                    : 'Kategori lain juga tidak ada.'}
            </div>` : '');
    }

    /** Sembunyikan kriteria di kategori aktif yang tidak cocok dengan filter. */
    function applyFilter() {
        if (!entries[fileIndex]) return;
        collectGroup();
        const items = currentGroupItems();
        const pilih = cara === 'pilih';
        let adaFokus = false;
        el('items').querySelectorAll('[data-idx]').forEach(card => {
            const c = items[parseInt(card.dataset.idx, 10)];
            // mode pilih: hanya kriteria yang dibuka dari pencarian/daftar Tidak Sesuai
            if (pilih) { card.hidden = !c || c.id !== fokusId; adaFokus ||= !card.hidden; }
            else card.hidden = !!c && !cocokFilter(c);
        });
        if (adaFokus) lihatCatatan = false;
        if (el('pilihHint')) el('pilihHint').hidden = !pilih || adaFokus || lihatCatatan;
        const catBox = document.getElementById('revCatatanBox');
        // catatan tambahan ikut kategori kriteria yang dibuka, atau dibuka dari daftar "Item lainnya"
        if (catBox) catBox.hidden = pilih && !adaFokus && !lihatCatatan;
        if (el('btnSimpanFokus')) el('btnSimpanFokus').disabled = pilih && !adaFokus;
        renderFilter();
        renderKategoriSelect(entries[fileIndex]);
    }

    function renderKategoriSelect(entry) {
        const sel = el('kategoriSel');
        if (!sel) return;
        sel.innerHTML = entry.groups.map((g, i) => {
            const done = isGroupReviewed(entry, g);
            // dengan filter aktif: jumlah kriteria yang cocok di kategori itu
            const cocok = filter === 'all' ? null : g.idx.filter(j => cocokFilter(entry.checks[j])).length;
            return `<option value="${i}" ${i === groupIndex ? 'selected' : ''}>${done ? '✓' : '○'} ${esc(g.kategori)} (${g.idx.length})${cocok ? ` · ${cocok} cocok` : ''}</option>`;
        }).join('');
    }

    /** @param {{tanpaPdf?: boolean}} [opsi] tanpaPdf: gambar ulang saja, PDF tidak dipindah ke halaman kategori */
    function renderGroup({ tanpaPdf = false } = {}) {
        const entry  = entries[fileIndex];
        const groups = entry.groups;
        const items  = currentGroupItems();
        const doneGroups = groups.filter(g => isGroupReviewed(entry, g)).length;

        el('stepBadge').textContent   = `Kategori ${groupIndex + 1}/${groups.length} · ${doneGroups} selesai`;
        el('progressBar').style.width = Math.round((doneGroups / groups.length) * 100) + '%';
        el('itemCount').textContent   = `${items.length} kriteria`;
        el('pageHint').textContent    = 'Menampilkan: ' + (PAGE_LABEL[items[0]?.target] ?? PAGE_LABEL.all);
        el('saveError').style.display = 'none';
        renderKategoriSelect(entry);
        renderFileTabs();
        renderContoh(groups[groupIndex].kategori);

        el('items').innerHTML = items.map((c, n) => {
            const sel = pilihanAwal(c);
            const ket = c.selKet ?? c.keterangan ?? '';
            return `
            <div class="border rounded p-2 mb-2 ${c.reviewed ? 'border-success' : ''}" data-idx="${n}" ${c.reviewed || c.dipilih ? 'data-dipilih="1"' : ''}>
                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                    <span class="badge bg-light text-dark border font-monospace">${esc(c.id)}</span>
                    <span class="small text-muted">
                        ${c.reviewed ? '<i class="ti ti-circle-check text-success" title="Sudah ditinjau"></i>' : ''}
                        ${c.autoLabel}: <span class="badge ${statusBadgeClass(c.autoStatus)}">${esc(c.autoStatus)}</span>
                    </span>
                </div>
                <div class="small fw-semibold mb-1">${esc(c.deskripsi)}</div>
                ${c.autoCatatan ? `<div class="small text-secondary mb-1"><i class="ti ti-robot me-1"></i>${esc(c.autoCatatan)}</div>` : ''}
                ${c.isianSipotret ? `<div class="small text-danger mb-1"><i class="ti ti-history me-1"></i>${esc(c.isianSipotret)}</div>` : ''}
                ${c.isianSipotret && !c.reviewed ? cekOtomatisHtml(c) : ''}
                ${!c.isianSipotret && bedaAcuan(c) ? `<div class="small text-warning mb-1"><i class="ti ti-alert-triangle me-1"></i>${esc(c.acuanAsal)} mencatat tidak sesuai${c.acuanKet ? `: ${esc(c.acuanKet)}` : ''}, tapi tersimpan ${esc(LABEL_PILIHAN[c.status]?.[0] ?? c.status)}.</div>` : ''}
                ${lokasiButtons(c.lokasi)}
                ${c.area ? `<div class="small text-muted mb-1"><i class="ti ti-current-location me-1"></i>Fokus area: ${esc(c.area)}</div>` : ''}
                <div class="btn-group btn-group-sm w-100 mb-1" role="group">
                    ${STATUS_BUTTONS.map(([val, label, color]) =>
                        `<button type="button" class="btn btn-outline-${color} ${val === sel ? 'active' : ''}" data-status="${val}">${label}</button>`
                    ).join('')}
                </div>
                <div class="rev-ket-wrap" ${sel === TIDAK_SESUAI ? '' : 'hidden'}>
                    <textarea class="form-control form-control-sm rev-ket" rows="2"
                              placeholder="Jelaskan apa yang tidak sesuai (wajib)">${esc(ket)}</textarea>
                    <div class="invalid-feedback">Keterangan wajib diisi untuk kriteria yang Tidak Sesuai.</div>
                    ${entry.hasilId ? `
                    <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                        <button type="button" class="btn btn-sm btn-outline-warning py-0" data-pin-kriteria
                                title="Klik tombol ini, lalu klik area yang tidak sesuai di halaman PDF">
                            <i class="ti ti-map-pin"></i> Tandai di PDF
                        </button>
                        <small class="rev-pin-info text-secondary">${pinInfoHtml(c)}</small>
                    </div>` : ''}
                    ${entries.length > 1 ? `
                    <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                        <button type="button" class="btn btn-sm btn-outline-danger py-0" data-apply-all
                                title="Tandai kriteria ini Tidak Sesuai dengan keterangan yang sama di semua publikasi yang sedang ditinjau, lalu langsung simpan">
                            <i class="ti ti-copy"></i> Salin &amp; simpan ke semua publikasi
                        </button>
                        <small class="rev-apply-msg text-secondary"></small>
                    </div>` : ''}
                </div>
            </div>`;
        }).join('');
        renderCatatanBox(); // catatan tambahan kategori ini, di bawah daftar kriteria
        el('items').scrollTop = 0;
        applyFilter();

        el('btnPrev').disabled = (fileIndex === 0 && groupIndex === 0);
        // Terakhir = kategori terakhir publikasi ini dan tidak ada publikasi lain yang belum selesai
        const isLast = (groupIndex === groups.length - 1) && nextUnfinishedFile() === null;
        el('btnNext').innerHTML = cara === 'pilih'
            ? 'Selesai: sisanya Sesuai <i class="ti ti-check"></i>'
            : isLast
                ? 'Simpan &amp; Selesai <i class="ti ti-check"></i>'
                : 'Simpan Kategori &amp; Lanjut <i class="ti ti-chevron-right"></i>';
        applyCaraUi();
        renderIsianInfo();
        renderMasalah();

        if (!tanpaPdf) goToPage();
    }

    // ── Cara meninjau: Berurutan / Pilih Kriteria Bermasalah ──────────────
    function setCara(c) {
        if ((c !== 'urut' && c !== 'pilih') || c === cara) return;
        collectGroup(); // pilihan di layar tetap diingat
        cara = c;
        try { localStorage.setItem(CARA_KEY, c); } catch { /* penyimpanan browser tidak tersedia */ }
        fokusId = null;
        konfirmasiBuka = null; // panel Konfirmasi temuan otomatis kembali ke bawaan cara ini (Berurutan: terlipat)
        if (entries[fileIndex]) renderGroup(); else applyCaraUi();
        if (c === 'pilih') document.getElementById('revCari')?.focus();
    }

    function applyCaraUi() {
        const pilih = cara === 'pilih';
        el('caraToggle')?.querySelectorAll('[data-cara]').forEach(b => b.classList.toggle('active', b.dataset.cara === cara));
        el('section')?.querySelectorAll('.rev-urut-only').forEach(n => { n.hidden = pilih; n.classList.toggle('d-none', pilih); });
        if (el('btnPrev')) el('btnPrev').hidden = pilih;
        if (el('btnSimpanFokus')) el('btnSimpanFokus').hidden = !pilih;
        labelTampilan();
        const cari = document.getElementById('revCari');
        if (cari) cari.placeholder = pilih
            ? 'Cari kriteria yang bermasalah (kode/kata), lalu pilih…'
            : 'Cari kriteria di semua kategori (kode/kata)…';
    }

    /** Tidak Sesuai yang sudah tersimpan dan tidak diubah di layar. */
    function tsTersimpan(c) {
        if (!(c.reviewed && c.status === TIDAK_SESUAI)) return false;
        return c.selStatus === undefined || (c.selStatus === TIDAK_SESUAI && (c.selKet ?? '') === (c.keterangan ?? ''));
    }

    /** Pilihan di layar sama dengan yang tersimpan di server (Tidak Sesuai: keterangannya juga sama). */
    function nilaiTersimpan(c) {
        return pilihanAwal(c) === TIDAK_SESUAI ? tsTersimpan(c)
            : !!c.reviewed && (c.selStatus === undefined || c.selStatus === c.status);
    }

    /** Kode kriteria bertipe manual (data-kriteria-manual di review-panel). */
    function kodeManual() {
        if (!kodeManualSet) {
            try { kodeManualSet = new Set(JSON.parse(catUrl('kriteriaManual') || '[]')); } catch { kodeManualSet = new Set(); }
        }
        return kodeManualSet;
    }

    /** Temuan cek sistem yang perlu dikonfirmasi petugas: Tidak Ada / Perlu Dicek dari kriteria yang benar-benar dicek otomatis. */
    function temuanSistem(c) {
        return c.autoLabel === 'Otomatis' && (c.autoStatus === 'TIDAK ADA' || c.autoStatus === 'PERLU DICEK') && !kodeManual().has(c.id);
    }

    /**
     * Satu daftar temuan cek sistem dari semua kategori untuk dikonfirmasi: Sesuai / Tidak Sesuai (keterangan diisi
     * dari catatan cek otomatis, bisa diubah) / Skip, lalu disimpan sekaligus. Yang sudah disimpan tidak tampil lagi.
     */
    const PILIHAN_NILAI = [['OK', 'Sesuai', 'success'], [TIDAK_SESUAI, 'Tidak Sesuai', 'danger'], ['TIDAK DIPERIKSA', 'Skip', 'secondary']];

    /** Jumlah halaman PDF yang sedang tampil (0 bila viewer belum siap). */
    function jumlahHalamanPdf() {
        try { return el('frame').contentWindow.PDFViewerApplication.pagesCount || 0; } catch { return 0; }
    }

    /**
     * Satu baris penilaian kriteria (dipakai Konfirmasi temuan otomatis & Kriteria Tidak Sesuai): kode · kategori
     * (klik → buka kriteria), nama, keterangan asal, tombol halaman temuan, Sesuai/Tidak Sesuai/Skip & keterangan.
     * @param {object} c kriteria
     * @param {{pil: ?string, asal?: string, badge?: string, aktif?: boolean, total: number}} o
     */
    function htmlBarisNilai(c, o) {
        const lokasi = c.lokasi?.length ? c.lokasi : (h => h ? [{ hal: h.hal, teks: h.teks, perkiraan: !h.teks }] : [])(halamanDari(c, o.total));
        return `
                    <div class="list-group-item py-1 px-2 small ${o.aktif ? 'bg-primary-lt' : ''}" data-nilai="${esc(c.id)}">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <a href="#" class="text-truncate text-reset" data-nilai-buka title="Buka kriteria ini & halamannya di PDF">
                                <span class="font-monospace">${esc(c.id)}</span> · <span class="text-secondary">${esc(c.kategori)}</span>
                            </a>
                            <span class="d-flex gap-1 flex-shrink-0">${o.badge || ''}</span>
                        </div>
                        ${(([nama, ...jelas]) => `<div class="text-wrap text-break fw-semibold">${esc(nama)}</div>`
                            + (jelas.length ? `<div class="text-wrap text-break text-secondary">${esc(jelas.join(' — '))}</div>` : ''))(String(c.deskripsi).split(' — '))}
                        ${o.asal ? `<div class="text-secondary text-wrap text-break">${o.asal}</div>` : ''}
                        <div class="mt-1">${lokasiButtons(lokasi)}</div>
                        <div class="btn-group btn-group-sm w-100" role="group">
                            ${PILIHAN_NILAI.map(([v, l, w]) => `<button type="button" class="btn btn-outline-${w} py-0 ${o.pil === v ? 'active' : ''}" data-nilai-pil="${v}">${l}</button>`).join('')}
                        </div>
                        ${o.pil === TIDAK_SESUAI ? `<textarea class="form-control form-control-sm mt-1" rows="2" data-nilai-ket
                            placeholder="Keterangan (wajib)">${esc(c.selKet ?? c.keterangan ?? '')}</textarea>` : ''}
                    </div>`;
    }

    /** Gambar ulang isi kotak daftar dengan posisi scroll daftarnya dipertahankan (digambar ulang tiap klik). */
    function isiDaftar(box, html) {
        const lama = box.querySelector('[data-nilai-list]')?.scrollTop ?? 0;
        box.innerHTML = html;
        const baru = box.querySelector('[data-nilai-list]');
        if (baru) baru.scrollTop = lama;
    }

    function renderKonfirmasi() {
        const box = el('konfirmasi');
        const entry = entries[fileIndex];
        if (!box || !entry) return;
        // yang sudah dikonfirmasi/disimpan tetap tampil (pilihan tersimpannya aktif) supaya bisa dicek & diubah lagi
        const semua = entry.checks.filter(temuanSistem);
        if (!semua.length) { box.innerHTML = ''; return; }
        const belum = semua.filter(c => !c.reviewed);
        const dipilih = semua.filter(c => c.konfirmasi && pilihanAwal(c) && !nilaiTersimpan(c));
        const buka = konfirmasiBuka ?? cara === 'pilih';
        const selesai = semua.length - belum.length;
        const total = jumlahHalamanPdf();
        isiDaftar(box, `
            <div class="d-flex align-items-center gap-1 flex-wrap ${buka ? 'mb-1' : ''}">
                <button type="button" class="btn btn-link btn-sm p-0 small fw-semibold text-orange text-decoration-none" data-konf-toggle
                        title="Hasil cek sistem (Tidak Ada / Perlu Dicek) dari semua kategori, tanpa kriteria yang memang diperiksa manual">
                    <i class="ti ti-chevron-${buka ? 'down' : 'right'} me-1"></i><i class="ti ti-robot me-1"></i>Konfirmasi temuan otomatis
                    ${belum.length ? `<span class="badge bg-orange-lt ms-1">${belum.length} belum</span>` : '<span class="badge bg-green-lt ms-1"><i class="ti ti-check"></i> semua dikonfirmasi</span>'}
                    ${selesai ? `<span class="badge bg-green-lt ms-1">${selesai} sudah</span>` : ''}
                </button>
                ${dipilih.length ? `<button type="button" class="btn btn-sm btn-primary py-0 ms-auto" data-konf-simpan>
                    <i class="ti ti-device-floppy me-1"></i>Simpan ${dipilih.length}</button>` : ''}
            </div>
            <div class="list-group list-group-flush border rounded overflow-auto" style="max-height:${cara === 'pilih' ? '32vh' : '18vh'};" data-nilai-list ${buka ? '' : 'hidden'}>
                ${semua.map(c => htmlBarisNilai(c, {
                    // belum disimpan: pilihan hanya tampil bila diklik di panel ini (bukan pilihan otomatis/acuan)
                    pil: c.reviewed || c.konfirmasi ? pilihanAwal(c) : null, total,
                    badge: `<span class="badge ${statusBadgeClass(c.autoStatus)}">${esc(c.autoStatus)}</span>`
                        + (c.reviewed && nilaiTersimpan(c) ? '<span class="badge bg-green-lt" title="Sudah disimpan"><i class="ti ti-check"></i></span>'
                            : c.konfirmasi ? '<span class="badge bg-yellow-lt">belum disimpan</span>' : ''),
                    asal: c.autoCatatan ? `<i class="ti ti-robot me-1"></i>${esc(c.autoCatatan)}` : '',
                })).join('')}
            </div>`);
    }

    /** Gambar ulang kategori aktif tanpa membuat daftar kriteria di kanan lompat ke atas & tanpa memindah halaman PDF. */
    function renderGroupTetap() {
        const items = el('items');
        const atas = items?.scrollTop ?? 0;
        renderGroup({ tanpaPdf: true });
        if (items) items.scrollTop = atas;
    }

    /** Catatan cek otomatis → keterangan Tidak Sesuai: tanpa penanda internal (mis. "[teks hasil OCR, cek ulang]"). */
    function ketDariOtomatis(catatan) {
        return String(catatan || '').replace(/\s*\[teks hasil OCR[^\]]*\]/g, '').trim();
    }

    /** Klik di panel Konfirmasi temuan otomatis. */
    function klikKonfirmasi(e) {
        if (e.target.closest('[data-konf-toggle]')) { konfirmasiBuka = !(konfirmasiBuka ?? cara === 'pilih'); renderKonfirmasi(); return; }
        if (e.target.closest('[data-konf-simpan]')) { simpanKonfirmasi(); return; }
        klikBarisNilai(e, 'konf');
    }

    /**
     * Klik di baris penilaian (htmlBarisNilai). jenis 'konf' = Konfirmasi temuan otomatis (keterangan Tidak Sesuai awal
     * dari catatan cek otomatis); 'mas' = Kriteria Tidak Sesuai (keterangan awal dari acuan / yang tersimpan).
     * @returns true bila ditangani
     */
    function klikBarisNilai(e, jenis) {
        const entry = entries[fileIndex];
        const row = e.target.closest('[data-nilai]');
        const c = row && entry?.checks.find(x => x.id === row.dataset.nilai);
        if (!c) return false;
        // tombol halaman temuan → PDF ke halaman itu (& sorot teksnya), daftar tidak digambar ulang
        const loc = e.target.closest('[data-hal]');
        if (loc) { goToLokasi(parseInt(loc.dataset.hal, 10), loc.dataset.teks); return true; }
        if (e.target.closest('[data-nilai-buka]')) {
            e.preventDefault();
            const gi = entry.groups.findIndex(g => g.idx.some(i => entry.checks[i].id === c.id));
            if (gi >= 0) lompatKeKriteria(gi, entry.groups[gi].idx.findIndex(i => entry.checks[i].id === c.id));
            return true;
        }
        const b = e.target.closest('[data-nilai-pil]');
        if (!b) return false;
        collectGroup(); // pilihan yang sedang diisi di kartu kriteria tidak hilang
        c.selStatus = b.dataset.nilaiPil;
        c.dipilih = true;
        if (jenis === 'konf') c.konfirmasi = true;
        else c.diMasalah = true; // tetap tampil di daftar Tidak Sesuai walau diubah jadi Sesuai (bisa dikoreksi lagi)
        // Tidak Sesuai: keterangan awal (bisa diubah)
        if (c.selStatus === TIDAK_SESUAI && !(c.selKet ?? '').trim()) {
            c.selKet = jenis === 'konf' ? ketDariOtomatis(c.autoCatatan) : (c.keterangan || c.acuanKet || '');
        }
        renderGroupTetap(); // kartu kriteria & kedua daftar ikut pilihan baru, posisi scroll & halaman PDF tetap
        if (c.selStatus === TIDAK_SESUAI) {
            const box = el(jenis === 'konf' ? 'konfirmasi' : 'masalah');
            box?.querySelector(`[data-nilai="${CSS.escape(c.id)}"] [data-nilai-ket]`)?.focus({ preventScroll: true });
        }
        return true;
    }

    /** Keterangan diketik di baris penilaian: simpan ke kriteria & samakan kartu kriteria yang sedang terbuka. */
    function ketikBarisNilai(e) {
        const t = e.target.closest('[data-nilai-ket]');
        const c = t && entries[fileIndex]?.checks.find(x => x.id === t.closest('[data-nilai]').dataset.nilai);
        if (!c) return;
        c.selKet = t.value; // tanpa render ulang supaya kursor tidak hilang
        // collectGroup membaca kartu kriteria: kartu yang sama di kategori yang sedang terbuka ikut disamakan
        const n = currentGroupItems().indexOf(c);
        const kartu = n >= 0 ? el('items').querySelector(`[data-idx="${n}"] .rev-ket`) : null;
        if (kartu) kartu.value = t.value;
    }

    /** Simpan semua pilihan di panel Konfirmasi temuan otomatis sekaligus. */
    async function simpanKonfirmasi() {
        const entry = entries[fileIndex];
        collectGroup();
        const items = entry.checks.filter(c => temuanSistem(c) && c.konfirmasi && pilihanAwal(c) && !nilaiTersimpan(c));
        items.forEach(c => { if (c.selStatus === undefined) c.selStatus = pilihanAwal(c); });
        if (!items.length) return;
        const kosong = items.filter(c => c.selStatus === TIDAK_SESUAI && !(c.selKet ?? c.keterangan ?? '').trim());
        if (kosong.length) {
            pesanSimpan('alert-warning', `Keterangan wajib diisi untuk Tidak Sesuai: ${kosong.map(c => c.id).join(', ')}.`);
            return;
        }
        items.forEach(c => { if (c.selStatus === TIDAK_SESUAI) c.selKet = (c.selKet ?? c.keterangan ?? '').trim(); });
        try {
            await simpanItems(entry, items);
            items.forEach(c => { delete c.konfirmasi; });
            renderGroupTetap();
            renderFileTabs();
            pesanSimpan('alert-success', `${items.length} temuan otomatis dikonfirmasi & disimpan.`);
        } catch (err) {
            pesanSimpan('alert-danger', 'Gagal menyimpan konfirmasi: ' + err.message);
        }
    }

    function renderMasalah() {
        renderKonfirmasi();
        const box = el('masalah');
        const entry = entries[fileIndex];
        if (!box || !entry) return;
        const pilih = cara === 'pilih';
        // Tidak Sesuai, ditambah kriteria dari acuan SIPOTRET yang sekarang dinilai Sesuai/Skip (mis. lolos cek otomatis
        // atau diubah petugas) supaya tetap bisa di-crosscheck; urut seperti urutan kriteria
        const dariAcuan = c => !!(c.isianSipotret || c.bedaAcuan);
        const list = entry.checks.filter(c => pilihanAwal(c) === TIDAK_SESUAI || dariAcuan(c) || c.diMasalah);
        const nTs = list.filter(c => pilihanAwal(c) === TIDAK_SESUAI).length;
        const nLolos = list.length - nTs;
        // temuan di luar daftar kriteria (mis. "Halaman kontributor" dari SIPOTRET): catatan tambahan
        // yang tersimpan & usulan item lainnya dari acuan yang belum disimpan
        const lainTersimpan = entry.catatan || [];
        const lainUsulan = (entry.usulanCatatan || []).map((u, i) => [u, i]).filter(([u]) => usulanBelumDipakai(entry, u));
        const nLain = lainTersimpan.length + lainUsulan.length;

        if (!list.length && !nLain) {
            box.innerHTML = pilih
                ? '<div class="small text-secondary"><i class="ti ti-circle-check text-success me-1"></i>Belum ada kriteria yang ditandai Tidak Sesuai.</div>'
                : '';
            return;
        }
        const buka = masalahBuka ?? pilih;
        // tersimpan = pilihan di layar sama dengan yang tersimpan
        const tersimpan = nilaiTersimpan;
        const perluSimpan = list.filter(c => !tersimpan(c) && pilihanAwal(c));
        const belumSimpan = list.filter(c => !tersimpan(c)).length + lainUsulan.length;
        const htmlLain = nLain ? `
                <div class="list-group-item py-1 px-2 small fw-semibold bg-light">Item lainnya (di luar daftar kriteria) · ${nLain}</div>
                ${lainTersimpan.map(c => `
                <button type="button" class="list-group-item list-group-item-action py-1 px-2 small" data-masalah-lain="c:${c.id}"
                        title="Buka catatan tambahan ini">
                    <div class="d-flex justify-content-between gap-2">
                        <span class="text-truncate fw-semibold">${esc(c.item)}</span>
                        <span class="d-flex gap-1 flex-shrink-0">
                            ${c.halaman ? `<span class="badge bg-secondary-lt"><i class="ti ti-file"></i> hal. ${c.halaman}</span>` : ''}
                            <span class="badge ${LEVEL_BADGE[c.flag_level] || 'bg-secondary-lt'}">${esc(labelLevel(c.flag_level))}</span>
                        </span>
                    </div>
                    <div class="text-wrap text-break text-danger">${esc(c.keterangan)}</div>
                </button>`).join('')}
                ${lainUsulan.map(([u, i]) => `
                <button type="button" class="list-group-item list-group-item-action py-1 px-2 small" data-masalah-lain="u:${i}"
                        title="Isi form catatan tambahan dengan item ini (periksa dulu, lalu Simpan)">
                    <div class="d-flex justify-content-between gap-2">
                        <span class="text-truncate fw-semibold">${esc(u.item)}</span>
                        <span class="badge bg-yellow-lt flex-shrink-0">dari acuan, belum disimpan</span>
                    </div>
                    <div class="text-wrap text-break text-danger">${esc(u.keterangan || '')}</div>
                </button>`).join('')}` : '';
        const total = jumlahHalamanPdf();
        isiDaftar(box, `
            <div class="d-flex align-items-center gap-1 flex-wrap ${buka ? 'mb-1' : ''}">
                <button type="button" class="btn btn-link btn-sm p-0 small fw-semibold text-danger text-decoration-none"
                        data-masalah-toggle title="${buka ? 'Lipat' : 'Tampilkan'} daftar kriteria Tidak Sesuai">
                    <i class="ti ti-chevron-${buka ? 'down' : 'right'} me-1"></i><i class="ti ti-alert-circle me-1"></i>Kriteria Tidak Sesuai
                    <span class="badge bg-red-lt ms-1">${nTs}</span>
                    ${nLolos ? `<span class="badge bg-green-lt ms-1" title="Tercatat di acuan SIPOTRET, sekarang dinilai Sesuai/Skip">${nLolos} dari acuan sudah sesuai</span>` : ''}
                    ${nLain ? `<span class="badge bg-orange-lt ms-1">+${nLain} item lainnya</span>` : ''}
                    ${belumSimpan ? `<span class="badge bg-yellow-lt ms-1">${belumSimpan} belum disimpan</span>` : ''}
                </button>
                ${perluSimpan.length ? `<button type="button" class="btn btn-sm btn-primary py-0 ms-auto" data-mas-simpan
                        title="Simpan penilaian kriteria di daftar ini saja">
                    <i class="ti ti-device-floppy me-1"></i>Simpan ${perluSimpan.length}</button>` : ''}
            </div>
            <div class="list-group list-group-flush border rounded overflow-auto" style="max-height:${pilih ? '30vh' : '18vh'};" data-nilai-list ${buka ? '' : 'hidden'}>
                ${list.map(c => {
                    const pil = pilihanAwal(c);
                    // asal: catatan acuan SIPOTRET (isian awal / tersimpan berbeda dari acuan)
                    const asalAcuan = c.isianSipotret || (c.bedaAcuan ? `${c.acuanAsal || 'Acuan SIPOTRET'}${c.acuanKet ? ': ' + c.acuanKet : ''}` : '');
                    const badge = [
                        !c.reviewed && c.cekOtomatis === 'OK' ? '<span class="badge bg-azure-lt" title="Cek otomatis: kemungkinan sudah diperbaiki"><i class="ti ti-robot"></i> lolos</span>' : '',
                        !c.reviewed && c.cekOtomatis === 'masalah' ? '<span class="badge bg-red-lt" title="Cek otomatis masih menemukan masalah"><i class="ti ti-robot"></i> masih</span>' : '',
                        !c.reviewed && c.cekOtomatis === 'manual' ? '<span class="badge bg-azure-lt" title="Tidak bisa dicek otomatis, periksa manual"><i class="ti ti-eye"></i> cek manual</span>' : '',
                        tersimpan(c) ? '<span class="badge bg-green-lt" title="Sudah disimpan"><i class="ti ti-check"></i></span>' : '<span class="badge bg-yellow-lt">belum disimpan</span>',
                    ].join('');
                    return htmlBarisNilai(c, {
                        pil, total, badge, aktif: pilih && c.id === fokusId,
                        asal: asalAcuan ? `<i class="ti ti-history me-1"></i>${esc(asalAcuan)}` : '',
                    });
                }).join('')}
                ${htmlLain}
            </div>`);
    }

    /** Simpan penilaian kriteria di daftar Kriteria Tidak Sesuai saja (yang belum tersimpan / berubah). */
    async function simpanMasalah() {
        const entry = entries[fileIndex];
        if (!entry || saving) return;
        collectGroup();
        const tersimpan = nilaiTersimpan;
        const items = entry.checks.filter(c => (pilihanAwal(c) === TIDAK_SESUAI || c.isianSipotret || c.bedaAcuan || c.diMasalah)
            && pilihanAwal(c) && !tersimpan(c));
        if (!items.length) return;
        items.forEach(c => { if (c.selStatus === undefined) c.selStatus = pilihanAwal(c); });
        const kosong = items.filter(c => c.selStatus === TIDAK_SESUAI && !(c.selKet ?? c.keterangan ?? '').trim());
        if (kosong.length) {
            pesanSimpan('alert-warning', `Keterangan wajib diisi untuk Tidak Sesuai: ${kosong.map(c => c.id).join(', ')}.`);
            return;
        }
        items.forEach(c => { if (c.selStatus === TIDAK_SESUAI) c.selKet = (c.selKet ?? c.keterangan ?? '').trim(); });
        saving = true;
        try {
            await simpanItems(entry, items);
            renderGroupTetap();
            renderFileTabs();
            pesanSimpan('alert-success', `${items.length} kriteria di daftar Tidak Sesuai disimpan.`);
        } catch (err) {
            pesanSimpan('alert-danger', 'Gagal menyimpan: ' + err.message);
        } finally {
            saving = false;
        }
    }

    /** Item lainnya di daftar Tidak Sesuai diklik: tampilkan kotak catatan tambahan (edit / isi dari usulan). */
    function bukaMasalahLain(kunci) {
        const entry = entries[fileIndex];
        if (!entry) return;
        const [jenis, id] = kunci.split(':');
        collectGroup();
        lihatCatatan = true;
        if (cara === 'pilih') fokusId = null;
        applyFilter();
        const box = catById('revCatatanBox');
        if (!box) return;
        if (jenis === 'c') {
            const c = (entry.catatan || []).find(x => x.id === +id);
            if (c) bukaFormCatatan(true, c);
            if (c?.halaman) keArea(c.halaman, c.pos_x, c.pos_y, `c:${c.id}`);
        } else {
            box.querySelector(`[data-cat-usulan="${id}"]`)?.click();
        }
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /** Kartu kriteria yang sedang dibuka (mode pilih) beserta datanya. */
    function kartuFokus() {
        const items = currentGroupItems();
        const card = [...el('items').querySelectorAll('[data-idx]')].find(k => items[parseInt(k.dataset.idx, 10)]?.id === fokusId);
        return card ? { card, c: items[parseInt(card.dataset.idx, 10)] } : null;
    }

    function pesanSimpan(cls, text) {
        const box = el('saveError');
        box.className = `alert ${cls} py-2 small mt-2 mb-0`;
        box.textContent = text;
        box.style.display = 'block';
    }

    /**
     * Mode pilih: simpan penilaian kriteria yang sedang dibuka.
     * @param {{bolehKosong?: boolean}} opts bolehKosong: tidak ada yang dibuka/dipilih = sukses tanpa menyimpan
     * @returns {Promise<boolean>} berhasil
     */
    async function simpanFokus({ bolehKosong = false } = {}) {
        if (saving) return false;
        collectGroup();
        const f = fokusId ? kartuFokus() : null;
        if (!f || !f.c.selStatus) {
            if (bolehKosong) return true;
            pesanSimpan('alert-warning', f ? 'Pilih Sesuai, Tidak Sesuai, atau Skip dulu.' : 'Cari dan buka kriteria dulu.');
            return false;
        }
        if (f.c.selStatus === TIDAK_SESUAI && !f.c.selKet) {
            f.card.querySelector('.rev-ket')?.classList.add('is-invalid');
            f.card.querySelector('.rev-ket')?.focus();
            pesanSimpan('alert-danger', 'Keterangan wajib diisi untuk kriteria yang Tidak Sesuai.');
            return false;
        }
        // sudah tersimpan & tidak berubah: tidak perlu dikirim ulang
        if (f.c.reviewed && f.c.selStatus === f.c.status && (f.c.selStatus !== TIDAK_SESUAI || f.c.selKet === f.c.keterangan)) return true;

        saving = true;
        el('btnSimpanFokus').disabled = true;
        try {
            await simpanItems(entries[fileIndex], [f.c]);
            f.card.classList.add('border-success');
            pesanSimpan('alert-success', `Kriteria ${f.c.id} disimpan.`);
            renderKategoriSelect(entries[fileIndex]);
            renderFileTabs();
            renderMasalah();
            return true;
        } catch (e) {
            console.error('Gagal menyimpan kriteria', e);
            pesanSimpan('alert-danger', e instanceof InputError ? e.message : `Gagal menyimpan: ${e.message}. Coba lagi.`);
            return false;
        } finally {
            saving = false;
            el('btnSimpanFokus').disabled = false;
        }
    }

    /**
     * Mode pilih, Selesai: kriteria yang belum dinilai di semua kategori menjadi Sesuai (Tidak Sesuai
     * & Skip tidak diubah), semua disimpan, lalu lanjut ke publikasi berikutnya yang belum selesai.
     */
    async function selesaiPilih() {
        const entry = entries[fileIndex];
        if (!entry || saving) return;
        collectGroup();
        const ts = entry.checks.filter(c => pilihanAwal(c) === TIDAK_SESUAI);
        const tanpaKet = ts.filter(c => !(c.selKet ?? c.keterangan));
        if (tanpaKet.length) {
            pesanSimpan('alert-danger', `Keterangan belum diisi untuk: ${tanpaKet.map(c => c.id).join(', ')}.`);
            return;
        }
        if (!confirm(`Selesaikan tinjauan publikasi ini?\n\n${ts.length} kriteria Tidak Sesuai. ` +
            'Kriteria lain yang belum dinilai ditandai Sesuai lalu semuanya disimpan (Skip tidak diubah).')) return;

        const r = await setAllSemuaKategori('OK', { tanpaTanya: true });
        if (!r || r.dilewati.length) return; // pesan sudah ditampilkan
        const j = nextUnfinishedFile();
        if (j !== null) switchFile(j);
        else finish();
    }

    // ── Gambar contoh yang benar per kategori ──────────────────────────
    function loadContoh() {
        if (!contohUrl || contohLoaded) return;
        contohLoaded = fetch(contohUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : {})
            .then(data => {
                contohMap = data || {};
                // render ulang kategori yang sedang terbuka begitu data tiba
                if (entries[fileIndex]) renderContoh(entries[fileIndex].groups[groupIndex].kategori);
            })
            .catch(() => { contohMap = {}; });
    }

    function renderContoh(kategori) {
        const box = el('contoh');
        if (!box) return;
        const list = contohMap[kategori] || [];

        // Panel perbandingan mengikuti kategori aktif bila petugas membukanya: contoh kategori ini
        // (posisi contoh dipertahankan bila kategorinya sama), atau disembunyikan sementara bila tidak ada contoh
        if (compareWanted && list.length) {
            openCompare(kategori, compare?.kategori === kategori ? compare.idx : 0);
        } else {
            hideCompare();
        }

        if (!list.length) { box.style.display = 'none'; box.innerHTML = ''; return; }

        box.style.display = '';
        box.innerHTML = `
            <div class="border rounded p-2 bg-green-lt">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                    <small class="fw-semibold">
                        <i class="ti ti-rosette-discount-check me-1"></i>Contoh yang benar (${list.length})
                        <span class="text-secondary fw-normal">(klik gambar untuk membandingkan)</span>
                    </small>
                    <button type="button" class="btn btn-sm py-0 px-2 flex-shrink-0" data-contoh-toggle></button>
                </div>
                <div class="d-flex gap-2 overflow-auto pb-1">
                    ${list.map((c, i) => {
                        const attrs = `data-kategori="${esc(kategori)}" data-contoh-idx="${i}"
                            title="${esc(c.keterangan || 'Contoh ' + (i + 1))}"`;
                        const box = 'height:44px;width:auto;max-width:80px;cursor:zoom-in;';
                        return c.tipe === 'pdf'
                            ? `<div ${attrs} class="contoh-thumb contoh-thumb-pdf border flex-shrink-0 px-2" style="${box}">
                                   <i class="ti ti-file-type-pdf" style="font-size:1.25rem;"></i><span class="small">PDF</span>
                               </div>`
                            : `<img src="${esc(c.url)}" alt="contoh" ${attrs} class="contoh-thumb border flex-shrink-0" style="${box}">`;
                    }).join('')}
                </div>
            </div>`;
        syncContohUi();
    }

    // ── Perbandingan: contoh yang benar di samping PDF yang diperiksa ──

    /**
     * URL PDF milik aplikasi ini disamakan dengan origin halaman (skema & host). pdf.js menolak file
     * yang origin-nya beda dari viewer ("file origin does not match viewer's"), dan http di halaman
     * https diblokir browser — mis. bila URL dibuat sebagai http:// atau tanpa/dengan www di server.
     * URL blob: (file upload) & data: dibiarkan.
     */
    function asalSama(url) {
        const u = new URL(url, location.href);
        if (u.protocol === 'blob:' || u.protocol === 'data:') return url;
        return location.origin + u.pathname + u.search;
    }

    // ── Viewer pdf.js lewat srcdoc ──────────────────────────────────────────
    // Server produksi mengirim CSP `frame-ancestors 'none'` (dipasang di depan Apache, tidak bisa
    // diubah lewat .htaccess), sehingga viewer.html yang dimuat via <iframe src> diblokir browser
    // (viewer kosong, Network: "blocked:other"). Dokumen srcdoc tidak punya respons HTTP sendiri,
    // jadi header itu tidak berlaku; isinya tetap viewer.html milik aplikasi (origin sama),
    // ditambah <base> supaya path relatifnya (viewer.mjs, ../build, locale) tetap benar.
    let viewerHtmlPromise = null;

    function viewerHtml() {
        viewerHtmlPromise ??= fetch(viewerBaseUrl, { credentials: 'same-origin' })
            .then(r => { if (!r.ok) throw new Error(`viewer.html ${r.status}`); return r.text(); })
            .then(html => html.replace(/<head(\s[^>]*)?>/i, m => `${m}<base href="${esc(new URL('.', viewerBaseUrl).href)}">`))
            .catch(e => { viewerHtmlPromise = null; throw e; });
        return viewerHtmlPromise;
    }

    // Opsi viewer diset sebelum PDFViewerApplication.run(): pdf.js mengirim event ini ke dokumen induk.
    document.addEventListener('webviewerloaded', e => {
        const opts = e.detail?.source?.PDFViewerApplicationOptions;
        if (!opts) return;
        // Di srcdoc `location` = about:srcdoc: pdf.js me-resolve URL worker terhadapnya (gagal), dan worker
        // (lewat blob wrapper) mengambil cmaps/fonts/wasm relatif ke lokasinya sendiri — jadikan semua absolut.
        if (viewerBaseUrl) {
            for (const name of ['workerSrc', 'cMapUrl', 'iccUrl', 'standardFontDataUrl', 'wasmUrl', 'sandboxBundleSrc']) {
                const v = opts.get(name);
                if (typeof v === 'string' && v && !/^[a-z][a-z0-9+.-]*:/i.test(v)) opts.set(name, new URL(v, viewerBaseUrl).href);
            }
        }
        opts.set('disablePreferences', true); // supaya preferensi tersimpan tidak menimpa opsi di bawah
        opts.set('defaultUrl', '');           // srcdoc tidak punya ?file= — jangan buka PDF demo bawaan
        opts.set('disableHistory', true);     // history.replaceState di about:srcdoc → SecurityError
        opts.set('sidebarViewOnLoad', 0);     // pengganti #pagemode=none
        opts.set('spreadModeOnLoad', 2);      // even spread (mengalahkan /PageLayout milik PDF)
    });

    /**
     * Muat viewer pdf.js ke iframe lalu buka pdfUrl.
     * beforeOpen(win): viewer siap, PDF belum dibuka (mis. pasang teks OCR). afterOpen(win): PDF sudah dibuka.
     */
    function mountViewer(frame, pdfUrl, { beforeOpen, afterOpen } = {}) {
        const token = `${Date.now()}-${Math.random()}`;
        frame.dataset.viewerToken = token;
        frame.onload = () => {
            // abaikan load about:blank awal & pemuatan lama yang sudah diganti
            if (frame.dataset.viewerToken !== token || !frame.hasAttribute('srcdoc')) return;
            try {
                const win = frame.contentWindow;
                win.PDFViewerApplication?.initializedPromise?.then(async () => {
                    beforeOpen?.(win);
                    if (pdfUrl) await win.PDFViewerApplication.open({ url: pdfUrl });
                    afterOpen?.(win);
                });
            } catch (e) { console.error('Viewer PDF gagal dimuat', e); }
        };
        viewerHtml()
            // komentar unik: srcdoc yang sama persis antar publikasi tetap memuat ulang viewer
            .then(html => { if (frame.dataset.viewerToken === token) frame.srcdoc = `${html}<!-- ${token} -->`; })
            .catch(e => {
                console.error('Gagal mengambil viewer.html, pakai pemuatan biasa', e);
                if (frame.dataset.viewerToken !== token) return;
                frame.removeAttribute('srcdoc');
                frame.onload = () => { try { afterOpen?.(frame.contentWindow); } catch { /* abaikan */ } };
                frame.src = `${viewerBaseUrl}?file=${encodeURIComponent(pdfUrl)}#pagemode=none&spread=even`;
            });
    }

    function openCompare(kategori, idx) {
        const list = contohMap[kategori] || [];
        if (!list.length) { hideCompare(); return; }
        idx = (idx + list.length) % list.length;
        const c = list[idx];
        const same = compare && compare.kategori === kategori && compare.idx === idx;
        compare = { kategori, idx };

        el('compare').style.display = 'flex';
        el('leftCol').className  = 'col-lg-9';
        el('rightCol').className = 'col-lg-3';
        el('compareInfo').textContent = `· ${idx + 1}/${list.length}` + (c.keterangan ? ` · ${c.keterangan}` : '');
        el('compare').querySelectorAll('[data-cmp="-1"], [data-cmp="1"]').forEach(b => { b.disabled = list.length < 2; });

        syncContohUi();
        if (same) return; // jangan muat ulang viewer bila contoh yang sama
        el('compareBody').innerHTML = c.tipe === 'pdf'
            ? `<iframe title="Contoh PDF" style="width:100%;height:100%;border:0;"></iframe>`
            : `<img src="${esc(c.url)}" alt="contoh" style="max-width:100%;max-height:100%;object-fit:contain;background:#fff;">`;
        if (c.tipe === 'pdf') mountViewer(el('compareBody').querySelector('iframe'), asalSama(c.url));
    }

    /** Sembunyikan panel contoh (tanpa mengubah pilihan petugas). */
    function hideCompare() {
        compare = null;
        syncContohUi();
        if (!el('compare')) return;
        el('compare').style.display = 'none';
        el('compareBody').innerHTML = '';
        el('leftCol').className  = 'col-lg-8';
        el('rightCol').className = 'col-lg-4';
    }

    /** Label tombol Tampilkan/Sembunyikan & penanda gambar contoh yang sedang dibandingkan. */
    function syncContohUi() {
        const box = el('contoh');
        if (!box) return;
        const btn = box.querySelector('[data-contoh-toggle]');
        if (btn) {
            btn.className = `btn btn-sm py-0 px-2 flex-shrink-0 ${compare ? 'btn-outline-secondary' : 'btn-success'}`;
            btn.innerHTML = compare
                ? '<i class="ti ti-eye-off"></i> Sembunyikan'
                : '<i class="ti ti-columns-2"></i> Tampilkan';
        }
        box.querySelectorAll('[data-contoh-idx]').forEach(t => {
            const aktif = compare && compare.kategori === t.dataset.kategori && compare.idx === +t.dataset.contohIdx;
            t.classList.toggle('border-success', !!aktif);
            t.style.outline = aktif ? '2px solid var(--tblr-green)' : '';
        });
    }

    /** Tampilan besar contoh (gambar, atau PDF di viewer pdf.js), dengan navigasi sebelumnya/berikutnya. */
    function openContoh(kategori, idx) {
        const list = contohMap[kategori] || [];
        if (!list.length) return;

        const wrap = document.createElement('div');
        wrap.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:2000;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px;';
        document.body.appendChild(wrap);

        const show = i => {
            idx = (i + list.length) % list.length;
            const c = list[idx];
            wrap.innerHTML = `
                <div style="color:#fff;margin-bottom:8px;text-align:center;max-width:90vw;">
                    <div class="small" style="opacity:.75;">Contoh yang benar · ${esc(kategori)} · ${idx + 1}/${list.length}</div>
                    ${c.keterangan ? `<div class="fw-semibold">${esc(c.keterangan)}</div>` : ''}
                </div>
                ${c.tipe === 'pdf'
                    ? `<iframe title="Contoh PDF" style="width:92vw;max-width:1200px;height:78vh;border:0;border-radius:6px;background:#fff;"></iframe>`
                    : `<img src="${esc(c.url)}" alt="contoh" style="max-width:92vw;max-height:78vh;object-fit:contain;background:#fff;border-radius:6px;">`}
                <div style="margin-top:10px;display:flex;gap:8px;">
                    ${list.length > 1 ? '<button type="button" class="btn btn-light btn-sm" data-nav="-1"><i class="ti ti-chevron-left"></i> Sebelumnya</button>' : ''}
                    <button type="button" class="btn btn-light btn-sm" data-nav="close"><i class="ti ti-x"></i> Tutup</button>
                    ${list.length > 1 ? '<button type="button" class="btn btn-light btn-sm" data-nav="1">Berikutnya <i class="ti ti-chevron-right"></i></button>' : ''}
                </div>`;
            if (c.tipe === 'pdf') mountViewer(wrap.querySelector('iframe'), asalSama(c.url));
        };

        const close = () => { wrap.remove(); document.removeEventListener('keydown', onKey); };
        const onKey = e => {
            if (e.key === 'Escape') close();
            else if (e.key === 'ArrowRight') show(idx + 1);
            else if (e.key === 'ArrowLeft') show(idx - 1);
        };
        wrap.addEventListener('click', e => {
            const nav = e.target.closest('[data-nav]')?.dataset.nav;
            if (nav === 'close' || e.target === wrap) close();
            else if (nav) show(idx + parseInt(nav, 10));
        });
        document.addEventListener('keydown', onKey);
        show(idx);
    }

    /** Tombol lokasi temuan cek otomatis: lompat ke halaman & sorot teksnya di viewer. */
    function lokasiButtons(lokasi) {
        if (!Array.isArray(lokasi) || !lokasi.length) return '';
        const short = s => { s = String(s ?? ''); return s.length > 40 ? s.slice(0, 40) + '…' : s; };
        return `<div class="d-flex flex-wrap gap-1 mb-1">${lokasi.map(l => l.perkiraan ? `
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 text-start"
                    style="font-size:11px;" data-hal="${l.hal}" data-teks=""
                    title="Perkiraan halaman dari bagian yang diperiksa kriteria ini">
                <i class="ti ti-file-arrow-right"></i> Hal. ${l.hal} (perkiraan)
            </button>` : `
            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-1 text-start"
                    style="font-size:11px;" data-hal="${l.hal}" data-teks="${esc(l.teks)}"
                    title="Buka halaman ${l.hal} dan sorot: ${esc(l.teks)}">
                <i class="ti ti-file-search"></i> Hal. ${l.hal}: “${esc(short(l.teks))}”
            </button>`).join('')}</div>`;
    }

    /**
     * Halaman PDF suatu kriteria, dari yang paling pasti: penanda petugas → temuan cek otomatis /
     * perkiraan dari server (lokasi) → target kriteria (kover/halaman 2/kover belakang).
     * @returns {{hal: number, teks: string}|null}
     */
    function halamanDari(c, total) {
        if (c.pin?.hal) return { hal: c.pin.hal, teks: '' };
        if (c.lokasi?.[0]?.hal) return { hal: c.lokasi[0].hal, teks: c.lokasi[0].teks || '' };
        const hal = c.target === 'cover' ? 1 : c.target === 'page2' ? 2 : c.target === 'last' ? total : null;
        return hal ? { hal, teks: '' } : null;
    }

    /** Buka halaman kriteria `c` di viewer (sorot teks temuannya bila ada; penanda ikut dikedipkan). */
    function keHalamanKriteria(c) {
        const app = el('frame')?.contentWindow?.PDFViewerApplication;
        if (!c || !app?.pdfDocument) return;
        if (c.pin?.hal) { keArea(c.pin.hal, c.pin.x, c.pin.y, `k:${c.id}`); return; }
        const h = halamanDari(c, app.pagesCount || 1);
        if (h) goToLokasi(h.hal, h.teks);
    }

    function goToLokasi(hal, teks, frame = el('frame')) {
        const app = frame?.contentWindow?.PDFViewerApplication;
        if (!app?.pdfDocument) return;
        try {
            app.page = Math.min(hal, app.pagesCount || hal);
            if (!teks) return;
            // Pencarian dimulai dari halaman aktif, jadi kemunculan di halaman ini yang disorot
            app.findBar?.open();
            if (app.findBar?.findField) app.findBar.findField.value = teks;
            app.eventBus.dispatch('find', {
                source: null, type: '', query: teks, caseSensitive: false, entireWord: false,
                highlightAll: true, findPrevious: false, matchDiacritics: false,
            });
        } catch (e) { /* viewer belum siap — petugas bisa cari manual */ }
    }

    /**
     * Pilihan yang sudah aktif saat kategori dibuka: pilihan di layar sebelumnya, status verifikasi
     * yang tersimpan, atau hasil otomatis OK/Skip. Hasil otomatis "Perlu Dicek"/"Tidak Ada" → belum dipilih.
     */
    function pilihanAwal(c) {
        if (c.selStatus !== undefined) return c.selStatus;
        return STATUS_VERIFIKASI.includes(c.status) ? c.status : null;
    }

    function setItemSelection(card, status) {
        card?.querySelectorAll('button[data-status]').forEach(b => {
            b.classList.toggle('active', b.dataset.status === status);
        });
        card?.classList.remove('border-danger');
        const wrap = card?.querySelector('.rev-ket-wrap');
        if (wrap) wrap.hidden = status !== TIDAK_SESUAI;
    }

    /**
     * Tandai semua kriteria yang tampil (sesuai filter) di kategori aktif dengan status yang sama.
     * Kriteria yang sudah ditandai Tidak Sesuai/Skip tidak ditimpa — keputusan petugas dipertahankan.
     */
    function setAll(status, lingkup = 'kategori') {
        if (lingkup === 'semua') { setAllSemuaKategori(status); return; }
        el('items').querySelectorAll('[data-idx]:not([hidden])').forEach(card => {
            const cur = card.querySelector('button[data-status].active')?.dataset.status;
            if (cur === TIDAK_SESUAI) return;
            if (status === 'TIDAK DIPERIKSA') {
                // Skip (mis. bagian opsional yang tidak ada): pilihan otomatis ikut di-Skip,
                // pilihan yang diklik/disimpan petugas dipertahankan
                if (card.dataset.dipilih) return;
            } else if (cur === 'TIDAK DIPERIKSA') {
                return;
            }
            setItemSelection(card, status);
        });
        collectGroup();
        renderFilter();
    }

    /** Aturan "sisanya" untuk satu kriteria (versi data dari setAll). @returns true bila pilihannya berubah */
    function aturSisa(c, status) {
        const cur = pilihanAwal(c);
        if (cur === TIDAK_SESUAI) return false;
        if (status === 'TIDAK DIPERIKSA' ? (c.dipilih || c.reviewed) : cur === 'TIDAK DIPERIKSA') return false;
        if (cur === status) return false;
        c.selStatus = status;
        return true;
    }

    /**
     * "Sisanya Sesuai/Skip" untuk semua kategori publikasi aktif, lalu langsung menyimpan setiap
     * kategori yang belum tersimpan atau berubah. Kategori dengan isian belum lengkap
     * (mis. Tidak Sesuai tanpa keterangan) dilewati dan dilaporkan.
     */
    /** @returns {Promise<{tersimpan: number, dilewati: string[]}|null>} null = dibatalkan */
    async function setAllSemuaKategori(status, { tanpaTanya = false } = {}) {
        const entry = entries[fileIndex];
        if (!entry || saving) return null;
        const label = status === 'OK' ? 'Sesuai' : 'Skip';
        if (!tanpaTanya && !confirm(`Tandai sisanya "${label}" di SEMUA ${entry.groups.length} kategori publikasi ini lalu simpan?\n\n` +
            `Tidak Sesuai${status === 'OK' ? ' dan Skip' : ' dan pilihan yang sudah diklik/disimpan petugas'} tidak diubah.`)) return null;

        collectGroup();
        entry.groups.forEach(g => g.idx.forEach(i => aturSisa(entry.checks[i], status)));

        // kategori yang perlu disimpan: belum tersimpan semua, atau ada pilihan yang berbeda dari tersimpan
        const perluSimpan = entry.groups.filter(g => g.idx.some(i => {
            const c = entry.checks[i];
            return !c.reviewed || (c.selStatus !== undefined && c.selStatus !== c.status);
        }));

        saving = true;
        el('btnNext').disabled = true;
        const box = el('saveError');
        box.style.display = 'none';
        const dilewati = [];
        let tersimpan = 0;
        for (const g of perluSimpan) {
            const items = g.idx.map(i => entry.checks[i]);
            items.forEach(c => { if (c.selStatus === undefined) { c.selStatus = pilihanAwal(c); c.selKet = c.keterangan ?? ''; } });
            const kurang = items.some(c => !c.selStatus || (c.selStatus === TIDAK_SESUAI && !(c.selKet ?? c.keterangan)));
            if (kurang) { dilewati.push(g.kategori); continue; }
            items.forEach(c => { if (c.selStatus === TIDAK_SESUAI) c.selKet = c.selKet || c.keterangan; });
            try {
                await simpanItems(entry, items);
                tersimpan++;
            } catch (e) {
                dilewati.push(`${g.kategori} (${e.message})`);
            }
        }
        saving = false;
        el('btnNext').disabled = false;

        renderGroup();
        box.className = `alert ${dilewati.length ? 'alert-warning' : 'alert-success'} py-2 small mt-2 mb-0`;
        box.textContent = `${tersimpan} kategori disimpan.` +
            (dilewati.length ? ` Belum lengkap, dilewati: ${dilewati.join(', ')}.` : '');
        box.style.display = 'block';
        return { tersimpan, dilewati };
    }

    /** Simpan pilihan di layar ke objek kriteria (belum dikirim ke server). */
    function collectGroup() {
        if (!entries[fileIndex]) return;
        const items = currentGroupItems();
        el('items').querySelectorAll('[data-idx]').forEach(card => {
            const c = items[parseInt(card.dataset.idx, 10)];
            if (!c) return;
            c.selStatus = card.querySelector('button.active')?.dataset.status ?? null;
            c.selKet    = card.querySelector('.rev-ket')?.value.trim() ?? '';
            if (card.dataset.dipilih) c.dipilih = true;
        });
    }

    /** Kesalahan isian petugas — ditampilkan apa adanya, tanpa awalan "Gagal menyimpan". */
    class InputError extends Error {}

    /** Semua kriteria wajib dinilai; Tidak Sesuai wajib berketerangan. Tandai kartu yang bermasalah. */
    function validateGroup(items) {
        let belum = 0, tanpaKet = 0, first = null;
        el('items').querySelectorAll('[data-idx]').forEach(card => {
            const c = items[parseInt(card.dataset.idx, 10)];
            if (!c) return;
            if (!c.selStatus) {
                belum++;
                card.classList.add('border-danger');
                card.hidden = false; // tampilkan walau tersembunyi oleh filter
                first ??= card;
            } else if (c.selStatus === TIDAK_SESUAI && !c.selKet) {
                tanpaKet++;
                card.querySelector('.rev-ket')?.classList.add('is-invalid');
                card.hidden = false;
                first ??= card;
            }
        });
        if (!belum && !tanpaKet) return;

        first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const pesan = [];
        if (belum)    pesan.push(`${belum} kriteria belum dinilai (pilih Sesuai, Tidak Sesuai, atau Skip)`);
        if (tanpaKet) pesan.push(`${tanpaKet} kriteria Tidak Sesuai belum diberi keterangan`);
        throw new InputError(pesan.join('; ') + '.');
    }

    async function saveGroup() {
        collectGroup();
        const entry = entries[fileIndex];
        const items = currentGroupItems();
        validateGroup(items);
        await simpanItems(entry, items);
    }

    /** Kirim pilihan (selStatus/selKet) sekelompok kriteria ke server lalu tandai sudah ditinjau. */
    async function simpanItems(entry, items) {
        if (entry.hasilId) {
            const res = await fetch(saveUrlTpl.replace('__ID__', entry.hasilId), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    items: items.map(c => ({
                        kriteria_id: c.id,
                        status:      c.selStatus,
                        keterangan:  c.selStatus === TIDAK_SESUAI ? c.selKet : null,
                        pin_halaman: c.selStatus === TIDAK_SESUAI ? (c.pin?.hal ?? null) : null,
                        pin_x:       c.selStatus === TIDAK_SESUAI ? (c.pin?.x ?? null) : null,
                        pin_y:       c.selStatus === TIDAK_SESUAI ? (c.pin?.y ?? null) : null,
                    })),
                }),
            });
            if (res.status === 422) {
                const data = await res.json().catch(() => ({}));
                throw new InputError(data.message || 'Isian tidak valid.');
            }
            if (!res.ok) throw new Error(`Server membalas ${res.status}`);
        }

        items.forEach(c => {
            c.status     = c.selStatus;
            c.keterangan = c.selStatus === TIDAK_SESUAI ? c.selKet : null;
            if (c.selStatus !== TIDAK_SESUAI) c.pin = null;
            c.reviewed   = true;
            delete c.selStatus;
            delete c.selKet;
        });
        recalcSummary(entry);
        gambarSemuaPin();
    }

    /**
     * Kesalahan yang sama di beberapa publikasi: tandai kriteria ini Tidak Sesuai dengan keterangan
     * yang sama di semua publikasi yang sedang ditinjau (termasuk yang aktif) dan langsung simpan.
     */
    async function applyToAll(card, btn) {
        const c   = currentGroupItems()[parseInt(card?.dataset.idx, 10)];
        const ta  = card?.querySelector('.rev-ket');
        const msg = card?.querySelector('.rev-apply-msg');
        if (!c || !ta) return;
        const ket = ta.value.trim();
        if (!ket) { ta.classList.add('is-invalid'); ta.focus(); return; }

        const targets = entries
            .map(e => ({ e, c: e.checks.find(x => x.id === c.id) }))
            .filter(t => t.c && t.e.hasilId);
        const lain = targets.filter(t => t.e !== entries[fileIndex]);
        if (!lain.length) { msg.textContent = 'Kriteria ini tidak ada di publikasi lain.'; return; }

        // penilaian lain (tersimpan atau masih dipilih di layar) yang akan tertimpa
        const timpa = lain.filter(t => {
            const st  = t.c.selStatus ?? (t.c.reviewed ? t.c.status : null);
            const kt  = t.c.selKet ?? t.c.keterangan;
            return st && !(st === TIDAK_SESUAI && kt === ket);
        }).length;
        const tanya = `Tandai kriteria ${c.id} "Tidak Sesuai" dengan keterangan ini di ${targets.length} publikasi dan langsung simpan?`
            + (timpa ? `\n\n${timpa} publikasi lain sudah punya penilaian untuk kriteria ini dan akan ditimpa.` : '');
        if (!confirm(tanya)) return;

        btn.disabled = true;
        msg.className = 'rev-apply-msg text-secondary';
        msg.textContent = 'Menyimpan…';

        const hasil = await Promise.all(targets.map(async t => {
            try {
                const res = await fetch(saveUrlTpl.replace('__ID__', t.e.hasilId), {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ items: [{ kriteria_id: t.c.id, status: TIDAK_SESUAI, keterangan: ket }] }),
                });
                if (!res.ok) throw new Error(`Server membalas ${res.status}`);
                t.c.status     = TIDAK_SESUAI;
                t.c.keterangan = ket;
                t.c.reviewed   = true;
                delete t.c.selStatus;
                delete t.c.selKet;
                recalcSummary(t.e);
                return true;
            } catch (err) {
                console.error('Gagal menyimpan ke ' + t.e.filename, err);
                return false;
            }
        }));

        const ok = hasil.filter(Boolean).length, gagal = hasil.length - ok;
        btn.disabled = false;
        msg.className = 'rev-apply-msg ' + (gagal ? 'text-danger' : 'text-success');
        msg.textContent = gagal
            ? `Tersimpan di ${ok} publikasi, gagal di ${gagal}. Coba lagi.`
            : `Tersimpan di ${ok} publikasi.`;
        if (hasil[targets.findIndex(t => t.e === entries[fileIndex])]) card.classList.add('border-success');
        renderKategoriSelect(entries[fileIndex]);
        renderFileTabs();
        renderFilter();
    }

    async function withSave(after) {
        if (saving) return;
        saving = true;
        el('btnNext').disabled = true;
        if (el('btnExit')) el('btnExit').disabled = true;
        try {
            await saveGroup();
            after();
        } catch (e) {
            console.error('Gagal menyimpan hasil tinjauan', e);
            const box = el('saveError');
            box.className     = 'alert alert-danger py-2 small mt-2 mb-0';
            box.textContent   = e instanceof InputError
                ? e.message
                : 'Gagal menyimpan kategori ini: ' + e.message + '. Coba lagi.';
            box.style.display = 'block';
        } finally {
            saving = false;
            el('btnNext').disabled = false;
            if (el('btnExit')) el('btnExit').disabled = false;
        }
    }

    // ── Mode "Jejer Semua": semua publikasi berdampingan untuk satu kriteria ─────────────────
    // Petugas mencari kriteria lalu cukup menandai publikasi yang Tidak Sesuai (wajib keterangan).
    // Saat disimpan, publikasi yang tidak ditandai menjadi Sesuai — semuanya dihitung sudah ditinjau.

    /** Kriteria gabungan semua publikasi, urut sesuai urutan kriteria. */
    function daftarKriteria() {
        const map = new Map();
        entries.forEach(e => e.checks.forEach(c => { if (!map.has(c.id)) map.set(c.id, c); }));
        return [...map.values()];
    }

    function kriteriaDi(entry, id) {
        return entry?.checks.find(c => c.id === id) || null;
    }

    /** Ringkasan satu kriteria di semua publikasi. */
    function rekapKriteria(id) {
        let ada = 0, ditinjau = 0, ts = 0;
        entries.forEach(e => {
            const c = kriteriaDi(e, id);
            if (!c) return;
            ada++;
            if (c.reviewed) { ditinjau++; if (c.status === TIDAK_SESUAI) ts++; }
        });
        return { ada, ditinjau, ts };
    }

    function jejerCol(i) {
        return el('jejerStrip')?.querySelector(`[data-jejer="${i}"]`) || null;
    }

    function setMode(m) {
        if (m === mode || (m === 'jejer' && entries.length < 2)) return;
        if (m === 'jejer') collectGroup(); // pilihan di layar mode tab tetap diingat
        mode = m;
        applyModeUi();
        if (m === 'jejer') {
            if (!el('jejerStrip').children.length) buildJejerStrip();
            if (jejerId) pilihJejer(jejerId);
            else { renderJejerKriteria(); renderJejerCari(''); el('jejerCari')?.focus(); }
        } else {
            el('jejerHasil').hidden = true;
            renderGroup();
            renderFileTabs();
        }
    }

    /** Label tombol "Tampilan" = pilihan aktif (cara meninjau, atau Jejer semua). */
    function labelTampilan() {
        const lbl = document.getElementById('revTampilanLabel');
        if (lbl) lbl.textContent = mode === 'jejer' ? 'Jejer semua' : (cara === 'pilih' ? 'Pilih kriteria bermasalah' : 'Berurutan');
    }

    function applyModeUi() {
        const jejer = mode === 'jejer';
        labelTampilan();
        el('modeToggle')?.querySelectorAll('[data-mode]').forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
        if (el('modeTab'))   el('modeTab').hidden   = jejer;
        if (el('modeJejer')) el('modeJejer').hidden = !jejer;
        if (el('caraToggle')) el('caraToggle').hidden = jejer; // cara meninjau hanya untuk mode Per Publikasi
        // judul, "File x/y", badge & progres kategori hanya bermakna untuk satu publikasi
        el('fileIdx')?.parentElement && (el('fileIdx').parentElement.hidden = jejer);
        if (el('stepBadge')) el('stepBadge').hidden = jejer;
        el('progressBar')?.parentElement && (el('progressBar').parentElement.hidden = jejer);
        if (entries[fileIndex]) el('fileName').textContent = jejer ? `Semua publikasi (${entries.length})` : entries[fileIndex].filename;
    }

    /** Kembalikan mode jejer ke awal (dipanggil saat tinjauan baru dimulai / selesai). */
    function resetJejer() {
        jejerObs?.disconnect();
        jejerObs = null;
        jejerId  = null;
        jejerDraft.clear();
        if (el('jejerStrip')) el('jejerStrip').innerHTML = ''; // lepas viewer (memori)
        if (el('jejerHasil')) el('jejerHasil').hidden = true;
        if (el('jejerMsg'))   el('jejerMsg').style.display = 'none';
        if (el('jejerCari'))  el('jejerCari').value = '';
        mode = 'tab';
        if (el('modeToggle')) el('modeToggle').hidden = entries.length < 2;
        applyModeUi();
    }

    /** Kolom per publikasi; viewer dimuat saat kolomnya terlihat (10 PDF sekaligus terlalu berat). */
    function buildJejerStrip() {
        const strip = el('jejerStrip');
        jejerObs?.disconnect();
        strip.innerHTML = entries.map((e, i) => `
            <div class="card review-jejer-col" data-jejer="${i}">
                <div class="card-header py-2 px-2 d-block">
                    <div class="fw-semibold small text-truncate" title="${esc(e.filename)}">${i + 1}. ${esc(e.filename)}</div>
                    <div class="jejer-isian mt-1"></div>
                </div>
                <div class="jejer-viewer"><iframe title="${esc(e.filename)}"></iframe></div>
            </div>`).join('');

        jejerObs = new IntersectionObserver(obs => obs.forEach(o => {
            if (!o.isIntersecting) return;
            jejerObs.unobserve(o.target);
            const i = Number(o.target.dataset.jejer), e = entries[i];
            const hasOcr = e.ocrLines && Object.keys(e.ocrLines).length > 0;
            mountViewer(o.target.querySelector('iframe'), asalSama(e.url), {
                beforeOpen: win => { if (hasOcr) injectOcrText(win, e.ocrLines); },
                afterOpen:  ()  => setTimeout(() => jejerKeHalaman(i), 300),
            });
        }), { root: strip, rootMargin: '0px 300px' });
        strip.querySelectorAll('[data-jejer]').forEach(col => jejerObs.observe(col));
        entries.forEach((e, i) => renderJejerIsian(i));
    }

    /** Lompat ke halaman kriteria aktif: lokasi temuan otomatis, atau halaman target kriteria. */
    function jejerKeHalaman(i) {
        const c   = jejerId ? kriteriaDi(entries[i], jejerId) : null;
        const app = jejerCol(i)?.querySelector('iframe')?.contentWindow?.PDFViewerApplication;
        if (!c || !app?.pdfDocument) return;
        const total = app.pagesCount || 1;
        const hal = c.lokasi?.[0]?.hal
            ?? (c.target === 'page2' ? 2 : c.target === 'last' ? total : c.target === 'cover' ? 1 : null);
        if (hal) { try { app.page = Math.min(hal, total); } catch { /* viewer belum siap */ } }
    }

    function renderJejerCari(q) {
        const box  = el('jejerHasil');
        const kata = q.trim().toLowerCase().split(/\s+/).filter(Boolean);
        const semua = daftarKriteria();
        const hasil = kata.length
            ? semua.filter(c => { const t = [c.id, c.kategori, c.deskripsi].join(' ').toLowerCase(); return kata.every(k => t.includes(k)); })
            : semua;
        box.hidden = false;
        if (!hasil.length) {
            box.innerHTML = '<div class="list-group-item small text-secondary">Tidak ada kriteria yang cocok.</div>';
            return;
        }
        const MAX = 60;
        box.innerHTML = hasil.slice(0, MAX).map(c => {
            const r = rekapKriteria(c.id);
            return `
                <button type="button" class="list-group-item list-group-item-action py-2 small ${c.id === jejerId ? 'bg-primary-lt' : ''}" data-jejer-id="${esc(c.id)}">
                    <div class="d-flex justify-content-between gap-2">
                        <span><span class="font-monospace text-secondary">${sorot(c.id, kata)}</span> · <span class="text-secondary">${sorot(c.kategori, kata)}</span></span>
                        <span class="d-flex gap-1 flex-shrink-0">
                            ${r.ts ? `<span class="badge bg-red-lt">${r.ts} Tidak Sesuai</span>` : ''}
                            <span class="badge ${r.ditinjau === r.ada ? 'bg-green-lt' : 'bg-secondary-lt'}">${r.ditinjau}/${r.ada} ditinjau</span>
                        </span>
                    </div>
                    <div class="text-wrap text-break">${sorot(c.deskripsi, kata)}</div>
                </button>`;
        }).join('') + (hasil.length > MAX
            ? `<div class="list-group-item small text-secondary">… ${hasil.length - MAX} lainnya, perjelas kata kunci</div>` : '');
    }

    function pilihJejer(id, { keepMsg = false } = {}) {
        jejerId = id;
        el('jejerHasil').hidden = true;
        if (!keepMsg) el('jejerMsg').style.display = 'none';
        renderJejerKriteria();
        entries.forEach((e, i) => { renderJejerIsian(i); jejerKeHalaman(i); });
    }

    function renderJejerKriteria() {
        const box = el('jejerKriteria');
        const c   = jejerId ? daftarKriteria().find(x => x.id === jejerId) : null;
        box.hidden = false;
        if (!c) {
            box.innerHTML = `<div class="small text-secondary"><i class="ti ti-info-circle me-1"></i>
                Cari dan pilih kriteria di atas. Semua publikasi tampil berdampingan; centang hanya yang
                <strong class="text-danger">Tidak Sesuai</strong>, publikasi lain disimpan sebagai Sesuai.</div>`;
            return;
        }
        const r = rekapKriteria(c.id);
        box.innerHTML = `
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <div class="flex-fill" style="min-width:260px;">
                    <div class="small">
                        <span class="badge bg-light text-dark border font-monospace">${esc(c.id)}</span>
                        <span class="text-secondary">${esc(c.kategori)} · ${esc(PAGE_LABEL[c.target] ?? PAGE_LABEL.all)}</span>
                    </div>
                    <div class="fw-semibold mt-1">${esc(c.deskripsi)}</div>
                    <div class="small text-secondary mt-1">${r.ditinjau}/${r.ada} publikasi sudah ditinjau untuk kriteria ini${r.ts ? ` · ${r.ts} Tidak Sesuai` : ''}</div>
                </div>
                <div class="d-flex gap-2 flex-wrap flex-shrink-0">
                    <button type="button" class="btn btn-primary btn-sm" data-jejer-simpan="0">
                        <i class="ti ti-device-floppy me-1"></i> Simpan
                    </button>
                    <button type="button" class="btn btn-success btn-sm" data-jejer-simpan="1">
                        Simpan &amp; Kriteria Berikutnya <i class="ti ti-chevron-right"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-jejer-selesai
                            title="Tutup tinjauan; yang belum ditinjau bisa dilanjutkan dari Riwayat">
                        <i class="ti ti-check me-1"></i> Selesai
                    </button>
                </div>
            </div>`;
    }

    /** Isian satu kolom untuk kriteria aktif: hasil otomatis/tersimpan, lokasi temuan, centang Tidak Sesuai. */
    function renderJejerIsian(i) {
        const col = jejerCol(i);
        if (!col) return;
        const box = col.querySelector('.jejer-isian');
        const c   = jejerId ? kriteriaDi(entries[i], jejerId) : null;
        col.classList.remove('is-ts');
        if (!jejerId) { box.innerHTML = ''; return; }
        if (!c) { box.innerHTML = '<div class="small text-secondary">Kriteria ini tidak ada di publikasi ini.</div>'; return; }

        const draft = jejerDraft.get(`${i}|${c.id}`);
        const pilih = c.selStatus ?? (c.reviewed ? c.status : null);
        const ts    = draft ? draft.ts : pilih === TIDAK_SESUAI;
        const ket   = draft ? draft.ket : (c.selKet ?? c.keterangan ?? '');
        col.classList.toggle('is-ts', ts);

        const tersimpan = c.reviewed
            ? `<span class="badge ${statusBadgeClass(c.status)}">${esc(LABEL_PILIHAN[c.status]?.[0] ?? c.status)}</span>`
            : '<span class="badge bg-yellow-lt">Belum ditinjau</span>';
        const otomatis = c.autoLabel === 'Otomatis'
            ? `<span class="text-secondary">Otomatis: <span class="badge ${statusBadgeClass(c.autoStatus)}">${esc(c.autoStatus)}</span></span>`
            : '<span></span>';
        box.innerHTML = `
            <div class="d-flex justify-content-between align-items-center gap-2 small mb-1">${otomatis}${tersimpan}</div>
            ${c.autoLabel === 'Otomatis' && c.autoCatatan ? `<div class="small text-secondary text-truncate mb-1" title="${esc(c.autoCatatan)}">${esc(c.autoCatatan)}</div>` : ''}
            ${lokasiButtons(c.lokasi)}
            <label class="form-check mb-1">
                <input type="checkbox" class="form-check-input jejer-ts" ${ts ? 'checked' : ''}>
                <span class="form-check-label fw-semibold text-danger">Tidak Sesuai</span>
            </label>
            <textarea class="form-control form-control-sm jejer-ket" rows="2"
                      placeholder="Keterangan (wajib bila Tidak Sesuai)" ${ts ? '' : 'hidden'}>${esc(ket)}</textarea>`;
    }

    /** Ingat isian kolom yang belum disimpan (bertahan saat pindah kriteria/mode). */
    function ingatJejer(col) {
        if (!jejerId || !col) return;
        jejerDraft.set(`${col.dataset.jejer}|${jejerId}`, {
            ts:  !!col.querySelector('.jejer-ts')?.checked,
            ket: col.querySelector('.jejer-ket')?.value ?? '',
        });
    }

    function showJejerMsg(type, text) {
        const box = el('jejerMsg');
        box.className = `alert alert-${type} py-2 small mb-2`;
        box.textContent = text;
        box.style.display = 'block';
    }

    /** Kriteria berikutnya (urutan daftar) yang belum ditinjau di semua publikasi. */
    function kriteriaBerikutnya(id) {
        const list = daftarKriteria();
        const from = list.findIndex(c => c.id === id);
        for (let k = 1; k <= list.length; k++) {
            const c = list[(from + k) % list.length];
            const r = rekapKriteria(c.id);
            if (r.ditinjau < r.ada) return c.id;
        }
        return null;
    }

    /** Simpan kriteria aktif di semua publikasi: dicentang → Tidak Sesuai, lainnya → Sesuai. */
    async function simpanJejer(lanjut) {
        if (!jejerId || jejerSaving) return;
        const id = jejerId;
        const targets = [];
        let kurang = 0, first = null;
        entries.forEach((e, i) => {
            const c = kriteriaDi(e, id), col = jejerCol(i);
            if (!c || !col) return;
            const ts  = !!col.querySelector('.jejer-ts')?.checked;
            const ta  = col.querySelector('.jejer-ket');
            const ket = ta?.value.trim() ?? '';
            if (ts && !ket) { kurang++; ta.classList.add('is-invalid'); first ??= col; }
            targets.push({ i, e, c, ts, ket });
        });
        if (kurang) {
            first.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            showJejerMsg('danger', `${kurang} publikasi ditandai Tidak Sesuai tapi belum diberi keterangan.`);
            return;
        }

        jejerSaving = true;
        el('jejerKriteria').querySelectorAll('button').forEach(b => { b.disabled = true; });
        const hasil = await Promise.allSettled(targets.map(t => {
            t.c.selStatus = t.ts ? TIDAK_SESUAI : 'OK';
            t.c.selKet    = t.ts ? t.ket : '';
            return simpanItems(t.e, [t.c]);
        }));
        jejerSaving = false;

        const gagal = [];
        hasil.forEach((h, k) => {
            const t = targets[k];
            if (h.status === 'fulfilled') { jejerDraft.delete(`${t.i}|${id}`); return; }
            delete t.c.selStatus; // gagal: biarkan seperti sebelum disimpan
            delete t.c.selKet;
            gagal.push(`${t.e.filename} (${h.reason?.message || 'gagal'})`);
        });
        entries.forEach((e, i) => renderJejerIsian(i));
        renderJejerKriteria();
        renderFileTabs();

        if (gagal.length) {
            showJejerMsg('danger', `Gagal menyimpan di ${gagal.length} publikasi: ${gagal.join('; ')}. Coba lagi.`);
            return;
        }
        const nTs = targets.filter(t => t.ts).length;
        showJejerMsg('success', `Kriteria ${id} disimpan untuk ${targets.length} publikasi: ${nTs} Tidak Sesuai, ${targets.length - nTs} Sesuai.`);
        if (lanjut) {
            const nxt = kriteriaBerikutnya(id);
            if (nxt) pilihJejer(nxt, { keepMsg: true });
            else showJejerMsg('success', `Kriteria ${id} disimpan. Semua kriteria sudah ditinjau di semua publikasi.`);
        }
    }

    function initJejer() {
        el('modeToggle')?.addEventListener('click', e => {
            const b = e.target.closest('[data-mode]');
            if (b) setMode(b.dataset.mode);
        });

        const cari = el('jejerCari'), hasil = el('jejerHasil');
        if (cari && hasil) {
            cari.addEventListener('input', () => renderJejerCari(cari.value));
            cari.addEventListener('focus', () => renderJejerCari(cari.value));
            cari.addEventListener('keydown', e => {
                if (e.key === 'Escape') { cari.value = ''; hasil.hidden = true; }
                if (e.key === 'Enter') { e.preventDefault(); hasil.querySelector('[data-jejer-id]')?.click(); }
            });
            hasil.addEventListener('click', e => {
                const b = e.target.closest('[data-jejer-id]');
                if (b) pilihJejer(b.dataset.jejerId);
            });
            document.addEventListener('click', e => {
                if (!e.target.closest('#revJejerCari, #revJejerHasil')) hasil.hidden = true;
            });
        }

        el('jejerKriteria')?.addEventListener('click', e => {
            const s = e.target.closest('[data-jejer-simpan]');
            if (s) { simpanJejer(s.dataset.jejerSimpan === '1'); return; }
            if (e.target.closest('[data-jejer-selesai]')) finish();
        });

        const strip = el('jejerStrip');
        strip?.addEventListener('change', e => {
            const cb = e.target.closest('.jejer-ts');
            if (!cb) return;
            const col = cb.closest('[data-jejer]');
            const ta  = col.querySelector('.jejer-ket');
            ta.hidden = !cb.checked;
            col.classList.toggle('is-ts', cb.checked);
            if (cb.checked) ta.focus();
            ingatJejer(col);
        });
        strip?.addEventListener('input', e => {
            const ta = e.target.closest('.jejer-ket');
            if (!ta) return;
            ta.classList.remove('is-invalid');
            ingatJejer(ta.closest('[data-jejer]'));
        });
        strip?.addEventListener('click', e => {
            const loc = e.target.closest('button[data-hal]');
            if (loc) goToLokasi(parseInt(loc.dataset.hal, 10), loc.dataset.teks, loc.closest('[data-jejer]')?.querySelector('iframe'));
        });
    }

    // ── Catatan tambahan per kategori (temuan di luar daftar kriteria → SIPOTRET "Item Lainnya") ──
    // Ditampilkan di bawah kriteria kategori aktif; disimpan langsung ke server (tidak menunggu Simpan Kategori).
    const LEVEL_BADGE = { minor: 'bg-azure-lt', moderate: 'bg-orange-lt', major: 'bg-red-lt' };
    let saranHtml = null;
    let catEditId = null; // id catatan yang sedang diedit; null = form untuk catatan baru
    let catItemMaster = null; // { id, item }: form berisi item master SIPOTRET (tanpa kriteria di pubchecker)
    const labelLevel = f => f || 'tanpa level';

    const catUrl  = key => el('section')?.dataset[key];
    // batas nama item (data-max-item); di SIPOTRET nama lengkap tersimpan di kolom catatan (TEXT)
    const maxItem = () => parseInt(catUrl('maxItem'), 10) || 2000;

    /**
     * Usulan item lainnya (acuan SIPOTRET) yang belum tersimpan sebagai catatan tambahan. Cocok bila keterangan
     * sama dan nama item sama, atau salah satunya awalan yang lain (nama lama yang tersimpan terpotong).
     */
    function usulanBelumDipakai(entry, u) {
        if (u.dipakai) return false;
        const norm = s => String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();
        const item = norm(u.item), ket = norm(u.keterangan);
        return !(entry?.catatan || []).some(c => {
            // usulan tanpa keterangan (kosong / "-" di SIPOTRET): petugas mengisi keterangannya saat "Pakai",
            // jadi cukup nama itemnya yang cocok
            if (ket && ket !== '-' && norm(c.keterangan) !== ket) return false;
            const a = norm(c.item);
            return a === item || (Math.min(a.length, item.length) >= 20 && (a.startsWith(item) || item.startsWith(a)));
        });
    }
    /** Penghitung "n / batas" di bawah nama item. */
    function sisaItem() {
        const inp = catById('revCatItem'), out = catById('revCatItemSisa');
        if (!inp || !out) return;
        inp.maxLength = maxItem();
        const max = inp.maxLength, n = inp.value.length;
        out.className = 'd-block text-end mb-1 ' + (n >= max ? 'text-danger' : 'text-secondary');
        out.textContent = `${n} / ${max} karakter`;
    }
    const catById = id => document.getElementById(id);

    /** Muat catatan publikasi (sekali per publikasi). */
    async function loadCatatan(entry) {
        if (!entry?.hasilId || !catUrl('catatanUrl') || entry.catatan) return;
        entry.catatan = [];
        try {
            const res = await fetch(catUrl('catatanUrl').replace('__ID__', entry.hasilId), { headers: { 'Accept': 'application/json' } });
            if (res.ok) entry.catatan = (await res.json()).catatan || [];
        } catch { /* tetap kosong */ }
        if (entry === entries[fileIndex]) { renderCatatanList(); gambarSemuaPin(); }
    }

    function loadSaran() {
        if (saranHtml !== null || !catUrl('catatanSaranUrl')) return;
        saranHtml = '';
        fetch(catUrl('catatanSaranUrl'), { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : { saran: [] })
            .then(d => {
                saranHtml = (d.saran || []).map(s => `<option value="${esc(s)}">`).join('');
                const dl = catById('revCatSaran');
                if (dl) dl.innerHTML = saranHtml;
            })
            .catch(() => {});
    }

    function kategoriAktif() {
        const entry = entries[fileIndex];
        return entry ? entry.groups[groupIndex].kategori : '';
    }

    /** Kotak catatan tambahan di bawah kriteria kategori aktif (dibuat ulang setiap renderGroup). */
    function renderCatatanBox() {
        const entry = entries[fileIndex];
        if (!entry?.hasilId || !catUrl('catatanUrl')) return;
        catEditId = null; // kotak baru (pindah kategori/publikasi): form kembali ke mode tambah
        catPos = null;
        gambarSemuaPin();
        loadSaran();

        el('items').insertAdjacentHTML('beforeend', `
            <div id="revCatatanBox" class="border border-warning rounded p-2 mb-2">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <small class="fw-semibold">
                        <i class="ti ti-notes text-warning"></i> Catatan tambahan <span id="revCatCount"></span>
                    </small>
                    <button type="button" class="btn btn-link btn-sm p-0" data-cat-tambah>
                        <i class="ti ti-plus"></i> Tambah
                    </button>
                </div>
                <div id="revCatList"></div>
                <small id="revCatInfo" class="d-block"></small>
                <div id="revCatForm" class="border-top pt-2 mt-2" hidden>
                    <input type="text" class="form-control form-control-sm mb-1" id="revCatItem" maxlength="${maxItem()}" list="revCatSaran"
                           placeholder="Nama item, mis. Penggunaan Bahasa Asing">
                    <small id="revCatItemSisa" class="d-block text-end text-secondary mb-1" style="margin-top:-2px;"></small>
                    <datalist id="revCatSaran">${saranHtml || ''}</datalist>
                    <div class="d-flex gap-1 mb-1">
                        <select class="form-select form-select-sm" id="revCatLevel" title="Level">
                            <option value="">Tanpa level (tidak perlu perbaikan)</option>
                            <option value="minor" selected>Minor</option>
                            <option value="moderate">Moderate</option>
                            <option value="major">Major</option>
                        </select>
                        <input type="number" class="form-control form-control-sm" id="revCatHal" min="1" placeholder="Hal." title="Halaman (opsional)" style="max-width:90px;">
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                        <button type="button" class="btn btn-sm btn-outline-warning py-0" data-cat-pin
                                title="Klik tombol ini, lalu klik area yang dikomentari di halaman PDF">
                            <i class="ti ti-map-pin"></i> Tandai di PDF
                        </button>
                        <small id="revCatPinInfo" class="text-secondary"></small>
                    </div>
                    <textarea class="form-control form-control-sm mb-1" id="revCatKet" rows="2" maxlength="2000"
                              placeholder="Keterangan, mis. Pada Tabel 4.2.5 istilah asing tidak dicetak miring"></textarea>
                    ${entries.length > 1 ? `
                    <label class="form-check mb-1" id="revCatSemuaWrap">
                        <input type="checkbox" class="form-check-input" id="revCatSemua">
                        <span class="form-check-label small">Tambahkan juga ke semua publikasi yang sedang ditinjau</span>
                    </label>` : ''}
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-warning btn-sm" data-cat-simpan><i class="ti ti-device-floppy"></i> <span id="revCatSimpanLbl">Simpan</span></button>
                        <button type="button" class="btn btn-sm" data-cat-batal>Batal</button>
                    </div>
                </div>
            </div>`);
        renderCatatanList();
        loadCatatan(entry);
    }

    /** Daftar catatan kategori aktif (tanpa mengganggu form yang sedang diisi). */
    function renderCatatanList() {
        const box = catById('revCatList');
        const entry = entries[fileIndex];
        if (!box || !entry) return;
        const kat  = kategoriAktif();
        // catatan tanpa kategori (mis. item lainnya dari acuan SIPOTRET) tampil di semua kategori
        const list = (entry.catatan || []).filter(c => !c.kategori || c.kategori === kat);
        const pins = pinCatatan(entry);
        catById('revCatCount').textContent = list.length ? `(${list.length})` : '';
        box.innerHTML = list.length
            ? list.map(c => `
                <div class="d-flex gap-2 align-items-start border-top pt-1 mt-1 small">
                    <div class="flex-fill" style="min-width:0;">
                        ${pins.includes(c) ? `<span class="badge bg-warning text-white" title="Penanda di halaman PDF">${pins.indexOf(c) + 1}</span>` : ''}
                        <span class="fw-semibold">${esc(c.item)}</span>
                        <span class="badge ${LEVEL_BADGE[c.flag_level] || 'bg-secondary-lt'}">${esc(labelLevel(c.flag_level))}</span>
                        ${c.item_periksa_id ? '<span class="badge bg-azure-lt" title="Dikirim ke SIPOTRET sebagai item master, bukan item custom">item SIPOTRET</span>' : ''}
                        <div class="text-secondary">${esc(c.keterangan)}</div>
                        ${c.halaman ? `
                        <div class="mt-1">
                            <i class="ti ${pins.includes(c) ? 'ti-map-pin-filled text-warning' : 'ti-file'}"></i>
                            Hal. ${c.halaman}${pins.includes(c) ? '' : ' <span class="text-secondary">(tanpa penanda)</span>'} ·
                            <a href="#" data-cat-hal="${c.halaman}" data-cat-id="${c.id}"
                               title="${pins.includes(c) ? 'Gulir ke penanda komentar ini di PDF' : 'Buka halaman ini di PDF'}">lihat</a>
                        </div>` : ''}
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost-primary btn-icon" data-cat-edit="${c.id}" title="Edit"><i class="ti ti-pencil"></i></button>
                    <button type="button" class="btn btn-sm btn-ghost-danger btn-icon" data-cat-hapus="${c.id}" title="Hapus"><i class="ti ti-trash"></i></button>
                </div>`).join('')
            : '<div class="small text-secondary">Temuan di kategori ini yang tidak ada di daftar kriteria akan dikirim ke SIPOTRET sebagai item lainnya.</div>';

        // usulan dari luar (mis. item lainnya SIPOTRET periode lalu) — baru tersimpan setelah "Pakai" lalu Simpan
        const usulan = (entry.usulanCatatan || []).map((u, i) => [u, i]).filter(([u]) => usulanBelumDipakai(entry, u));
        if (usulan.length) {
            box.insertAdjacentHTML('beforeend', `
                <div class="border-top pt-1 mt-1 small">
                    <div class="text-danger"><i class="ti ti-history"></i> Item lainnya dari SIPOTRET periode lalu:</div>
                    ${usulan.map(([u, i]) => `
                    <div class="d-flex gap-2 align-items-start mt-1">
                        <div class="flex-fill" style="min-width:0;">
                            <span class="fw-semibold">${esc(u.item)}</span>
                            ${u.flag_level ? `<span class="badge ${LEVEL_BADGE[u.flag_level] || 'bg-secondary-lt'}">${esc(u.flag_level)}</span>` : ''}
                            <div class="text-secondary">${esc(u.keterangan)}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-warning py-0" data-cat-usulan="${i}"
                                title="Isi form catatan tambahan dengan item ini (periksa dulu, lalu Simpan)">Pakai</button>
                    </div>`).join('')}
                </div>`);
        }
        renderMasalah(); // daftar Tidak Sesuai ikut menampilkan item lainnya
    }

    /**
     * Buka/tutup form catatan. Dengan `catatan`: mode edit (form diisi catatan itu);
     * tanpa: mode tambah (halaman diisi dari posisi viewer, atau dari `pos` = area yang diklik di PDF).
     */
    function bukaFormCatatan(buka, catatan = null, pos = null) {
        const form = catById('revCatForm');
        if (!form) return;
        const dariEdit = catEditId !== null;
        form.hidden = !buka;
        catById('revCatInfo').textContent = '';
        catEditId = buka && catatan ? catatan.id : null;
        // item master SIPOTRET yang sedang diisi (dari catatan yang diedit; "Pakai" usulan mengisinya setelah ini)
        catItemMaster = buka && catatan?.item_periksa_id ? { id: catatan.item_periksa_id, item: catatan.item } : null;
        if (catById('revCatItem')) catById('revCatItem').maxLength = maxItem();
        // nama item diganti → bukan item master lagi (batas custom); penghitung ikut diperbarui
        catById('revCatItem')?.addEventListener('input', sisaItem);
        catPos = !buka ? null
               : catatan ? (catatan.pos_x != null ? { halaman: catatan.halaman, x: catatan.pos_x, y: catatan.pos_y } : null)
               : pos;
        el('items').querySelectorAll('[data-cat-edit]').forEach(b => b.classList.toggle('active', +b.dataset.catEdit === catEditId));
        gambarSemuaPin();
        if (!buka) return;
        infoPinCatatan();

        if (catatan) {
            catById('revCatItem').value  = catatan.item;
            catById('revCatLevel').value = catatan.flag_level ?? '';
            catById('revCatHal').value   = catatan.halaman || '';
            catById('revCatKet').value   = catatan.keterangan || '';
        } else {
            if (dariEdit) { catById('revCatItem').value = ''; catById('revCatKet').value = ''; }
            if (pos) catById('revCatHal').value = pos.halaman;
            else try { catById('revCatHal').value = el('frame').contentWindow.PDFViewerApplication.page || ''; } catch { /* abaikan */ }
        }
        // "semua publikasi" hanya untuk catatan baru
        const semua = catById('revCatSemuaWrap');
        if (semua) semua.hidden = !!catatan;
        if (catById('revCatSemua')) catById('revCatSemua').checked = false;
        catById('revCatSimpanLbl').textContent = catatan ? 'Simpan Perubahan' : 'Simpan';
        sisaItem();
        catById('revCatItem').focus();
    }

    /** Simpan perubahan satu catatan (mode edit). */
    async function ubahCatatan(payload) {
        const entry = entries[fileIndex];
        const btn = el('items').querySelector('[data-cat-simpan]');
        btn.disabled = true;
        infoCatatan('text-secondary', 'Menyimpan…');
        try {
            const res = await fetch(catUrl('catatanHapusUrl').replace('__ID__', catEditId), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || `Server membalas ${res.status}`);
            entry.catatan = (entry.catatan || []).map(c => c.id === data.catatan.id ? data.catatan : c);
        } catch (err) {
            btn.disabled = false;
            infoCatatan('text-danger', 'Gagal menyimpan: ' + err.message);
            return;
        }
        btn.disabled = false;
        renderCatatanList();
        catById('revCatItem').value = '';
        catById('revCatKet').value = '';
        bukaFormCatatan(false);
        infoCatatan('text-success', 'Perubahan tersimpan.');
    }

    function infoCatatan(cls, text) {
        const info = catById('revCatInfo');
        if (info) { info.className = 'd-block mt-1 ' + cls; info.textContent = text; }
    }

    async function simpanCatatan() {
        const item = catById('revCatItem').value.trim();
        const ket  = catById('revCatKet').value.trim();
        if (!item || !ket) { infoCatatan('text-danger', 'Nama item dan keterangan wajib diisi.'); return; }

        const aktif   = entries[fileIndex];
        const halaman = parseInt(catById('revCatHal').value, 10) || null;
        // penanda hanya berlaku bila nomor halaman tidak diubah setelah area di PDF diklik
        const pos     = catPos && catPos.halaman === halaman ? catPos : null;
        const payload = {
            kategori: kategoriAktif(),
            item, keterangan: ket,
            flag_level: catById('revCatLevel').value || null,
            // tetap item master SIPOTRET selama nama itemnya tidak diganti petugas
            item_periksa_id: catItemMaster && catItemMaster.item === item ? catItemMaster.id : null,
            halaman,
            pos_x: pos ? pos.x : null,
            pos_y: pos ? pos.y : null,
        };
        if (catEditId !== null) { ubahCatatan(payload); return; }
        const targets = catById('revCatSemua')?.checked ? entries.filter(e => e.hasilId) : [aktif];

        const btn = el('items').querySelector('[data-cat-simpan]');
        btn.disabled = true;
        infoCatatan('text-secondary', 'Menyimpan…');
        const gagal = (await Promise.all(targets.map(async e => {
            try {
                const res = await fetch(catUrl('catatanUrl').replace('__ID__', e.hasilId), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    // nomor halaman hanya berlaku untuk publikasi yang sedang dibuka
                    body: JSON.stringify(e === aktif ? payload : { ...payload, halaman: null, pos_x: null, pos_y: null }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || `Server membalas ${res.status}`);
                (e.catatan ??= []).push(data.catatan);
                return null;
            } catch (err) {
                return err.message;
            }
        }))).filter(Boolean);
        btn.disabled = false;

        renderCatatanList();
        if (gagal.length) {
            infoCatatan('text-danger', (targets.length > 1 ? `Gagal di ${gagal.length} publikasi: ` : '') + gagal[0]);
            return;
        }
        catById('revCatItem').value = '';
        catById('revCatKet').value = '';
        bukaFormCatatan(false);
        infoCatatan('text-success', targets.length > 1 ? `Tersimpan di ${targets.length} publikasi.` : 'Catatan tersimpan.');
    }

    async function hapusCatatan(id) {
        if (!confirm('Hapus catatan tambahan ini?')) return;
        const entry = entries[fileIndex];
        const res = await fetch(catUrl('catatanHapusUrl').replace('__ID__', id), {
            method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        }).catch(() => null);
        if (!res?.ok) { infoCatatan('text-danger', 'Gagal menghapus catatan.'); return; }
        entry.catatan = (entry.catatan || []).filter(c => c.id !== id);
        if (id === catEditId) bukaFormCatatan(false);
        renderCatatanList();
        gambarSemuaPin();
    }

    /** Klik di kotak catatan (didelegasikan dari daftar kriteria). @returns true bila ditangani */
    function klikCatatan(e) {
        const t = e.target;
        // "Tambah": buka form kosong (juga keluar dari mode edit); klik lagi saat mode tambah = tutup
        if (t.closest('[data-cat-tambah]')) { bukaFormCatatan(catById('revCatForm')?.hidden || catEditId !== null); return true; }
        const usulan = t.closest('[data-cat-usulan]');
        if (usulan) {
            const u = entries[fileIndex].usulanCatatan?.[+usulan.dataset.catUsulan];
            if (!u) return true;
            bukaFormCatatan(true);
            // item master SIPOTRET: nama lengkap (maks. 150); item custom: batas SIPOTRET 50
            catById('revCatItem').value = String(u.item || '').slice(0, maxItem());
            catById('revCatLevel').value = u.flag_level || ''; // kosong di SIPOTRET = tidak perlu perbaikan
            catItemMaster = u.item_periksa_id ? { id: u.item_periksa_id, item: catById('revCatItem').value } : null;
            sisaItem();
            catById('revCatKet').value  = u.keterangan || '';
            u.dipakai = true;
            renderCatatanList();
            return true;
        }
        const edit = t.closest('[data-cat-edit]');
        if (edit) {
            const c = (entries[fileIndex].catatan || []).find(x => x.id === +edit.dataset.catEdit);
            if (c) bukaFormCatatan(true, c);
            return true;
        }
        if (t.closest('[data-cat-pin]')) { setPinMode(!(pinMode && pinTarget === PIN_CATATAN), PIN_CATATAN); return true; }
        if (t.closest('[data-cat-pin-hapus]')) {
            e.preventDefault();
            catPos = null;
            infoPinCatatan();
            gambarSemuaPin();
            return true;
        }
        if (t.closest('[data-cat-batal]'))  { bukaFormCatatan(false); return true; }
        if (t.closest('[data-cat-simpan]')) { simpanCatatan(); return true; }
        const hapus = t.closest('[data-cat-hapus]');
        if (hapus) { hapusCatatan(parseInt(hapus.dataset.catHapus, 10)); return true; }
        const hal = t.closest('[data-cat-hal]');
        if (hal) {
            e.preventDefault();
            const c = (entries[fileIndex].catatan || []).find(x => x.id === +hal.dataset.catId);
            keArea(parseInt(hal.dataset.catHal, 10), c?.pos_x, c?.pos_y, `c:${c?.id}`);
            return true;
        }
        return false;
    }

    // ── Komentar di halaman PDF: klik area di viewer → catatan tambahan dengan penanda di posisi itu ──
    // Posisi disimpan sebagai pecahan lebar/tinggi halaman (pos_x/pos_y), jadi tetap pas di semua zoom.
    const PIN_WARNA = { minor: '#4299e1', moderate: '#f76707', major: '#d63939' };
    const PIN_TIDAK_SESUAI = '#d63939';
    let pinMode   = false;
    let isiKomentar = true; // balon isi komentar di samping penanda (tombol "Isi komentar")
    let pinTarget = null; // kriteria yang sedang diberi penanda, PIN_CATATAN (form catatan), atau null (catatan baru)
    let catPos    = null; // penanda untuk form catatan yang sedang dibuka: { halaman, x, y }
    const PIN_CATATAN = { id: 'catatan tambahan' }; // target: penanda untuk form catatan yang sedang dibuka

    /** Info penanda di form catatan tambahan. */
    function infoPinCatatan() {
        const info = catById('revCatPinInfo');
        if (!info) return;
        info.innerHTML = catPos
            ? `<i class="ti ti-map-pin-filled text-warning"></i> Hal. ${catPos.halaman} ·
               <a href="#" class="text-danger" data-cat-pin-hapus>hapus</a>`
            : 'Belum ada penanda di PDF';
        catById('revCatForm')?.querySelector('[data-cat-pin]')?.classList.toggle('active', pinTarget === PIN_CATATAN);
    }

    /** Catatan publikasi yang punya penanda; urutannya = nomor penanda. */
    function pinCatatan(entry) {
        return (entry?.catatan || []).filter(c => c.halaman && c.pos_x != null && c.pos_y != null);
    }

    /** Status kriteria seperti di layar: pilihan petugas yang belum disimpan, atau status tersimpan. */
    function statusEfektif(c) {
        return c.selStatus !== undefined ? c.selStatus : c.status;
    }

    /** Kriteria Tidak Sesuai yang punya penanda di PDF. */
    function pinKriteria(entry) {
        return (entry?.checks || []).filter(c => c.pin?.hal && statusEfektif(c) === TIDAK_SESUAI);
    }

    function pinInfoHtml(c) {
        return c.pin?.hal
            ? `<i class="ti ti-map-pin-filled text-danger"></i> Hal. ${c.pin.hal} ·
               <a href="#" data-pin-lihat>lihat</a> · <a href="#" class="text-danger" data-pin-hapus>hapus</a>`
            : 'Belum ada penanda di PDF';
    }

    /** @param {object|null} target kriteria yang akan diberi penanda; null = catatan tambahan */
    function setPinMode(on, target = null) {
        pinMode   = on;
        pinTarget = on ? target : null;
        const btn = el('btnPin');
        if (btn) {
            btn.classList.toggle('active', on);
            btn.innerHTML = !on ? '<i class="ti ti-message-plus me-1"></i> Komentar di PDF'
                : pinTarget ? `<i class="ti ti-pointer me-1"></i> Klik area untuk ${esc(pinTarget.id)}… (Esc batal)`
                : '<i class="ti ti-pointer me-1"></i> Klik area di PDF… (Esc batal)';
        }
        const items = entries[fileIndex] ? currentGroupItems() : [];
        el('items')?.querySelectorAll('[data-idx]').forEach(card => {
            card.querySelector('[data-pin-kriteria]')?.classList.toggle('active', !!pinTarget && items[+card.dataset.idx] === pinTarget);
        });
        catById('revCatForm')?.querySelector('[data-cat-pin]')?.classList.toggle('active', pinTarget === PIN_CATATAN);
        try { el('frame').contentWindow.document.body.classList.toggle('pc-pin-mode', on); } catch { /* viewer belum siap */ }
    }

    /** Tombol penanda di kartu kriteria Tidak Sesuai. @returns true bila ditangani */
    function klikPinKriteria(e) {
        const t = e.target.closest('[data-pin-kriteria], [data-pin-lihat], [data-pin-hapus]');
        const card = t?.closest('[data-idx]');
        if (!card) return false;
        e.preventDefault();
        const c = currentGroupItems()[+card.dataset.idx];
        if (!c) return true;
        if (t.matches('[data-pin-kriteria]')) {
            setPinMode(!(pinMode && pinTarget === c), c);
        } else if (t.matches('[data-pin-lihat]')) {
            keArea(c.pin.hal, c.pin.x, c.pin.y, `k:${c.id}`);
        } else {
            c.pin = null;
            card.querySelector('.rev-pin-info').innerHTML = pinInfoHtml(c);
            gambarSemuaPin();
            simpanPinKriteria(c, card);
        }
        return true;
    }

    /** Simpan penanda kriteria langsung ke server (tidak menunggu Simpan Kategori). */
    async function simpanPinKriteria(c, card) {
        const entry = entries[fileIndex];
        const info  = card?.querySelector('.rev-pin-info');
        if (!entry?.hasilId || !catUrl('pinUrl')) return;
        try {
            const res = await fetch(catUrl('pinUrl').replace('__ID__', entry.hasilId), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ kriteria_id: c.id, pin_halaman: c.pin?.hal ?? null, pin_x: c.pin?.x ?? null, pin_y: c.pin?.y ?? null }),
            });
            if (!res.ok) throw new Error(`Server membalas ${res.status}`);
        } catch (err) {
            if (info) info.innerHTML = pinInfoHtml(c) + ` <span class="text-danger">(gagal disimpan: ${esc(err.message)})</span>`;
        }
    }

    /**
     * Gulir viewer ke posisi penanda (pecahan halaman) lalu kedipkan penandanya.
     * `kunci` = data-pin-key penanda ('k:<kode kriteria>' / 'c:<id catatan>').
     */
    function keArea(hal, x, y, kunci) {
        hal = parseInt(hal, 10);
        const win = el('frame')?.contentWindow;
        const app = win?.PDFViewerApplication;
        if (!app?.pdfViewer || !hal) return;
        try {
            const view = app.pdfViewer.getPageView(hal - 1);
            if (view?.viewport && x != null && y != null) {
                const vp = view.viewport;
                const [, py] = vp.convertToPdfPoint(x * vp.width, y * vp.height);
                // sisakan ±120px di atas penanda supaya tidak menempel di tepi atas viewer
                app.pdfViewer.scrollPageIntoView({
                    pageNumber: hal,
                    destArray: [null, { name: 'XYZ' }, null, py + 120 / (vp.scale || 1), null],
                });
            } else {
                app.page = hal;
            }
        } catch { try { app.page = hal; } catch { /* viewer belum siap */ } }

        // penanda baru tergambar setelah halaman dirender — tunggu sebentar sebelum dikedipkan
        setTimeout(() => {
            const pin = win.document.querySelector(`.pc-pin[data-pin-key="${CSS.escape(kunci)}"]`);
            if (!pin) return;
            pin.classList.remove('pc-sorot');
            void pin.offsetWidth;
            pin.classList.add('pc-sorot');
        }, 350);
    }

    /** Penanda kriteria di PDF diklik: buka kategorinya dan sorot kartunya. */
    function bukaPinKriteria(c) {
        const entry = entries[fileIndex];
        const i = entry.checks.indexOf(c);
        const g = entry.groups.findIndex(gr => gr.idx.includes(i));
        if (g < 0) return;
        setPinMode(false);
        if (g !== groupIndex) {
            collectGroup();
            groupIndex = g;
            renderGroup();
            try { el('frame').contentWindow.PDFViewerApplication.page = c.pin.hal; } catch { /* abaikan */ }
        }
        const card = el('items').querySelector(`[data-idx="${entry.groups[g].idx.indexOf(i)}"]`);
        if (!card) return;
        card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        card.classList.add('border-danger');
        setTimeout(() => card.classList.remove('border-danger'), 1500);
    }

    /** Pasang gaya, klik halaman & gambar ulang penanda tiap halaman dirender (zoom membuang elemen tambahan). */
    function pasangPin(win) {
        const doc = win.document;
        win.__pcHasilId = entries[fileIndex]?.hasilId;
        const st = doc.createElement('style');
        st.textContent = `
            body.pc-pin-mode .page { cursor: crosshair; }
            body.pc-pin-mode .textLayer, body.pc-pin-mode .annotationLayer { pointer-events: none; }
            .pc-pin { position: absolute; z-index: 30; width: 24px; height: 24px; transform: translate(-50%, -50%);
                      border-radius: 50%; border: 2px solid #fff; color: #fff; font: bold 12px/20px sans-serif;
                      text-align: center; cursor: pointer; box-shadow: 0 1px 4px rgba(0,0,0,.5); }
            .pc-pin:hover { transform: translate(-50%, -50%) scale(1.2); }
            .pc-pin.pc-baru { background: #f59f00; border-style: dashed; animation: pc-denyut 1s infinite alternate; }
            @keyframes pc-denyut { to { box-shadow: 0 0 0 6px rgba(245,159,0,.35); } }
            .pc-bubble { position: absolute; z-index: 29; max-width: 230px; padding: 4px 7px; border-radius: 6px;
                         background: #fff; color: #1e293b; font: 11px/1.35 sans-serif; cursor: pointer;
                         border-left: 4px solid var(--pc-warna, #d63939); box-shadow: 0 2px 6px rgba(0,0,0,.35);
                         display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
                         white-space: pre-line; transform: translate(16px, -50%); }
            .pc-bubble.pc-kiri { transform: translate(calc(-100% - 16px), -50%); }
            .pc-bubble:hover { -webkit-line-clamp: unset; z-index: 31; max-width: 320px; }
            .pc-bubble b { display: block; }
            body.pc-tanpa-isi .pc-bubble { display: none; }
            .pc-pin.pc-sorot { animation: pc-sorot .5s ease-in-out 4 alternate; }
            @keyframes pc-sorot { to { transform: translate(-50%, -50%) scale(1.8); box-shadow: 0 0 0 10px rgba(214,57,57,.35); } }`;
        doc.head.appendChild(st);
        if (pinMode) doc.body.classList.add('pc-pin-mode');
        doc.body.classList.toggle('pc-tanpa-isi', !isiKomentar);

        win.PDFViewerApplication.eventBus.on('pagerendered', ({ source, pageNumber }) => gambarPin(source.div, pageNumber));

        doc.addEventListener('click', e => {
            if (!pinMode) return;
            const page = e.target.closest?.('.page');
            if (!page || e.target.closest('.pc-pin')) return;
            e.preventDefault();
            e.stopPropagation();
            const r = page.getBoundingClientRect();
            const frac = v => Math.round(Math.min(1, Math.max(0, v)) * 10000) / 10000;
            klikHalaman({
                halaman: parseInt(page.dataset.pageNumber, 10),
                x: frac((e.clientX - r.left - page.clientLeft) / page.clientWidth),
                y: frac((e.clientY - r.top - page.clientTop) / page.clientHeight),
            });
        }, true);
        doc.addEventListener('keydown', e => { if (e.key === 'Escape' && pinMode) setPinMode(false); });
    }

    function gambarPin(pageDiv, halaman) {
        if (!pageDiv) return;
        pageDiv.querySelectorAll('.pc-pin, .pc-bubble').forEach(p => p.remove());
        const doc = pageDiv.ownerDocument;
        const tambah = (x, y, cls, isi, title, onClick, kunci = '') => {
            const pin = doc.createElement('div');
            pin.className = 'pc-pin ' + cls;
            pin.dataset.pinKey = kunci;
            pin.style.left = (x * 100) + '%';
            pin.style.top  = (y * 100) + '%';
            pin.textContent = isi;
            pin.title = title;
            pin.addEventListener('click', ev => { ev.stopPropagation(); onClick?.(); });
            return pin;
        };
        // balon isi komentar di samping penanda (di kiri bila penanda dekat tepi kanan halaman)
        const balon = (x, y, warna, judul, isi, onClick) => {
            const b = doc.createElement('div');
            b.className = 'pc-bubble' + (x > 0.6 ? ' pc-kiri' : '');
            b.style.left = (x * 100) + '%';
            b.style.top  = (y * 100) + '%';
            b.style.setProperty('--pc-warna', warna);
            const j = doc.createElement('b');
            j.textContent = judul;
            b.append(j, isi || '');
            b.addEventListener('click', ev => { ev.stopPropagation(); onClick?.(); });
            pageDiv.appendChild(b);
        };
        pinCatatan(entries[fileIndex]).forEach((c, n) => {
            if (+c.halaman !== halaman || (catPos && c.id === catEditId)) return; // yang sedang dipindah: tampil sebagai penanda baru
            const warna = PIN_WARNA[c.flag_level] || '#667382';
            const pin = tambah(c.pos_x, c.pos_y, '', n + 1, `${c.item}\n${c.keterangan || ''}`, () => bukaPin(c.id), `c:${c.id}`);
            pin.style.background = warna;
            pageDiv.appendChild(pin);
            balon(c.pos_x, c.pos_y, warna, c.item, c.keterangan, () => bukaPin(c.id));
        });
        pinKriteria(entries[fileIndex]).forEach(c => {
            if (+c.pin.hal !== halaman) return;
            const ket = c.selKet ?? c.keterangan ?? '';
            const pin = tambah(c.pin.x, c.pin.y, '', '!', `${c.deskripsi}\nTidak sesuai: ${ket}`, () => bukaPinKriteria(c), `k:${c.id}`);
            pin.style.background = PIN_TIDAK_SESUAI;
            pin.style.borderRadius = '4px';
            pageDiv.appendChild(pin);
            balon(c.pin.x, c.pin.y, PIN_TIDAK_SESUAI, c.deskripsi, ket ? `Tidak sesuai: ${ket}` : '', () => bukaPinKriteria(c));
        });
        if (catPos?.halaman === halaman) {
            pageDiv.appendChild(tambah(catPos.x, catPos.y, 'pc-baru', '+', 'Komentar yang sedang ditulis'));
        }
    }

    function gambarSemuaPin() {
        let app;
        try { app = el('frame').contentWindow.PDFViewerApplication; } catch { return; }
        // viewer lama (sebelum publikasi baru selesai dimuat) tidak digambari
        if (!app?.pdfViewer || el('frame').contentWindow.__pcHasilId !== entries[fileIndex]?.hasilId) return;
        for (let i = 1; i <= app.pagesCount; i++) gambarPin(app.pdfViewer.getPageView(i - 1)?.div, i);
    }

    /** Area di halaman PDF diklik: form catatan baru, atau pindahkan penanda catatan yang sedang diedit. */
    function klikHalaman(pos) {
        const target = pinTarget;
        setPinMode(false);
        if (target === PIN_CATATAN) {
            // penanda untuk form catatan yang sedang dibuka (baru maupun edit); tersimpan saat catatan disimpan
            if (catById('revCatForm')?.hidden !== false) bukaFormCatatan(true, null, pos);
            catPos = pos;
            catById('revCatHal').value = pos.halaman;
            infoPinCatatan();
            gambarSemuaPin();
            catById('revCatatanBox')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            return;
        }
        if (target) {
            target.pin = { hal: pos.halaman, x: pos.x, y: pos.y };
            const items = currentGroupItems();
            const card = [...el('items').querySelectorAll('[data-idx]')].find(k => items[+k.dataset.idx] === target);
            const info = card?.querySelector('.rev-pin-info');
            if (info) info.innerHTML = pinInfoHtml(target);
            gambarSemuaPin();
            simpanPinKriteria(target, card);
            return;
        }
        if (!catById('revCatForm')) return;
        if (catEditId !== null && !catById('revCatForm').hidden) {
            catPos = pos;
            catById('revCatHal').value = pos.halaman;
            infoPinCatatan();
            gambarSemuaPin();
        } else {
            bukaFormCatatan(true, null, pos);
        }
        catById('revCatatanBox')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    /** Penanda diklik: buka catatannya untuk diedit (pindah ke kategorinya bila perlu). */
    function bukaPin(id) {
        const entry = entries[fileIndex];
        const c = (entry.catatan || []).find(x => x.id === id);
        if (!c) return;
        setPinMode(false);
        const g = entry.groups.findIndex(gr => gr.kategori === (c.kategori || ''));
        if (g >= 0 && g !== groupIndex) {
            collectGroup();
            groupIndex = g;
            renderGroup();
            try { el('frame').contentWindow.PDFViewerApplication.page = c.halaman; } catch { /* abaikan */ }
        }
        bukaFormCatatan(true, c);
        catById('revCatatanBox')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    // ── Unduh PDF dengan catatan tambahan tertanam sebagai anotasi komentar (sticky note) ──
    // pdf-lib dimuat hanya saat dibutuhkan; lokasinya relatif terhadap skrip ini (app bisa di subfolder).
    const PDFLIB_URL = document.currentScript
        ? new URL('../assets/vendors/pdf-lib/pdf-lib.min.js', document.currentScript.src).href : null;
    let pdfLibPromise = null;

    function muatPdfLib() {
        if (window.PDFLib) return Promise.resolve(window.PDFLib);
        pdfLibPromise ??= new Promise((ok, gagal) => {
            const s = document.createElement('script');
            s.src = PDFLIB_URL;
            s.onload  = () => ok(window.PDFLib);
            s.onerror = () => { pdfLibPromise = null; gagal(new Error('pustaka pdf-lib gagal dimuat')); };
            document.head.appendChild(s);
        });
        return pdfLibPromise;
    }

    /**
     * Posisi penanda (pecahan halaman seperti tampil di viewer) → koordinat PDF.
     * Viewer menampilkan CropBox yang sudah diputar sesuai /Rotate halaman, jadi rotasinya dibalik di sini.
     */
    function titikPdf(page, u, v) {
        const { x, y, width: w, height: h } = page.getCropBox();
        const rot = ((page.getRotation().angle % 360) + 360) % 360;
        const [fx, fy] = rot === 90  ? [v, u]
                       : rot === 180 ? [1 - u, v]
                       : rot === 270 ? [1 - v, 1 - u]
                       :               [u, 1 - v];
        return [x + fx * w, y + fy * h];
    }

    /**
     * Cari posisi potongan teks (lokasi temuan cek otomatis) di halaman PDF lewat pdf.js viewer
     * yang sedang terbuka (teks OCR ikut, lihat injectOcrText). Hasil: koordinat PDF [x, y] atau null.
     */
    async function posisiTeks(entry, hal, teks) {
        const norm = s => String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();
        const cari = norm(teks);
        if (!cari) return null;
        try {
            const win = el('frame').contentWindow;
            if (win.__pcHasilId !== entry.hasilId || !win.PDFViewerApplication?.pdfDocument) return null;
            const page = await win.PDFViewerApplication.pdfDocument.getPage(hal);
            const items = (await page.getTextContent()).items.filter(it => it.str?.trim());
            const awal = cari.slice(0, 25);
            const it = items.find(i => norm(i.str).includes(awal)) || items.find(i => cari.includes(norm(i.str)) && norm(i.str).length > 3);
            return it ? [it.transform[4], it.transform[5] + (it.height || 10)] : null;
        } catch { return null; }
    }

    /** Halaman untuk kriteria Tidak Sesuai: lokasi temuan, atau halaman target kriteria. */
    function halamanKriteria(c, total) {
        if (c.lokasi?.[0]?.hal) return Math.min(c.lokasi[0].hal, total);
        return c.target === 'page2' ? Math.min(2, total) : c.target === 'last' ? total : 1;
    }

    async function unduhPdfKomentar() {
        const entry = entries[fileIndex];
        const btn = el('btnUnduhPdf');
        if (!entry || btn.disabled) return;
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader-2 icon-spin me-1"></i> Menyiapkan…';
        try {
            collectGroup(); // pilihan di layar yang belum disimpan ikut
            await loadCatatan(entry);
            const { PDFDocument, PDFString, PDFHexString } = await muatPdfLib();
            const res = await fetch(asalSama(entry.url), { credentials: 'same-origin' });
            if (!res.ok) throw new Error(`PDF tidak bisa diambil (${res.status})`);
            const pdf   = await PDFDocument.load(await res.arrayBuffer(), { ignoreEncryption: true });
            const pages = pdf.getPages();
            const pins  = pinCatatan(entry);
            // halaman → jumlah komentar tanpa posisi, ditumpuk di pojok (catatan kiri, kriteria kanan)
            const tumpuk = { kiri: {}, kanan: {} };
            const sudut = (sisi, hal) => {
                const k = tumpuk[sisi][hal] = (tumpuk[sisi][hal] || 0) + 1;
                return [sisi === 'kiri' ? 0.04 : 0.96, Math.min(0.96, 0.02 + k * 0.04)];
            };
            const rgb = hex => hex.match(/\w\w/g).map(h => parseInt(h, 16) / 255);

            // komentar: { hal, xy (koordinat PDF) | uv (pecahan halaman), judul, isi, oleh, warna, nm }
            const komentar = [];

            for (const c of entry.checks) {
                if (statusEfektif(c) !== TIDAK_SESUAI) continue;
                // penanda petugas → lokasi temuan cek otomatis → pojok halaman target kriteria
                const pin = c.pin?.hal && c.pin.hal <= pages.length ? c.pin : null;
                const hal = pin ? pin.hal : halamanKriteria(c, pages.length);
                const xy  = !pin && c.lokasi?.[0] ? await posisiTeks(entry, hal, c.lokasi[0].teks) : null;
                komentar.push({
                    hal, xy, uv: pin ? [pin.x, pin.y] : xy ? null : sudut('kanan', hal),
                    judul: 'Tidak sesuai',
                    isi:   `${c.deskripsi}\nTidak sesuai: ${c.selKet || c.keterangan || '-'}`,
                    warna: rgb(PIN_TIDAK_SESUAI),
                    nm:    `pubchecker-kriteria-${c.id}`,
                });
            }

            for (const c of (entry.catatan || []).filter(c => c.halaman && c.halaman <= pages.length)) {
                const n = pins.indexOf(c) + 1;
                komentar.push({
                    hal: c.halaman, xy: null,
                    uv: n ? [c.pos_x, c.pos_y] : sudut('kiri', c.halaman),
                    judul: c.item,
                    isi:   `${c.item}\n${c.keterangan || ''}`,
                    oleh:  c.oleh,
                    warna: rgb(PIN_WARNA[c.flag_level] || '#667382'),
                    nm:    `pubchecker-catatan-${c.id}`,
                });
            }

            for (const k of komentar) {
                const page = pages[k.hal - 1];
                const [px, py] = k.xy || titikPdf(page, k.uv[0], k.uv[1]);
                const annot = pdf.context.obj({
                    Type: 'Annot', Subtype: 'Text', Name: 'Comment',
                    Rect: [px - 10, py - 10, px + 10, py + 10],
                    Contents: PDFHexString.fromText(k.isi),
                    T: PDFHexString.fromText(k.oleh || 'Pubchecker'),
                    Subj: PDFHexString.fromText(k.judul),
                    NM: PDFString.of(k.nm),
                    M: PDFString.fromDate(new Date()),
                    C: k.warna,
                    F: 4 | 8 | 16, // Print | NoZoom | NoRotate
                    Open: false,
                });
                page.node.addAnnot(pdf.context.register(annot));
            }

            const blob = new Blob([await pdf.save()], { type: 'application/pdf' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url;
            a.download = entry.filename.replace(/\.pdf$/i, '') + ' - komentar.pdf';
            a.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            if (!komentar.length) alert('Belum ada kriteria Tidak Sesuai atau catatan berhalaman, PDF diunduh tanpa komentar.');
        } catch (err) {
            console.error(err);
            alert('Gagal membuat PDF dengan komentar: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = label;
        }
    }

    /** Publikasi lain (setelah yang aktif, berputar) yang masih punya kategori belum ditinjau. */
    function nextUnfinishedFile() {
        for (let k = 1; k < entries.length; k++) {
            const j = (fileIndex + k) % entries.length;
            if (firstUnreviewedGroup(entries[j]) !== null) return j;
        }
        return null;
    }

    function next() {
        if (cara === 'pilih') { selesaiPilih(); return; }
        withSave(() => {
            const entry = entries[fileIndex];
            if (groupIndex < entry.groups.length - 1) {
                groupIndex++;
                renderGroup();
                return;
            }
            const j = nextUnfinishedFile();
            if (j !== null) switchFile(j);
            else finish();
        });
    }

    /** Pindah publikasi (tab). Pilihan yang belum disimpan tetap diingat di memori. */
    function switchFile(j, group = null) {
        if (!entries[j] || j === fileIndex) return;
        collectGroup();
        fileIndex = j;
        loadFile(group ?? firstUnreviewedGroup(entries[j]) ?? 0);
    }

    function renderFileTabs() {
        const box = el('fileTabs');
        if (!box) return;
        if (entries.length < 2) { box.hidden = true; return; }
        box.hidden = false;
        box.innerHTML = entries.map((e, i) => {
            const done  = e.groups.filter(g => isGroupReviewed(e, g)).length;
            const total = e.groups.length;
            return `
                <li class="nav-item">
                    <a href="#" class="nav-link ${i === fileIndex ? 'active' : ''}" data-file="${i}" title="${esc(e.filename)}"
                       ${i === fileIndex ? 'aria-current="page"' : ''}>
                        <span class="rev-tab-no">${i + 1}</span>
                        <span class="rev-tab-judul">${esc(e.filename)}</span>
                        ${e.menunggu ? '<i class="ti ti-loader-2 icon-spin text-azure" title="Cek otomatis berjalan"></i>' : ''}
                        ${done === total ? '<i class="ti ti-circle-check text-green"></i>' : ''}
                        <span class="badge ${done === total ? 'bg-green-lt' : 'bg-secondary-lt'}" title="Kategori selesai ditinjau">${done}/${total}</span>
                    </a>
                </li>`;
        }).join('');
        // tab aktif selalu terlihat walau tabnya banyak (digeser ke samping)
        const aktif = box.querySelector('.nav-link.active');
        if (aktif) box.scrollLeft = Math.max(0, aktif.parentElement.offsetLeft - (box.clientWidth - aktif.parentElement.offsetWidth) / 2);
    }

    /** Simpan kategori yang sedang dibuka lalu keluar — sisanya bisa dilanjutkan dari Riwayat. */
    function saveAndExit() {
        // mode pilih: simpan kriteria yang sedang dibuka (bila ada pilihan) lalu keluar, sisanya tidak diubah
        if (cara === 'pilih') { simpanFokus({ bolehKosong: true }).then(ok => { if (ok) finish(); }); return; }
        withSave(finish);
    }

    function prev() {
        collectGroup();
        if (groupIndex > 0) {
            groupIndex--;
            renderGroup();
        } else if (fileIndex > 0) {
            switchFile(fileIndex - 1, entries[fileIndex - 1].groups.length - 1);
        }
    }

    /**
     * Isi awal tinjauan dari catatan SIPOTRET periode terakhir (SipotretService::isianTerakhir):
     * kriteria yang tercatat → pilihan "Tidak Sesuai" + keterangannya (belum tersimpan sampai disimpan);
     * item lainnya → usulan catatan tambahan (r._usulanCatatan). Dipasang sekali saat hasil pemeriksaan
     * datang, supaya membuka ulang tinjauan tidak menimpa yang sudah disimpan.
     * Dipakai halaman Publikasi Bermasalah & detail publikasi ("lanjutkan dari SIPOTRET").
     */
    const BULAN_PENDEK = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const BULAN_PANJANG = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    function terapkanIsian(r, isian) {
        if (!r || !isian) return;
        const asal = `Catatan SIPOTRET ${BULAN_PENDEK[isian.periode.bulan]} ${isian.periode.tahun}`;
        let diisi = 0, sudahDitinjau = 0;
        (r.checks || []).forEach(c => {
            if (!(c.id in isian.kriteria)) return;
            // yang sudah ditinjau & disimpan petugas (mis. tinjauan dilanjutkan dari Riwayat) tidak ditimpa,
            // begitu juga pilihan yang sudah diklik petugas di layar (saat acuan diganti)
            const ket = isian.kriteria[c.id];
            if (c.reviewed) {
                sudahDitinjau++;
                // tersimpan Sesuai/Skip padahal tercatat di acuan: ditandai supaya bisa dibandingkan (lihat terapkanBedaAcuan)
                if (c.status !== TIDAK_SESUAI) { c.bedaAcuan = true; c.acuanKet = ket; c.acuanAsal = asal; }
                return;
            }
            if (c.dipilih) return;
            c.selStatus     = TIDAK_SESUAI;
            c.selKet        = ket;
            c.isianSipotret = ket ? `${asal}: ${ket}` : `${asal} (tanpa keterangan, isi dulu)`;
            diisi++;
        });
        // kriteria acuan yang tidak ada di hasil pemeriksaan ini (mis. kriteria ditambahkan setelah diperiksa):
        // jangan hilang, jadikan usulan catatan tambahan
        const ada = new Set((r.checks || []).map(c => c.id));
        const takAda = Object.keys(isian.kriteria).filter(k => !ada.has(k));
        r._usulanCatatan = [
            ...(isian.lainnya || []).map(u => ({ ...u })),
            ...takAda.map(k => ({ item: isian.nama?.[k] || k, keterangan: isian.kriteria[k] || '',
                flag_level: isian.flag?.[k] ?? null, item_periksa_id: isian.itemId?.[k] ?? null })),
        ];
        // ringkasan untuk keterangan di panel (renderIsianInfo): ikut disalin ke entry sebagai isianInfo
        // bila `r` hasil pemeriksaan (Publikasi Bermasalah / detail); di Riwayat `r` sudah entry-nya
        r.isianInfo = { periode: isian.periode, diisi, sudahDitinjau, lainnya: r._usulanCatatan.length,
            rincian: isian.rincian ? { ...isian.rincian, takAda: takAda.length, kriteria: Object.keys(isian.kriteria).length } : null };
    }

    const namaPeriode = p => `${BULAN_PANJANG[p.bulan] || p.bulan} ${p.tahun}`;

    /** Kenapa jumlah catatan acuan bisa berbeda dari jumlah kriteria Tidak Sesuai. */
    function rincianAcuan(r) {
        if (!r) return '';
        const bagian = [
            `${r.kriteria} kriteria`,
            r.kedua ? `${r.kedua} catatan lain untuk kriteria yang sudah terisi (jadi item lainnya, tetap terpisah)` : '',
            r.dobel ? `${r.dobel} catatan dobel (keterangan sama persis) dihitung sekali` : '',
            r.lainnya ? `${r.lainnya} item lainnya` : '',
            r.tanpaKriteria ? `${r.tanpaKriteria} item yang tidak cocok dengan kriteria mana pun (jadi item lainnya)` : '',
            r.takAda ? `${r.takAda} kriteria tidak ada di hasil pemeriksaan ini (jadi item lainnya)` : '',
        ].filter(Boolean);
        return `${r.catatan} catatan di SIPOTRET: ${bagian.join(', ')}.`;
    }

    /**
     * Keterangan asal isian awal publikasi aktif: periode SIPOTRET acuan & jumlah kriteria yang diisi.
     * Bila entry membawa acuanPilihan (tinjauan dari Riwayat), petugas bisa memilih/mengganti acuan;
     * pilihannya disimpan di hasil (PATCH acuan-sipotret) sehingga tetap dipakai saat dilanjutkan lagi.
     */
    function renderIsianInfo() {
        const box = el('isianInfo');
        const entry = entries[fileIndex];
        if (!box || !entry) return;
        const info = entry.isianInfo;
        const bisaPilih = Array.isArray(entry.acuanPilihan) && entry.acuanPilihan.length && entry.hasilId && catUrl('acuanUrl');
        const adaInfo = info && (info.diisi || info.lainnya || info.sudahDitinjau);
        if (!adaInfo && !bisaPilih && !entry.acuan) { box.hidden = true; return; }

        const acuan = entry.acuan || info?.periode || null;

        // satu baris di judul panel; penjelasan lengkap di tooltip supaya kolom kriteria tetap lega
        const jelas = [
            acuan ? `Isian awal dari catatan SIPOTRET ${namaPeriode(acuan)}.` : 'Belum ada acuan isian awal dari SIPOTRET.',
            info?.diisi ? `${info.diisi} kriteria yang belum ditinjau diisi Tidak Sesuai beserta keterangannya.` : '',
            info?.lainnya ? `${info.lainnya} usulan item lainnya di catatan tambahan.` : '',
            info?.sudahDitinjau ? `${info.sudahDitinjau} kriteria lain dari catatan itu sudah ditinjau sebelumnya, tidak diubah.` : '',
            rincianAcuan(info?.rincian),
            acuan && !adaInfo ? 'Tidak ada catatan yang bisa dipakai dari periode ini.' : '',
            acuan ? 'Periksa ulang di PDF, lalu ubah bila sudah diperbaiki.' : '',
            bisaPilih ? 'Acuan bisa diganti; pilihan disimpan di Riwayat.' : '',
        ].filter(Boolean).join('\n');
        const jumlah = [info?.diisi ? `${info.diisi} kriteria diisi` : '', info?.lainnya ? `${info.lainnya} item lainnya` : '']
            .filter(Boolean).join(', ');
        const { kriteria: belumKriteria, usulan: belumUsulan } = isianBelumDisimpan(entry);
        const nBelum = belumKriteria.length + belumUsulan.length;
        const nCek = acuanBelumDicek(entry).length;
        const beda = daftarBedaAcuan(entry);

        box.hidden = false;
        box.title = jelas;
        box.innerHTML = `<i class="ti ti-history text-azure"></i><span class="text-secondary">Acuan SIPOTRET</span>
            ${bisaPilih ? `
                <select id="revAcuanSel" class="form-select form-select-sm py-0 w-auto" data-acuan aria-label="Acuan isian awal SIPOTRET">
                    <option value="">Tanpa acuan</option>
                    ${entry.acuanPilihan.map(p => `<option value="${p.bulan}-${p.tahun}" ${acuan && acuan.bulan === p.bulan && acuan.tahun === p.tahun ? 'selected' : ''}>
                        ${esc(namaPeriode(p))} (${p.catatan} catatan)</option>`).join('')}
                </select>`
            : `<strong>${acuan ? esc(namaPeriode(acuan)) : '-'}</strong>`}
            ${jumlah ? `<span class="text-secondary">· ${esc(jumlah)}</span>` : ''}
            <i class="ti ti-info-circle text-secondary" title="${esc(jelas)}"></i>
            ${aksiAcuanHtml(entry, beda, nCek, nBelum, belumKriteria, belumUsulan)}`;
    }

    /**
     * Satu tombol "Aksi acuan" berisi aksi terhadap isian dari acuan SIPOTRET (simpan semua, cek otomatis,
     * beda dari acuan), masing-masing dengan penjelasan — supaya judul panel tidak penuh tombol.
     */
    function aksiAcuanHtml(entry, beda, nCek, nBelum, belumKriteria, belumUsulan) {
        const item = (attr, ikon, judul, jelas) => `
            <button type="button" class="dropdown-item d-block text-wrap" ${attr}>
                <i class="ti ti-${ikon} me-1"></i><strong>${judul}</strong>
                <div class="small opacity-75 ms-4">${jelas}</div>
            </button>`;
        const aksi = [
            nBelum && entry.hasilId ? item('data-acuan-simpan', 'device-floppy', `Simpan semua dari acuan (${nBelum})`,
                `Simpan sekaligus ${belumKriteria.length} kriteria${belumUsulan.length ? ` dan ${belumUsulan.length} item lainnya` : ''} dari acuan tanpa membuka satu per satu.`) : '',
            nCek ? item('data-acuan-cek', 'robot', `Cek otomatis acuan (${nCek})`,
                'Bandingkan dengan cek otomatis: yang sudah lolos dipilihkan Sesuai. Belum disimpan, periksa dulu di PDF.') : '',
            beda.length ? item('data-acuan-beda', 'git-compare', `Beda dari acuan (${beda.length})`,
                `Tercatat tidak sesuai di acuan tapi tersimpan Sesuai/Skip (${esc(beda.map(c => c.id).join(', '))}). Pilihkan Tidak Sesuai lagi.`) : '',
        ].filter(Boolean);
        if (!aksi.length) return '';
        return `
            <div class="dropdown d-inline-block ms-1">
                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ti ti-bolt me-1"></i>Aksi acuan
                </button>
                <div class="dropdown-menu p-1" style="min-width:340px;">${aksi.join('')}</div>
            </div>`;
    }

    /**
     * Isian dari acuan yang belum disimpan: kriteria dari acuan yang belum ditinjau (Tidak Sesuai, atau
     * Sesuai hasil "Cek otomatis acuan") & usulan item lainnya.
     */
    function isianBelumDisimpan(entry) {
        return {
            kriteria: (entry?.checks || []).filter(c => c.isianSipotret && (
                (pilihanAwal(c) === TIDAK_SESUAI && !tsTersimpan(c))
                || (!c.reviewed && c.cekOtomatis === 'OK' && pilihanAwal(c) === 'OK'))),
            usulan:   (entry?.usulanCatatan || []).filter(u => usulanBelumDipakai(entry, u)),
        };
    }

    // ── Beda dari acuan: tercatat tidak sesuai di SIPOTRET, tapi tersimpan Sesuai/Skip di tinjauan ──
    function bedaAcuan(c) {
        return !!c.bedaAcuan && c.reviewed && c.status !== TIDAK_SESUAI;
    }

    /** Kriteria yang tersimpan berbeda dari acuan dan belum dipilihkan ulang di layar. */
    function daftarBedaAcuan(entry) {
        return (entry?.checks || []).filter(c => bedaAcuan(c) && !c.isianSipotret);
    }

    /**
     * Pilihkan lagi "Tidak Sesuai" + keterangan acuan untuk kriteria yang tersimpan berbeda dari acuan,
     * supaya bisa diperiksa lalu disimpan (mis. lewat "Simpan semua dari acuan"). Belum dikirim ke server.
     */
    function terapkanBedaAcuan() {
        const entry = entries[fileIndex];
        if (!entry) return;
        collectGroup();
        const list = daftarBedaAcuan(entry);
        if (!list.length) return;
        list.forEach(c => {
            c.selStatus     = TIDAK_SESUAI;
            c.selKet        = c.acuanKet || '';
            c.isianSipotret = c.acuanKet
                ? `${c.acuanAsal}: ${c.acuanKet} (tersimpan ${LABEL_PILIHAN[c.status]?.[0] ?? c.status})`
                : `${c.acuanAsal} (tanpa keterangan; tersimpan ${LABEL_PILIHAN[c.status]?.[0] ?? c.status})`;
        });
        renderGroup();
        pesanSimpan('alert-warning', `${list.length} kriteria tercatat tidak sesuai di acuan tapi tersimpan Sesuai/Skip `
            + `(${list.map(c => c.id).join(', ')}). Sudah dipilihkan Tidak Sesuai lagi, periksa di PDF lalu Simpan semua dari acuan, `
            + 'atau ubah ke Sesuai bila memang sudah diperbaiki.');
    }

    // ── Cek otomatis acuan: catatan SIPOTRET dibandingkan dengan hasil cek otomatis publikasi saat ini ──
    const AUTO_MASALAH = ['PERLU DICEK', 'TIDAK ADA', TIDAK_SESUAI];

    /** Kriteria dari acuan yang belum ditinjau, belum diubah petugas, dan belum dicek otomatis. */
    function acuanBelumDicek(entry) {
        return (entry?.checks || []).filter(c => c.isianSipotret && !c.reviewed && !c.dipilih && !c.cekOtomatis);
    }

    /** Keterangan hasil cek otomatis di kartu kriteria dari acuan. */
    function cekOtomatisHtml(c) {
        if (c.cekOtomatis === 'OK' && pilihanAwal(c) === TIDAK_SESUAI) return `<div class="small text-danger mb-1"><i class="ti ti-robot me-1"></i>Cek otomatis menilai Sesuai, dikoreksi petugas jadi Tidak Sesuai.</div>`;
        if (c.cekOtomatis === 'OK') return `<div class="small text-success mb-1"><i class="ti ti-robot me-1"></i>Cek otomatis: Sesuai, kemungkinan sudah diperbaiki. Pastikan di PDF.</div>`;
        if (c.cekOtomatis === 'masalah') return `<div class="small text-danger mb-1"><i class="ti ti-robot me-1"></i>Cek otomatis: masih ${esc(c.autoStatus)}.</div>`;
        if (c.cekOtomatis === 'manual') return `<div class="small text-azure mb-1"><i class="ti ti-eye me-1"></i>Tidak bisa dicek otomatis, periksa manual di PDF.</div>`;
        return '';
    }

    /**
     * Pilihan awal kriteria dari acuan diganti sesuai hasil cek otomatis publikasi ini:
     * otomatis OK → Sesuai (kemungkinan sudah diperbaiki), Perlu Dicek/Tidak Ada → tetap Tidak Sesuai,
     * lainnya (kriteria manual / tidak diperiksa) → tetap, ditandai "cek manual". Belum dikirim ke server.
     */
    function cekOtomatisAcuan() {
        const entry = entries[fileIndex];
        if (!entry) return;
        collectGroup();
        const hasil = { OK: [], masalah: [], manual: [] };
        acuanBelumDicek(entry).forEach(c => {
            // dari Riwayat, status yang sudah ditinjau adalah penilaian petugas, bukan hasil otomatis
            const a = c.autoLabel === 'Otomatis' ? c.autoStatus : null;
            if (a === 'OK') { c.selStatus = 'OK'; c.cekOtomatis = 'OK'; }
            else if (AUTO_MASALAH.includes(a)) { c.selStatus = TIDAK_SESUAI; c.cekOtomatis = 'masalah'; }
            else c.cekOtomatis = 'manual';
            hasil[c.cekOtomatis].push(c.id);
        });
        renderGroup();
        pesanSimpan(hasil.OK.length ? 'alert-success' : 'alert-info',
            `Cek otomatis acuan: ${hasil.OK.length} kriteria sekarang lolos dan dipilihkan Sesuai${hasil.OK.length ? ` (${hasil.OK.join(', ')})` : ''}, `
            + `${hasil.masalah.length} masih bermasalah, ${hasil.manual.length} perlu dicek manual. Belum disimpan, periksa dulu di PDF.`);
    }

    /**
     * Simpan semua isian dari acuan SIPOTRET sekaligus, tanpa membuka kriteria satu per satu:
     * kriteria Tidak Sesuai yang diisi dari acuan (beserta keterangannya, dalam satu permintaan)
     * dan usulan item lainnya sebagai catatan tambahan. Catatan SIPOTRET tanpa keterangan diberi
     * keterangan bawaan (asal catatannya), karena Tidak Sesuai wajib berketerangan.
     */
    async function simpanSemuaAcuan() {
        const entry = entries[fileIndex];
        if (!entry?.hasilId || saving) return;
        collectGroup();
        const { kriteria, usulan } = isianBelumDisimpan(entry);
        const lolos    = kriteria.filter(c => pilihanAwal(c) === 'OK'); // Sesuai dari cek otomatis acuan
        const ts       = kriteria.filter(c => pilihanAwal(c) === TIDAK_SESUAI);
        // catatan SIPOTRET tanpa keterangan: keterangan diisi asal catatannya supaya tetap ikut tersimpan
        const tanpaKet = ts.filter(c => !(c.selKet ?? '').trim());
        const siap     = [...ts, ...lolos];
        const periodeKet = entry.acuan || entry.isianInfo?.periode;
        const ketBawaan  = `Tercatat tidak sesuai di SIPOTRET${periodeKet ? ' ' + namaPeriode(periodeKet) : ''} (tanpa keterangan)`;
        if (!siap.length && !usulan.length) {
            pesanSimpan('alert-warning', 'Semua isian dari acuan sudah disimpan.');
            return;
        }
        if (!confirm(`Simpan semua isian dari catatan SIPOTRET${periodeKet ? ' ' + namaPeriode(periodeKet) : ''} tanpa diperiksa satu per satu?\n\n`
            + `• ${siap.length - lolos.length} kriteria disimpan sebagai Tidak Sesuai beserta keterangannya\n`
            + (lolos.length ? `• ${lolos.length} kriteria lolos cek otomatis disimpan sebagai Sesuai (${lolos.map(c => c.id).join(', ')})\n` : '')
            + (usulan.length ? `• ${usulan.length} item lainnya disimpan sebagai catatan tambahan\n` : '')
            + (tanpaKet.length ? `   ${tanpaKet.length} di antaranya tanpa keterangan di SIPOTRET (${tanpaKet.map(c => c.id).join(', ')}), diberi keterangan "${ketBawaan}"\n` : '')
            + '\nBisa diubah lagi kapan saja dari tinjauan.')) return;

        saving = true;
        const tombol = el('isianInfo')?.querySelector('[data-acuan-simpan]');
        if (tombol) tombol.disabled = true;
        let nKriteria = 0;
        const gagal = [];
        try {
            if (siap.length) {
                siap.forEach(c => { c.selKet = (c.selKet ?? '').trim() || (pilihanAwal(c) === TIDAK_SESUAI ? ketBawaan : ''); });
                await simpanItems(entry, siap); // semua kategori sekaligus (endpoint menerima kriteria campuran)
                nKriteria = siap.length;
            }
        } catch (e) {
            gagal.push(`kriteria: ${e.message}`);
        }
        let nUsulan = 0;
        for (const u of usulan) {
            try {
                const res = await fetch(catUrl('catatanUrl').replace('__ID__', entry.hasilId), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        kategori: u.kategori || null, // item lainnya SIPOTRET tidak berkategori
                        item: String(u.item || '').slice(0, maxItem()), // item master tidak dipotong
                        keterangan: u.keterangan || '-',
                        // flag kosong dibiarkan kosong (di SIPOTRET = tidak perlu perbaikan)
                        flag_level: ['minor', 'moderate', 'major'].includes(u.flag_level) ? u.flag_level : null,
                        // item master SIPOTRET tetap dikirim sebagai item master, seperti "Dari History"
                        item_periksa_id: u.item_periksa_id || null,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || `Server membalas ${res.status}`);
                (entry.catatan ??= []).push(data.catatan);
                u.dipakai = true;
                nUsulan++;
            } catch (e) {
                gagal.push(`${u.item}: ${e.message}`);
            }
        }
        saving = false;

        renderGroup();
        renderFileTabs();
        pesanSimpan(gagal.length ? 'alert-warning' : 'alert-success',
            `${nKriteria - (nKriteria ? lolos.length : 0)} kriteria Tidak Sesuai${nKriteria && lolos.length ? `, ${lolos.length} Sesuai` : ''}${nUsulan ? ` dan ${nUsulan} catatan tambahan` : ''} dari acuan disimpan.`
            + (nKriteria && tanpaKet.length ? ` ${tanpaKet.length} kriteria tanpa keterangan di SIPOTRET diberi keterangan bawaan, lengkapi bila perlu.` : '')
            + (gagal.length ? ` Gagal: ${gagal.join('; ')}.` : ''));
    }

    /** Ganti/simpan acuan isian awal (nilai select "bulan-tahun", kosong = tanpa acuan), lalu pasang ulang isiannya. */
    async function gantiAcuan(nilai) {
        const entry = entries[fileIndex];
        if (!entry?.hasilId) return;
        collectGroup();
        const [bulan, tahun] = nilai ? nilai.split('-').map(Number) : [null, null];
        const sel = document.getElementById('revAcuanSel');
        if (sel) sel.disabled = true;
        try {
            const res = await fetch(catUrl('acuanUrl').replace('__ID__', entry.hasilId), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(nilai ? { bulan, tahun } : {}),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.error || data.message || `Server membalas ${res.status}`);

            // lepas isian acuan lama dari kriteria yang belum ditinjau & tidak diubah petugas
            entry.checks.forEach(c => {
                delete c.bedaAcuan; delete c.acuanKet; delete c.acuanAsal;
                if (!c.isianSipotret) return;
                // yang sudah tersimpan: pilihan dari acuan lama (Beda dari acuan) dilepas, kembali ke yang tersimpan
                if (!c.dipilih || c.reviewed) { delete c.selStatus; delete c.selKet; }
                delete c.isianSipotret;
                delete c.cekOtomatis;
            });
            entry.acuan = data.acuan;
            entry.isianInfo = null;
            entry.usulanCatatan = [];
            if (data.isian) {
                terapkanIsian(entry, data.isian);
                // item lainnya yang sudah tersimpan sebagai catatan tambahan tidak diusulkan lagi
                entry.usulanCatatan = (entry._usulanCatatan || []).filter(u => usulanBelumDipakai(entry, u));
                entry.isianInfo.lainnya = entry.usulanCatatan.length;
            }
            renderGroup();
        } catch (e) {
            console.error('Gagal mengganti acuan SIPOTRET', e);
            pesanSimpan('alert-danger', 'Gagal menyimpan acuan isian awal: ' + e.message);
            if (sel) sel.disabled = false;
        }
    }

    function recalcSummary(entry) {
        if (!entry.summary) return;
        const checks = entry.checks;
        const ok   = checks.filter(c => c.status === 'OK').length;
        const warn = checks.filter(c => c.status === 'PERLU DICEK').length;
        const err  = checks.filter(c => c.status === 'TIDAK ADA' || c.status === TIDAK_SESUAI).length;
        const skip = checks.filter(c => c.status === 'TIDAK DIPERIKSA').length;
        Object.assign(entry.summary, {
            ok, perlu_dicek: warn, tidak_ada: err, tidak_diperiksa: skip,
            status_akhir: err > 0 ? 'masalah' : (warn > 0 ? 'perlu_dicek' : 'ok'),
        });
    }

    function finish() {
        const section = el('section');
        if (section) section.style.display = 'none';
        hideCompare();
        resetJejer();
        setFocusMode(false);
        entries.forEach(e => e.cleanup?.());
        onFinishCb();
    }

    return { init, start, next, prev, saveAndExit, setAll, simpanFokus, terapkanIsian, gabungHasil, pdfSiap, progresPdf, scanUlang, semuaSesuai, toggleSiblings, mountViewer, statusBadge, statusBadgeClass };
})();
