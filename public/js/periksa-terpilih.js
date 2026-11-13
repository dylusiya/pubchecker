/**
 * PeriksaTerpilih — daftar kartu publikasi yang bisa dicentang lalu diperiksa sekaligus, di-approve,
 * atau (admin) ditugaskan. Dipakai bersama Publikasi Bermasalah & Tugas Saya, dengan markup
 * partials/pub-card, pub-pilih-bar & pub-batch. Butuh CheckerReview, PdfExtract, BatchCheck & Penugasan.
 *
 * init({
 *   items:      [{ pub_id, title, history_id, tahap, file_url, tugas, domain }]  (urutan = data-index kartu)
 *   isian:      { pub_id: isian SIPOTRET } — isian awal tinjauan; periodenya disimpan sebagai acuan di hasil
 *   periode:    () => ({ bulan, tahun }) | null — periode pemeriksaan (hasil dikirim ke SIPOTRET pada periode ini)
 *   bisaApprove, adminTugas, maxPubs,
 *   csrf, url: { detail, run, draf, sesi, pdfProxy, riwayat ('__ID__'), penugasan, salin },
 *   ulangSetelahTugaskan: true = muat ulang halaman setelah penugasan berubah (daftar ikut berubah)
 * })
 */
const PeriksaTerpilih = (() => {
    let cfg = null, items = [];
    const selected = new Map(); // kunci publikasi → item
    let batchOutcomes = [], allResults = [], sesiId = null, detailSelesai = false;
    // tinjau sambil diproses: hasil draf & hasil lengkap per kartu, entry tinjauan per hasil_id (objek yang sama
    // dipakai ulang supaya pilihan petugas yang belum disimpan tidak hilang saat tinjauan dibuka ulang)
    let hasilDraf = [], hasilAkhir = [], entriPer = new Map();
    // unduhan PDF per kartu oleh proses pemeriksaan: { selesai, persen, url } — viewer tinjauan menunggunya
    let unduhPdf = [];
    let berjalan = false, tinjauanTerbuka = false, selesaiMenunggu = false;

    const BULAN_PENDEK = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    // publikasi yang sama di beberapa bulan = satu pilihan; tanpa pub_id dibedakan per periode
    const kunciPub = p => p.pub_id ? String(p.pub_id) : 'h' + p.history_id;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const proxied = pdf => cfg.url.pdfProxy + '?url=' + encodeURIComponent(pdf);
    const $ = id => document.getElementById(id);

    /** "2026-03-10" / "2026-03-10 14:05:00" → "10 Mar 2026"; format lain ditampilkan apa adanya. */
    function tanggal(s) {
        const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${+m[3]} ${BULAN_PENDEK[+m[2]]} ${m[1]}` : s;
    }

    function showAlert(type, msg) {
        const div = document.createElement('div');
        div.className = `alert alert-${type} alert-dismissible fade show`;
        div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.querySelector('.content-wrapper').prepend(div);
        setTimeout(() => div.remove(), 6000);
    }

    // ── DETAIL DARI API BPS (kover, PDF, ISSN, tanggal revisi) ────
    async function loadDetail(pubId, domain) {
        const res = await fetch(cfg.url.detail, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
            body: JSON.stringify({ pub_id: pubId, domain: domain || null }),
        });
        const data = await res.json();
        if (!res.ok || !data.publication) throw new Error(data.error || ('Server membalas ' + res.status));
        return data.publication;
    }

    function renderDetail(i, pub, err) {
        const cb = document.querySelector(`.pub-select[data-index="${i}"]`);
        const badge = $('pdfbadge-' + i);
        if (!cb || !badge) return;
        if (err) {
            if (cfg.adminTugas) cb.disabled = false; // tetap bisa ditugaskan
            cb.closest('label').title = 'Detail publikasi tidak bisa dimuat dari API BPS';
            badge.innerHTML = `<span class="badge bg-warning-lt fw-normal" title="${esc(err.message)}"><i class="ti ti-alert-triangle me-1"></i>API BPS gagal</span>`;
            return;
        }
        Object.assign(items[i], pub, { domain: items[i].domain || String(pub.domain || '') }); // pub.cover sudah lewat cover-proxy
        if (pub.cover) {
            // ikon PDF tetap tampil bila sampul gagal dimuat
            const ikon = $('cover-' + i);
            const img  = Object.assign(new Image(), { className: 'pub-cover', alt: 'cover', src: pub.cover });
            img.onload = () => ikon?.replaceWith(img);
        }
        // tanggal revisi terakhir di portal BPS (updt_date) — petunjuk apakah kab/kota sudah mengganti file.
        // Yang punya file perbaikan di SIPOTRET sudah menampilkan tanggal unggahnya sendiri.
        const fileSipotret = !!items[i].file_url;
        $('meta-' + i).innerHTML =
            (pub.issn ? `<span class="badge bg-secondary fw-normal">${esc(pub.issn)}</span>` : '') +
            (pub.rl_date ? `<small class="text-muted">Rilis ${esc(tanggal(pub.rl_date))}</small>` : '') +
            (pub.updt_date && !fileSipotret ? `<small class="fw-semibold"><i class="ti ti-refresh"></i> Revisi terakhir ${esc(tanggal(pub.updt_date))}</small>` : '') +
            (pub.size && !fileSipotret ? `<small class="text-muted">${esc(pub.size)}</small>` : '');
        // hanya yang perlu diperhatikan yang diberi tanda: publikasi tanpa PDF tidak bisa diperiksa
        badge.innerHTML = pub.has_pdf || fileSipotret ? '' : `<span class="badge bg-secondary fw-normal">Tanpa PDF</span>`;
        if (pub.has_pdf) {
            cb.disabled = false;
            cb.closest('label').title = items[i].tahap === 'menunggu' && cfg.bisaApprove
                ? 'Pilih untuk di-approve atau diperiksa ulang' : 'Pilih untuk diperiksa sekaligus';
            $('pdflink-' + i).innerHTML =
                `<a href="${esc(proxied(pub.pdf) + '&nama=' + encodeURIComponent(items[i].title || 'publikasi'))}" download class="btn btn-outline-success btn-sm" title="Download PDF">
                    <i class="ti ti-download"></i><span class="d-none d-md-inline ms-1">PDF</span></a>`;
        } else if (cb.disabled) {
            cb.disabled = !cfg.adminTugas; // admin tetap bisa mencentang untuk ditugaskan
            cb.closest('label').title = cfg.adminTugas ? 'Tidak ada PDF (tetap bisa dicentang untuk ditugaskan)' : 'Tidak ada PDF';
        }
    }

    // Satu publikasi bisa muncul di beberapa bulan — detailnya cukup diambil sekali; 4 permintaan paralel
    async function loadAllDetails() {
        const perPub = new Map();
        items.forEach((it, i) => {
            if (!it.pub_id) return;
            if (!perPub.has(it.pub_id)) perPub.set(it.pub_id, []);
            perPub.get(it.pub_id).push(i);
        });
        const antrian = [...perPub.entries()];
        const pekerja = async () => {
            for (let job; (job = antrian.shift());) {
                const [pubId, idx] = job;
                try {
                    const pub = await loadDetail(pubId, items[idx[0]].domain);
                    idx.forEach(i => renderDetail(i, pub));
                } catch (e) {
                    idx.forEach(i => renderDetail(i, null, e));
                }
            }
        };
        await Promise.all(Array.from({ length: 4 }, pekerja));
        detailSelesai = true;
        renderSelection();
        if ($('urutKartu')?.value) urutkanKartu($('urutKartu').value); // tanggal revisi sudah lengkap
    }

    // ── URUTKAN KARTU ─────────────────────────────────────────────
    const URUT_KEY = 'pubchecker.urutKartu';
    let urutanAsli = null; // kartu & judul kelompok wilayah dalam urutan bawaan (untuk dikembalikan)

    /** Tanggal revisi terakhir kartu ke-i (Y-m-d…): revisi di portal BPS atau unggah file perbaikan SIPOTRET, yang terbaru. */
    function revisiKartu(i) {
        const t = [items[i]?.updt_date, items[i]?.diubah_at].filter(Boolean).map(String).sort();
        return t.length ? t[t.length - 1] : '';
    }

    /**
     * Urutkan kartu: '' = bawaan (wilayah, judul; judul kelompok tampil), 'revisi-baru' / 'revisi-lama' = menurut
     * revisi terakhir lintas wilayah (judul kelompok disembunyikan); yang belum diketahui revisinya di akhir.
     */
    function urutkanKartu(mode) {
        const kartu = [...document.querySelectorAll('.pub-card[id^="pubcard-"]')];
        if (!kartu.length) return;
        const wadah = kartu[0].parentElement;
        urutanAsli ??= [...wadah.children].filter(n => n.matches('.pub-card, .pub-grup-wilayah'));
        try { localStorage.setItem(URUT_KEY, mode); } catch { /* penyimpanan browser tidak tersedia */ }
        const sel = $('urutKartu');
        if (sel && sel.value !== mode) sel.value = mode;
        const akhir = urutanAsli[urutanAsli.length - 1]?.nextSibling ?? null;

        if (!mode) {
            urutanAsli.forEach(n => { n.hidden = false; wadah.insertBefore(n, akhir); });
            if ($('urutInfo')) $('urutInfo').textContent = '';
            return;
        }
        const idx = k => +k.id.replace('pubcard-', '');
        const urut = kartu.slice().sort((a, b) => {
            const ra = revisiKartu(idx(a)), rb = revisiKartu(idx(b));
            if (!ra !== !rb) return ra ? -1 : 1; // tanpa tanggal di akhir
            return mode === 'revisi-baru' ? rb.localeCompare(ra) : ra.localeCompare(rb);
        });
        urutanAsli.filter(n => n.matches('.pub-grup-wilayah')).forEach(n => { n.hidden = true; });
        urut.forEach(k => wadah.insertBefore(k, akhir));
        const tanpa = kartu.filter(k => !revisiKartu(idx(k))).length;
        if ($('urutInfo')) $('urutInfo').textContent = detailSelesai
            ? (tanpa ? `${tanpa} publikasi tanpa tanggal revisi di akhir` : '')
            : 'tanggal revisi masih dimuat dari API BPS, urutan diperbarui otomatis';
    }

    // ── PILIH BEBERAPA PUBLIKASI ──────────────────────────────────
    /** Pilih semua publikasi yang bisa dicentang (ber-PDF, menunggu approve, atau admin). */
    function pilihSemua() {
        document.querySelectorAll('.pub-select:not(:disabled)').forEach(cb => {
            const p = items[cb.dataset.index];
            selected.set(kunciPub(p), p);
        });
        syncCheckboxes();
        renderSelection();
    }

    function toggleSelect(i, cb) {
        const pub = items[i];
        if (cb.checked) selected.set(kunciPub(pub), pub);
        else selected.delete(kunciPub(pub));
        syncCheckboxes();
        renderSelection();
    }

    /** Publikasi yang sama di bulan lain ikut tercentang/terlepas. */
    function syncCheckboxes() {
        document.querySelectorAll('.pub-select').forEach(cb => {
            const on = selected.has(kunciPub(items[cb.dataset.index]));
            cb.checked = on;
            cb.closest('.pub-card')?.classList.toggle('border-primary', on);
        });
    }

    function clearSelection() {
        selected.clear();
        syncCheckboxes();
        renderSelection();
    }

    /** Bisa diperiksa: tidak ditugaskan ke pemeriksa lain, dan ada file perbaikan di SIPOTRET atau PDF portal BPS. */
    const bisaDiperiksa = p => Penugasan.boleh(p.tugas) && (!!p.file_url || !!(p.has_pdf && p.pdf));

    /** history_id yang dicentang & statusnya menunggu approve (satu publikasi bisa punya beberapa periode). */
    function historyMenunggu() {
        return [...new Set(items.filter(p => p.history_id && p.tahap === 'menunggu' && selected.has(kunciPub(p))).map(p => p.history_id))];
    }

    function renderSelection() {
        const n        = selected.size;
        const nPeriksa = [...selected.values()].filter(bisaDiperiksa).length;
        const nApprove = historyMenunggu().length;
        const nKunci   = [...selected.values()].filter(p => !Penugasan.boleh(p.tugas)).length;
        $('selectionInfo').innerHTML = n
            ? `<strong>${n}</strong> publikasi dipilih: ${[...selected.values()].map(p => esc(p.title)).join(' · ')}`
              + (nKunci ? ` <span class="text-danger">· ${nKunci} ditugaskan ke pemeriksa lain, tidak ikut diperiksa</span>` : '')
            : `Centang publikasi untuk diperiksa ulang (maks. ${cfg.maxPubs} ber-PDF sekali periksa)`
              + (cfg.bisaApprove ? ' atau untuk di-approve' : '')
              + (detailSelesai ? '.' : ', data PDF masih dimuat dari API BPS…');
        $('btnCheckSel').disabled = !nPeriksa;
        $('btnCheckSelLabel').textContent = nPeriksa ? `Periksa (${nPeriksa})` : 'Periksa Terpilih';
        $('btnClearSel').hidden = !n;
        const btnApprove = $('btnApproveSel');
        if (btnApprove) {
            btnApprove.hidden = !nApprove;
            $('btnApproveSelLabel').textContent = `Approve (${nApprove})`;
        }
        // salin bulan lalu: publikasi ber-pub_id yang boleh diperiksa (server memilah belum upload/approve)
        const nSalin = pubSalin().length;
        if ($('btnSalinSel')) {
            $('btnSalinSel').hidden = !nSalin;
            $('btnSalinSelLabel').textContent = `Salin bulan lalu (${nSalin})`;
        }
        if (cfg.adminTugas && $('tugasBar')) {
            $('tugasBar').hidden = !n;
            $('btnTugasLabel').textContent = n ? `Tugaskan (${n})` : 'Tugaskan';
        }
    }

    // ── SALIN KETIDAKSESUAIAN BULAN LALU (belum upload / belum approve) ──
    const pubSalin = () => [...new Set([...selected.values()].filter(p => p.pub_id && Penugasan.boleh(p.tugas)).map(p => String(p.pub_id)))];
    let salinDikirim = false;

    /** Tanpa argumen: pratinjau di modal. simpan=true: salin ke SIPOTRET lalu tampilkan hasilnya. */
    async function salinTerpilih(simpan = false) {
        const ids = pubSalin();
        const periode = cfg.periode();
        if (!ids.length) return;
        if (!periode) {
            showAlert('warning', 'Pilih periode pemeriksaan (bulan & tahun) dulu: catatan bulan sebelumnya disalin ke periode itu.');
            $('periodeBulan')?.focus();
            return;
        }
        const modal = bootstrap.Modal.getOrCreateInstance($('modalSalinLalu'));
        const lalu = new Date(periode.tahun, periode.bulan - 2, 1);
        $('slPeriode').textContent = `· ${BULAN_PENDEK[lalu.getMonth() + 1]} ${lalu.getFullYear()} → ${BULAN_PENDEK[periode.bulan]} ${periode.tahun}`;
        $('slKirim').disabled = true;
        $('slInfo').className = 'me-auto small text-secondary';
        $('slInfo').textContent = simpan ? 'Menyalin ke SIPOTRET…' : 'Membaca SIPOTRET…';
        if (!simpan) { $('slBody').innerHTML = '<div class="text-center text-secondary py-4"><i class="ti ti-loader-2 icon-spin"></i> Membaca SIPOTRET…</div>'; modal.show(); }
        try {
            const res = await fetch(cfg.url.salin, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                body: JSON.stringify({ pub_ids: ids, ...periode, simpan }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.error) throw new Error(data.error || data.message || 'Server membalas ' + res.status);
            const lap = data.laporan || [];
            const nama = id => esc(items.find(p => String(p.pub_id) === id)?.title || id);
            const TAHAP = { belum: ['bg-red-lt', 'Belum upload'], menunggu: ['bg-yellow-lt', 'Menunggu approve'], approve: ['bg-green-lt', 'Sudah approve'] };
            $('slBody').innerHTML = `<div class="list-group">${lap.map(r => `
                <div class="list-group-item ${r.bisa ? '' : 'text-secondary'}">
                    <div class="d-flex justify-content-between gap-2">
                        <span class="fw-semibold text-truncate">${r.judul ? esc(r.judul) : nama(r.pub_id)}</span>
                        <span class="d-flex gap-1 flex-shrink-0">
                            ${r.tahap ? `<span class="badge ${TAHAP[r.tahap][0]}">${TAHAP[r.tahap][1]}</span>` : ''}
                            ${r.bisa ? `<span class="badge ${simpan ? 'bg-green' : 'bg-green-lt'}">${simpan ? 'tersalin' : 'akan disalin'} ${r.baru}</span>` : ''}
                            ${r.sudah ? `<span class="badge bg-secondary-lt" title="Sudah ada di periode pemeriksaan">${r.sudah} sudah ada</span>` : ''}
                            ${r.diputuskan?.length ? `<span class="badge bg-azure-lt" title="Diputuskan Sesuai/Skip oleh pemeriksa, tidak disalin: ${esc(r.diputuskan.join(', '))}">${r.diputuskan.length} diputuskan sesuai</span>` : ''}
                            ${r.keputusan_ts ? `<span class="badge bg-red-lt" title="Diputuskan Tidak Sesuai oleh pemeriksa: disalin dengan keterangan pemeriksa">${r.keputusan_ts} pakai keterangan pemeriksa</span>` : ''}
                        </span>
                    </div>
                    ${r.alasan ? `<div class="small">${esc(r.alasan)}</div>` : ''}
                </div>`).join('')}</div>`;
            const nPub = lap.filter(r => r.bisa).length, nCat = lap.reduce((n, r) => n + (r.bisa ? r.baru : 0), 0);
            if (simpan) {
                salinDikirim = true;
                $('slInfo').className = 'me-auto small text-success';
                $('slInfo').textContent = `${nCat} catatan dari ${nPub} publikasi tersalin ke SIPOTRET.`;
                $('slKirimLabel').textContent = 'Selesai';
            } else {
                $('slKirim').disabled = !nPub;
                $('slInfo').textContent = nPub ? '' : 'Tidak ada yang perlu disalin.';
                $('slKirimLabel').textContent = nPub ? `Salin & kirim ${nCat} catatan (${nPub} publikasi)` : 'Salin & kirim';
            }
        } catch (e) {
            $('slInfo').className = 'me-auto small text-danger';
            $('slInfo').textContent = (simpan ? 'Gagal menyalin: ' : 'Gagal membaca: ') + e.message;
            if (!simpan) $('slBody').innerHTML = '';
            else $('slKirim').disabled = false;
        }
    }

    function approveTerpilih() {
        const ids = historyMenunggu();
        if (!ids.length) return;
        if (!confirm(`Approve perbaikan ${ids.length} publikasi di SIPOTRET?\n\nPerbaikan kab/kota diunggah ke portal publikasi BPS. Pastikan PDF terbarunya sudah diperiksa ulang.`)) return;
        const form = $('formApprove');
        ids.forEach(id => form.insertAdjacentHTML('beforeend', `<input type="hidden" name="history_ids[]" value="${id}">`));
        form.submit();
    }

    // ── PENUGASAN PEMERIKSAAN (admin) ─────────────────────────────
    function renderTugas() {
        items.forEach((p, i) => {
            const box = $('tugas-' + i);
            if (box) box.innerHTML = Penugasan.badge(p.tugas);
        });
    }

    async function tugaskanTerpilih(lepas = false) {
        const pubs = [...new Map([...selected.values()].filter(p => p.pub_id).map(p => [String(p.pub_id), p])).values()];
        if (!pubs.length) { showAlert('warning', 'Centang publikasi yang akan ditugaskan dulu (publikasi tanpa pub_id tidak bisa ditugaskan).'); return; }
        const sel = $('tugasUser');
        if (!lepas && !sel.value) { sel.focus(); showAlert('warning', 'Pilih pemeriksa dulu.'); return; }
        const nama = sel.selectedOptions[0]?.textContent.trim();
        if (!confirm(lepas ? `Lepas penugasan ${pubs.length} publikasi?` : `Tugaskan ${pubs.length} publikasi ke ${nama}?\n\nSelama ditugaskan, hanya pemeriksa itu (dan admin) yang bisa memeriksa, meninjau, dan mengirim ke SIPOTRET.`)) return;
        try {
            const r = await Penugasan.simpan(pubs.map(p => ({ pub_id: String(p.pub_id), judul: p.title, domain: p.domain || null })), lepas ? null : sel.value);
            if (cfg.ulangSetelahTugaskan) { location.reload(); return; }
            items.forEach(p => { if (pubs.some(x => String(x.pub_id) === String(p.pub_id))) p.tugas = r.tugas[p.pub_id] ?? null; });
            renderTugas();
            renderSelection();
            showAlert('success', esc(r.pesan));
        } catch (e) {
            showAlert('danger', 'Gagal menyimpan penugasan: ' + esc(e.message));
        }
    }

    // ── PERIKSA BEBERAPA PUBLIKASI SEKALIGUS ──────────────────────
    async function startCheck() {
        const pubs = [...selected.values()].filter(bisaDiperiksa);
        if (!pubs.length) return;
        if (pubs.length > cfg.maxPubs) {
            showAlert('warning', `${pubs.length} publikasi ber-PDF dipilih, maksimal ${cfg.maxPubs} sekali periksa. Kurangi pilihannya dulu.`);
            return;
        }
        if (!cfg.periode()) {
            showAlert('warning', 'Pilih periode pemeriksaan (bulan & tahun) dulu. Hasilnya akan dikirim ke SIPOTRET pada periode itu.');
            $('periodeBulan')?.focus();
            return;
        }
        const btn = $('btnCheckSel');
        btn.disabled = true;

        const total = pubs.length;
        $('batchSection').style.display = 'block';
        $('reviewSection').style.display = 'none';
        $('batchTitle').textContent = `Memeriksa ${total} publikasi…`;
        $('btnReviewAll').disabled = true;
        $('batchSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
        allResults = []; sesiId = null;

        // Sesi dibuat dulu supaya semua publikasi (diproses paralel) masuk ke sesi yang sama
        try {
            const res = await fetch(cfg.url.sesi, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf } });
            sesiId = (await res.json()).sesi_id ?? null;
        } catch (e) { sesiId = null; }

        const periodeKirim = cfg.periode();
        hasilDraf = []; hasilAkhir = []; entriPer = new Map(); unduhPdf = [];
        tinjauanTerbuka = false; selesaiMenunggu = false;
        const jobs = pubs.map((pub, i) => {
            // file perbaikan di SIPOTRET (provinsi) diutamakan; selain itu PDF portal BPS lewat proxy
            const sumber = pub.file_url || proxied(pub.pdf);
            const isian  = cfg.isian[String(pub.pub_id)] || null;
            const pdfUrl = pub.pdf || new URL(pub.file_url, location.href).href;
            // draf hasil lebih dulu: petugas bisa langsung meninjau selama PDF diunduh/dibaca/dicek
            const draf = buatDraf(pub, pdfUrl, isian, periodeKirim).then(r => {
                if (!r) return null;
                r._pdfUrl = sumber;
                r._pub = pub;
                CheckerReview.terapkanIsian(r, isian);
                hasilDraf[i] = r;
                BatchCheck.siapTinjau($('batchGrid'), i);
                return r;
            });
            const pdf = unduhPdf[i] = { selesai: false, persen: 0, url: null };
            const entriKartu = () => entriPer.get((hasilAkhir[i] ?? hasilDraf[i])?.hasil_id);
            return {
                title:  pub.title,
                cover:  pub.cover || null,
                source: sumber,
                onUnduh: frac => { pdf.persen = frac; CheckerReview.progresPdf(entriKartu()); },
                onPdf:   url => { pdf.selesai = true; pdf.url = url; CheckerReview.pdfSiap(entriKartu(), url); },
                send:   async extracted => {
                    const d = await draf;
                    const res = await fetch(cfg.url.run, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                        body: JSON.stringify({
                            publications: [{
                                pub_id:  String(pub.pub_id ?? ''),
                                title:   pub.title,
                                // tetap URL portal bila ada (dipakai Riwayat & kiriman SIPOTRET); teks sudah dibaca dari `sumber`
                                pdf:     pdfUrl,
                                hasil_id: d?.hasil_id ?? null, // isi draf yang mungkin sedang ditinjau
                                domain:  pub.domain || null,
                                issn:    pub.issn || null,
                                catalog: pub.catalog || null, // cadangan cek sama_api bila detail Web API gagal diambil server
                                pub_no:  pub.pub_no || null,
                                rl_date: pub.rl_date || null,
                                // acuan isian awal = periode catatan SIPOTRET yang dipakai (disimpan di Riwayat)
                                acuan_bulan: isian ? isian.periode.bulan : null,
                                acuan_tahun: isian ? isian.periode.tahun : null,
                                // periode pemeriksaan tujuan (dipakai status Tugas Saya per periode)
                                periode_bulan: periodeKirim.bulan,
                                periode_tahun: periodeKirim.tahun,
                            }],
                            extracted_b64: PdfExtract.kemas(extracted), // base64: lihat PdfExtract.kemas
                            sesi_id: sesiId,
                        }),
                    });
                    // halaman HTML (mis. 403 dari firewall hosting) → pesan yang jelas, bukan "Unexpected token '<'"
                    const data = await res.json().catch(() => { throw new Error(res.status === 403 ? 'Ditolak server (403) sebelum sampai ke aplikasi, kemungkinan firewall hosting. Coba lagi; bila tetap gagal, hubungi pengelola server.' : 'Server membalas ' + res.status + ' (bukan JSON)'); });
                    if (data.error || !res.ok) throw new Error(data.error || data.message || ('Server membalas ' + res.status));
                    const r = data.results?.[0];
                    if (!r || r.error) throw new Error(r?.error || 'Tidak ada hasil pemeriksaan');
                    sesiId ??= data.sesi_id;
                    r._pdfUrl = sumber; // viewer tinjauan menampilkan file yang diperiksa
                    r._pub = pub;       // data publikasi dari API BPS (ISSN, revisi terakhir, dll.) untuk judul panel tinjauan
                    CheckerReview.terapkanIsian(r, isian);
                    hasilAkhir[i] = r;
                    // draf sedang/pernah ditinjau: hasil cek otomatis mengisi entry yang sama
                    const entri = entriPer.get(r.hasil_id);
                    if (entri) CheckerReview.gabungHasil(entri, r);
                    return r;
                },
            };
        });

        berjalan = true;
        // menutup/pindah halaman menghentikan unduh & cek (draf yang ditinjau tetap tersimpan, hasil otomatisnya tidak)
        const cegahTutup = e => { e.preventDefault(); e.returnValue = ''; };
        window.addEventListener('beforeunload', cegahTutup);
        batchOutcomes = await BatchCheck.run($('batchGrid'), jobs, { onReview: i => beginReview(i) });
        window.removeEventListener('beforeunload', cegahTutup);
        berjalan = false;
        allResults = batchOutcomes.filter(o => o.result).map(o => o.result);

        const gagal = total - allResults.length;
        $('batchTitle').textContent = `${allResults.length} dari ${total} publikasi selesai diperiksa` + (gagal ? ` · ${gagal} gagal` : '');
        $('btnReviewAll').disabled = !allResults.length;
        btn.disabled = false;
        clearSelection();

        if (selesaiMenunggu) { finishReview(); return; } // tinjauan sudah diselesaikan selama menunggu
        if (total === 1 && allResults.length === 1 && !tinjauanTerbuka) beginReview();
    }

    /** POST draf hasil (semua kriteria "menunggu"); null bila gagal — pemeriksaan tetap jalan seperti biasa. */
    async function buatDraf(pub, pdfUrl, isian, periode) {
        if (!sesiId || !cfg.url.draf) return null;
        try {
            const res = await fetch(cfg.url.draf, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                body: JSON.stringify({
                    title: pub.title, pdf: pdfUrl, pub_id: String(pub.pub_id ?? ''), domain: pub.domain || null,
                    issn: pub.issn || null, rl_date: pub.rl_date || null, sesi_id: sesiId,
                    acuan_bulan: isian ? isian.periode.bulan : null, acuan_tahun: isian ? isian.periode.tahun : null,
                    periode_bulan: periode?.bulan ?? null, periode_tahun: periode?.tahun ?? null,
                }),
            });
            const data = await res.json().catch(() => ({}));
            return res.ok && data.result ? data.result : null;
        } catch (e) {
            console.warn('Draf hasil gagal dibuat, tinjau setelah pemeriksaan selesai', e);
            return null;
        }
    }

    /** Entry tinjauan untuk hasil r (draf atau lengkap); objek yang sama per hasil_id. */
    function entriDari(r) {
        let e = entriPer.get(r.hasil_id);
        if (e) return e;
        e = {
            filename: r.filename,
            checks:   r.checks,
            hasilId:  r.hasil_id,
            ocrLines: r.ocr_lines,
            summary:  r.summary,
            url:      r._pdfUrl,
            usulanCatatan: r._usulanCatatan ?? null,
            isianInfo: r.isianInfo ?? null,
            pubId:     r._pub?.pub_id ?? null,
            pubDomain: r._pub?.domain ?? null,
            meta:      r._pub?.updt_date !== undefined ? r._pub : null, // detail API sudah dimuat di daftar
            menunggu:  !!r.menunggu, // draf: cek otomatis belum selesai
        };
        entriPer.set(r.hasil_id, e);
        return e;
    }

    /** @param {number} [jobIndex] kartu yang diklik "Tinjau": publikasi itu dibuka pertama */
    function beginReview(jobIndex) {
        // termasuk publikasi yang baru berhasil lewat "Coba lagi" setelah pemeriksaan awal selesai
        // selama diproses: hasil lengkap bila sudah ada, selain itu draf (bisa ditinjau sambil menunggu)
        const n = Math.max(hasilDraf.length, hasilAkhir.length, batchOutcomes.length);
        const perKartu = Array.from({ length: n }, (_, k) => hasilAkhir[k] ?? batchOutcomes[k]?.result ?? hasilDraf[k] ?? null);
        allResults = perKartu.filter(r => r && !r.menunggu);
        const target  = Number.isInteger(jobIndex) ? perKartu[jobIndex] : null;
        const entries = perKartu.map((r, k) => {
            if (!r?.checks?.length) return null;
            const e = entriDari(r);
            const pdf = unduhPdf[k];
            if (pdf?.selesai) { if (pdf.url) e.url = pdf.url; e.pdfTunggu = null; }
            // PDF masih diunduh proses pemeriksaan: viewer menunggu unduhan itu (tidak unduh dobel)
            else if (pdf && e.pdfTunggu === undefined) e.pdfTunggu = pdf;
            return e;
        }).filter(Boolean);
        if (!entries.length) return;
        const startIndex = target ? entries.findIndex(e => e.hasilId === target.hasil_id) : -1;
        tinjauanTerbuka = true;
        CheckerReview.start(entries, { onFinish: finishReview, startIndex: startIndex >= 0 ? startIndex : undefined });
    }

    function finishReview() {
        tinjauanTerbuka = false;
        $('batchSection').style.display = 'block';
        // masih ada yang diunduh/dicek: tunggu dulu (pindah halaman menghentikan prosesnya), lalu lanjut sendiri
        if (berjalan) {
            selesaiMenunggu = true;
            $('batchTitle').innerHTML = '<i class="ti ti-loader-2 icon-spin me-1"></i> Tinjauan tersimpan. Menunggu pemeriksaan otomatis selesai, '
                + 'jangan tutup halaman ini. Setelah selesai langsung ke Kirim ke SIPOTRET.';
            $('batchSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        if (sesiId) {
            // lanjut ke Riwayat: modal "Kirim ke SIPOTRET" langsung terbuka dengan periode pemeriksaan
            const url = cfg.url.riwayat.replace('__ID__', sesiId) + '?' + new URLSearchParams({ sipotret: 1, ...(cfg.periode() ?? {}) });
            $('batchTitle').innerHTML = `Tinjauan tersimpan · <a href="${url}">Kirim hasil ke SIPOTRET</a>`;
            window.location = url;
            return;
        }
        $('batchSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /** Buka/tutup catatan masalah semua publikasi sekaligus. */
    function toggleSemuaDetail(btn) {
        const buka = btn.dataset.terbuka !== '1';
        document.querySelectorAll('.pub-card .collapse').forEach(el => bootstrap.Collapse.getOrCreateInstance(el, { toggle: false })[buka ? 'show' : 'hide']());
        btn.dataset.terbuka = buka ? '1' : '';
        btn.querySelector('span').textContent = buka ? 'Sembunyikan semua catatan' : 'Tampilkan semua catatan';
    }

    function init(opsi) {
        cfg = { maxPubs: 10, isian: {}, ...opsi };
        items = cfg.items;
        Penugasan.init({ url: cfg.url.penugasan, csrf: cfg.csrf });
        document.addEventListener('DOMContentLoaded', () => {
            // urutan kartu pilihan terakhir (tersimpan di browser ini)
            let urut = '';
            try { urut = localStorage.getItem(URUT_KEY) || ''; } catch { /* abaikan */ }
            if (urut && $('urutKartu')) urutkanKartu(urut);
            // setelah ada yang tersalin, muat ulang supaya status kartu (Tercatat di SIPOTRET) ikut berubah
            $('modalSalinLalu')?.addEventListener('hidden.bs.modal', () => { if (salinDikirim) location.reload(); });
            renderTugas();
            renderSelection();
            loadAllDetails();
        });
    }

    return { init, toggleSelect, pilihSemua, clearSelection, approveTerpilih, tugaskanTerpilih, salinTerpilih, startCheck, beginReview, toggleSemuaDetail, urutkanKartu };
})();

// dipanggil dari atribut onclick/onchange di partial kartu & bar pilihan
const { toggleSelect, pilihSemua, clearSelection, approveTerpilih, tugaskanTerpilih, salinTerpilih, startCheck, beginReview, toggleSemuaDetail, urutkanKartu } = PeriksaTerpilih;
