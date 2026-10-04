/**
 * CheckerReview — panel tinjau manual step-by-step (split view: kiri halaman PDF, kanan kriteria).
 * Dipakai bersama oleh halaman upload (checker.blade.php) & detail import API (bps-detail.blade.php),
 * lewat markup partials/review-panel.blade.php, supaya perilaku/tampilan viewer (mis. mode "even spreads")
 * cukup diatur di satu tempat.
 *
 * Entry shape yang diterima start():
 *   {
 *     filename: string,
 *     checks:   array (item: { id, kategori, deskripsi, status, catatan, target, area }),
 *     hasilId:  number|null,   // untuk PATCH /checker/hasil/{id}/review
 *     summary:  object|null,   // referensi ke result.summary, di-update di tempat setelah tiap simpan
 *     url:      string,        // URL PDF untuk viewer (blob: lokal atau proxied remote)
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

    const ID = {
        section:      'reviewSection',
        fileName:     'revFileName',
        fileIdx:      'revFileIdx',
        fileTotal:    'revFileTotal',
        stepBadge:    'revStepBadge',
        progressBar:  'revProgressBar',
        frame:        'revPdfFrame',
        pageHint:     'revPageHint',
        kode:         'revKode',
        kategori:     'revKategori',
        deskripsi:    'revDeskripsi',
        areaHint:     'revAreaHint',
        autoStatus:   'revAutoStatus',
        autoCatatan:  'revAutoCatatan',
        statusButtons:'revStatusButtons',
        catatan:      'revCatatan',
        btnPrev:      'revBtnPrev',
        btnNext:      'revBtnNext',
        toggleDetail: 'revToggleDetail',
    };

    const SHOW_LABEL = '<i class="mdi mdi-eye-outline me-1"></i> Tampilkan Info Lain';
    const HIDE_LABEL = '<i class="mdi mdi-eye-off-outline me-1"></i> Sembunyikan Info Lain';

    let viewerBaseUrl = null;
    let csrfToken     = null;

    let entries        = [];
    let fileIndex       = 0;
    let stepIndex       = 0;
    let onFinishCb      = null;
    let hiddenSiblings  = [];
    let siblingsVisible = false;

    function el(key) { return document.getElementById(ID[key]); }

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

    function init({ viewerUrl, csrf }) {
        viewerBaseUrl = viewerUrl;
        csrfToken     = csrf;
        el('statusButtons')?.addEventListener('click', e => {
            const btn = e.target.closest('button[data-status]');
            if (btn) setStatusSelection(btn.dataset.status);
        });
    }

    function start(list, { onFinish } = {}) {
        entries    = (list || []).filter(e => e.checks && e.checks.length && e.url);
        onFinishCb = onFinish || (() => {});
        fileIndex  = 0;

        if (!entries.length) { finish(); return; }

        setFocusMode(true);
        el('section').style.display = 'block';
        el('section').scrollIntoView({ behavior: 'smooth', block: 'start' });
        loadFile(0);
    }

    function loadFile(startStep) {
        const entry = entries[fileIndex];

        el('fileName').textContent   = entry.filename;
        el('fileIdx').textContent    = fileIndex + 1;
        el('fileTotal').textContent  = entries.length;

        stepIndex = startStep;
        renderStep();

        const frame = el('frame');
        const url = `${viewerBaseUrl}?file=${encodeURIComponent(entry.url)}#pagemode=none&spread=even`;
        frame.onload = () => {
            try {
                const app = frame.contentWindow.PDFViewerApplication;
                if (app?.initializedPromise) app.initializedPromise.then(goToPage);
                else setTimeout(goToPage, 800);
            } catch (e) { /* cross-origin/timing, biarkan viewer dipakai manual */ }
        };
        frame.src = url;
    }

    function goToPage() {
        const step  = entries[fileIndex]?.checks[stepIndex];
        const frame = el('frame');
        const win   = frame?.contentWindow;
        if (!step || !win?.PDFViewerApplication) return;
        const page = step.target === 'page2' ? 2 : 1;
        try {
            const total = win.PDFViewerApplication.pagesCount || page;
            win.PDFViewerApplication.page = Math.min(page, total);
        } catch (e) { /* ignore */ }
    }

    function renderStep() {
        const entry  = entries[fileIndex];
        const checks = entry.checks;
        const step   = checks[stepIndex];

        el('stepBadge').textContent   = `Kriteria ${stepIndex + 1}/${checks.length}`;
        el('progressBar').style.width = Math.round((stepIndex / checks.length) * 100) + '%';

        el('kode').textContent      = step.id ?? '-';
        el('kategori').textContent  = step.kategori ?? '-';
        el('deskripsi').textContent = step.deskripsi ?? '-';

        const pageLabel = step.target === 'page2' ? 'Halaman 2'
                         : step.target === 'all'  ? 'Semua Halaman'
                         :                           'Halaman 1 (Kover)';
        el('pageHint').textContent = 'Menampilkan: ' + pageLabel;

        const areaEl = el('areaHint');
        if (step.area) {
            areaEl.style.display = 'block';
            areaEl.querySelector('span').textContent = 'Fokus area: ' + step.area;
        } else {
            areaEl.style.display = 'none';
        }

        el('autoStatus').className    = 'badge ' + statusBadgeClass(step.status);
        el('autoStatus').textContent  = step.status;
        el('autoCatatan').textContent = step.catatan || '—';

        el('catatan').value = step.catatan ?? '';
        setStatusSelection(step.status);

        el('btnPrev').disabled = (fileIndex === 0 && stepIndex === 0);
        const isLast = (stepIndex === checks.length - 1) && (fileIndex === entries.length - 1);
        el('btnNext').innerHTML = isLast
            ? 'Selesai Tinjauan <i class="mdi mdi-check"></i>'
            : 'Simpan &amp; Lanjut <i class="mdi mdi-chevron-right"></i>';

        goToPage();
    }

    function setStatusSelection(status) {
        document.querySelectorAll(`#${ID.statusButtons} button`).forEach(b => {
            b.classList.toggle('active', b.dataset.status === status);
        });
    }

    async function next() {
        const entry  = entries[fileIndex];
        const checks = entry.checks;
        const step   = checks[stepIndex];

        const selectedBtn = document.querySelector(`#${ID.statusButtons} button.active`);
        const status  = selectedBtn ? selectedBtn.dataset.status : step.status;
        const catatan = el('catatan').value.trim();

        step.status  = status;
        step.catatan = catatan;
        recalcSummary(entry);

        if (entry.hasilId) {
            try {
                await fetch(`/checker/hasil/${entry.hasilId}/review`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ kriteria_id: step.id, status, catatan }),
                });
            } catch (e) { console.error('Gagal menyimpan hasil tinjauan', e); }
        }

        if (stepIndex < checks.length - 1) {
            stepIndex++;
            renderStep();
        } else if (fileIndex < entries.length - 1) {
            entry.cleanup?.();
            fileIndex++;
            loadFile(0);
        } else {
            finish();
        }
    }

    function prev() {
        if (stepIndex > 0) {
            stepIndex--;
            renderStep();
        } else if (fileIndex > 0) {
            entries[fileIndex].cleanup?.();
            fileIndex--;
            loadFile(entries[fileIndex].checks.length - 1);
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

    return { init, start, next, prev, toggleSiblings, statusBadge, statusBadgeClass };
})();
