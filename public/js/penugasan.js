/**
 * Penugasan pemeriksaan per publikasi (dipakai Import API BPS & Publikasi Bermasalah).
 * Admin menugaskan publikasi terpilih ke satu pemeriksa; selama ditugaskan, hanya pemeriksa itu
 * (dan admin) yang bisa memeriksa, meninjau, dan mengirim ke SIPOTRET.
 *
 * Bentuk `tugas` dari server (PenugasanPemeriksaan::ringkas): { user_id, nama, saya, boleh } atau null.
 */
const Penugasan = (() => {
    let url = null, csrf = null;

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function init(opts) { url = opts.url; csrf = opts.csrf; }

    /** Badge penugasan untuk kartu publikasi ('' bila belum ditugaskan). */
    function badge(t) {
        if (!t) return '';
        const judul = t.boleh
            ? (t.saya ? 'Publikasi ini ditugaskan ke Anda' : `Ditugaskan ke ${t.nama}`)
            : `Ditugaskan ke ${t.nama}: hanya pemeriksa itu (dan admin) yang bisa memeriksa`;
        return `<span class="badge ${t.saya ? 'bg-green-lt' : 'bg-purple-lt'} fw-normal" title="${esc(judul)}">
            <i class="ti ti-${t.boleh ? 'user-check' : 'lock'} me-1"></i>${t.saya ? 'Tugas saya' : esc(t.nama)}</span>`;
    }

    /** Boleh diperiksa user ini (belum ditugaskan, ditugaskan ke dia, atau admin). */
    const boleh = t => !t || t.boleh;

    /**
     * Simpan penugasan. pubs: [{ pub_id, judul, domain }]; userId null = lepas penugasan.
     * @returns {Promise<{tugas: Object<string, object>, pesan: string}>}
     */
    async function simpan(pubs, userId) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ pubs, user_id: userId || null }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || data.error || `Server membalas ${res.status}`);
        return data;
    }

    return { init, badge, boleh, simpan };
})();
