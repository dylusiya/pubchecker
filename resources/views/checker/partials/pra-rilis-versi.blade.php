{{-- Satu versi draf di panel "Periksa draf": status pemeriksaan, temuan (pubchecker / IPDS SIPOTRET), dan aksi.
     Butuh $bv = ['v' => versi, 'h' => hasil pubchecker terbaru|null, 'n' => jumlah pemeriksaan, 'status' => [warna, ikon, teks]], $terbaru,
     serta $p, $flag, $kiriman, $idTerbaru, $boleh, $waktu, $levelWarna dari halaman detail. --}}
@php
    $v = $bv['v']; $h = $bv['h'];
    [$sw, $sik, $steks] = $bv['status'];
    $belum = !$h && !$v['tanggal_ipds'] && !$v['ada_revisi'];
    // temuan: hasil pubchecker (bila diperiksa di sini), selain itu temuan IPDS yang tercatat di SIPOTRET
    $temuan = $h
        ? $h->detail->map(fn($d) => ['kategori' => $d->kategori, 'item' => $d->deskripsi, 'ket' => $d->keterangan, 'level' => null, 'tambahan' => false])
            ->concat($h->catatanTambahan->map(fn($c) => ['kategori' => $c->kategori, 'item' => $c->item, 'ket' => $c->keterangan, 'level' => null, 'tambahan' => true]))
        : collect($v['temuan'])->map(fn($t) => ['kategori' => $t['kategori'], 'item' => $t['item'], 'ket' => $t['keterangan'], 'level' => $t['level'], 'tambahan' => false]);
    $bisaKirim = $h && isset($kiriman[$h->id]) && $boleh;
    $id = $v['tr_id'];
