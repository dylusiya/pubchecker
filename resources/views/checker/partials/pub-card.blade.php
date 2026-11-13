{{--
    Kartu satu publikasi (Publikasi Bermasalah & Tugas Saya), dipakai bersama js/periksa-terpilih.js.
    Variabel: $p   baris SipotretService::susunDaftar() (history_id null = belum ada catatan SIPOTRET)
              $i   indeks (sama dengan urutan `items` di JS)
              $bisaApprove  boleh approve file perbaikan
              $progres      opsional (Tugas Saya): status pengerjaan periode — lihat PenugasanController::index
--}}
@php
    $bulanNama   ??= ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    // tahap pub_file_rev_status → [warna, ikon, label]
    $tahapTampil ??= [
        'belum'    => ['bg-red-lt',    'x',     'Belum upload perbaikan'],
        'menunggu' => ['bg-yellow-lt', 'clock', 'Menunggu approve'],
        'approve'  => ['bg-green-lt',  'check', 'Sudah approve'],
    ];
    // kab/kota hanya konfirmasi sudah mengunggah ke portal publikasi BPS; file perbaikan hanya ada untuk provinsi
    $revLabel  ??= ['uploaded_kabkota' => 'kab/kota konfirmasi sudah upload ke portal', 'uploaded' => 'file perbaikan diunggah'];
    $tglPendek ??= fn($s) => $s ? (fn($d) => $d->day . ' ' . mb_substr($bulanNama[$d->month], 0, 3) . ' ' . $d->year)(\Illuminate\Support\Carbon::parse($s)) : null;
    $progres   ??= null;

    $adaSipotret   = !empty($p['history_id']);
    $bisaDiapprove = $adaSipotret && $p['tahap'] === 'menunggu' && $bisaApprove;
    $detailUrl = $p['pub_id_api'] ? route('checker.bps.detail.show', array_filter(['pub_id' => $p['pub_id_api'], 'domain' => $p['wilayah'] ?? null])) : null;
