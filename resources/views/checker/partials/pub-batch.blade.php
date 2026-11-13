{{-- Pemeriksaan berjalan (kartu per publikasi berjejer, js/batch-check.js) + panel tinjauan.
     Bersama js/periksa-terpilih.js untuk Publikasi Bermasalah & Tugas Saya. --}}
<div id="batchSection" style="display:none;">
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title" id="batchTitle">Memeriksa…</h3>
            <div class="card-actions">
                <button type="button" class="btn btn-primary btn-sm" id="btnReviewAll" disabled onclick="beginReview()">
                    <i class="ti ti-player-play"></i> <span>Mulai Tinjauan</span>
                </button>
            </div>
        </div>
        <div class="card-body" id="batchGrid"></div>
    </div>
</div>

{{-- Tinjauan per kategori, tab per publikasi --}}
@include('checker.partials.review-panel')

@push('styles')
<style>
    .toggle-detail .ti-chevron-right { display: inline-block; transition: transform .15s; }
    .toggle-detail:not(.collapsed) .ti-chevron-right { transform: rotate(90deg); }
</style>
@endpush
