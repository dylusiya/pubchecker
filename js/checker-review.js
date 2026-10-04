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
        'TIDAK DIPERIKSA': 'bg-secondary',
    };

    const STATUS_BUTTONS = [
        ['OK',              'OK',          'success'],
        ['PERLU DICEK',     'Perlu Dicek', 'warning'],
        ['TIDAK ADA',       'Tidak Ada',   'danger'],
        ['TIDAK DIPERIKSA', 'Skip',        'secondary'],
    ];

    const PAGE_LABEL = {
        cover: 'Halaman 1 (Kover)',
        page2: 'Halaman 2',
        front: 'Bagian awal publikasi',
        last:  'Halaman terakhir (Kover Belakang)',
        all:   'Semua Halaman',
    };

    const ID = {
        section:      'reviewSection',
        fileName:     'revFileName',
        fileIdx:      'revFileIdx',
        fileTotal:    'revFileTotal',
        stepBadge:    'revStepBadge',
        progressBar:  'revProgressBar',
        frame:        'revPdfFrame',
        pageHint:     'revPageHint',
        kategoriSel:  'revKategoriSelect',
        itemCount:    'revItemCount',
        items:        'revItems',
        saveError:    'revSaveError',
        btnPrev:      'revBtnPrev',
        btnNext:      'revBtnNext',
        btnExit:      'revBtnExit',
        toggleDetail: 'revToggleDetail',
    };

    const SHOW_LABEL = '<i class="mdi mdi-eye-outline me-1"></i> Tampilkan Info Lain';
    const HIDE_LABEL = '<i class="mdi mdi-eye-off-outline me-1"></i> Sembunyikan Info Lain';

    let viewerBaseUrl = null;
    let csrfToken     = null;
    let saveUrlTpl    = null; // route('checker.hasil.review_kategori', '__ID__') — app bisa berada di subfolder

    let entries         = [];
    let fileIndex       = 0;
    let groupIndex      = 0;
    let saving          = false;
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

        if (on) {
            hiddenSiblings = [...section.parentElement.children]
                .filter(node => node !== section)
                .map(node => ({ node, display: node.style.display }));
            siblingsVisible = false;
            applySiblingsVisibility();

            const toggleBtn = el('toggleDetail');
            if (toggleBtn) { toggleBtn.style.display = ''; toggleBtn.innerHTML = SHOW_LABEL; }
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

    function init({ viewerUrl, csrf, saveUrl }) {
        viewerBaseUrl = viewerUrl;
        csrfToken     = csrf;
        saveUrlTpl    = saveUrl;

        el('items')?.addEventListener('click', e => {
            const loc = e.target.closest('button[data-hal]');
            if (loc) { goToLokasi(parseInt(loc.dataset.hal, 10), loc.dataset.teks); return; }
            const btn = e.target.closest('button[data-status]');
            if (!btn) return;
            const card = btn.closest('[data-idx]');
            setItemSelection(card, btn.dataset.status);
        });

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

    function start(list, { onFinish } = {}) {
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

        // Lanjutkan dari file & kategori pertama yang belum ditinjau
        fileIndex = Math.max(0, entries.findIndex(e => firstUnreviewedGroup(e) !== null));

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
        renderGroup();

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
        frame.src = `${viewerBaseUrl}?file=${file}#pagemode=none&spread=even`;
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

    function renderKategoriSelect(entry) {
        const sel = el('kategoriSel');
        if (!sel) return;
        sel.innerHTML = entry.groups.map((g, i) => {
            const done = isGroupReviewed(entry, g);
            return `<option value="${i}" ${i === groupIndex ? 'selected' : ''}>${done ? '✓' : '○'} ${esc(g.kategori)} (${g.idx.length})</option>`;
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

        el('items').innerHTML = items.map((c, n) => {
            const sel = c.selStatus ?? c.status;
            const note = c.selCatatan ?? c.catatan ?? '';
            return `
            <div class="border rounded p-2 mb-2 ${c.reviewed ? 'border-success' : ''}" data-idx="${n}">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                    <span class="badge bg-light text-dark border font-monospace">${esc(c.id)}</span>
                    <span class="small text-muted">
                        ${c.reviewed ? '<i class="mdi mdi-check-circle text-success" title="Sudah ditinjau"></i>' : ''}
                        ${c.autoLabel}: <span class="badge ${statusBadgeClass(c.autoStatus)}">${esc(c.autoStatus)}</span>
                    </span>
                </div>
                <div class="small fw-semibold mb-1">${esc(c.deskripsi)}</div>
                ${lokasiButtons(c.lokasi)}
                ${c.area ? `<div class="small text-muted mb-1"><i class="mdi mdi-crosshairs-gps me-1"></i>Fokus area: ${esc(c.area)}</div>` : ''}
                <div class="btn-group btn-group-sm w-100 mb-1" role="group">
                    ${STATUS_BUTTONS.map(([val, label, color]) =>
                        `<button type="button" class="btn btn-outline-${color} ${val === sel ? 'active' : ''}" data-status="${val}">${label}</button>`
                    ).join('')}
                </div>
                <input type="text" class="form-control form-control-sm rev-note" placeholder="Catatan (opsional)" value="${esc(note)}">
            </div>`;
        }).join('');
        el('items').scrollTop = 0;

        el('btnPrev').disabled = (fileIndex === 0 && groupIndex === 0);
        const isLast = (groupIndex === groups.length - 1) && (fileIndex === entries.length - 1);
        el('btnNext').innerHTML = isLast
            ? 'Simpan &amp; Selesai <i class="mdi mdi-check"></i>'
            : 'Simpan Kategori &amp; Lanjut <i class="mdi mdi-chevron-right"></i>';

        goToPage();
    }

    /** Tombol lokasi temuan cek otomatis: lompat ke halaman & sorot teksnya di viewer. */
    function lokasiButtons(lokasi) {
        if (!lokasi?.length) return '';
        const short = s => s.length > 40 ? s.slice(0, 40) + '…' : s;
        return `<div class="d-flex flex-wrap gap-1 mb-1">${lokasi.map(l => `
            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-1 text-start"
                    style="font-size:11px;" data-hal="${l.hal}" data-teks="${esc(l.teks)}"
                    title="Buka halaman ${l.hal} dan sorot: ${esc(l.teks)}">
                <i class="mdi mdi-file-find-outline"></i> Hal. ${l.hal}: “${esc(short(l.teks))}”
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

    function setItemSelection(card, status) {
        card?.querySelectorAll('button[data-status]').forEach(b => {
            b.classList.toggle('active', b.dataset.status === status);
        });
    }

    /** Tandai semua kriteria di kategori aktif dengan status yang sama. */
    function setAll(status) {
        el('items').querySelectorAll('[data-idx]').forEach(card => setItemSelection(card, status));
    }

    /** Simpan pilihan di layar ke objek kriteria (belum dikirim ke server). */
    function collectGroup() {
        if (!entries[fileIndex]) return;
        const items = currentGroupItems();
        el('items').querySelectorAll('[data-idx]').forEach(card => {
            const c = items[parseInt(card.dataset.idx, 10)];
            if (!c) return;
            c.selStatus  = card.querySelector('button.active')?.dataset.status ?? c.status;
            c.selCatatan = card.querySelector('.rev-note')?.value.trim() ?? '';
        });
    }

    async function saveGroup() {
        collectGroup();
        const entry = entries[fileIndex];
        const items = currentGroupItems();

        if (entry.hasilId) {
            const res = await fetch(saveUrlTpl.replace('__ID__', entry.hasilId), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    items: items.map(c => ({ kriteria_id: c.id, status: c.selStatus, catatan: c.selCatatan })),
                }),
            });
            if (!res.ok) throw new Error(`Server membalas ${res.status}`);
        }

        items.forEach(c => {
            c.status   = c.selStatus;
            c.catatan  = c.selCatatan;
            c.reviewed = true;
            delete c.selStatus;
            delete c.selCatatan;
        });
        recalcSummary(entry);
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
            box.textContent   = 'Gagal menyimpan kategori ini: ' + e.message + '. Coba lagi.';
            box.style.display = 'block';
        } finally {
            saving = false;
            el('btnNext').disabled = false;
            if (el('btnExit')) el('btnExit').disabled = false;
        }
    }

    function next() {
        withSave(() => {
            const entry = entries[fileIndex];
            if (groupIndex < entry.groups.length - 1) {
                groupIndex++;
                renderGroup();
            } else if (fileIndex < entries.length - 1) {
                entry.cleanup?.();
                fileIndex++;
                loadFile(firstUnreviewedGroup(entries[fileIndex]) ?? 0);
            } else {
                finish();
            }
        });
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
            entries[fileIndex].cleanup?.();
            fileIndex--;
            loadFile(entries[fileIndex].groups.length - 1);
        }
    }

    function recalcSummary(entry) {
        if (!entry.summary) return;
        const checks = entry.checks;
        const ok   = checks.filter(c => c.status === 'OK').length;
        const warn = checks.filter(c => c.status === 'PERLU DICEK').length;
        const err  = checks.filter(c => c.status === 'TIDAK ADA').length;
        const skip = checks.filter(c => c.status === 'TIDAK DIPERIKSA').length;
        Object.assign(entry.summary, {
            ok, perlu_dicek: warn, tidak_ada: err, tidak_diperiksa: skip,
            status_akhir: err > 0 ? 'masalah' : (warn > 0 ? 'perlu_dicek' : 'ok'),
        });
    }

    function finish() {
        const section = el('section');
        if (section) section.style.display = 'none';
        setFocusMode(false);
        entries.forEach(e => e.cleanup?.());
        onFinishCb();
    }

    return { init, start, next, prev, saveAndExit, setAll, toggleSiblings, statusBadge, statusBadgeClass };
})();