@endphp
<div class="pub-card mb-2" id="pubcard-{{ $i }}">
    {{-- klik kotak → detail publikasi; klik di centang, tautan, toggle catatan & isinya diabaikan lewat closest()
         (bukan stopPropagation: toggle collapse Bootstrap butuh event sampai document) --}}
    <div class="d-flex gap-3 align-items-start flex-wrap"
         @if($detailUrl) style="cursor:pointer;" title="Buka detail publikasi" onclick="if (!event.target.closest('label, a, button, .toggle-detail, .collapse')) window.location={{ Js::from($detailUrl) }}" @endif>
        {{-- bisa dicentang untuk diperiksa (setelah PDF dari API BPS termuat), di-approve, atau (admin) ditugaskan --}}
        <label class="form-check m-0 pt-1"
               title="{{ $bisaDiapprove || $p['file_rev'] ? 'Pilih untuk diperiksa ulang' . ($bisaDiapprove ? ' atau di-approve' : '') : 'Memuat data publikasi dari API BPS…' }}">
            {{-- ber-pub_id: bisa dicentang untuk "Salin bulan lalu" walau PDF belum termuat / tidak ada --}}
            <input type="checkbox" class="form-check-input pub-select" data-index="{{ $i }}" @disabled(!$bisaDiapprove && !$p['file_rev'] && !($p['pub_id_api'] && Gate::allows('periksa')))
                   onchange="toggleSelect({{ $i }}, this)">
        </label>
        <div class="pub-cover d-flex align-items-center justify-content-center text-muted" id="cover-{{ $i }}" style="font-size:20px;">
            <i class="ti ti-file-type-pdf text-danger"></i>
        </div>
        {{-- flex-basis 0: lebar tidak ikut isi catatan, jadi tidak turun baris saat toggle dibuka --}}
        <div class="flex-grow-1" style="flex: 1 1 0; min-width:0;">
            <div class="fw-semibold small mb-1" style="word-break: break-word; overflow-wrap: break-word;">{{ $p['judul'] }}</div>

            {{-- Tugas Saya: status pengerjaan periode pemeriksaan --}}
            @if($progres)
                {{-- satu baris: status + tanggal terkirim / diperiksa, progres tinjauan & sesinya --}}
                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                    <span class="badge {{ $progres['warna'] }} fs-5 px-2 py-1"><i class="ti ti-{{ $progres['ikon'] }} me-1"></i>{{ $progres['label'] }}</span>
                    @include('checker.partials.tugas-info', ['r' => $progres])
                </div>
            @endif

            {{-- Status perbaikan di SIPOTRET: sudah upload perbaikan atau belum --}}
            @if($adaSipotret)
                @php [$warna, $ikon, $label] = $tahapTampil[$p['tahap']]; @endphp
                <div class="mb-1">
                    <span class="badge {{ $warna }} {{ $progres ? '' : 'fs-5 px-2 py-1' }}">
                        <i class="ti ti-{{ $ikon }} me-1"></i>{{ $label }}
                    </span>
                    @isset($revLabel[$p['rev']])
                        <small class="text-secondary ms-1">{{ $revLabel[$p['rev']] }}</small>
                    @endisset
                </div>
            @endif

            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if($adaSipotret)
                    <span class="badge bg-azure-lt fw-normal">Pasca Rilis {{ $bulanNama[$p['bulan']] }} {{ $p['tahun'] }}</span>
                @elseif($progres)
                    <span class="badge bg-secondary-lt fw-normal">Belum ada catatan SIPOTRET periode ini</span>
                @endif
                {{-- provinsi mengunggah file perbaikan langsung di SIPOTRET → tanggal & file itu yang dipakai --}}
                @if($p['file_rev'])
                    <small class="fw-semibold text-primary">
                        <i class="ti ti-upload"></i> Perbaikan diunggah di SIPOTRET{{ $p['diubah_at'] ? ' ' . $tglPendek($p['diubah_at']) : '' }}
                    </small>
                @endif
                @unless($p['pub_id_api'])
                    <small class="text-warning">pub_id tidak tersimpan di SIPOTRET, data portal BPS tidak bisa dimuat</small>
                @endunless
                <span id="tugas-{{ $i }}"></span>
                <span id="meta-{{ $i }}" class="d-flex flex-wrap gap-2"></span>
            </div>

            {{-- Catatan masalah & riwayat: disembunyikan, dibuka lewat toggle --}}
            @if($p['catatan'] || $p['riwayat'])
                <a href="#detail-{{ $i }}" class="small d-inline-block mt-2 text-decoration-none toggle-detail collapsed"
                   data-bs-toggle="collapse" role="button" aria-expanded="false">
                    <i class="ti ti-chevron-right"></i> {{ count($p['catatan']) }} catatan masalah{{ $p['riwayat'] ? ' & riwayat' : '' }}
                </a>
                <div class="collapse" id="detail-{{ $i }}" style="cursor:auto;">
                    {{-- urut seperti urutan kriteria, dengan kode & kategori (seperti daftar saat meninjau) --}}
                    <ol class="small mb-0 mt-1 ps-3">
                        @foreach($p['catatan'] as $c)
                            <li class="mb-1">
                                <div class="text-secondary">
                                    @if($c['kode'] ?? null)
                                        <span class="font-monospace">{{ $c['kode'] }}</span> ·
                                    @endif
                                    {{ ($c['kategori'] ?? null) ?: 'Item lainnya' }}
                                </div>
                                <div>{{ $c['catatan'] }}</div>
                                @if($c['keterangan'])<div class="text-danger">{{ $c['keterangan'] }}</div>@endif
                            </li>
                        @endforeach
                    </ol>

                    @if($p['riwayat'])
                        <div class="d-flex flex-wrap align-items-center gap-1 mt-2 small">
                            <span class="text-secondary me-1"><i class="ti ti-history"></i> Riwayat SIPOTRET:</span>
                            @foreach($p['riwayat'] as $h)
                                <span class="badge {{ !$h['catatan'] ? 'bg-secondary-lt' : $tahapTampil[$h['tahap']][0] }} {{ $h['ini'] ? 'border border-dark' : '' }}"
                                      title="{{ $h['catatan'] }} catatan · {{ !$h['catatan'] ? 'tanpa temuan' : mb_strtolower($tahapTampil[$h['tahap']][2]) }}">
                                    {{ \Illuminate\Support\Str::substr($bulanNama[$h['bulan']], 0, 3) }} {{ $h['tahun'] }} · {{ $h['catatan'] }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>
    <div class="d-flex gap-2 align-items-center justify-content-end mt-2 flex-wrap">
        @if($bisaDiapprove)
            <form method="post" action="{{ route('dashboard.sipotret.approve', $p['history_id']) }}" class="m-0"
                  onsubmit="return confirm({{ Js::from("Approve perbaikan publikasi ini di SIPOTRET?\n\n" . $p['judul'] . ($p['rev'] === 'uploaded_kabkota' ? "\n\nPastikan PDF terbaru di portal BPS sudah diperiksa ulang (centang lalu Periksa)." : '')) }})">
                @csrf
                <button type="submit" class="btn btn-success btn-sm" title="Setujui file perbaikan (status SIPOTRET → approve)">
                    <i class="ti ti-circle-check"></i>
                    <span class="d-none d-md-inline ms-1">Approve</span>
                </button>
            </form>
        @endif
        @if($p['file_rev'])
            <a href="{{ route('dashboard.sipotret.file', $p['history_id']) }}" target="_blank" class="btn btn-outline-primary btn-sm"
               title="Buka file perbaikan yang diunggah di SIPOTRET">
                <i class="ti ti-file-upload"></i><span class="d-none d-md-inline ms-1">File perbaikan</span>
            </a>
        @endif
        <span id="pdfbadge-{{ $i }}">
            @if($p['pub_id_api'])
                <span class="badge bg-secondary-lt fw-normal"><i class="ti ti-loader-2 icon-spin me-1"></i>Memuat…</span>
            @endif
        </span>
        <span id="pdflink-{{ $i }}"></span>
        {{-- Tugas Saya: aksi lanjutan sesuai status (Lanjutkan Tinjauan / Kirim / Lihat) --}}
        @if($progres)
            @include('checker.partials.tugas-aksi', ['r' => $progres])
        @endif
    </div>
    @if($p['rev'] === 'uploaded_kabkota' && $bisaApprove)
        <div class="text-end small text-secondary mt-1">
            <i class="ti ti-info-circle"></i>
            Perbaikan diunggah kab/kota langsung ke portal publikasi BPS. Centang lalu klik <b>Periksa</b> di atas untuk memeriksa ulang PDF terbarunya sebelum approve.
        </div>
    @endif
</div>