@endphp
<div class="list-group-item {{ $terbaru ? 'py-3' : 'py-2' }}">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge {{ $v['final'] ? 'bg-green-lt' : 'bg-primary-lt' }} {{ $terbaru ? 'fs-5' : '' }}">{{ $v['flag'] ?? 'v?' }}</span>
        <div class="lh-sm">
            <div class="small">{{ $terbaru ? 'Draf terbaru' : 'Draf' }} · upload {{ $waktu($v['tanggal']) }}</div>
            @if($v['catatan_pjk'])<div class="small text-secondary text-truncate" style="max-width:340px;" title="{{ $v['catatan_pjk'] }}">“{{ $v['catatan_pjk'] }}”</div>@endif
        </div>
        <span class="badge bg-{{ $sw }}-lt"><i class="ti {{ $sik }} me-1"></i>{{ $steks }}{{ $h && $h->sipotret_terkirim_at ? ' ' . $waktu($h->sipotret_terkirim_at) : '' }}</span>
        @if($h || $temuan->isNotEmpty())
            <button type="button" class="btn btn-sm btn-ghost-{{ $temuan->isEmpty() ? 'secondary' : 'danger' }} py-0" data-bs-toggle="collapse" data-bs-target="#temuan-v{{ $id }}">
                {{ $temuan->count() }} temuan <i class="ti ti-chevron-down ms-1"></i>
            </button>
        @endif

        <div class="ms-auto d-flex flex-wrap gap-1">
            @if($v['pdf'])<a href="{{ route('checker.sipotret_draf.pdf', $id) }}" target="_blank" class="btn btn-sm btn-icon btn-ghost-secondary" title="Lihat draf PDF"><i class="ti ti-eye"></i></a>@endif
            @if($v['ada_revisi'])<a href="{{ route('checker.sipotret_draf.pdf', [$id, 'berkas' => 'revisi']) }}" target="_blank" class="btn btn-sm btn-icon btn-ghost-success" title="File revisi yang dikirim ke PJK"><i class="ti ti-file-check"></i></a>@endif
            @if($h)<a href="{{ route('checker.riwayat.detail', $h->sesi_id) }}" class="btn btn-sm btn-icon btn-ghost-secondary" title="Riwayat pemeriksaan{{ $bv['n'] > 1 ? ' (' . $bv['n'] . '×)' : '' }}"><i class="ti ti-history"></i></a>@endif
            @if($v['pdf'] && $boleh)
                {{-- satu tombol utama per versi: periksa (belum) → kirim (sudah diperiksa, belum dikirim) --}}
                <a href="{{ route('checker.index', ['draf' => $id]) }}" class="btn btn-sm {{ $terbaru && $belum ? 'btn-primary' : '' }}">
                    <i class="ti ti-player-play me-1"></i>{{ $belum ? 'Periksa' : 'Periksa ulang' }}
                </a>
            @elseif($v['pdf'])
                <button type="button" class="btn btn-sm" disabled title="Ditugaskan ke pemeriksa lain"><i class="ti ti-lock me-1"></i>Periksa</button>
            @elseif($v['ada_file'])
                <span class="text-warning small">Bukan PDF</span>
            @endif
            @if($bisaKirim)
                <button type="button" class="btn btn-sm {{ $h->sipotret_terkirim_at ? 'btn-outline-purple' : 'btn-purple' }}" data-bs-toggle="collapse" data-bs-target="#kirim-v{{ $id }}">
                    <i class="ti ti-send me-1"></i>{{ $h->sipotret_terkirim_at ? 'Kirim ulang' : 'Kirim ke SIPOTRET' }}
                </button>
            @endif
        </div>
    </div>
    @if($h?->error_msg)<div class="small text-danger mt-1">{{ $h->error_msg }}</div>@endif

    {{-- temuan --}}
    @if($temuan->isNotEmpty() || $h)
        <div class="collapse mt-2 {{ $terbaru && $temuan->isNotEmpty() ? 'show' : '' }}" id="temuan-v{{ $id }}">
            @if($temuan->isEmpty())
                <div class="small text-success"><i class="ti ti-circle-check"></i> Tidak ada temuan.</div>
            @else
                <table class="table table-sm table-bordered mb-0 small">
                    <thead><tr><th style="width:150px;">Kategori</th><th>Kriteria / Item</th><th>Keterangan</th></tr></thead>
                    <tbody>
                    @foreach($temuan as $t)
                        <tr>
                            <td class="text-secondary">{{ $t['kategori'] ?? '-' }}</td>
                            <td>@if($t['level'])<span class="badge {{ $levelWarna[$t['level']] ?? 'bg-secondary-lt' }} me-1">{{ $t['level'] }}</span>@endif{{ $t['item'] }}
                                @if($t['tambahan'])<span class="badge bg-secondary-lt ms-1">tambahan</span>@endif</td>
                            <td class="text-danger">{{ $t['ket'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(!$h)<div class="small text-secondary mt-1">Dari SIPOTRET{{ $v['tanggal_ipds'] ? ' · ' . $waktu($v['tanggal_ipds']) : '' }}</div>@endif
            @endif
        </div>
    @endif

    {{-- kirim ke SIPOTRET --}}
    @if($bisaKirim)
        @php $km = collect($kiriman[$h->id]); @endphp
        <div class="collapse mt-2" id="kirim-v{{ $id }}">
            <div class="border rounded p-3 bg-light">
                <div class="small text-secondary mb-2">{{ $km->count() }} temuan · PJK diberi notifikasi</div>
                @if((int) $id !== $idTerbaru)
                    <div class="alert alert-warning py-2 small mb-2">Bukan versi terbaru.</div>
                @endif
                @if($v['temuan'] || $v['tanggal_ipds'] || $v['ada_revisi'])
                    <div class="alert alert-warning py-2 small mb-2">Hasil lama di SIPOTRET akan <strong>diganti</strong>.</div>
                @endif
                <form method="post" action="{{ route('checker.hasil.sipotret_draf', $h) }}" enctype="multipart/form-data"
                      data-confirm="Kirim hasil pemeriksaan {{ $v['flag'] }} ke SIPOTRET?" data-confirm-ok="Kirim">
                    @csrf
                    <label class="form-label small mb-1">PDF hasil (maks. 30 MB)</label>
                    <input type="file" name="file" accept="application/pdf,.pdf" class="form-control form-control-sm mb-1">
                    <div class="form-hint mb-2"><a href="{{ route('checker.hasil.tinjau', $h) }}">Tinjau</a> untuk kirim PDF berkomentar.</div>
                    @if($h->pdf_path)
                        <label class="form-check small mb-2">
                            <input type="checkbox" name="pakai_draf" value="1" class="form-check-input">
                            <span class="form-check-label">Pakai PDF draf (tanpa komentar)</span>
                        </label>
                    @endif
                    <button class="btn btn-purple btn-sm"><i class="ti ti-send me-1"></i> Kirim ke SIPOTRET</button>
                </form>
            </div>
        </div>
    @endif
</div>
