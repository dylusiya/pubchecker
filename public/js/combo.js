/**
 * Combobox pencarian sederhana tanpa library (aset lokal; jaringan BPS bisa memblokir CDN).
 * Markup: resources/views/checker/partials/combo.blade.php → <div class="combo" data-combo='{"kode":"nama",...}'>.
 * Ketik kode atau nama (semua kata harus cocok, urutan bebas); pilih dengan klik atau ↑↓ Enter; Esc / klik di luar = batal.
 * Nilai yang dikirim (input hidden) selalu kode dari master; kosong boleh lewat pilihan "Kosongkan".
 */
(() => {
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const regexAman = k => k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const tandai = (teks, kata) => kata.reduce((h, k) => h.replace(new RegExp('(' + regexAman(esc(k)) + ')', 'ig'), '<mark>$1</mark>'), esc(teks));

    function pasang(box) {
        let data;
        try { data = JSON.parse(box.dataset.combo || '{}'); } catch { data = {}; }
        const kodeTampil = box.dataset.kode !== '0'; // data-kode="0": kode hanya id (mis. user), yang tampil & dicari nama saja
        const DATA = Object.entries(data).map(([kode, nama]) => ({ kode, nama: nama || '', cari: ((kodeTampil ? kode + ' ' : '') + (nama || '')).toLowerCase() }));
        // kunci angka (id user) diurutkan JS menurut angka → urutkan ulang menurut nama
        if (!kodeTampil) DATA.sort((a, b) => a.nama.localeCompare(b.nama, 'id'));
        const nilai = box.querySelector('.combo-nilai'), cari = box.querySelector('.combo-cari'), daftar = box.querySelector('.combo-daftar');
        const teksKosong = box.dataset.kosong || 'Kosongkan';
        const label = d => kodeTampil ? d.kode + (d.nama ? ' · ' + d.nama : '') : d.nama || d.kode;
        let hasil = [], aktif = -1, awal = cari.value;

        const tutup = () => { daftar.hidden = true; cari.setAttribute('aria-expanded', 'false'); aktif = -1; };
        const render = () => {
            const kata = cari.value.toLowerCase().split(/\s+/).filter(k => k && k !== '·');
            hasil = DATA.filter(d => kata.every(k => d.cari.includes(k))).slice(0, 50);
            daftar.innerHTML = (nilai.value && box.dataset.wajib !== '1' ? `<a href="#" class="list-group-item list-group-item-action py-1 small text-secondary" data-i="-2"><i class="ti ti-x me-1"></i>${esc(teksKosong)}</a>` : '')
                + (hasil.length ? hasil.map((d, i) => `<a href="#" class="list-group-item list-group-item-action py-2${d.kode === nilai.value ? ' fw-semibold' : ''}" role="option" data-i="${i}">
                        ${kodeTampil ? `<span class="font-monospace">${tandai(d.kode, kata)}</span>${d.nama ? ' <span class="text-secondary">· ' + tandai(d.nama, kata) + '</span>' : ''}` : tandai(d.nama || d.kode, kata)}</a>`).join('')
                    : `<div class="list-group-item small text-secondary">Tidak ada yang cocok di master SIPOTRET.</div>`);
            daftar.hidden = false; cari.setAttribute('aria-expanded', 'true'); aktif = -1;
        };
        const pilih = i => {
            if (i === -2) { nilai.value = ''; cari.value = ''; }
            else if (hasil[i]) { nilai.value = hasil[i].kode; cari.value = label(hasil[i]); }
            awal = cari.value; tutup();
            nilai.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const sorot = n => {
            const el = [...daftar.querySelectorAll('[data-i]')];
            if (!el.length) return;
            aktif = (aktif + n + el.length) % el.length;
            el.forEach((x, j) => x.classList.toggle('active', j === aktif));
            el[aktif].scrollIntoView({ block: 'nearest' });
        };
        cari.addEventListener('focus', () => { cari.select(); render(); });
        cari.addEventListener('input', render);
        cari.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown') { e.preventDefault(); daftar.hidden ? render() : sorot(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); sorot(-1); }
            else if (e.key === 'Enter' && !daftar.hidden) {
                e.preventDefault(); // memilih, bukan mengirim form
                const el = daftar.querySelectorAll('[data-i]')[aktif < 0 ? 0 : aktif];
                if (el) pilih(+el.dataset.i);
            }
            else if (e.key === 'Escape') { cari.value = awal; tutup(); }
        });
        daftar.addEventListener('mousedown', e => { // mousedown: sebelum blur
            const a = e.target.closest('[data-i]');
            if (!a) return;
            e.preventDefault(); pilih(+a.dataset.i);
        });
        cari.addEventListener('blur', () => { cari.value = awal; tutup(); }); // harus dari master
    }

    const mulai = () => document.querySelectorAll('.combo[data-combo]').forEach(pasang);
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', mulai) : mulai();
})();
