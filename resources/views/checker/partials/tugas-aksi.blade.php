{{-- Tugas Saya: tombol lanjutan sesuai status pengerjaan (bagian dari pub-card). $r = $progres. --}}
@php $h = $r['hasil']; @endphp
@switch($r['status'])
    @case('tinjau')
        <a href="{{ route('checker.hasil.tinjau', $h) }}" class="btn btn-sm btn-warning"><i class="ti ti-player-play"></i><span class="ms-1">Lanjutkan Tinjauan</span></a>
        @break
    @case('selesai')
        <a href="{{ route('checker.riwayat.detail', $h->sesi_id) . '?' . http_build_query(['sipotret' => 1, 'bulan' => $r['bulan'], 'tahun' => $r['tahun']]) }}"
           class="btn btn-sm btn-danger"><i class="ti ti-send"></i><span class="ms-1">Kirim ke SIPOTRET</span></a>
        @break
    @case('terkirim')
        <a href="{{ route('checker.riwayat.detail', $h->sesi_id) }}" class="btn btn-sm"><i class="ti ti-eye"></i><span class="ms-1">Lihat</span></a>
        @break
    @default
        {{-- belum diperiksa: centang lalu Periksa Terpilih di atas --}}
@endswitch
