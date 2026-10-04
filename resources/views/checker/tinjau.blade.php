@extends('layouts.app')

@section('title', 'Tinjau: ' . $hasil->judul)

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Header (disembunyikan otomatis oleh panel review, bisa dibuka lewat "Tampilkan Info Lain") --}}
        <div class="card card-rounded mb-3">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="card-title mb-1">
                        <i class="mdi mdi-clipboard-check-outline text-primary me-2"></i>
                        Lanjutkan Tinjauan
                    </h4>
                    <p class="text-muted mb-0 small">{{ $hasil->judul }} · Sesi #{{ $hasil->sesi_id }}</p>
                </div>
                <a href="{{ route('checker.riwayat.detail', $hasil->sesi_id) }}" class="btn btn-light btn-sm border">
                    <i class="mdi mdi-arrow-left me-1"></i> Kembali ke Riwayat
                </a>
            </div>
        </div>

        @include('checker.partials.review-panel')

    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/checker-review.js') }}?v={{ filemtime(base_path('js/checker-review.js')) }}"></script>
<script>
const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
CheckerReview.init({ viewerUrl: '{{ asset("pdfjs/web/viewer.html") }}', csrf: CSRF, saveUrl: '{{ route("checker.hasil.review_kategori", "__ID__") }}' });

document.addEventListener('DOMContentLoaded', () => {
    CheckerReview.start([{
        filename: @json($hasil->judul),
        checks:   @json($checks),
        hasilId:  {{ $hasil->id }},
        summary:  null,
        url:      @json($pdfUrl),
        ocrLines: @json($hasil->ocr_lines ?? (object) []),
    }], {
        onFinish: () => { window.location = @json(route('checker.riwayat.detail', $hasil->sesi_id)) + '#pub-{{ $hasil->id }}'; },
    });
});
</script>
@endpush
