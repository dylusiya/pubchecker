{{--
    Skrip daftar publikasi bersama (Publikasi Bermasalah & Tugas Saya) — js/periksa-terpilih.js.
    Variabel: $items        [{ pub_id, title, history_id, tahap, file_url, tugas, domain }] urut sama dengan kartu
              $isian        isian awal tinjauan per pub_id (catatan SIPOTRET acuan)
              $periodeTetap opsional ['bulan','tahun']: periode pemeriksaan tetap; tanpa ini dibaca dari #periodeBulan/#periodeTahun
              $bisaApprove, $pemeriksa (admin: daftar pemeriksa), $ulangSetelahTugaskan (opsional)
--}}
@push('scripts')
<script src="{{ asset('js/checker-review.js') }}?v={{ filemtime(public_path('js/checker-review.js')) }}"></script>
<script src="{{ asset('js/penugasan.js') }}?v={{ filemtime(public_path('js/penugasan.js')) }}"></script>
<script src="{{ asset('js/pdf-extract.js') }}?v={{ filemtime(public_path('js/pdf-extract.js')) }}"></script>
<script src="{{ asset('js/batch-check.js') }}?v={{ filemtime(public_path('js/batch-check.js')) }}"></script>
<script src="{{ asset('js/periksa-terpilih.js') }}?v={{ filemtime(public_path('js/periksa-terpilih.js')) }}"></script>
<script>
(() => {
    const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    CheckerReview.init({ viewerUrl: '{{ asset("pdfjs/web/viewer.html") }}', csrf: CSRF, saveUrl: '{{ route("checker.hasil.review_kategori", "__ID__") }}', contohUrl: '{{ route("checker.contoh.json") }}' });
    PdfExtract.init({ pdfjsBuild: '{{ asset("pdfjs/build") }}/' });

    const periodeTetap = {{ Js::from($periodeTetap ?? null) }};
    /** Periode pemeriksaan: tetap (Tugas Saya) atau dari pilihan di bar (Publikasi Bermasalah). */
    const periode = () => {
        if (periodeTetap) return periodeTetap;
        const bulan = parseInt(document.getElementById('periodeBulan')?.value, 10);
        const tahun = parseInt(document.getElementById('periodeTahun')?.value, 10);
        return bulan >= 1 && bulan <= 12 && tahun >= 2000 && tahun <= 2100 ? { bulan, tahun } : null;
    };

    PeriksaTerpilih.init({
        items:       {{ Js::from(array_values($items)) }},
        isian:       {{ Js::from((object) $isian) }},
        periode,
        bisaApprove: {{ Js::from((bool) $bisaApprove) }},
        adminTugas:  {{ Js::from((bool) $pemeriksa) }},
        ulangSetelahTugaskan: {{ Js::from((bool) ($ulangSetelahTugaskan ?? false)) }},
        csrf: CSRF,
        url: {
            detail:    '{{ route("checker.bps.detail") }}',
            run:       '{{ route("checker.bps.run") }}',
            draf:      '{{ route("checker.bps.draf") }}', // tinjau sambil diproses
            sesi:      '{{ route("checker.sesi.create") }}',
            pdfProxy:  '{{ route("checker.bps.pdf_proxy") }}',
            riwayat:   '{{ route("checker.riwayat.detail", "__ID__") }}',
            penugasan: '{{ route("checker.penugasan.simpan") }}',
            salin:     '{{ route("checker.sipotret.salin") }}', // salin catatan bulan lalu (belum upload/approve)
        },
    });
})();
</script>
@endpush
