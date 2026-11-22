{{-- Judul panel satu langkah pra rilis: nomor, judul, siapa, status, keterangan & batas. Butuh $l (langkah), $gaya, $tgl. --}}
@php [$w, $ik, $nm] = $gaya[$l['status']]; @endphp
<div class="card-header align-items-start">
    <div class="d-flex gap-3 w-100">
        <span class="avatar rounded-circle flex-shrink-0 bg-{{ $w }}{{ $l['status'] === 'menunggu' ? '-lt' : ' text-white' }}">
            @if($ik)<i class="ti {{ $ik }}"></i>@else{{ $l['no'] }}@endif
        </span>
        <div class="flex-fill">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0"><span class="text-secondary fw-normal me-1">Langkah {{ $l['no'] }}</span>{{ $l['judul'] }}</h3>
                <span class="badge {{ $l['oleh'] === 'PJK' ? 'bg-orange-lt' : 'bg-blue-lt' }}">{{ $l['oleh'] }}</span>
                <span class="badge bg-{{ $w }}-lt">{{ $nm }}</span>
            </div>
            <div class="small mt-1 {{ $l['status'] === 'terlewat' ? 'text-danger' : 'text-secondary' }}">
                {{ $l['ket'] }}
            </div>
        </div>
    </div>
</div>
