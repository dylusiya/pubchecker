{{-- Satu notifikasi SIPOTRET. $n dari SipotretDrafService::notifikasi(); $denganPub = tampilkan judul publikasi --}}
@php
    $peranCls = ['PJK' => 'bg-purple-lt', 'Admin' => 'bg-orange-lt', 'Ketua tim' => 'bg-cyan-lt'];
@endphp
<div class="list-group-item py-2" data-peran="{{ \Illuminate\Support\Str::slug($n['peran'] ?? 'lain') }}">
    <div class="d-flex gap-2 align-items-start">
        <span class="avatar avatar-sm bg-{{ $n['warna'] }}-lt flex-shrink-0"><i class="ti {{ $n['ikon'] }}"></i></span>
        <div class="flex-grow-1" style="min-width:0;">
            <div class="d-flex flex-wrap align-items-center gap-1">
                <strong class="small">{{ $n['judul'] ?: '(tanpa judul)' }}</strong>
                <span class="small text-secondary">→ {{ $n['penerima'] }}</span>
                @if($n['peran'])<span class="badge {{ $peranCls[$n['peran']] ?? 'bg-secondary-lt' }}">{{ $n['peran'] }}</span>@endif
                @if($n['dibaca'])
                    <span class="badge bg-green-lt" title="Sudah diklik penerima di SIPOTRET"><i class="ti ti-checks me-1"></i>Dibaca</span>
                @else
                    <span class="badge bg-secondary-lt"><i class="ti ti-mail me-1"></i>Belum dibaca</span>
                @endif
            </div>
            @if($n['pesan'])<div class="small mt-1">{{ $n['pesan'] }}</div>@endif
            @if(($denganPub ?? false) && $n['pub_id'])
                <a href="{{ route('checker.sipotret_draf.show', $n['pub_id']) }}" class="small d-inline-block mt-1">
                    <i class="ti ti-book me-1"></i>{{ $n['pub'] ?? 'Publikasi #' . $n['pub_id'] }}
                </a>
            @endif
        </div>
        <span class="small text-secondary text-nowrap flex-shrink-0" title="{{ $n['tanggal'] }}">
            {{ $n['tanggal'] ? \Carbon\Carbon::parse($n['tanggal'])->locale('id')->translatedFormat('d M Y H:i') : 'waktu tidak tercatat' }}
        </span>
    </div>
</div>
