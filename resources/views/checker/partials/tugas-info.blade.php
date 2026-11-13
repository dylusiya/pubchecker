{{-- Tugas Saya: keterangan singkat di samping badge status (bagian dari pub-card). $r = $progres.
     Hanya tanggal terkirim & alasan "Sudah sesuai"; status SIPOTRET sudah ada di badge status perbaikan kartu,
     dan sesinya dibuka lewat tombol aksi kartu (Lanjutkan / Kirim / Lihat).
     Sengaja hanya karakter ASCII (pemisah pakai &middot;) supaya tidak berubah jadi "?" bila file tersimpan bukan UTF-8. --}}
<small class="d-flex flex-wrap gap-1 align-items-center text-secondary">
    @if($r['terkirim'])
        <span>terkirim {{ $r['terkirim'] }}</span>
    @endif
    {{-- Sudah sesuai: diperiksa periode ini & semua kriteria Sesuai ("Tandai semua sesuai"), atau periode sebelumnya bersih --}}
    @if($r['status'] === 'ok' && ($r['sesuaiDiperiksa'] ?? false))
        <span>diperiksa, semua kriteria sesuai</span>
    @elseif($r['status'] === 'ok' && $r['lalu'])
        @php $bulanPendek = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']; @endphp
        <span>periode {{ $bulanPendek[$r['lalu']['bulan']] }} {{ $r['lalu']['tahun'] }}
            {{ $r['lalu']['tercatat'] ? 'tanpa catatan tidak sesuai' : 'sudah tidak ada catatan di SIPOTRET' }}</span>
    @endif
    {{-- nama pemeriksa tidak di sini: badge penugasan kartu (#tugas-i, js/penugasan.js) sudah menampilkannya --}}
</small>
