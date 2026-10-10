/**
 * BatchCheck — memeriksa beberapa publikasi (maks. 5) sekaligus dengan tampilan kartu berjejer.
 * Dipakai bersama oleh Upload Manual (checker.blade.php) dan BPS Import (bps-import.blade.php).
 *
 * Semua publikasi berjalan paralel (unduh, baca teks, cek kriteria); OCR antre bergiliran di
 * PdfExtract supaya memori browser aman. Tiap kartu menampilkan tahapan, progres & hasilnya sendiri.
 *
 * Job: {
 *   title:  string,
 *   cover:  string|null,                       // URL sampul (BPS) — null = ikon PDF
 *   source: File|string,                       // file upload atau URL PDF (same-origin/proxy)
 *   send:   async (extracted|null) => result,  // kirim ke server, kembalikan hasil (punya hasil_id & checks)
 * }
 */
const BatchCheck = (() => {
    const STEPS = [
        ['download', 'Unduh PDF'],
        ['text',     'Baca teks'],
        ['ocr',      'OCR kover'],
        ['check',    'Cek kriteria'],
    ];
    const ICON = {
        pending: '<i class="ti ti-circle text-secondary"></i>',
        active:  '<i class="ti ti-loader-2 icon-spin text-primary"></i>',
        wait:    '<i class="ti ti-hourglass text-warning"></i>',
        done:    '<i class="ti ti-circle-check text-green"></i>',
        skip:    '<i class="ti ti-minus text-secondary"></i>',
        error:   '<i class="ti ti-circle-x text-red"></i>',
    };

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function cardHtml(job, i) {
        const cover = job.cover
            ? `<img src="${esc(job.cover)}" alt="sampul" onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'ti ti-file-type-pdf'}))">`
            : '<i class="ti ti-file-type-pdf"></i>';
        return `
        <div class="col">
            <div class="card h-100 batch-card" data-i="${i}">
                <div class="batch-cover">${cover}</div>
                <div class="card-body p-3 d-flex flex-column">
                    <div class="fw-semibold small batch-title" title="${esc(job.title)}">${esc(job.title)}</div>
                    <ul class="list-unstyled small my-2 batch-steps">
                        ${STEPS.map(([key, label]) => `<li data-step="${key}">${ICON.pending} <span>${label}</span></li>`).join('')}
                    </ul>
                    <div class="progress progress-sm"><div class="progress-bar" style="width:0%"></div></div>
                    <div class="small text-secondary mt-1 batch-msg text-truncate">Menunggu…</div>
                    <div class="batch-result mt-auto pt-2"></div>
                </div>
            </div>
        </div>`;
    }

    function setStep(card, key, state) {
        const li = card.querySelector(`[data-step="${key}"]`);
        if (li) li.querySelector('i').outerHTML = ICON[state];
    }

    /** Tandai tahap aktif; tahap sebelumnya otomatis selesai. */
    function activate(card, key, state = 'active') {
        let reached = false;
        for (const [k] of STEPS) {
            if (k === key) { setStep(card, k, state); reached = true; continue; }
            const li = card.querySelector(`[data-step="${k}"]`);
            const cur = li?.querySelector('i')?.className || '';
            if (!reached && !cur.includes('ti-minus') && !cur.includes('ti-circle-check')) setStep(card, k, 'done');
        }
    }

    function progress(card, frac, msg) {
        if (frac != null) card.querySelector('.progress-bar').style.width = Math.round(frac * 100) + '%';
        if (msg != null) card.querySelector('.batch-msg').textContent = msg;
    }

    function resultHtml(r) {
        const s = r.summary || {};
        return `
            <div class="d-flex flex-wrap gap-1 mb-2">
                <span class="badge bg-green-lt">${s.ok ?? 0} OK</span>
                <span class="badge bg-yellow-lt">${s.perlu_dicek ?? 0} Dicek</span>
                <span class="badge bg-red-lt">${s.tidak_ada ?? 0} Masalah</span>
            </div>`;
    }

    /**
     * Jalankan semua job paralel. Kembalikan array hasil per job: { job, result } atau { job, error }.
     * opts.onReview(index) dipanggil saat tombol "Tinjau" di kartu diklik.
     */
    async function run(container, jobs, { onReview } = {}) {
        container.innerHTML = `<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-5 g-3">${jobs.map(cardHtml).join('')}</div>`;
        const cards = [...container.querySelectorAll('.batch-card')];
        const outcomes = new Array(jobs.length);

        container.onclick = e => {
            const b = e.target.closest('[data-review]');
            if (b) onReview?.(parseInt(b.dataset.review, 10));
        };

        await Promise.all(jobs.map(async (job, i) => {
            const card = cards[i];
            let phaseFrac = { download: 0, text: 0, ocr: 0 };
            try {
                if (!(typeof job.source === 'string')) setStep(card, 'download', 'skip');

                let extracted = null;
                try {
                    const src = typeof job.source === 'string' ? job.source : await job.source.arrayBuffer();
                    extracted = await PdfExtract.extract(src, {
                        onProgress: (msg, frac, phase) => {
                            if (phase === 'ocr-wait') { activate(card, 'ocr', 'wait'); progress(card, null, msg); return; }
                            if (!phase) { progress(card, null, msg); return; }
                            activate(card, phase);
                            phaseFrac[phase] = frac || 0;
                            // progres gabungan: unduh 0–30%, baca teks 30–70%, OCR 70–90%
                            const total = phaseFrac.download * 0.3 + phaseFrac.text * 0.4 + phaseFrac.ocr * 0.2;
                            progress(card, total, msg);
                        },
                    });
                    if (!extracted.ocr_pages?.length) setStep(card, 'ocr', 'skip'); // tidak perlu OCR / dilewati
                } catch (err) {
                    // pembacaan di browser gagal → server mencoba membaca sendiri
                    console.warn('Ekstraksi teks di browser gagal, dibaca di server', err);
                    ['download', 'text', 'ocr'].forEach(k => setStep(card, k, 'skip'));
                }

                activate(card, 'check');
                progress(card, 0.92, 'Memeriksa kriteria…');
                const result = await job.send(extracted);
                if (extracted) result.ocr_lines = extracted.ocr_lines;

                setStep(card, 'check', 'done');
                progress(card, 1, 'Selesai');
                card.querySelector('.progress-bar').classList.add('bg-green');
                card.querySelector('.batch-result').innerHTML = resultHtml(result) +
                    `<button type="button" class="btn btn-sm btn-primary w-100" data-review="${i}">
                        <i class="ti ti-player-play"></i> Tinjau
                     </button>`;
                outcomes[i] = { job, result };
            } catch (err) {
                const active = card.querySelector('.ti-loader-2, .ti-hourglass')?.closest('li')?.dataset.step || 'check';
                setStep(card, active, 'error');
                card.querySelector('.progress-bar').classList.add('bg-red');
                progress(card, 1, 'Gagal');
                card.querySelector('.batch-result').innerHTML =
                    `<div class="text-red small">${esc(err.message)}</div>`;
                outcomes[i] = { job, error: err.message };
            }
        }));

        return outcomes;
    }

    /** Tandai kartu yang sudah ditinjau (dipanggil setelah tinjauan selesai/ditutup). */
    function markReviewed(container, i, label) {
        const btn = container.querySelector(`[data-review="${i}"]`);
        if (btn) btn.innerHTML = `<i class="ti ti-player-play"></i> ${esc(label)}`;
    }

    return { run, markReviewed };
})();
