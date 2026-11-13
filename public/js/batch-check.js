/**
 * BatchCheck — memeriksa beberapa publikasi (maks. 10) sekaligus dengan tampilan kartu berjejer.
 * Dipakai bersama oleh Upload Manual (checker.blade.php) dan BPS Import (bps-import.blade.php).
 *
 * Paling banyak MAX_PARALLEL publikasi diproses bersamaan (unduh, baca teks, cek kriteria), sisanya
 * menunggu giliran; OCR antre satu per satu di PdfExtract — supaya memori browser aman.
 * Tiap kartu menampilkan tahapan, progres & hasilnya sendiri.
 *
 * Job: {
 *   title:  string,
 *   cover:  string|null,                       // URL sampul (BPS) — null = ikon PDF
 *   source: File|string,                       // file upload atau URL PDF (same-origin/proxy)
 *   send:   async (extracted|null) => result,  // kirim ke server, kembalikan hasil (punya hasil_id & checks)
 * }
 */
const BatchCheck = (() => {
    const MAX_PARALLEL = 3;

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
                    <div class="small text-secondary mt-1 batch-msg text-truncate">Menunggu giliran…</div>
                    <!-- tinjau draf sambil diproses (siapTinjau); disembunyikan saat hasil lengkap & tombol Tinjau muncul -->
                    <div class="batch-awal mt-2" hidden></div>
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

    /**
     * Unduh PDF sekali (dipakai untuk baca teks DAN viewer tinjauan, jadi tidak diunduh dua kali).
     * Tahan koneksi lemah: macet (tidak ada data masuk) ≥ MACET_MS → dihentikan & dicoba lagi s.d. COBA kali,
     * melanjutkan dari byte yang sudah terunduh (Range) bila server mendukung; selain itu mulai dari awal.
     * @returns {Promise<Uint8Array>}
     */
    const MACET_MS = 45000, COBA = 3;
    async function unduh(src, onProgress) {
        const url = (() => { const u = new URL(src, location.href); return location.origin + u.pathname + u.search; })();
        const bagian = [];
        let terunduh = 0, total = 0, galat = null;
        const mb = b => (b / 1048576).toFixed(1);

        for (let coba = 1; coba <= COBA; coba++) {
            const ctrl = new AbortController();
            let jaga = setTimeout(() => ctrl.abort(), MACET_MS);
            const jagaUlang = () => { clearTimeout(jaga); jaga = setTimeout(() => ctrl.abort(), MACET_MS); };
            try {
                const res = await fetch(url, {
                    credentials: 'same-origin', signal: ctrl.signal,
                    headers: terunduh ? { Range: `bytes=${terunduh}-` } : {},
                });
                if (terunduh && res.status !== 206) { bagian.length = 0; terunduh = 0; } // server tidak mendukung lanjutan
                if (!res.ok) throw new Error(`Server membalas ${res.status}`);
                const panjang = parseInt(res.headers.get('Content-Length') || '0', 10);
                if (!total) total = res.status === 206
                    ? parseInt((res.headers.get('Content-Range') || '').split('/')[1] || '0', 10) || terunduh + panjang
                    : panjang;
                const reader = res.body.getReader();
                for (;;) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    jagaUlang();
                    bagian.push(value);
                    terunduh += value.length;
                    onProgress(`Mengunduh PDF ${mb(terunduh)}${total ? '/' + mb(total) : ''} MB${coba > 1 ? ` (percobaan ${coba})` : ''}`,
                        total ? terunduh / total : 0);
                }
                clearTimeout(jaga);
                if (total && terunduh < total) throw new Error('Unduhan terputus');
                const hasil = new Uint8Array(terunduh);
                let pos = 0;
                for (const b of bagian) { hasil.set(b, pos); pos += b.length; }
                return hasil;
            } catch (e) {
                clearTimeout(jaga);
                galat = e.name === 'AbortError' ? new Error('Koneksi macet saat mengunduh PDF') : e;
                if (coba < COBA) onProgress(`${galat.message}, mencoba lagi (${coba + 1}/${COBA})...`, total ? terunduh / total : 0);
            }
        }
        throw galat;
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
            // gagal (mis. koneksi lemah) → ulangi publikasi itu saja, tanpa mengulang semuanya
            const u = e.target.closest('[data-ulang]');
            if (u) { u.disabled = true; jalankan(parseInt(u.dataset.ulang, 10)); }
        };

        // Batasi job yang berjalan bersamaan: tiap PDF (s.d. 50MB) diunduh & diparse di memori browser,
        // jadi 10 sekaligus bisa membuat tab kehabisan memori. Job lain menunggu giliran ("Menunggu…").
        let aktif = 0;
        const antre = [];
        const ambilSlot = () => aktif < MAX_PARALLEL ? (aktif++, Promise.resolve()) : new Promise(r => antre.push(r));
        const lepasSlot = () => { const next = antre.shift(); if (next) next(); else aktif--; };

        /** Proses satu publikasi (juga dipakai tombol "Coba lagi"). Hasil disimpan di outcomes[i]. */
        async function jalankan(i) {
            const job  = jobs[i];
            const card = cards[i];
            let phaseFrac = { download: 0, text: 0, ocr: 0 };
            // ulang: kembalikan tampilan kartu ke awal
            STEPS.forEach(([k]) => setStep(card, k, 'pending'));
            card.querySelector('.progress-bar').classList.remove('bg-red', 'bg-green');
            card.querySelector('.batch-result').innerHTML = '';
            progress(card, 0, 'Menunggu giliran…');
            const lapor = (msg, frac, phase) => {
                if (phase === 'ocr-wait') { activate(card, 'ocr', 'wait'); progress(card, null, msg); return; }
                if (!phase) { progress(card, null, msg); return; }
                activate(card, phase);
                phaseFrac[phase] = frac || 0;
                // progres gabungan: unduh 0–30%, baca teks 30–70%, OCR 70–90%
                const total = phaseFrac.download * 0.3 + phaseFrac.text * 0.4 + phaseFrac.ocr * 0.2;
                progress(card, total, msg);
            };

            await ambilSlot();
            let blobUrl = null; // PDF yang sudah terunduh → dipakai viewer tinjauan (tidak diunduh ulang)
            try {
                if (!(typeof job.source === 'string')) setStep(card, 'download', 'skip');

                let extracted = null;
                try {
                    let data;
                    if (typeof job.source === 'string') {
                        activate(card, 'download');
                        let bytes;
                        try {
                            bytes = await unduh(job.source, (msg, frac) => { lapor(msg, frac, 'download'); job.onUnduh?.(frac || 0); });
                        } catch (e) {
                            job.onPdf?.(null); // viewer tinjauan tidak menunggu lagi: buka langsung dari sumbernya
                            throw e;
                        }
                        // salinan untuk viewer dibuat dulu: pdf.js memindahkan isi buffer ke worker-nya
                        blobUrl = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
                        job.onPdf?.(blobUrl); // tinjauan yang sudah dibuka memakai unduhan ini (tidak unduh dobel)
                        data = bytes.buffer;
                    } else {
                        data = await job.source.arrayBuffer();
                    }
                    extracted = await PdfExtract.extract(data, { onProgress: lapor });
                    if (!extracted.ocr_pages?.length) setStep(card, 'ocr', 'skip'); // tidak perlu OCR / dilewati
                } catch (err) {
                    // pembacaan di browser gagal (mis. koneksi putus) → server mengunduh & membaca sendiri
                    console.warn('Ekstraksi teks di browser gagal, dibaca di server', err);
                    ['download', 'text', 'ocr'].forEach(k => setStep(card, k, 'skip'));
                    progress(card, null, 'Unduhan di browser gagal, server membaca PDF sendiri…');
                }

                activate(card, 'check');
                progress(card, 0.92, 'Memeriksa kriteria…');
                const result = await job.send(extracted);
                if (extracted) result.ocr_lines = extracted.ocr_lines;
                // viewer tinjauan memakai PDF yang sudah terunduh; tanpa itu tetap URL proxy (dimuat bertahap)
                if (blobUrl) result._pdfUrl = blobUrl;

                setStep(card, 'check', 'done');
                progress(card, 1, 'Selesai');
                card.querySelector('.progress-bar').classList.add('bg-green');
                card.querySelector('.batch-result').innerHTML = resultHtml(result) +
                    `<button type="button" class="btn btn-sm btn-primary w-100" data-review="${i}">
                        <i class="ti ti-player-play"></i> Tinjau
                     </button>`;
                outcomes[i] = { job, result };
                card.querySelector('.batch-awal').hidden = true; // tombol Tinjau hasil lengkap menggantikannya
            } catch (err) {
                // blob mungkin sudah dibuka viewer tinjauan (job.onPdf) → jangan dicabut
                if (blobUrl && !job.onPdf) URL.revokeObjectURL(blobUrl);
                const active = card.querySelector('.ti-loader-2, .ti-hourglass')?.closest('li')?.dataset.step || 'check';
                setStep(card, active, 'error');
                card.querySelector('.progress-bar').classList.add('bg-red');
                progress(card, 1, 'Gagal');
                card.querySelector('.batch-result').innerHTML =
                    `<div class="text-red small mb-2">${esc(err.message)}</div>
                     <button type="button" class="btn btn-sm btn-outline-primary w-100" data-ulang="${i}">
                        <i class="ti ti-refresh"></i> Coba lagi
                     </button>`;
                outcomes[i] = { job, error: err.message };
            } finally {
                lepasSlot();
            }
        }

        await Promise.all(jobs.map((job, i) => jalankan(i)));

        return outcomes;
    }

    /**
     * Draf hasil publikasi ke-i sudah dibuat di server: tampilkan tombol untuk langsung meninjau
     * (menambah ketidaksesuaian/catatan) selama PDF masih diunduh, dibaca & dicek.
     */
    function siapTinjau(container, i) {
        const box = container.querySelector(`.batch-card[data-i="${i}"] .batch-awal`);
        if (!box || box.dataset.siap) return;
        box.dataset.siap = '1';
        box.hidden = false;
        box.innerHTML = `
            <button type="button" class="btn btn-sm btn-outline-primary w-100" data-review="${i}"
                    title="Tandai ketidaksesuaian & isi catatan sekarang; hasil cek otomatis menyusul tanpa mengubah pilihanmu">
                <i class="ti ti-pencil"></i> Tinjau sekarang
            </button>`;
    }

    /** Tandai kartu yang sudah ditinjau (dipanggil setelah tinjauan selesai/ditutup). */
    function markReviewed(container, i, label) {
        const btn = container.querySelector(`.batch-result [data-review="${i}"]`);
        if (btn) btn.innerHTML = `<i class="ti ti-player-play"></i> ${esc(label)}`;
    }

    return { run, markReviewed, siapTinjau };
})();
