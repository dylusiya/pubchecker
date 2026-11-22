/**
 * Dialog — pengganti alert()/confirm() bawaan browser dengan modal Tabler (Bootstrap 5).
 *
 *   if (!(await Dialog.confirm('Hapus catatan ini?', { okText: 'Hapus', danger: true }))) return;
 *   Dialog.alert('PDF diunduh tanpa komentar.');
 *
 * Form: <form data-confirm="Hapus sesi ini?" data-confirm-ok="Hapus" data-confirm-danger> — dikonfirmasi
 * lewat modal sebelum dikirim (didelegasikan di document, tidak perlu onsubmit).
 * Pesan berupa teks biasa (di-escape); baris baru (\n) tetap tampil sebagai baris baru.
 * Bila Bootstrap belum tersedia, jatuh kembali ke dialog bawaan browser.
 */
const Dialog = (() => {
    let modalEl = null;

    function buat() {
        if (modalEl) return modalEl;
        modalEl = document.createElement('div');
        modalEl.className = 'modal modal-blur fade';
        modalEl.tabIndex = -1;
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-status"></div>
                    <div class="modal-body py-4">
                        <div class="d-flex gap-3 align-items-start">
                            <i class="ti dialog-ikon flex-shrink-0" style="font-size:2rem;"></i>
                            <div style="min-width:0;">
                                <h3 class="mb-2 dialog-judul"></h3>
                                <div class="text-secondary dialog-pesan" style="white-space:pre-line; overflow-wrap:anywhere;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn dialog-batal" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn dialog-ok ms-auto">OK</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modalEl);
        return modalEl;
    }

    /**
     * @param {string} pesan
     * @param {{title?: string, okText?: string, cancelText?: string, danger?: boolean, warning?: boolean, alert?: boolean}} o
     * @returns {Promise<boolean>} true bila OK diklik
     */
    function tampil(pesan, o = {}) {
        const bs = window.bootstrap || window.tabler?.bootstrap;
        if (!bs?.Modal) return Promise.resolve(o.alert ? (window.alert(pesan), true) : window.confirm(pesan));

        const el = buat();
        const warna = o.danger ? 'danger' : o.warning ? 'warning' : o.alert ? 'info' : 'primary';
        const ikon  = o.danger ? 'ti-alert-triangle' : o.warning ? 'ti-alert-circle' : o.alert ? 'ti-info-circle' : 'ti-help-circle';
        el.querySelector('.modal-status').className = `modal-status bg-${warna}`;
        el.querySelector('.dialog-ikon').className  = `ti ${ikon} text-${warna} dialog-ikon flex-shrink-0`;
        el.querySelector('.dialog-judul').textContent = o.title || (o.alert ? 'Informasi' : 'Konfirmasi');
        el.querySelector('.dialog-pesan').textContent = pesan;
        const ok = el.querySelector('.dialog-ok'), batal = el.querySelector('.dialog-batal');
        ok.textContent = o.okText || (o.alert ? 'Tutup' : 'Ya, lanjutkan');
        ok.className = `btn btn-${warna} dialog-ok ${o.alert ? '' : 'ms-auto'}`;
        batal.textContent = o.cancelText || 'Batal';
        batal.hidden = !!o.alert;

        const modal = bs.Modal.getOrCreateInstance(el);
        return new Promise(selesai => {
            let hasil = false;
            const klikOk = () => { hasil = true; modal.hide(); };
            const tertutup = () => {
                ok.removeEventListener('click', klikOk);
                el.removeEventListener('hidden.bs.modal', tertutup);
                selesai(o.alert ? true : hasil);
            };
            ok.addEventListener('click', klikOk);
            el.addEventListener('hidden.bs.modal', tertutup);
            el.addEventListener('shown.bs.modal', () => ok.focus(), { once: true });
            modal.show();
        });
    }

    const confirm = (pesan, o = {}) => tampil(pesan, { ...o, alert: false });
    const alert   = (pesan, o = {}) => tampil(pesan, { ...o, alert: true });

    // <form data-confirm="..."> → konfirmasi lewat modal sebelum dikirim
    document.addEventListener('submit', async e => {
        const form = e.target.closest?.('form[data-confirm]');
        if (!form) return;
        e.preventDefault();
        const ya = await confirm(form.dataset.confirm, {
            okText: form.dataset.confirmOk || undefined,
            danger: form.hasAttribute('data-confirm-danger'),
        });
        if (!ya) return;
        // kirim ulang tanpa memicu konfirmasi lagi (requestSubmit menjalankan validasi & event submit)
        form.removeAttribute('data-confirm');
        form.requestSubmit ? form.requestSubmit(e.submitter || undefined) : form.submit();
    }, true);

    return { confirm, alert };
})();
