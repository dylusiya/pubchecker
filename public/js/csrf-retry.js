/**
 * Token CSRF kedaluwarsa (419) → ambil token baru lalu ulangi permintaan sekali.
 *
 * Halaman memakai token dari <meta name="csrf-token"> yang dibaca saat halaman dimuat. Bila session berganti
 * selama halaman terbuka (mis. pemeriksaan PDF secured yang lama, atau login ulang di tab lain), semua
 * permintaan berikutnya gagal "CSRF token mismatch". Pembungkus fetch ini memperbaruinya otomatis:
 * permintaan same-origin yang membawa header X-CSRF-TOKEN selalu memakai token terbaru.
 * Bila ternyata sudah ter-logout (401 sesudah diulang), pesannya dibuat jelas.
 */
(() => {
    const asli = window.fetch.bind(window);
    // alamat dari atribut data-url (aplikasi bisa berada di subfolder)
    const URL_TOKEN = document.currentScript?.dataset.url || '/csrf-token';
    const meta = () => document.querySelector('meta[name="csrf-token"]');
    let tokenBaru = null;   // token hasil penyegaran (menggantikan token lama di header)
    let menyegarkan = null; // Promise<string|null>: beberapa permintaan 419 bersamaan → satu penyegaran

    function segarkan() {
        menyegarkan ??= asli(URL_TOKEN, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
            .then(r => (r.ok ? r.json() : null))
            .then(d => {
                if (!d?.token) return null;
                tokenBaru = d.token;
                meta()?.setAttribute('content', d.token);
                return d.token;
            })
            .catch(() => null)
            .finally(() => { setTimeout(() => { menyegarkan = null; }, 0); });
        return menyegarkan;
    }

    const sameOrigin = url => {
        try { return new URL(url, location.href).origin === location.origin; } catch { return false; }
    };

    window.fetch = async function (input, init = {}) {
        const url = typeof input === 'string' || input instanceof URL ? String(input) : input?.url;
        const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
        if (!sameOrigin(url) || !headers.has('X-CSRF-TOKEN') || input instanceof Request) return asli(input, init);

        const kirim = () => {
            if (tokenBaru) headers.set('X-CSRF-TOKEN', tokenBaru);
            return asli(input, { ...init, headers });
        };
        const b = init.body;
        const bisaDiulang = !b || typeof b === 'string' || b instanceof FormData || b instanceof URLSearchParams || b instanceof Blob;
        const res = await kirim();
        if (res.status !== 419 || !bisaDiulang) return res; // body berupa stream tidak bisa dikirim ulang
        if (!(await segarkan())) return res;
        const ulang = await kirim();
        if (ulang.status !== 401) return ulang;
        return new Response(JSON.stringify({ message: 'Sesi login berakhir. Muat ulang halaman lalu login lagi; pemeriksaan yang belum selesai perlu diulang.' }),
            { status: 401, headers: { 'Content-Type': 'application/json' } });
    };
})();
