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
 *   }
 */
const CheckerReview = (() => {
    const STATUS_CLASS = {
        'OK':              'bg-success',
        'PERLU DICEK':     'bg-warning text-dark',
        'TIDAK ADA':       'bg-danger',
        'TIDAK SESUAI':    'bg-danger',
        'TIDAK DIPERIKSA': 'bg-secondary',
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
        ['auto',            'Temuan otomatis'], // hasil cek otomatis Perlu Dicek / Tidak Ada
    ];

    const PAGE_LABEL = {
        cover: 'Halaman 1 (Kover)',
        page2: 'Halaman 2',
        front: 'Bagian awal publikasi',
        tim_penyusun: 'Halaman Tim Penyusun',
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
        toggleDetail: 'revToggleDetail',
    };

    const SHOW_LABEL = '<i class="ti ti-eye me-1"></i> Tampilkan Info Lain';
    const HIDE_LABEL = '<i class="ti ti-eye-off me-1"></i> Sembunyikan Info Lain';

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

            // Tombol hanya muncul bila memang ada info yang bisa ditampilkan
            const toggleBtn = el('toggleDetail');
            if (toggleBtn) {
                toggleBtn.style.display = hiddenSiblings.some(s => s.visible) ? '' : 'none';
                toggleBtn.innerHTML = SHOW_LABEL;
            }
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

    function toggleSiblings() {
        siblingsVisible = !siblingsVisible;
        applySiblingsVisibility();
        const toggleBtn = el('toggleDetail');
        if (toggleBtn) toggleBtn.innerHTML = siblingsVisible ? HIDE_LABEL : SHOW_LABEL;
    }

    function init({ viewerUrl, csrf, saveUrl, contohUrl: cUrl }) {
        viewerBaseUrl = viewerUrl;
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

        el('items')?.addEventListener('input', e => {
            if (e.target.matches('.rev-ket')) e.target.classList.remove('is-invalid');
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
                lompatKeKriteria(g, n);
            });
            // klik di luar → tutup daftar hasil
            document.addEventListener('click', e => {
                if (!e.target.closest('#revCari, #revCariHasil')) cariHasil.hidden = true;
            });
        }

        el('kategoriSel')?.addEventListener('change', e => {
            collectGroup();
            groupIndex = parseInt(e.target.value, 10) || 0;
            renderGroup();
        });
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

    function loadFile(startGroup) {
        const entry = entries[fileIndex];

        el('fileName').textContent  = entry.filename;
        el('fileIdx').textContent   = fileIndex + 1;
        el('fileTotal').textContent = entries.length;

        groupIndex = startGroup;
        renderGroup(); // panel contoh ikut kategori aktif, tetap terbuka/tertutup sesuai pilihan petugas

        const frame = el('frame');
        const hasOcr = entry.ocrLines && Object.keys(entry.ocrLines).length > 0;
        // Dengan hasil OCR: viewer dibuka kosong dulu, teks OCR dipasang, baru PDF dimuat
        // (supaya kait terpasang sebelum halaman pertama dirender — lihat injectOcrText)
        const file = hasOcr ? '' : encodeURIComponent(entry.url);
        frame.onload = () => {
            try {
                const win = frame.contentWindow;
                const app = win.PDFViewerApplication;
                if (!app?.initializedPromise) { setTimeout(goToPage, 800); return; }
                app.initializedPromise.then(async () => {
                    if (hasOcr) {
                        injectOcrText(win, entry.ocrLines);
                        // URL absolut: open() me-resolve URL relatif terhadap lokasi viewer, bukan halaman ini
                        await app.open({ url: new URL(entry.url, location.href).href });
                    }
                    setTimeout(goToPage, 300);
                });
            } catch (e) { /* cross-origin/timing, biarkan viewer dipakai manual */ }
        };
        // `v` unik per pemuatan: tanpa itu, URL viewer kosong (mode OCR) sama persis antar publikasi
        // → browser hanya menganggapnya pindah #hash, iframe tidak dimuat ulang dan PDF lama tetap tampil
        frame.src = `${viewerBaseUrl}?file=${file}&v=${Date.now()}#pagemode=none`;
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

    function currentGroupItems() {
        const entry = entries[fileIndex];
        return entry.groups[groupIndex].idx.map(i => entry.checks[i]);
    }

    function goToPage() {
        const first = entries[fileIndex] ? currentGroupItems()[0] : null;
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
                        <span><span class="font-monospace text-secondary">${esc(c.id)}</span> · <span class="text-secondary">${esc(c.kategori)}</span></span>
                        <span class="badge ${cls}">${lbl}</span>
                    </div>
                    <div class="text-truncate">${esc(c.deskripsi)}</div>
                </button>`;
        }).join('') + (hasil.length > 40 ? `<div class="list-group-item small text-secondary">… ${hasil.length - 40} lainnya, perjelas kata kunci</div>` : '');
    }

    /** Buka kategori `g` lalu sorot kriteria ke-`n` di kategori itu. */
    function lompatKeKriteria(g, n) {
        if (g !== groupIndex) {
            collectGroup();
            groupIndex = g;
            renderGroup();
        }
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
        if (f === 'auto')  return c.autoStatus === 'PERLU DICEK' || c.autoStatus === 'TIDAK ADA';
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
        el('items').querySelectorAll('[data-idx]').forEach(card => {
            const c = items[parseInt(card.dataset.idx, 10)];
            card.hidden = !!c && !cocokFilter(c);
        });
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

    function renderGroup() {
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
        el('btnNext').innerHTML = isLast
            ? 'Simpan &amp; Selesai <i class="ti ti-check"></i>'
            : 'Simpan Kategori &amp; Lanjut <i class="ti ti-chevron-right"></i>';

        goToPage();
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
                        <span class="text-secondary fw-normal">— klik gambar untuk membandingkan</span>
                    </small>
                    <button type="button" class="btn btn-sm py-0 px-2 flex-shrink-0" data-contoh-toggle></button>
                </div>
                <div class="d-flex gap-2 overflow-auto pb-1">
                    ${list.map((c, i) => {
                        const attrs = `data-kategori="${esc(kategori)}" data-contoh-idx="${i}"
                            title="${esc(c.keterangan || 'Contoh ' + (i + 1))}"`;
                        const box = 'height:72px;width:auto;max-width:120px;cursor:zoom-in;';
                        return c.tipe === 'pdf'
                            ? `<div ${attrs} class="contoh-thumb contoh-thumb-pdf border flex-shrink-0 px-3" style="${box}">
                                   <i class="ti ti-file-type-pdf" style="font-size:1.75rem;"></i><span class="small">PDF</span>
                               </div>`
                            : `<img src="${esc(c.url)}" alt="contoh" ${attrs} class="contoh-thumb border flex-shrink-0" style="${box}">`;
                    }).join('')}
                </div>
            </div>`;
        syncContohUi();
    }

    // ── Perbandingan: contoh yang benar di samping PDF yang diperiksa ──
    function contohViewerUrl(c) {
        return viewerBaseUrl + '?file=' + encodeURIComponent(new URL(c.url, location.href).href) + '#pagemode=none';
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
            ? `<iframe src="${esc(contohViewerUrl(c))}" title="Contoh PDF" style="width:100%;height:100%;border:0;"></iframe>`
            : `<img src="${esc(c.url)}" alt="contoh" style="max-width:100%;max-height:100%;object-fit:contain;background:#fff;">`;
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
                    ? `<iframe src="${esc(viewerBaseUrl + '?file=' + encodeURIComponent(new URL(c.url, location.href).href) + '#pagemode=none')}"
                               title="Contoh PDF" style="width:92vw;max-width:1200px;height:78vh;border:0;border-radius:6px;background:#fff;"></iframe>`
                    : `<img src="${esc(c.url)}" alt="contoh" style="max-width:92vw;max-height:78vh;object-fit:contain;background:#fff;border-radius:6px;">`}
                <div style="margin-top:10px;display:flex;gap:8px;">
                    ${list.length > 1 ? '<button type="button" class="btn btn-light btn-sm" data-nav="-1"><i class="ti ti-chevron-left"></i> Sebelumnya</button>' : ''}
                    <button type="button" class="btn btn-light btn-sm" data-nav="close"><i class="ti ti-x"></i> Tutup</button>
                    ${list.length > 1 ? '<button type="button" class="btn btn-light btn-sm" data-nav="1">Berikutnya <i class="ti ti-chevron-right"></i></button>' : ''}
                </div>`;
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
        if (!lokasi?.length) return '';
        const short = s => s.length > 40 ? s.slice(0, 40) + '…' : s;
        return `<div class="d-flex flex-wrap gap-1 mb-1">${lokasi.map(l => `
            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-1 text-start"
                    style="font-size:11px;" data-hal="${l.hal}" data-teks="${esc(l.teks)}"
                    title="Buka halaman ${l.hal} dan sorot: ${esc(l.teks)}">
                <i class="ti ti-file-search"></i> Hal. ${l.hal}: “${esc(short(l.teks))}”
            </button>`).join('')}</div>`;
    }

    function goToLokasi(hal, teks) {
        const app = el('frame')?.contentWindow?.PDFViewerApplication;
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
    async function setAllSemuaKategori(status) {
        const entry = entries[fileIndex];
        if (!entry || saving) return;
        const label = status === 'OK' ? 'Sesuai' : 'Skip';
        if (!confirm(`Tandai sisanya "${label}" di SEMUA ${entry.groups.length} kategori publikasi ini lalu simpan?\n\n` +
            `Tidak Sesuai${status === 'OK' ? ' dan Skip' : ' dan pilihan yang sudah diklik/disimpan petugas'} tidak diubah.`)) return;

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
            c.reviewed   = true;
            delete c.selStatus;
            delete c.selKet;
        });
        recalcSummary(entry);
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
            ? `Tersimpan di ${ok} publikasi, gagal di ${gagal} — coba lagi.`
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

    // ── Catatan tambahan per kategori (temuan di luar daftar kriteria → SIPOTRET "Item Lainnya") ──
    // Ditampilkan di bawah kriteria kategori aktif; disimpan langsung ke server (tidak menunggu Simpan Kategori).
    const LEVEL_BADGE = { minor: 'bg-azure-lt', moderate: 'bg-orange-lt', major: 'bg-red-lt' };
    let saranHtml = null;
    let catEditId = null; // id catatan yang sedang diedit; null = form untuk catatan baru

    const catUrl  = key => el('section')?.dataset[key];
    const catById = id => document.getElementById(id);

    /** Muat catatan publikasi (sekali per publikasi). */
    async function loadCatatan(entry) {
        if (!entry?.hasilId || !catUrl('catatanUrl') || entry.catatan) return;
        entry.catatan = [];
        try {
            const res = await fetch(catUrl('catatanUrl').replace('__ID__', entry.hasilId), { headers: { 'Accept': 'application/json' } });
            if (res.ok) entry.catatan = (await res.json()).catatan || [];
        } catch { /* tetap kosong */ }
        if (entry === entries[fileIndex]) renderCatatanList();
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
                    <input type="text" class="form-control form-control-sm mb-1" id="revCatItem" maxlength="50" list="revCatSaran"
                           placeholder="Nama item (maks. 50 karakter), mis. Penggunaan Bahasa Asing">
                    <datalist id="revCatSaran">${saranHtml || ''}</datalist>
                    <div class="d-flex gap-1 mb-1">
                        <select class="form-select form-select-sm" id="revCatLevel" title="Level">
                            <option value="minor">Minor</option>
                            <option value="moderate">Moderate</option>
                            <option value="major">Major</option>
                        </select>
                        <input type="number" class="form-control form-control-sm" id="revCatHal" min="1" placeholder="Hal." title="Halaman (opsional)" style="max-width:90px;">
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
        const list = (entry.catatan || []).filter(c => (c.kategori || '') === kat);
        catById('revCatCount').textContent = list.length ? `(${list.length})` : '';
        box.innerHTML = list.length
            ? list.map(c => `
                <div class="d-flex gap-2 align-items-start border-top pt-1 mt-1 small">
                    <div class="flex-fill" style="min-width:0;">
                        <span class="fw-semibold">${esc(c.item)}</span>
                        <span class="badge ${LEVEL_BADGE[c.flag_level] || 'bg-secondary-lt'}">${esc(c.flag_level)}</span>
                        ${c.halaman ? `<a href="#" data-cat-hal="${c.halaman}">hal. ${c.halaman}</a>` : ''}
                        <div class="text-secondary">${esc(c.keterangan)}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost-primary btn-icon" data-cat-edit="${c.id}" title="Edit"><i class="ti ti-pencil"></i></button>
                    <button type="button" class="btn btn-sm btn-ghost-danger btn-icon" data-cat-hapus="${c.id}" title="Hapus"><i class="ti ti-trash"></i></button>
                </div>`).join('')
            : '<div class="small text-secondary">Temuan di kategori ini yang tidak ada di daftar kriteria — dikirim ke SIPOTRET sebagai item lainnya.</div>';
    }

    /**
     * Buka/tutup form catatan. Dengan `catatan`: mode edit (form diisi catatan itu);
     * tanpa: mode tambah (halaman diisi dari posisi viewer).
     */
    function bukaFormCatatan(buka, catatan = null) {
        const form = catById('revCatForm');
        if (!form) return;
        const dariEdit = catEditId !== null;
        form.hidden = !buka;
        catById('revCatInfo').textContent = '';
        catEditId = buka && catatan ? catatan.id : null;
        el('items').querySelectorAll('[data-cat-edit]').forEach(b => b.classList.toggle('active', +b.dataset.catEdit === catEditId));
        if (!buka) return;

        if (catatan) {
            catById('revCatItem').value  = catatan.item;
            catById('revCatLevel').value = catatan.flag_level;
            catById('revCatHal').value   = catatan.halaman || '';
            catById('revCatKet').value   = catatan.keterangan || '';
        } else {
            if (dariEdit) { catById('revCatItem').value = ''; catById('revCatKet').value = ''; }
            try { catById('revCatHal').value = el('frame').contentWindow.PDFViewerApplication.page || ''; } catch { /* abaikan */ }
        }
        // "semua publikasi" hanya untuk catatan baru
        const semua = catById('revCatSemuaWrap');
        if (semua) semua.hidden = !!catatan;
        if (catById('revCatSemua')) catById('revCatSemua').checked = false;
        catById('revCatSimpanLbl').textContent = catatan ? 'Simpan Perubahan' : 'Simpan';
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
        const payload = {
            kategori: kategoriAktif(),
            item, keterangan: ket,
            flag_level: catById('revCatLevel').value,
            halaman: parseInt(catById('revCatHal').value, 10) || null,
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
                    body: JSON.stringify(e === aktif ? payload : { ...payload, halaman: null }),
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
    }

    /** Klik di kotak catatan (didelegasikan dari daftar kriteria). @returns true bila ditangani */
    function klikCatatan(e) {
        const t = e.target;
        // "Tambah": buka form kosong (juga keluar dari mode edit); klik lagi saat mode tambah = tutup
        if (t.closest('[data-cat-tambah]')) { bukaFormCatatan(catById('revCatForm')?.hidden || catEditId !== null); return true; }
        const edit = t.closest('[data-cat-edit]');
        if (edit) {
            const c = (entries[fileIndex].catatan || []).find(x => x.id === +edit.dataset.catEdit);
            if (c) bukaFormCatatan(true, c);
            return true;
        }
        if (t.closest('[data-cat-batal]'))  { bukaFormCatatan(false); return true; }
        if (t.closest('[data-cat-simpan]')) { simpanCatatan(); return true; }
        const hapus = t.closest('[data-cat-hapus]');
        if (hapus) { hapusCatatan(parseInt(hapus.dataset.catHapus, 10)); return true; }
        const hal = t.closest('[data-cat-hal]');
        if (hal) {
            e.preventDefault();
            try { el('frame').contentWindow.PDFViewerApplication.page = parseInt(hal.dataset.catHal, 10); } catch { /* abaikan */ }
            return true;
        }
        return false;
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
                    <a href="#" class="nav-link ${i === fileIndex ? 'active' : ''}" data-file="${i}" title="${esc(e.filename)}">
                        ${done === total ? '<i class="ti ti-circle-check text-green me-1"></i>' : ''}
                        <span class="text-truncate">${esc(e.filename)}</span>
                        <span class="badge ${done === total ? 'bg-green-lt' : 'bg-secondary-lt'} ms-2">${done}/${total}</span>
                    </a>
                </li>`;
        }).join('');
    }

    /** Simpan kategori yang sedang dibuka lalu keluar — sisanya bisa dilanjutkan dari Riwayat. */
    function saveAndExit() {
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
        setFocusMode(false);
        entries.forEach(e => e.cleanup?.());
        onFinishCb();
    }

    return { init, start, next, prev, saveAndExit, setAll, toggleSiblings, statusBadge, statusBadgeClass };
})();
