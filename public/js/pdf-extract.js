/**
 * PdfExtract — baca teks PDF di browser petugas (pdf.js) + OCR halaman gambar (Tesseract.js).
 *
 * Server hosting tidak mengizinkan exec() (Ghostscript/Tesseract tidak bisa dipanggil), jadi
 * ekstraksi dilakukan di sini lalu hasilnya dikirim ke server sebagai field `extracted`;
 * evaluasi kriteria tetap di server (PdfCheckerService::checkFromText).
 *
 * - pdf.js bisa membuka PDF terenkripsi dari web BPS (password pemilik saja) tanpa Ghostscript.
 * - Halaman yang hampir tanpa teks tetapi berisi gambar (mis. kover JPG) di-OCR dengan Tesseract.js.
 *   Mesin OCR (±15 MB) diunduh dari CDN saat pertama dipakai — petugas diminta konfirmasi dulu.
 *
 * Hasil extract():
 *   { pages: [string], cover_words: [{text,x,y}], page_w, page_h, method,
 *     ocr_pages: [nomor halaman yang di-OCR], image_pages: [nomor halaman berupa gambar], ocr_skipped,
 *     ocr_lines: { nomorHalaman: [{s, x, y, w, h}] } }  // baris OCR di ruang PDF, untuk pencarian di viewer
 */
