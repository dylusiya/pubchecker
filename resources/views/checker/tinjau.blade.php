@extends('layouts.app')

@section('title', 'Tinjau Sesi #' . $sesi->id)
@section('pretitle', 'Lanjutkan Tinjauan · Sesi #' . $sesi->id)
@section('page-title', $daftar->count() > 1 ? $daftar->count() . ' publikasi dalam sesi ini' : $daftar->first()->judul)
@section('page-actions')
    <a href="{{ route('checker.riwayat.detail', $sesi) }}" class="btn">
        <i class="ti ti-arrow-left"></i> Kembali ke Riwayat
    </a>
@endsection

@php
    $statusBadge = [
        'ok'          => ['bg-green-lt',  'Semua OK'],
        'perlu_dicek' => ['bg-yellow-lt', 'Perlu Dicek'],
        'masalah'     => ['bg-red-lt',    'Ada Masalah'],
    ];
@endphp

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Info publikasi dalam sesi: disembunyikan selama tinjauan, dibuka lewat "Tampilkan Info Lain" --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    Sesi <a href="{{ route('checker.riwayat.detail', $sesi) }}">#{{ $sesi->id }}</a>
                    <span class="text-secondary fw-normal">· {{ $sesi->created_at?->format('d M Y, H:i') }}</span>
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Publikasi</th>
                            <th>Sumber</th>
                            <th>Status</th>
                            <th>Hasil Kriteria</th>
                            <th style="width:200px;">Progres Tinjauan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($daftar as $hasil)
                            @php
                                $grup       = $hasil->detail->groupBy('kategori');
                                $katSelesai = $grup->filter(fn($g) => $g->every(fn($d) => $d->ditinjau_at))->count();
                                $badge      = $statusBadge[$hasil->status_akhir] ?? ['bg-secondary-lt', $hasil->status_akhir];
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $hasil->judul }}</td>
                                <td class="text-secondary small">
                                    {{ $hasil->sumber ?? '-' }}
                                    @if($hasil->total_halaman) · {{ $hasil->total_halaman }} hal. @endif
                                </td>
                                <td><span class="badge {{ $badge[0] }}">{{ $badge[1] }}</span></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-green-lt">{{ $hasil->total_ok }} OK</span>
                                        <span class="badge bg-yellow-lt">{{ $hasil->total_perlu_dicek }} Dicek</span>
                                        <span class="badge bg-red-lt">{{ $hasil->total_tidak_ada }} Masalah</span>
                                        <span class="badge bg-secondary-lt">{{ $hasil->total_tdk_diperiksa }} Skip</span>
                                    </div>
                                </td>
                                <td class="small">
                                    {{ $katSelesai }}/{{ $grup->count() }} kategori
                                    <div class="progress progress-sm mt-1">
                                        <div class="progress-bar bg-green" style="width: {{ $grup->count() ? round($katSelesai / $grup->count() * 100) : 0 }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($tanpaPdf)
                <div class="card-footer small text-secondary">
                    <i class="ti ti-info-circle"></i>
                    {{ $tanpaPdf }} publikasi lain di sesi ini tidak bisa ditinjau karena PDF-nya tidak tersimpan.
                </div>
            @endif
        </div>

        @include('checker.partials.review-panel')

    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/checker-review.js') }}?v={{ filemtime(public_path('js/checker-review.js')) }}"></script>
<script>
const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
CheckerReview.init({ viewerUrl: '{{ asset("pdfjs/web/viewer.html") }}', csrf: CSRF, saveUrl: '{{ route("checker.hasil.review_kategori", "__ID__") }}', contohUrl: '{{ route("checker.contoh.json") }}' });

document.addEventListener('DOMContentLoaded', () => {
    // Semua publikasi dalam sesi, tab per publikasi — seperti saat pemeriksaan
    CheckerReview.start(@json($entries), {
        startIndex: @json($startIndex),
        onFinish: () => { window.location = @json(route('checker.riwayat.detail', $sesi)); },
    });
});
</script>
@endpush
