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
    const COVER_SCALE   = 3;
    const ISI_SCALE     = 1.5;  // halaman isi (di luar halaman depan): lebih rendah supaya OCR lebih cepat
    // jumlah mesin OCR paralel: tiap mesin ±100–200 MB memori — 3 bila laptop ≥ 8 GB & ≥ 4 inti, selain itu 2
    const JUMLAH_MESIN  = (navigator.deviceMemory || 4) >= 8 && (navigator.hardwareConcurrency || 2) >= 4 ? 3
                        : (navigator.hardwareConcurrency || 2) >= 2 ? 2 : 1;    // kover depan/belakang dirender lebih tajam: katalog, nama BPS, alamat biasanya kecil
    const MIN_TEXT      = 20;   // < 20 karakter → dianggap halaman gambar (jika memang ada gambar)
    const FRONT_PAGES   = 20;   // halaman awal — sama dengan PdfCheckerService::FRONT_PAGES
    const MIN_CONFIDENCE = 60;  // kata OCR di bawah tingkat keyakinan ini dibuang
    const WHITE_MIN     = 220;  // ambang piksel "putih" untuk masker teks putih di kover
    const PSM_AUTO      = '3';  // Tesseract page segmentation: otomatis
    const PSM_SPARSE    = '11'; // Tesseract page segmentation: teks tersebar (sparse text)
    const OCR_FLAG      = 'pubchecker.ocrDownloaded';

    let buildBase = null;
    let pdfjsLib  = null;

    function init({ pdfjsBuild }) {
        // modul & worker pdf.js harus satu origin dengan halaman (lihat sameOrigin)
        buildBase = sameOrigin(pdfjsBuild).replace(/\/?$/, '/');
    }

    async function loadPdfjs() {
        if (pdfjsLib) return pdfjsLib;
        pdfjsLib = await import(buildBase + 'pdf.mjs?v=pc3');
        pdfjsLib.GlobalWorkerOptions.workerSrc = buildBase + 'pdf.worker.mjs?v=pc3';
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

    // ── Tepi halaman & font ────────────────────────────────────────
    const PITA_TEPI     = 0.12; // pita atas/bawah (12% tinggi halaman): letak nomor halaman & running title
    const MAKS_TEPI     = 60;   // item per halaman yang dikirim (cukup untuk header + footer)
    const HAL_FONT_AWAL = 5;    // halaman awal yang dibaca nama fontnya (kover, halaman judul utama) + halaman terakhir

    /** Teks di pita atas & bawah halaman, koordinat dari kiri-atas (pt): {w, h, it: [{t, x, y}]}. */
    function tepiHalaman(items, page) {
        const vp = page.getViewport({ scale: 1 });
        const it = [];
        for (const x of items) {
            if (!('str' in x) || !x.str.trim()) continue;
            const [px, py] = vp.convertToViewportPoint(x.transform[4], x.transform[5]);
            if (py < vp.height * PITA_TEPI || py > vp.height * (1 - PITA_TEPI)) {
                it.push({ t: x.str.trim().slice(0, 120), x: Math.round(px), y: Math.round(py) });
            }
        }
        return { w: Math.round(vp.width), h: Math.round(vp.height), it: it.slice(0, MAKS_TEPI) };
    }

    /**
     * Nama font setiap teks (mis. "ABCDEF+Arial-BoldItalicMT"): baru tersedia setelah operator list halaman dimuat.
     * @returns {Promise<Array<{t, f, x, y}>>}
     */
    async function fontHalaman(items, page) {
        await page.getOperatorList();
        const vp = page.getViewport({ scale: 1 });
        const nama = id => { try { return page.commonObjs.get(id)?.name || ''; } catch (e) { return ''; } };
        return items.filter(x => 'str' in x && x.str.trim()).slice(0, 400).map(x => {
            const [px, py] = vp.convertToViewportPoint(x.transform[4], x.transform[5]);
            return { t: x.str.trim().slice(0, 120), f: nama(x.fontName).slice(0, 80), x: Math.round(px), y: Math.round(py) };
        });
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

    /**
     * Halaman berisi gambar, atau teks yang sudah diubah jadi kurva vektor (PDF "secured" dari portal BPS:
     * hurufnya digambar sebagai path, tidak ada lapisan teks) → perlu OCR untuk dibaca.
     */
    const MIN_PATHS = 30; // banyak path di halaman tanpa teks = teks berbentuk kurva (garis tabel saja jauh lebih sedikit)
    /** @returns {Promise<{gambar: boolean, perlu: boolean}>} gambar: ada gambar/foto (mis. pembatas bab) */
    async function infoHalaman(page, lib) {
        const ops = await page.getOperatorList();
        const imgOps = [lib.OPS.paintImageXObject, lib.OPS.paintInlineImageXObject, lib.OPS.paintImageMaskXObject];
        if (ops.fnArray.some(fn => imgOps.includes(fn))) return { gambar: true, perlu: true };
        let paths = 0;
        for (const fn of ops.fnArray) if (fn === lib.OPS.constructPath && ++paths >= MIN_PATHS) return { gambar: false, perlu: true };
        return { gambar: false, perlu: false };
    }
    const perluOcr = async (page, lib) => (await infoHalaman(page, lib)).perlu;

    // ── Halaman penting untuk OCR PDF secured (supaya tidak perlu meng-OCR semua halaman) ──
    const BACK_PAGES = 4; // halaman belakang (daftar pustaka/lampiran sering di sini, + kover belakang)
    const MAKS_PEMBATAS = 0.25; // halaman isi bergambar > 25% → bukan pembatas bab (mis. logo/latar di tiap halaman)

    /**
     * Pembatas bab = halaman isi (di luar halaman depan & belakang) tanpa teks yang berisi gambar. Bila hampir
     * semua halaman isi bergambar (PDF secured sering menaruh logo/latar di tiap halaman), itu bukan pembatas:
     * tanpa penyaringan ini "Halaman penting" berisi hampir semua halaman (sama lamanya dengan "Semua halaman").
     */
    function pembatasBab(n, halGambar) {
        const isi = [...halGambar].filter(i => i >= FRONT_PAGES && i < n - BACK_PAGES);
        const jumlahIsi = Math.max(1, n - FRONT_PAGES - BACK_PAGES);
        return isi.length > Math.max(3, jumlahIsi * MAKS_PEMBATAS) ? new Set() : new Set(isi);
    }

    /**
     * Halaman yang di-OCR pada pilihan "Halaman penting": halaman depan (kover, tim penyusun, katalog, kata
     * pengantar, daftar isi), halaman belakang, pembatas bab (halaman isi berisi gambar) + 2 halaman sesudahnya
     * (awal bab; halaman sesudah pembatas sering kosong). Hanya yang memang perlu OCR (`perlu`).
     */
    function halamanPenting(n, perlu, halGambar) {
        const pilih = new Set();
        for (let i = 0; i < Math.min(FRONT_PAGES, n); i++) pilih.add(i);
        for (let i = Math.max(0, n - BACK_PAGES); i < n; i++) pilih.add(i);
        // pembatas bab + halaman sesudahnya (awal bab); bila halaman sesudahnya kosong (tidak perlu OCR), satu lagi
        halGambar.forEach(i => {
            if (i < FRONT_PAGES) return;
            pilih.add(i);
            if (i + 1 < n) pilih.add(i + 1);
            if (i + 2 < n && !perlu.has(i + 1)) pilih.add(i + 2);
        });
        return [...pilih].filter(i => perlu.has(i)).sort((a, b) => a - b);
    }

    /**
     * Halaman PDF (0-based) awal bab & daftar pustaka menurut daftar isi hasil OCR halaman depan. Nomor halaman
     * cetak ≠ nomor halaman PDF (halaman romawi di depan), jadi selisihnya ditaksir dari pembatas bab yang
     * terdeteksi: selisih yang paling banyak membuat nomor "BAB …" jatuh di pembatas bab (atau sesudahnya).
     */
    function halamanDariDaftarIsi(teksDepan, n, halGambar) {
        const bab = [], pustaka = [];
        teksDepan.forEach((t, i) => {
            if (!/DAFTAR\s+ISI|CONTENTS/i.test(t) && !/DAFTAR\s+ISI|CONTENTS/i.test(teksDepan[i - 1] || '')) return;
            t.split('\n').forEach(baris => {
                const no = baris.match(/(\d{1,4})\s*$/)?.[1];
                if (!no) return;
                if (/^\s*(BAB|CHAPTER)\s+[IVX\d]+/i.test(baris)) bab.push(+no);
                else if (/DAFTAR\s+PUSTAKA|REFERENSI|REFERENCES|BIBLIOGRA/i.test(baris)) pustaka.push(+no);
            });
        });
        const pembatas = [...halGambar].filter(i => i >= FRONT_PAGES);
        if (!bab.length || !pembatas.length) return [];
        const suara = new Map();
        bab.forEach(p => pembatas.forEach(g => [g, g + 1].forEach(t => {
            const s = t - (p - 1);
            if (s >= 0) suara.set(s, (suara.get(s) || 0) + 1);
        })));
        const [selisih, jumlah] = [...suara].sort((a, b) => b[1] - a[1])[0] || [];
        if (selisih === undefined || jumlah < 2) return []; // tidak meyakinkan
        return [...bab, ...pustaka]
            .flatMap(p => [p - 1 + selisih, p + selisih])
            .filter(i => i >= 0 && i < n);
    }

    // ── Perataan angka dalam tabel (kriteria rata_angka) ──
    // Tabel BPS punya baris nomor kolom "(1) (2) (3) …" yang diletakkan di tengah tiap kolom: pusat label itu
    // dipakai sebagai pusat kolom. Untuk tiap kolom angka (kolom 2 dst., kolom 1 = stub) diukur apakah tepi kanan
    // angka-angkanya sejajar (rata kanan) dan apakah blok angka (angka terlebar s.d. tepi kanan) berada di tengah.
    const LABEL_KOLOM = /^\((\d{1,2})\)$/;
    const ANGKA_SEL   = /^[-−–]?\d+(?:[.,]\d+)*\*{0,2}$/; // "1.234,56", "-0,5", "12*" (bukan "2025," dari kalimat)
    const AKHIR_TABEL = /^(Sumber|Source|Catatan|Note|Keterangan)\b/i;
    const TOL_RATA    = 1.5;  // pt: selisih tepi kanan yang masih dianggap sejajar
    const MAKS_MELENCENG = 0.1; // ≤ 10% sel boleh tidak sejajar (mis. sel gabungan, angka bercatatan)
    const MAKS_TABEL_ANGKA = 3000;

    const median = a => { const s = [...a].sort((x, y) => x - y); return s[Math.floor(s.length / 2)]; };

    /** Kotak teks item (koordinat PDF, tanpa spasi di awal/akhir); null bila teks diputar. */
    function kotakItem(it) {
        const [a, b, c] = it.transform;
        if (Math.abs(b) > 0.01 || Math.abs(c) > 0.01 || a <= 0 || !it.str) return null;
        const s = it.str.trim();
        if (!s) return null;
        const per = (it.width || 0) / it.str.length; // lebar rata-rata per karakter, untuk membuang spasi
        const depan = it.str.length - it.str.trimStart().length;
        const belakang = it.str.length - it.str.trimEnd().length;
        const x0 = it.transform[4] + depan * per;
        const x1 = it.transform[4] + (it.width || 0) - belakang * per;
        return { s, x0, x1, y: it.transform[5], h: Math.abs(it.height || a) };
    }

    /**
     * @param {Array} items  textContent.items satu halaman
     * @param {number} hal   nomor halaman (1-based)
     * @returns {Array<{hal, kol, n, rata: 'kanan'|'kiri'|'tengah'|'tidak', tengah: boolean, geser: number, contoh: string}>}
     */
    function ukurTabel(items, hal) {
        const kotak = items.map(kotakItem).filter(Boolean);
        // baris nomor kolom: ≥ 3 label "(k)" pada satu garis, berurutan mulai (1)
        const perBaris = new Map();
        kotak.forEach(k => {
            const m = k.s.match(LABEL_KOLOM);
            if (!m) return;
            const key = Math.round(k.y / 2);
            if (!perBaris.has(key)) perBaris.set(key, []);
            perBaris.get(key).push({ ...k, no: +m[1], c: (k.x0 + k.x1) / 2 });
        });
        const header = [...perBaris.values()]
            .map(l => l.sort((p, q) => p.c - q.c))
            .filter(l => l.length >= 3 && l[0].no === 1 && l.every((x, i) => x.no === i + 1))
            .sort((p, q) => q[0].y - p[0].y); // dari atas ke bawah halaman

        const hasil = [];
        header.forEach((label, t) => {
            const yAtas = label[0].y - 2;
            // batas bawah: header tabel berikutnya di halaman ini, baris Sumber/Catatan, atau baris lebar
            // (paragraf / judul tabel berikutnya) — supaya angka di kalimat sesudah tabel tidak ikut diukur
            const lebarTabel = label[label.length - 1].x1 - label[0].x0;
            const yHeaderBerikut = header[t + 1]?.[0].y ?? -Infinity;
            const yAkhir = Math.max(yHeaderBerikut, ...kotak
                .filter(k => k.y < yAtas && (AKHIR_TABEL.test(k.s) || k.x1 - k.x0 > lebarTabel * 0.6))
                .map(k => k.y), -Infinity);
            // isi tabel memakai ukuran huruf yang sama dengan nomor kolom (teks paragraf & superscript tidak)
            const hLabel = median(label.map(l => l.h));
            const sel = kotak.filter(k => k.y < yAtas && k.y > yAkhir + 1 && ANGKA_SEL.test(k.s)
                && Math.abs(k.h - hLabel) <= Math.max(1, hLabel * 0.15));

            for (let i = 1; i < label.length; i++) {
                const kiri  = (label[i - 1].c + label[i].c) / 2;
                const kanan = label[i + 1] ? (label[i].c + label[i + 1].c) / 2 : label[i].c + (label[i].c - kiri);
                const isi = sel.filter(k => { const c = (k.x0 + k.x1) / 2; return c > kiri && c < kanan; });
                if (isi.length < 3) continue;

                const medKanan = median(isi.map(k => k.x1));
                const sejajar = (arr, m) => arr.filter(v => Math.abs(v - m) > TOL_RATA).length <= isi.length * MAKS_MELENCENG;
                let rata = 'kanan';
                if (!sejajar(isi.map(k => k.x1), medKanan)) {
                    const lefts = isi.map(k => k.x0), centers = isi.map(k => (k.x0 + k.x1) / 2);
                    rata = sejajar(lefts, median(lefts)) ? 'kiri' : sejajar(centers, median(centers)) ? 'tengah' : 'tidak';
                }
                // blok angka: dari tepi kiri angka terlebar s.d. tepi kanan (sel yang sejajar saja)
                const inti = isi.filter(k => Math.abs(k.x1 - medKanan) <= TOL_RATA);
                const blokKiri = Math.min(...(inti.length ? inti : isi).map(k => k.x0));
                const geser = (blokKiri + medKanan) / 2 - label[i].c; // + = ke kanan
                const lebar = kanan - kiri;
                const melenceng = rata !== 'kanan'
                    ? isi.find(k => Math.abs(k.x1 - medKanan) > TOL_RATA) : null;
                hasil.push({
                    hal, kol: label[i].no, n: isi.length, rata,
                    tengah: Math.abs(geser) <= Math.max(3, lebar * 0.12),
                    geser: Math.round(geser * 10) / 10,
                    contoh: (melenceng || isi[0]).s,
                });
            }
        });
        return hasil;
    }

    /** Item teks yang isinya hanya satu URL (mis. watermark "https://xxx.bps.go.id" di tiap halaman PDF portal). */
    const URL_SAJA = /^\s*https?:\/\/\S+\s*$/i;

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

    // ── PDF secured: teks diubah jadi gambar/kurva (biasanya PDF portal BPS yang sudah di-secure) ──
    // Banyak halaman harus di-OCR (lama): petugas memilih lanjut OCR, hanya kover, atau batal.
    const SECURED_MIN   = 5;  // ≥ 5 halaman isi tanpa lapisan teks → dianggap PDF secured
    // perkiraan waktu OCR per halaman per mesin di browser (dibagi JUMLAH_MESIN). Halaman secured (teks berupa
    // ribuan kurva) lambat dirender & dibaca; kecepatan nyata laptop ini dicatat sesudah OCR (KECEPATAN_FLAG)
    const DETIK_PER_HAL = 15;
    const KECEPATAN_FLAG = 'pubchecker.ocrDetikPerHal';
    let pilihanSecured = null;           // 'penting' | 'semua' | 'tanpa' | 'batal' bila dicentang "pakai untuk publikasi lain"
    let antreSecured   = Promise.resolve(); // beberapa publikasi paralel: dialog tampil satu per satu

    /** Detik per halaman OCR (waktu nyata, sudah termasuk mesin paralel): hasil ukur sebelumnya, atau perkiraan. */
    function detikPerHal() {
        try {
            const v = parseFloat(localStorage.getItem(KECEPATAN_FLAG));
            if (v > 0 && v < 600) return v;
        } catch { /* abaikan */ }
        return DETIK_PER_HAL / JUMLAH_MESIN;
    }
    function catatKecepatan(detik, halaman) {
        if (halaman < 5) return; // terlalu sedikit untuk diukur (mesin OCR baru dimuat)
        try { localStorage.setItem(KECEPATAN_FLAG, String(Math.round(detik / halaman * 10) / 10)); } catch { /* abaikan */ }
    }
    const menit = jml => Math.max(1, Math.round(jml * detikPerHal() / 60));
    const sisaWaktu = detik => detik < 60 ? '< 1 menit' : `± ${Math.round(detik / 60)} menit`;

    /** @returns {Promise<'penting'|'semua'|'tanpa'|'batal'>} */
    function tanyaSecured(info, selaluTanya = false) {
        const run = antreSecured.then(() => (!selaluTanya && pilihanSecured) || dialogSecured(info));
        antreSecured = run.catch(() => {});
        return run;
    }

    /** Lupakan pilihan PDF secured yang diingat (hanya bila sama dengan `pilihan`, atau semua bila kosong). */
    function lupakanPilihanSecured(pilihan = null) {
        if (!pilihan || pilihanSecured === pilihan) pilihanSecured = null;
    }

    function dialogSecured({ nama, total, tanpaTeks, semua, penting }) {
        return new Promise(resolve => {
            const mnt = menit;
            const wrap = document.createElement('div');
            wrap.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:2000;display:flex;align-items:center;justify-content:center;padding:16px;';
            wrap.innerHTML = `
                <div style="background:var(--tblr-bg-surface,#fff);border-radius:10px;max-width:520px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,.25);overflow:hidden;">
                    <div class="bg-warning" style="height:4px;"></div>
                    <div style="padding:18px 20px 8px;">
                        <h5 style="margin:0 0 8px;font-weight:600;">
                            <i class="ti ti-lock text-warning me-1"></i> PDF secured, pemeriksaan akan lama
                        </h5>
                        ${nama ? `<div class="small fw-semibold mb-2 text-truncate" title="${esc(nama)}">${esc(nama)}</div>` : ''}
                        <p class="small mb-2">
                            <strong>${tanpaTeks} dari ${total} halaman</strong> tidak punya lapisan teks: tulisannya sudah
                            diubah jadi gambar/kurva (PDF yang di-<em>secure</em> sebelum diunggah ke portal).
                            Halaman seperti ini hanya bisa dibaca dengan OCR.
                        </p>
                        <ul class="small mb-2 ps-3">
                            <li><strong>Halaman penting</strong> (± ${penting} hal., <strong>± ${mnt(penting)} menit</strong>): kover, halaman depan
                                (tim penyusun, katalog, kata pengantar, daftar isi), pembatas &amp; awal tiap bab, daftar pustaka,
                                dan halaman belakang. Halaman isi lainnya (tabel) tidak dibaca, cek manual bila perlu.</li>
                            <li><strong>Semua halaman</strong> (${semua} hal., <strong>± ${mnt(semua)} menit</strong>): paling lengkap, paling lama.</li>
                            <li><strong>Tanpa OCR</strong> (paling cepat): hanya teks yang ada yang diperiksa; kriteria di halaman
                                tanpa teks (termasuk kover) ditandai untuk dicek manual.</li>
                            <li>Hasil OCR bisa salah baca, kriterianya diberi tanda untuk dicek ulang. OCR bisa
                                <strong>dihentikan</strong> kapan saja lewat panel di pojok kanan bawah.</li>
                        </ul>
                        <div class="alert alert-info py-2 px-3 small mb-2">
                            <i class="ti ti-bulb me-1"></i>Lebih cepat &amp; akurat: periksa <strong>PDF asli</strong> (sebelum di-secure) lewat <strong>Upload Manual</strong>.
                        </div>
                        <label class="form-check small mb-0">
                            <input type="checkbox" class="form-check-input" data-ingat>
                            <span class="form-check-label">Pakai pilihan ini untuk PDF secured lain selama halaman ini terbuka</span>
                        </label>
                    </div>
                    <div style="padding:12px 20px 18px;display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" class="btn btn-light border btn-sm" data-act="batal">Batalkan</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" data-act="tanpa">
                            <i class="ti ti-bolt me-1"></i> Tanpa OCR
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-act="semua">
                            Semua halaman (± ${mnt(semua)} mnt)
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" data-act="penting">
                            <i class="ti ti-photo-scan me-1"></i> Halaman penting (± ${mnt(penting)} mnt)
                        </button>
                    </div>
                </div>`;
            wrap.addEventListener('click', e => {
                const act = e.target.closest('[data-act]')?.dataset.act;
                if (!act) return;
                if (wrap.querySelector('[data-ingat]')?.checked) pilihanSecured = act;
                wrap.remove();
                resolve(act);
            });
            document.body.appendChild(wrap);
            wrap.querySelector('[data-act="penting"]')?.focus();
        });
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
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
                            Halaman <strong>${list}</strong> perlu dibaca dengan OCR karena judul kover sering berupa
                            gambar JPG / outline, dan halaman hasil scan tidak memiliki teks.
                        </p>
                        <div class="alert alert-warning py-2 px-3 small mb-2">
                            <i class="ti ti-download me-1"></i>
                            Browser Anda akan <strong>mengunduh mesin OCR ± ${OCR_SIZE_MB} MB</strong>
                            (Tesseract, bahasa Indonesia &amp; Inggris) dari internet.
                            Ini hanya terjadi <strong>sekali</strong>, setelahnya tersimpan di cache browser.
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

    // ── Menghentikan OCR yang sedang berjalan ──
    /** Penghenti: alasan null (jalan terus) | 'lewati' (periksa dengan halaman yang sudah terbaca) | 'batal'. */
    function buatPenghenti() {
        let lepas;
        const h = { alasan: null, sinyal: new Promise(r => { lepas = r; }) };
        h.stop = alasan => { if (!h.alasan) { h.alasan = alasan; lepas(alasan); } };
        return h;
    }

    /**
     * Panel kecil di pojok kanan bawah selama OCR berjalan: progres, sisa waktu, dan tombol untuk menghentikan.
     * Dibuat di sini (bukan di tiap halaman pemanggil) supaya berlaku di Periksa Terpilih, detail BPS & scan ulang.
     */
    function panelOcr(nama, henti) {
        const el = document.createElement('div');
        el.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:2100;width:340px;max-width:calc(100vw - 32px);' +
            'background:var(--tblr-bg-surface,#fff);border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.25);overflow:hidden;';
        el.innerHTML = `
            <div class="bg-primary" data-bar style="height:4px;width:0;transition:width .4s;"></div>
            <div style="padding:12px 14px;">
                <div class="small fw-semibold mb-1"><i class="ti ti-photo-scan text-primary me-1"></i> OCR berjalan</div>
                ${nama ? `<div class="small text-secondary text-truncate mb-1" title="${esc(nama)}">${esc(nama)}</div>` : ''}
                <div class="small" data-msg>Menyiapkan OCR…</div>
                <div class="small text-secondary" data-eta></div>
                <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-sm btn-light border" data-act="batal"
                        title="Hentikan dan jangan periksa publikasi ini">Batalkan</button>
                    <button type="button" class="btn btn-sm btn-warning ms-auto" data-act="lewati"
                        title="Hentikan OCR, periksa dengan halaman yang sudah terbaca; sisanya ditandai cek manual">
                        <i class="ti ti-player-stop me-1"></i> Hentikan OCR &amp; periksa
                    </button>
                </div>
            </div>`;
        el.addEventListener('click', e => {
            const act = e.target.closest('[data-act]')?.dataset.act;
            if (!act) return;
            henti.stop(act);
            el.querySelectorAll('button').forEach(b => { b.disabled = true; });
            el.querySelector('[data-msg]').textContent = act === 'batal' ? 'Membatalkan…' : 'Menghentikan OCR…';
        });
        document.body.appendChild(el);
        const mulai = Date.now();
        return {
            update(msg, frac) {
                if (henti.alasan) return;
                el.querySelector('[data-msg]').textContent = msg;
                el.querySelector('[data-bar]').style.width = `${Math.round((frac || 0) * 100)}%`;
                const lewat = (Date.now() - mulai) / 1000;
                el.querySelector('[data-eta]').textContent = frac > 0.03 && lewat > 20
                    ? `Sisa waktu ${sisaWaktu(lewat * (1 - frac) / frac)}` : '';
            },
            tutup: () => el.remove(),
        };
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
    /**
     * OCR halaman `pageIdx` dengan beberapa mesin Tesseract paralel (JUMLAH_MESIN, sesuai memori & CPU laptop).
     * @param {Set<number>} coverIdx indeks halaman kover (depan & belakang) — dibaca lebih teliti
     */
    async function runOcr(pdf, pageIdx, coverIdx, onProgress, henti = buatPenghenti()) {
        await loadScript(TESSERACT_URL);
        if (henti.alasan) return { texts: {}, coverWords: null, ocrLines: {} };

        const k = Math.max(1, Math.min(JUMLAH_MESIN, pageIdx.length));
        const prog = new Array(k).fill(0); // progres halaman yang sedang dibaca tiap mesin (0–1)
        let selesai = 0;
        // progres keseluruhan (semua halaman OCR), bukan per halaman: Tesseract melapor 0–100% untuk
        // setiap halaman, yang tampak seperti membaca ulang terus bila ditampilkan apa adanya
        const lapor = label => {
            const frac = Math.min(1, (selesai + prog.reduce((x, y) => x + y, 0)) / pageIdx.length);
            onProgress(`${label} (${selesai}/${pageIdx.length} halaman${k > 1 ? `, ${k} mesin` : ''})… ${Math.round(frac * 100)}%`, frac, 'ocr');
        };
        const workers = await Promise.all(Array.from({ length: k }, (_, w) => window.Tesseract.createWorker(OCR_LANG, 1, {
            logger: m => {
                const label = OCR_STATUS[m.status];
                if (!label) return;
                prog[w] = m.status === 'recognizing text' ? (m.progress || 0) : 0;
                lapor(label);
            },
        })));
        markOcrDownloaded();

        const texts    = {};
        const ocrLines = {};
        let coverWords = null;

        const ocrSatu = async (worker, idx) => {
            const page     = await pdf.getPage(idx + 1);
            const isCover  = coverIdx.has(idx);
            // halaman isi (di luar halaman depan) cukup resolusi lebih rendah: jauh lebih cepat
            const scale    = isCover ? COVER_SCALE : idx < FRONT_PAGES ? OCR_SCALE : ISI_SCALE;
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

            canvas.width = canvas.height = 0; // lepas memori
            if (henti.alasan) return; // dihentikan saat halaman ini dibaca: hasilnya tidak dipakai
            texts[idx] = lines.map(l => l.text).join('\n');
            ocrLines[idx + 1] = lines.map(l => toPdfLine(l, viewport)).filter(Boolean);
            if (idx === 0) {
                coverWords = lines.flatMap(l => l.words).map(w => ({
                    text: w.text,
                    x: Math.round(w.bbox.x0 / scale),
                    y: Math.round(w.bbox.y1 / scale),
                }));
            }
        };

        try {
            let berikut = 0;
            await Promise.all(workers.map(async (worker, w) => {
                while (berikut < pageIdx.length && !henti.alasan) {
                    const idx = pageIdx[berikut++];
                    const job = ocrSatu(worker, idx);
                    job.catch(() => {}); // dihentikan: mesin dimatikan di tengah halaman, galatnya diabaikan
                    // tombol Hentikan: tidak menunggu halaman yang sedang dibaca selesai
                    if (await Promise.race([job.then(() => false), henti.sinyal.then(() => true)])) return;
                    prog[w] = 0;
                    selesai++;
                    lapor(OCR_STATUS['recognizing text']);
                }
            }));
        } finally {
            await Promise.all(workers.map(x => x.terminate()));
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
    function sameOrigin(src) {
        const u = new URL(src, location.href);
        if (u.protocol === 'blob:' || u.protocol === 'data:') return src;
        return location.origin + u.pathname + u.search;
    }

    /**
     * @param {{onProgress?: Function, nama?: string, selaluTanya?: boolean}} opsi nama: judul publikasi (untuk dialog
     *        PDF secured); selaluTanya: abaikan pilihan PDF secured yang diingat (mis. scan ulang satu publikasi)
     * @throws Error dengan `dibatalkan: true` bila petugas membatalkan pemeriksaan PDF secured
     */
    async function extract(source, { onProgress = () => {}, nama = '', selaluTanya = false } = {}) {
        const lib  = await loadPdfjs();
        // URL milik aplikasi disamakan dengan origin halaman (http di halaman https diblokir browser)
        const url = typeof source === 'string' ? sameOrigin(source) : null;
        const task = lib.getDocument(url
            ? { url, disableRange: true, disableStream: true }
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
        // watermark URL: item yang isinya hanya URL, dicatat per halaman; yang muncul di sebagian besar halaman
        // dibuang dari teks (lihat bawah) dan tidak dihitung sebagai "teks" saat menentukan halaman yang perlu OCR
        const urlHalaman = new Map(); // url → jumlah halaman
        const itemsBerUrl = {};       // index halaman → items (hanya halaman yang punya item URL saja)
        const halGambar = new Set();  // halaman tanpa teks yang berisi gambar (kover, pembatas bab, foto)
        const tabelAngka = [];        // perataan kolom angka tiap tabel (ukurTabel), untuk kriteria rata_angka
        const tepi = [];              // per halaman: teks di pita atas/bawah (nomor halaman & running title) — tepiHalaman
        const fonts = {};             // nomor halaman → [{t, f, x, y}]: nama font halaman awal & terakhir (nama BPS penerbit)

        for (let i = 1; i <= n; i++) {
            const page    = await pdf.getPage(i);
            const content = await page.getTextContent();
            const text    = itemsToText(content.items);
            pages.push(text);
            const urls = new Set(content.items.filter(it => URL_SAJA.test(it.str || '')).map(it => it.str.trim()));
            urls.forEach(u => urlHalaman.set(u, (urlHalaman.get(u) || 0) + 1));
            if (urls.size) itemsBerUrl[i - 1] = content.items;
            const tanpaUrl = urls.size ? itemsToText(content.items.filter(it => !URL_SAJA.test(it.str || ''))) : text;

            if (i === 1) {
                const vp = page.getViewport({ scale: 1 });
                pageW = Math.round(vp.width);
                pageH = Math.round(vp.height);
                coverWords = coverWordsFrom(content.items, vp);
                coverHasImage = await perluOcr(page, lib);
            } else if (i <= FRONT_PAGES && /ISSN/i.test(text)) {
                const vp = page.getViewport({ scale: 1 });
                coverWordsFrom(content.items, vp)
                    .filter(w => /ISSN/i.test(w.text))
                    .forEach(w => frontWords.push({ hal: i, ...w, pw: Math.round(vp.width), ph: Math.round(vp.height) }));
            }
            try { tepi.push(tepiHalaman(content.items, page)); } catch (e) { tepi.push(null); }
            if (i <= HAL_FONT_AWAL || i === n) {
                try { fonts[i] = await fontHalaman(content.items, page); } catch (e) { console.warn('Baca font gagal', i, e); }
            }
            const textPoor = tanpaUrl.replace(/\s+/g, '').length < MIN_TEXT;
            if (!textPoor && tabelAngka.length < MAKS_TABEL_ANGKA && /\(\s*2\s*\)/.test(text)) {
                try { tabelAngka.push(...ukurTabel(content.items, i)); } catch (e) { console.warn('Ukur tabel gagal', i, e); }
            }
            if (textPoor) {
                const info = i === 1 ? { perlu: coverHasImage, gambar: coverHasImage } : await infoHalaman(page, lib);
                if (info.perlu) imageCandidates.push(i - 1);
                if (info.gambar) halGambar.add(i - 1);
            }
            page.cleanup();
            onProgress(`Membaca teks halaman ${i}/${n}`, i / n, 'text');
        }

        // Watermark (URL yang sama di ≥ 60% halaman): dibuang dari teks halaman supaya tidak dianggap isi
        // publikasi (mis. lolos cek alamat website padahal hanya watermark portal)
        const watermark = new Set([...urlHalaman].filter(([, k]) => n >= 3 && k >= n * 0.6).map(([u]) => u));
        if (watermark.size) {
            Object.entries(itemsBerUrl).forEach(([idx, items]) => {
                pages[idx] = itemsToText(items.filter(it => !watermark.has((it.str || '').trim())));
            });
        }

        // Kover depan & kover belakang (halaman terakhir) selalu di-OCR walau sudah ada teks: judul,
        // slogan, alamat, dll. sering berupa gambar JPG atau teks yang di-outline (vektor, tidak terdeteksi
        // sebagai gambar). Hasil OCR digabung dengan teks yang sudah terbaca.
        // Semua halaman tanpa lapisan teks di-OCR (tanpa batas jumlah halaman); untuk PDF secured petugas
        // ditanya dulu karena bisa memakan waktu beberapa menit (tanyaSecured).
        const coverIdx   = new Set([0, n - 1]);
        const supplement = new Set([...coverIdx].filter(i => !imageCandidates.includes(i)));
        const lainnya = imageCandidates.filter(i => !coverIdx.has(i)).sort((a, b) => a - b);
        let ocrIdx = [...new Set([...coverIdx, ...lainnya])].sort((a, b) => a - b);
        let ocrTerlewat = 0;
        let ocrPages = [], ocrSkipped = false, ocrLinesAll = {};

        // PDF secured (banyak halaman isi tanpa teks): tanya dulu — halaman penting saja (bawaan) atau semua
        const perlu = new Set([...coverIdx, ...lainnya]);
        const pembatas = pembatasBab(n, halGambar);
        let secured = null;
        if (lainnya.length >= SECURED_MIN) {
            const penting = [...new Set([...coverIdx, ...halamanPenting(n, perlu, pembatas)])];
            secured = await tanyaSecured({ nama, total: n, tanpaTeks: lainnya.length, semua: ocrIdx.length,
                penting: Math.min(ocrIdx.length, penting.length + 4) /* + perkiraan awal bab & daftar pustaka dari daftar isi */ }, selaluTanya);
            if (secured === 'batal') {
                await pdf.destroy();
                const err = new Error('Pemeriksaan dibatalkan: PDF secured, perlu OCR yang lama. Periksa PDF aslinya lewat Upload Manual, atau coba lagi.');
                err.dibatalkan = true;
                throw err;
            }
            if (secured === 'penting') ocrIdx = penting.sort((a, b) => a - b);
            if (secured === 'tanpa') {
                // tanpa OCR: halaman tanpa teks tidak dibaca, kriterianya ditandai cek manual di server
                ocrIdx = [];
                ocrTerlewat = perlu.size;
            }
        }

        if (ocrIdx.length) {
            const ok = await confirmFirstDownload(ocrIdx.map(i => i + 1));
            if (ok) {
                onProgress('Menunggu giliran OCR…', 0, 'ocr-wait');
                const terbaca = new Set(); // halaman yang benar-benar selesai di-OCR (bisa dihentikan di tengah)
                const pakai = ({ texts, coverWords: ocrWords, ocrLines }) => {
                    Object.assign(ocrLinesAll, ocrLines);
                    Object.entries(texts).forEach(([idx, t]) => {
                        terbaca.add(Number(idx));
                        pages[idx] = supplement.has(Number(idx)) ? pages[idx] + '\n' + t : t;
                    });
                    if (ocrWords) coverWords = supplement.has(0) ? coverWords.concat(ocrWords) : ocrWords;
                };
                const henti = buatPenghenti();
                await withOcrLock(async () => {
                    const panel = panelOcr(nama, henti);
                    const mulai = Date.now();
                    const lapor = prefix => (msg, frac, tahap) => {
                        panel.update(prefix + msg, frac);
                        onProgress(prefix + msg, frac, tahap);
                    };
                    try {
                        pakai(await runOcr(pdf, ocrIdx, coverIdx, lapor(''), henti));
                        if (secured === 'penting' && !henti.alasan) {
                            // tahap 2: awal bab & daftar pustaka menurut daftar isi (yang baru terbaca lewat OCR)
                            const tambah = [...new Set(halamanDariDaftarIsi(pages.slice(0, FRONT_PAGES), n, pembatas))]
                                .filter(i => perlu.has(i) && !ocrIdx.includes(i)).sort((a, b) => a - b);
                            if (tambah.length) pakai(await runOcr(pdf, tambah, coverIdx, lapor('Awal bab & daftar pustaka: '), henti));
                        }
                        if (!henti.alasan) catatKecepatan((Date.now() - mulai) / 1000, terbaca.size);
                    } finally {
                        panel.tutup();
                    }
                });
                if (henti.alasan === 'batal') {
                    await pdf.destroy();
                    const err = new Error('Pemeriksaan dibatalkan saat OCR. Periksa PDF aslinya lewat Upload Manual, atau coba lagi (bisa pilih "Tanpa OCR").');
                    err.dibatalkan = true;
                    throw err;
                }
                ocrPages = [...terbaca].sort((a, b) => a - b).map(i => i + 1);
                ocrTerlewat = [...perlu].filter(i => !terbaca.has(i)).length;
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
            // halaman tanpa lapisan teks yang tidak di-OCR (petugas memilih "Halaman penting" untuk PDF secured)
            ocr_terlewat: ocrTerlewat,
            secured: secured, // null = bukan PDF secured; 'penting' / 'semua' = pilihan petugas
            watermark: [...watermark],
            tabel_angka: tabelAngka.slice(0, MAKS_TABEL_ANGKA), // [{hal, kol, n, rata, tengah, geser, contoh}]
            tepi,  // [{w, h, it: [{t, x, y}]}] per halaman — nomor halaman & running title (kriteria nomor_halaman, running_title, daftar_halaman)
            fonts, // { halaman: [{t, f, x, y}] } — nama font halaman 1–5 & terakhir (kriteria font_bps)
            ocr_lines: ocrLinesAll, // { nomorHalaman: [{s, x, y, w, h}] } — untuk pencarian di viewer
        };
    }

    /**
     * Hasil ekstraksi → base64 (JSON UTF-8, dikompres gzip bila browser mendukung) untuk dikirim ke server sebagai
     * `extracted_b64` (server mengenali gzip dari 2 byte awalnya, lihat PdfCheckerService::extractedDari).
     * - Teks publikasi apa adanya bisa memuat pola yang salah dibaca firewall hosting (WAF) sebagai serangan,
     *   sehingga permintaan ditolak 403 sebelum sampai ke aplikasi; base64 menghindari salah baca itu.
     * - gzip: publikasi tebal (mis. "Dalam Angka" ±900 halaman tabel) bisa ±10 MB teks dan melewati batas upload
     *   server (post_max_size → 413); dikompres jadi ±5–10× lebih kecil.
     * @returns {Promise<?string>}
     */
    async function kemas(extracted) {
        if (!extracted) return null;
        let bytes = new TextEncoder().encode(JSON.stringify(extracted));
        if (typeof CompressionStream === 'function') {
            try {
                const aliran = new Blob([bytes]).stream().pipeThrough(new CompressionStream('gzip'));
                bytes = new Uint8Array(await new Response(aliran).arrayBuffer());
            } catch (e) { console.warn('Kompresi gzip gagal, dikirim tanpa kompresi', e); }
        }
        let biner = '';
        for (let i = 0; i < bytes.length; i += 0x8000) biner += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        return btoa(biner);
    }

    /**
     * Balasan server → JSON. Bila server membalas halaman HTML (error 413/500/504, firewall), lempar pesan yang jelas,
     * bukan "Unexpected token '<'".
     */
    async function bacaJson(res) {
        const teks = await res.text();
        try { return JSON.parse(teks); } catch (e) { /* bukan JSON */ }
        const pesan = {
            403: 'Ditolak server (403) sebelum sampai ke aplikasi, kemungkinan firewall hosting. Coba lagi; bila tetap gagal, hubungi pengelola server.',
            413: 'Data hasil baca PDF terlalu besar untuk batas upload server (413). Minta pengelola server menaikkan post_max_size / upload_max_filesize PHP.',
            419: 'Sesi login kedaluwarsa (419). Muat ulang halaman lalu coba lagi.',
            500: 'Server error (500) saat memeriksa, kemungkinan kehabisan waktu/memori untuk PDF setebal ini. Lihat storage/logs/laravel.log di server.',
            502: 'Server tidak merespons (502). Coba lagi beberapa saat lagi.',
            503: 'Server sedang sibuk / maintenance (503). Coba lagi beberapa saat lagi.',
            504: 'Server kehabisan waktu (504) untuk PDF setebal ini. Minta pengelola server menaikkan batas waktu (max_execution_time / timeout proxy).',
        }[res.status];
        throw new Error(pesan || `Server membalas ${res.status} (bukan JSON)`);
    }

    return { init, extract, kemas, bacaJson, lupakanPilihanSecured };
})();