const PdfExtract = (() => {
    const TESSERACT_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
    const OCR_LANG      = 'ind+eng';
    const OCR_SIZE_MB   = 15;   // perkiraan unduhan pertama (core wasm + data bahasa)
    const OCR_SCALE     = 2;    // resolusi render halaman untuk OCR
    const COVER_SCALE   = 3;    // kover depan/belakang dirender lebih tajam: katalog, nama BPS, alamat biasanya kecil
    const MIN_TEXT      = 20;   // < 20 karakter → dianggap halaman gambar (jika memang ada gambar)
    const FRONT_PAGES   = 20;   // halaman awal — sama dengan PdfCheckerService::FRONT_PAGES
    const MAX_OCR_PAGES = 30;
    const MIN_CONFIDENCE = 60;  // kata OCR di bawah tingkat keyakinan ini dibuang
    const WHITE_MIN     = 220;  // ambang piksel "putih" untuk masker teks putih di kover
    const PSM_AUTO      = '3';  // Tesseract page segmentation: otomatis
    const PSM_SPARSE    = '11'; // Tesseract page segmentation: teks tersebar (sparse text)
    const OCR_FLAG      = 'pubchecker.ocrDownloaded';

    let buildBase = null;
    let pdfjsLib  = null;

    function init({ pdfjsBuild }) {
        buildBase = pdfjsBuild.replace(/\/?$/, '/');
    }

    async function loadPdfjs() {
        if (pdfjsLib) return pdfjsLib;
        pdfjsLib = await import(buildBase + 'pdf.mjs');
        pdfjsLib.GlobalWorkerOptions.workerSrc = buildBase + 'pdf.worker.mjs';
        return pdfjsLib;
    }

    // ── Teks per halaman ────────────────────────────────────────────
    // Gabungkan item teks dengan memperhatikan jarak, supaya kata yang terpecah
    // jadi beberapa item tidak disisipi spasi (mis. "Katalog" + ":" → "Katalog:").
    function itemsToText(items) {
        let out = '', lastY = null, lastEnd = null;
        for (const it of items) {
            if (!('str' in it)) continue;
            const x = it.transform[4], y = it.transform[5];
            const h = Math.abs(it.height || it.transform[3] || 10);
            if (lastY !== null && it.str !== '') {
                if (Math.abs(y - lastY) > h * 0.5) {
                    if (!out.endsWith('\n')) out += '\n';
                } else if (x - lastEnd > h * 0.15 && !/\s$/.test(out) && !/^\s/.test(it.str)) {
                    out += ' ';
                }
            }
            out += it.str;
            if (it.str !== '') { lastY = y; lastEnd = x + (it.width || 0); }
            if (it.hasEOL && !out.endsWith('\n')) out += '\n';
        }
        return out;
    }

    // Posisi kata di kover (koordinat dari kiri-atas, satuan pt) untuk cek posisi_area
    function coverWordsFrom(items, viewport) {
        return items
            .filter(it => 'str' in it && it.str.trim() !== '')
            .map(it => {
                const [x, y] = viewport.convertToViewportPoint(it.transform[4], it.transform[5]);
                return { text: it.str.trim(), x: Math.round(x), y: Math.round(y) };
            });
    }

    async function hasImage(page, lib) {
        const ops = await page.getOperatorList();
        const imgOps = [lib.OPS.paintImageXObject, lib.OPS.paintInlineImageXObject, lib.OPS.paintImageMaskXObject];
        return ops.fnArray.some(fn => imgOps.includes(fn));
    }

    // ── OCR ─────────────────────────────────────────────────────────
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            if (window.Tesseract) return resolve();
            const s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = () => reject(new Error('Gagal memuat mesin OCR dari ' + new URL(src).host));
            document.head.appendChild(s);
        });
    }

    function ocrDownloadedBefore() {
        try { return localStorage.getItem(OCR_FLAG) === '1'; } catch { return false; }
    }

    function markOcrDownloaded() {
        try { localStorage.setItem(OCR_FLAG, '1'); } catch { /* abaikan */ }
    }

    /**
     * Peringatan sebelum unduhan pertama mesin OCR. Resolve true = lanjut OCR, false = lewati.
     * Dibuat tanpa bergantung pada Bootstrap JS supaya selalu tampil.
     */
    // Beberapa publikasi bisa meminta OCR bersamaan: tampilkan peringatan sekali saja, dan
    // pakai jawaban yang sama untuk semua publikasi selama halaman ini terbuka.
    let ocrChoice = null;   // Promise<boolean>

    function confirmFirstDownload(pageNumbers) {
        if (ocrDownloadedBefore()) return Promise.resolve(true);
        ocrChoice ??= askFirstDownload(pageNumbers);
        return ocrChoice;
    }

    function askFirstDownload(pageNumbers) {
        return new Promise(resolve => {
            const list = pageNumbers.length > 8
                ? pageNumbers.slice(0, 8).join(', ') + ', …'
                : pageNumbers.join(', ');
            const wrap = document.createElement('div');
            wrap.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:2000;display:flex;align-items:center;justify-content:center;padding:16px;';
            wrap.innerHTML = `
                <div style="background:#fff;border-radius:10px;max-width:480px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,.25);">
                    <div style="padding:18px 20px 8px;">
                        <h5 style="margin:0 0 8px;font-weight:600;">
                            <i class="ti ti-photo-scan text-primary me-1"></i> Diperlukan OCR untuk membaca gambar
                        </h5>
                        <p class="small mb-2">
                            Halaman <strong>${list}</strong> perlu dibaca dengan OCR — judul kover sering berupa
                            gambar JPG / outline, dan halaman hasil scan tidak memiliki teks.
                        </p>
                        <div class="alert alert-warning py-2 px-3 small mb-2">
                            <i class="ti ti-download me-1"></i>
                            Browser Anda akan <strong>mengunduh mesin OCR ± ${OCR_SIZE_MB} MB</strong>
                            (Tesseract, bahasa Indonesia &amp; Inggris) dari internet.
                            Ini hanya terjadi <strong>sekali</strong> — setelahnya tersimpan di cache browser.
                        </div>
                        <p class="small text-muted mb-0">
                            Hasil OCR bisa salah baca (mis. angka 0 terbaca huruf O), jadi kriteria di halaman tersebut
                            akan diberi tanda untuk dicek ulang. Jika dilewati, halaman tersebut tidak diperiksa otomatis.
                        </p>
                    </div>
                    <div style="padding:12px 20px 18px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
                        <button type="button" class="btn btn-light border btn-sm" data-act="skip">Lewati OCR</button>
                        <button type="button" class="btn btn-primary btn-sm" data-act="ok">
                            <i class="ti ti-download me-1"></i> Unduh &amp; Lanjutkan
                        </button>
                    </div>
                </div>`;
            wrap.addEventListener('click', e => {
                const act = e.target.closest('[data-act]')?.dataset.act;
                if (!act) return;
                wrap.remove();
                resolve(act === 'ok');
            });
            document.body.appendChild(wrap);
        });
    }

    const OCR_STATUS = {
        'loading tesseract core':        'Mengunduh mesin OCR',
        'initializing tesseract':        'Menyiapkan OCR',
        'loading language traineddata':  'Mengunduh data bahasa OCR',
        'initializing api':              'Menyiapkan OCR',
        'recognizing text':              'Membaca teks gambar',
    };

    /**
     * OCR satu gambar → baris berisi kata yang terbaca yakin saja. Foto/ilustrasi di kover
     * menghasilkan "teks" acak (mis. "BS AB. I~ Dae") yang bisa memicu cek pola secara keliru.
     */
    async function recognizeLines(worker, canvas) {
        const { data } = await worker.recognize(canvas, {}, { blocks: true });
        return (data.blocks || [])
            .flatMap(b => b.paragraphs.flatMap(p => p.lines))
            .map(l => {
                const words = l.words
                    .filter(w => w.text.trim() && w.confidence >= MIN_CONFIDENCE)
                    .map(w => ({ text: w.text.trim(), bbox: w.bbox }));
                return { text: words.map(w => w.text).join(' '), words, bbox: l.bbox };
            })
            .filter(l => l.text.replace(/[^\p{L}\p{N}]/gu, '').length >= 2);
    }

    function normLine(s) {
        return s.toLowerCase().replace(/[^\p{L}\p{N}]/gu, '');
    }

    /** Baris dengan teks sama yang kotak posisinya saling tumpang tindih. */
    function sameSpot(a, b) {
        if (normLine(a.text) !== normLine(b.text)) return false;
        const A = a.bbox, B = b.bbox;
        return A.x0 < B.x1 && B.x0 < A.x1 && A.y0 < B.y1 && B.y0 < A.y1;
    }

    /** Piksel hampir putih → hitam, sisanya → putih (teks putih jadi teks hitam di latar bersih). */
    function whiteTextMask(canvas) {
        const ctx = canvas.getContext('2d');
        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const d   = img.data;
        for (let i = 0; i < d.length; i += 4) {
            const v = Math.min(d[i], d[i + 1], d[i + 2]) >= WHITE_MIN ? 0 : 255;
            d[i] = d[i + 1] = d[i + 2] = v;
        }
        ctx.putImageData(img, 0, 0);
    }

    // Antrean OCR: mesin Tesseract berat (±100–200 MB per proses), jadi saat beberapa publikasi
    // diperiksa paralel, OCR dijalankan bergiliran — satu per satu.
    let ocrQueue = Promise.resolve();

    function withOcrLock(fn) {
        const run = ocrQueue.then(fn);
        ocrQueue = run.catch(() => {});
        return run;
    }

    /** @param {Set<number>} coverIdx indeks halaman kover (depan & belakang) — dibaca lebih teliti */
    async function runOcr(pdf, pageIdx, coverIdx, onProgress) {
        await loadScript(TESSERACT_URL);

        let current = '';
        const worker = await window.Tesseract.createWorker(OCR_LANG, 1, {
            logger: m => {
                const label = OCR_STATUS[m.status];
                if (!label) return;
                const pct = Math.round((m.progress || 0) * 100);
                onProgress(`${label}${current}… ${pct}%`, m.progress || 0, 'ocr');
            },
        });
        markOcrDownloaded();

        const texts    = {};
        const ocrLines = {};
        let coverWords = null;
        try {
            for (let n = 0; n < pageIdx.length; n++) {
                const idx = pageIdx[n];
                current = ` (halaman ${idx + 1}, ${n + 1}/${pageIdx.length})`;
                const page     = await pdf.getPage(idx + 1);
                const isCover  = coverIdx.has(idx);
                const scale    = isCover ? COVER_SCALE : OCR_SCALE;
                const viewport = page.getViewport({ scale });
                const canvas   = document.createElement('canvas');
                canvas.width   = Math.ceil(viewport.width);
                canvas.height  = Math.ceil(viewport.height);
                await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;

                // Kover depan/belakang: mode "sparse text" — teks tersebar di atas foto (mis. nomor Katalog kecil)
                await worker.setParameters({ tessedit_pageseg_mode: isCover ? PSM_SPARSE : PSM_AUTO });
                let lines = await recognizeLines(worker, canvas);
                if (isCover) {
                    // Teks putih di atas kotak gelap/foto (mis. "BADAN PUSAT STATISTIK" di pita bawah,
                    // "Volume 2, 2026") tidak terbaca — baca ulang hanya piksel putih sebagai teks hitam.
                    // (sparse text juga di sini: slogan "DATA Mencerdaskan Bangsa" di atas foto ramai
                    // hanya terbaca dengan mode ini)
                    whiteTextMask(canvas);
                    // Buang baris yang sudah terbaca di posisi yang sama (bukan berdasarkan isi:
                    // "PROVINSI KALIMANTAN SELATAN" bisa muncul di judul DAN di nama BPS penerbit)
                    const extra = (await recognizeLines(worker, canvas)).filter(l => !lines.some(o => sameSpot(o, l)));
                    lines = lines.concat(extra);
                }

                texts[idx] = lines.map(l => l.text).join('\n');
                ocrLines[idx + 1] = lines.map(l => toPdfLine(l, viewport)).filter(Boolean);
                if (idx === 0) {
                    coverWords = lines.flatMap(l => l.words).map(w => ({
                        text: w.text,
                        x: Math.round(w.bbox.x0 / scale),
                        y: Math.round(w.bbox.y1 / scale),
                    }));
                }
                canvas.width = canvas.height = 0; // lepas memori
            }
        } finally {
            await worker.terminate();
        }
        return { texts, coverWords, ocrLines };
    }

    /**
     * Baris OCR → koordinat ruang PDF (titik asal kiri-bawah), untuk disisipkan ke lapisan teks
     * viewer pdf.js supaya hasil OCR bisa dicari (Ctrl+F) dan disorot. Lihat CheckerReview.
     */
    function toPdfLine(line, viewport) {
        if (!line.words.length) return null;
        const x0 = Math.min(...line.words.map(w => w.bbox.x0));
        const x1 = Math.max(...line.words.map(w => w.bbox.x1));
        const y0 = Math.min(...line.words.map(w => w.bbox.y0));
        const y1 = Math.max(...line.words.map(w => w.bbox.y1));
        const [px0, py0] = viewport.convertToPdfPoint(x0, y1); // kiri-bawah
        const [px1, py1] = viewport.convertToPdfPoint(x1, y0); // kanan-atas
        const r = n => Math.round(n * 10) / 10;
        return {
            s: line.text,
            x: r(Math.min(px0, px1)), y: r(Math.min(py0, py1)),
            w: r(Math.abs(px1 - px0)), h: r(Math.abs(py1 - py0)),
        };
    }

    // ── API utama ───────────────────────────────────────────────────
    /**
     * @param {ArrayBuffer|string} source  isi file (upload) atau URL PDF (same-origin/proxy)
     * @param {{onProgress?: (msg:string, fraction:number, phase:'download'|'text'|'ocr-wait'|'ocr') => void}} opts
     */
    async function extract(source, { onProgress = () => {} } = {}) {
        const lib  = await loadPdfjs();
        const task = lib.getDocument(typeof source === 'string'
            ? { url: source, disableRange: true, disableStream: true }
            : { data: source });
        const mb = b => (b / 1048576).toFixed(1);
        task.onProgress = ({ loaded, total }) => {
            onProgress(total ? `Mengunduh PDF ${mb(loaded)}/${mb(total)} MB` : `Mengunduh PDF ${mb(loaded)} MB`,
                total ? loaded / total : 0, 'download');
        };
        const pdf  = await task.promise;
        const n    = pdf.numPages;

        const pages = [];
        const imageCandidates = [];
        let coverWords = [], pageW = 595, pageH = 842, coverHasImage = false;
        const frontWords = []; // posisi kata "ISSN" di halaman awal (mis. pojok kanan atas halaman Tim Penyusun)

        for (let i = 1; i <= n; i++) {
            const page    = await pdf.getPage(i);
            const content = await page.getTextContent();
            const text    = itemsToText(content.items);
            pages.push(text);

            if (i === 1) {
                const vp = page.getViewport({ scale: 1 });
                pageW = Math.round(vp.width);
                pageH = Math.round(vp.height);
                coverWords = coverWordsFrom(content.items, vp);
                coverHasImage = await hasImage(page, lib);
            } else if (i <= FRONT_PAGES && /ISSN/i.test(text)) {
                const vp = page.getViewport({ scale: 1 });
                coverWordsFrom(content.items, vp)
                    .filter(w => /ISSN/i.test(w.text))
                    .forEach(w => frontWords.push({ hal: i, ...w, pw: Math.round(vp.width), ph: Math.round(vp.height) }));
            }
            const textPoor = text.replace(/\s+/g, '').length < MIN_TEXT;
            if (textPoor && (i === 1 ? coverHasImage : await hasImage(page, lib))) {
                imageCandidates.push(i - 1);
            }
            page.cleanup();
            onProgress(`Membaca teks halaman ${i}/${n}`, i / n, 'text');
        }

        // Kover depan & kover belakang (halaman terakhir) selalu di-OCR walau sudah ada teks: judul,
        // slogan, alamat, dll. sering berupa gambar JPG atau teks yang di-outline (vektor, tidak terdeteksi
        // sebagai gambar). Hasil OCR digabung dengan teks yang sudah terbaca.
        const coverIdx   = new Set([0, n - 1]);
        const supplement = new Set([...coverIdx].filter(i => !imageCandidates.includes(i)));
        const ocrIdx = [...new Set([...supplement, ...imageCandidates])]
            .sort((a, b) => a - b).slice(0, MAX_OCR_PAGES);
        let ocrPages = [], ocrSkipped = false, ocrLinesAll = {};

        if (ocrIdx.length) {
            const ok = await confirmFirstDownload(ocrIdx.map(i => i + 1));
            if (ok) {
                onProgress('Menunggu giliran OCR…', 0, 'ocr-wait');
                const { texts, coverWords: ocrWords, ocrLines } =
                    await withOcrLock(() => runOcr(pdf, ocrIdx, coverIdx, onProgress));
                ocrLinesAll = ocrLines;
                Object.entries(texts).forEach(([idx, t]) => {
                    pages[idx] = supplement.has(Number(idx)) ? pages[idx] + '\n' + t : t;
                });
                if (ocrWords) coverWords = supplement.has(0) ? coverWords.concat(ocrWords) : ocrWords;
                ocrPages = ocrIdx.map(i => i + 1);
            } else {
                ocrSkipped = true;
            }
        }

        const { length: fileSize } = await pdf.getDownloadInfo().catch(() => ({ length: 0 }));
        await pdf.destroy();

        return {
            file_size: fileSize || 0,
            pages,
            cover_words: coverWords,
            front_words: frontWords,
            page_w: pageW,
            page_h: pageH,
            method: ocrPages.length ? 'pdfjs+ocr' : 'pdfjs',
            ocr_pages: ocrPages,
            image_pages: [...new Set([0, n - 1, ...imageCandidates])].sort((a, b) => a - b).map(i => i + 1),
            ocr_skipped: ocrSkipped,
            ocr_lines: ocrLinesAll, // { nomorHalaman: [{s, x, y, w, h}] } — untuk pencarian di viewer
        };
    }

    return { init, extract };
})();
