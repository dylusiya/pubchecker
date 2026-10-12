@extends('layouts.app')

@section('title', 'Detail Sesi #' . $sesi->id)
@section('pretitle', 'Riwayat Pemeriksaan')
@section('page-title', 'Detail Sesi #' . $sesi->id)
@section('page-subtitle')
    <code>{{ $sesi->uuid }}</code> · {{ $sesi->created_at->format('d M Y, H:i') }}
@endsection
@php
    // Tombol kirim: koneksi SIPOTRET diatur & ada hasil Import API BPS yang punya temuan Tidak Sesuai
    $sipotretAktif = (bool) config('database.connections.sipotret.database');
    $bisaKirim     = fn($h) => $sipotretAktif && $h->pdf_url
        && ($h->detail->contains('status', \App\Models\DetailPemeriksaan::STATUS_TIDAK_SESUAI) || $h->catatanTambahan->isNotEmpty());
    $kirimSipotret = $sesi->hasilPemeriksaan->contains($bisaKirim);

    // Salin ketidaksesuaian/catatan ke publikasi lain dalam sesi (butuh ≥ 2 publikasi)
    $bisaSalin = $sesi->hasilPemeriksaan->count() > 1;
    $petaSalin = $bisaSalin ? $sesi->hasilPemeriksaan->map(fn($h) => [
        'id'       => $h->id,
        'judul'    => $h->judul,
        // status & keterangan per kode kriteria
        'kriteria' => $h->detail->mapWithKeys(fn($d) => [$d->kriteria_id => [$d->status, $d->keterangan, (bool) $d->ditinjau_at]]),
        // kunci catatan tambahan yang sudah ada (item + keterangan) supaya tidak dobel
        'catatan'  => $h->catatanTambahan->map(fn($c) => mb_strtolower(trim($c->item)) . '|' . mb_strtolower(trim((string) $c->keterangan)))->values(),
    ])->values() : collect();
@endphp
@section('page-actions')
    <a href="{{ route('checker.riwayat') }}" class="btn">
        <i class="ti ti-arrow-left"></i> Kembali
    </a>
    @if($sesi->hasilPemeriksaan->filter(fn($h) => $h->hasPdf())->count() > 1)
        <a href="{{ route('checker.riwayat.tinjau', $sesi) }}" class="btn btn-primary"
           title="Buka semua PDF di sesi ini, satu tab per publikasi">
            <i class="ti ti-player-play"></i> Tinjau Semua
        </a>
    @endif
    @if($sesi->hasilPemeriksaan->filter($bisaKirim)->count() > 1)
        <button type="button" class="btn btn-danger" onclick="Sipotret.buka()"
                title="Kirim kriteria berstatus Tidak Sesuai semua publikasi di sesi ini ke menu Pasca Rilis SIPOTRET">
            <i class="ti ti-send"></i> Kirim Semua ke SIPOTRET
        </button>
    @endif
    <a href="{{ route('checker.riwayat.export', $sesi) }}" class="btn btn-success">
        <i class="ti ti-file-spreadsheet"></i> Export Excel
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Stat Cards --}}
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg-3"><x-stat label="Total Publikasi"   :value="$sesi->total_file" icon="files"          color="blue"   sub="File diperiksa" /></div>
            <div class="col-sm-6 col-lg-3"><x-stat label="Semua Kriteria OK" :value="$sesi->total_ok"   icon="circle-check"   color="green"  sub="Tidak ada masalah" /></div>
            <div class="col-sm-6 col-lg-3"><x-stat label="Perlu Dicek"       :value="$sesi->total_warn" icon="alert-triangle" color="yellow" sub="Ada catatan" /></div>
            <div class="col-sm-6 col-lg-3"><x-stat label="Ada Masalah"       :value="$sesi->total_err"  icon="circle-x"       color="red"    sub="Kriteria tidak terpenuhi" /></div>
        </div>

        {{-- Detail per file --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Hasil per Publikasi</h3>
            </div>
            <div class="card-body">

                @forelse($sesi->hasilPemeriksaan as $hasil)
                <div class="border rounded mb-2" id="pub-{{ $hasil->id }}">

                    {{-- Row header --}}
                    <div class="d-flex align-items-center p-3 gap-2 bg-white rounded result-row-header"
                         onclick="togglePub({{ $hasil->id }})">
                        <i class="ti {{ $hasil->status_akhir === 'masalah' ? 'ti-circle-x text-danger' : ($hasil->status_akhir === 'perlu_dicek' ? 'ti-alert-triangle text-warning' : 'ti-circle-check text-success') }}"
                           style="font-size:18px; flex-shrink:0;"></i>

                        <span class="flex-grow-1 fw-semibold small text-truncate"
                              title="{{ $hasil->nama_file }}">{{ $hasil->judul }}</span>

                        <div class="d-flex gap-1 flex-shrink-0">
                            @if($hasil->total_ok)
                                <span class="badge bg-success">{{ $hasil->total_ok }} OK</span>
                            @endif
                            @if($hasil->total_perlu_dicek)
                                <span class="badge bg-warning text-dark">{{ $hasil->total_perlu_dicek }} Dicek</span>
                            @endif
                            @if($hasil->total_tidak_ada)
                                <span class="badge bg-danger">{{ $hasil->total_tidak_ada }} Masalah</span>
                            @endif
                            @if($hasil->total_tdk_diperiksa)
                                <span class="badge bg-secondary">{{ $hasil->total_tdk_diperiksa }} Skip</span>
                            @endif
                        </div>

                        @php
                            $grupKategori   = $hasil->detail->groupBy('kategori');
                            $kategoriSelesai = $grupKategori->filter(fn($g) => $g->every(fn($d) => $d->ditinjau_at))->count();
                            $tinjauSelesai  = $grupKategori->isNotEmpty() && $kategoriSelesai === $grupKategori->count();
                        @endphp
                        @if($grupKategori->isNotEmpty())
                        <span class="badge {{ $tinjauSelesai ? 'bg-success' : 'bg-light text-dark border' }} flex-shrink-0 ms-1"
                              title="Kategori yang sudah ditinjau manual">
                            <i class="ti ti-clipboard-check"></i> {{ $kategoriSelesai }}/{{ $grupKategori->count() }}
                        </span>
                        @if($hasil->hasPdf())
                        <a href="{{ route('checker.hasil.tinjau', $hasil) }}" onclick="event.stopPropagation()"
                           class="btn btn-sm {{ $tinjauSelesai ? 'btn-outline-success' : 'btn-primary' }} py-0 px-2 flex-shrink-0 ms-1 text-nowrap">
                            <i class="ti ti-{{ $tinjauSelesai ? 'pencil' : 'player-play' }}"></i>
                            {{ $tinjauSelesai ? 'Tinjau Ulang' : ($kategoriSelesai ? 'Lanjutkan' : 'Mulai Tinjauan') }}
                        </a>
                        @endif
                        @endif
                        @if($bisaKirim($hasil))
                            <button type="button" onclick="event.stopPropagation(); Sipotret.buka({{ $hasil->id }})"
                                    class="btn btn-sm {{ $hasil->sipotret_terkirim_at ? 'btn-outline-danger' : 'btn-danger' }} py-0 px-2 flex-shrink-0 ms-1 text-nowrap"
                                    title="{{ $hasil->sipotret_terkirim_at
                                        ? 'Terakhir dikirim ke SIPOTRET ' . $hasil->sipotret_terkirim_at->format('d M Y H:i') . ' — kirim lagi (item yang sudah ada dilewati)'
                                        : 'Kirim temuan Tidak Sesuai publikasi ini ke SIPOTRET' }}">
                                <i class="ti ti-{{ $hasil->sipotret_terkirim_at ? 'circle-check' : 'send' }}"></i>
                                {{ $hasil->sipotret_terkirim_at ? 'Terkirim' : 'Kirim' }}
                            </button>
                        @elseif($hasil->sipotret_terkirim_at)
                            <span class="badge bg-red-lt flex-shrink-0 ms-1"
                                  title="Temuan Tidak Sesuai terakhir dikirim ke SIPOTRET {{ $hasil->sipotret_terkirim_at->format('d M Y H:i') }}">
                                <i class="ti ti-send"></i> SIPOTRET
                            </span>
                        @endif
                        <small class="text-muted flex-shrink-0 ms-1">
                            {{ $hasil->total_halaman ?? '?' }} hal.
                        </small>
                        <i class="ti ti-chevron-down text-muted ms-1 pub-chevron-{{ $hasil->id }}"
                           style="transition: transform .2s; flex-shrink:0;"></i>
                    </div>

                    {{-- Detail body --}}
                    <div id="detail-{{ $hasil->id }}" style="display:none;"
                         class="p-3 border-top bg-light rounded-bottom">

                        @if($hasil->error_msg)
                            <div class="alert alert-danger py-2 small mb-3">
                                <i class="ti ti-alert-triangle me-1"></i>{{ $hasil->error_msg }}
                            </div>
                        @endif

                        @if($grupKategori->isNotEmpty())
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <small class="text-muted">
                                Tinjauan manual: {{ $kategoriSelesai }} dari {{ $grupKategori->count() }} kategori selesai
                            </small>
                            @if($hasil->hasPdf())
                                <a href="{{ route('checker.hasil.tinjau', $hasil) }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-{{ $tinjauSelesai ? 'pencil' : 'player-play' }} me-1"></i>
                                    {{ $tinjauSelesai ? 'Tinjau Ulang' : ($kategoriSelesai ? 'Lanjutkan Tinjauan' : 'Mulai Tinjauan') }}
                                </a>
                            @else
                                <small class="text-muted fst-italic">PDF tidak tersimpan — tinjauan tidak bisa dilanjutkan</small>
                            @endif
                        </div>
                        @endif

                        {{-- Ringkasan: hanya kriteria Tidak Sesuai (verifikasi petugas) beserta keterangannya --}}
                        @php $tidakSesuai = $hasil->detail->where('status', \App\Models\DetailPemeriksaan::STATUS_TIDAK_SESUAI); @endphp
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-bordered mb-0 bg-white"
                                   style="font-size:12px;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:70px;">Kode</th>
                                        <th style="width:150px;">Kategori</th>
                                        <th>Kriteria Tidak Sesuai</th>
                                        <th>Keterangan</th>
                                        @if($bisaSalin)<th style="width:70px;"></th>@endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tidakSesuai as $d)
                                    <tr>
                                        <td class="font-monospace text-muted" style="font-size:11px;">
                                            {{ $d->kriteria_id }}
                                        </td>
                                        <td class="text-muted small">{{ $d->kategori }}</td>
                                        <td style="font-size:12px;">{{ $d->deskripsi }}</td>
                                        <td class="small text-danger">{{ $d->keterangan ?: '—' }}</td>
                                        @if($bisaSalin)
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-ghost-primary py-0 px-1"
                                                    title="Salin ketidaksesuaian ini ke publikasi lain dalam sesi"
                                                    data-salin-ts="{{ json_encode(['hasil' => $hasil->id, 'kode' => $d->kriteria_id, 'kategori' => $d->kategori, 'deskripsi' => $d->deskripsi, 'keterangan' => $d->keterangan]) }}">
                                                <i class="ti ti-copy"></i> Salin
                                            </button>
                                        </td>
                                        @endif
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="{{ $bisaSalin ? 5 : 4 }}" class="text-center text-secondary py-3">Tidak ada kriteria Tidak Sesuai.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Catatan tambahan petugas (di luar daftar kriteria) --}}
                        @if($hasil->catatanTambahan->isNotEmpty())
                        <div class="fw-semibold small mt-3 mb-1">
                            <i class="ti ti-notes text-warning me-1"></i>Catatan Tambahan ({{ $hasil->catatanTambahan->count() }})
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 bg-white" style="font-size:12px;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:160px;">Kategori</th>
                                        <th style="width:200px;">Item</th>
                                        <th style="width:90px;">Level</th>
                                        <th style="width:60px;">Hal.</th>
                                        <th>Keterangan</th>
                                        <th style="width:130px;">Oleh</th>
                                        @if($bisaSalin)<th style="width:70px;"></th>@endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($hasil->catatanTambahan as $c)
                                    <tr>
                                        <td class="text-muted">{{ $c->kategori ?? "—" }}</td>
                                        <td class="fw-semibold">{{ $c->item }}</td>
                                        <td><span class="badge {{ ['minor' => 'bg-azure-lt', 'moderate' => 'bg-orange-lt', 'major' => 'bg-red-lt'][$c->flag_level] ?? 'bg-secondary-lt' }}">{{ $c->flag_level }}</span></td>
                                        <td>{{ $c->halaman ?? '—' }}</td>
                                        <td>{{ $c->keterangan }}</td>
                                        <td class="text-muted">{{ $c->dibuat_oleh ?? '—' }}</td>
                                        @if($bisaSalin)
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-ghost-primary py-0 px-1"
                                                    title="Salin catatan ini ke publikasi lain dalam sesi"
                                                    data-salin-cat="{{ json_encode(['hasil' => $hasil->id, 'kategori' => $c->kategori, 'item' => $c->item, 'flag_level' => $c->flag_level, 'keterangan' => $c->keterangan]) }}">
                                                <i class="ti ti-copy"></i> Salin
                                            </button>
                                        </td>
                                        @endif
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>

                </div>
                @empty
                <div class="text-center py-5 text-muted">
                    <i class="ti ti-file-unknown d-block mb-2"
                       style="font-size:40px; opacity:.3;"></i>
                    Tidak ada data hasil pemeriksaan
                </div>
                @endforelse

            </div>
        </div>

    </div>
</div>

@if($bisaSalin)
{{-- Salin ketidaksesuaian / catatan tambahan ke publikasi lain dalam sesi --}}
<div class="modal modal-blur fade" id="modalSalin" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-copy me-1"></i> <span id="slJudul">Salin ke publikasi lain</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="border rounded p-2 bg-light small mb-3" id="slSumber"></div>
        <div class="mb-3">
          <label class="form-label" for="slKet">Keterangan <span class="text-danger">*</span></label>
          <textarea class="form-control" id="slKet" rows="2" maxlength="2000"></textarea>
          <small class="text-secondary">Bisa disesuaikan sebelum disalin (mis. nomor halaman/tabel berbeda).</small>
        </div>
        <label class="form-label">Salin ke</label>
        <div id="slTujuan" class="list-group"></div>
      </div>
      <div class="modal-footer">
        <span class="me-auto small" id="slInfo"></span>
        <button type="button" class="btn" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="slSimpan" onclick="Salin.simpan()">
          <i class="ti ti-copy"></i> <span id="slSimpanLbl">Salin</span>
        </button>
      </div>
    </div>
  </div>
</div>
@endif

@if($kirimSipotret)
{{-- Kirim temuan Tidak Sesuai ke SIPOTRET (Pasca Rilis) --}}
<div class="modal modal-blur fade" id="modalSipotret" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-send me-1"></i> Kirim ke SIPOTRET — Pasca Rilis <span class="text-secondary fw-normal" id="spJudul"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 align-items-end mb-3">
          <div class="col-auto">
            <label class="form-label mb-1" for="spBulan">Periode pemeriksaan</label>
            <select class="form-select" id="spBulan">
              @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $b)
                <option value="{{ $i + 1 }}" @selected($i + 1 === (int) date('n'))>{{ $b }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-auto">
            <input type="number" class="form-control" id="spTahun" value="{{ date('Y') }}" min="2000" max="2100" style="width:100px;">
          </div>
          <div class="col">
            <small class="text-secondary">
              Hanya kriteria berstatus <strong>Tidak Sesuai</strong> beserta keterangannya. Item yang sudah tercatat
              untuk publikasi & periode yang sama di SIPOTRET dilewati.
            </small>
          </div>
        </div>
        <div id="spBody"></div>
      </div>
      <div class="modal-footer">
        <span class="me-auto small text-secondary" id="spInfo"></span>
        <button type="button" class="btn" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-danger" id="spKirim" disabled onclick="Sipotret.kirim()">
          <i class="ti ti-send"></i> <span id="spKirimLabel">Kirim</span>
        </button>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
@if($bisaSalin)
/**
 * Salin satu ketidaksesuaian (kriteria Tidak Sesuai + keterangan) atau catatan tambahan ke
 * publikasi lain dalam sesi. Ketidaksesuaian disimpan lewat endpoint simpan per kategori yang
 * sama dengan panel tinjauan; catatan tambahan lewat endpoint catatan.
 */
const Salin = (() => {
    const PETA      = @json($petaSalin);
    const URL_TS    = @json(route('checker.hasil.review_kategori', '__ID__'));
    const URL_CAT   = @json(route('checker.hasil.catatan.store', '__ID__'));
    const CSRF      = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const kunci = (item, ket) => `${String(item ?? '').trim().toLowerCase()}|${String(ket ?? '').trim().toLowerCase()}`;
    const LABEL = { 'OK': ['Sesuai', 'bg-green-lt'], 'TIDAK SESUAI': ['Tidak Sesuai', 'bg-red-lt'], 'TIDAK DIPERIKSA': ['Skip', 'bg-secondary-lt'],
                    'PERLU DICEK': ['Perlu Dicek', 'bg-yellow-lt'], 'TIDAK ADA': ['Tidak Ada', 'bg-orange-lt'] };
    let sumber = null, jenis = null, berubah = false;

    function buka(j, data) {
        jenis = j; sumber = data;
        $('slJudul').textContent = j === 'ts' ? 'Salin ketidaksesuaian ke publikasi lain' : 'Salin catatan tambahan ke publikasi lain';
        $('slSumber').innerHTML = j === 'ts'
            ? `<span class="font-monospace text-secondary">${esc(data.kode)}</span> · <span class="text-secondary">${esc(data.kategori)}</span><div>${esc(data.deskripsi)}</div>`
            : `<span class="text-secondary">${esc(data.kategori || 'Tanpa kategori')}</span> · <strong>${esc(data.item)}</strong> <span class="badge bg-secondary-lt">${esc(data.flag_level)}</span>`;
        $('slKet').value = data.keterangan || '';
        $('slInfo').textContent = '';
        renderTujuan();
        bootstrap.Modal.getOrCreateInstance($('modalSalin')).show();
    }

    function renderTujuan() {
        const ket = $('slKet').value.trim();
        $('slTujuan').innerHTML = PETA.filter(h => h.id !== sumber.hasil).map(h => {
            let catatan = '', bisa = true;
            if (jenis === 'ts') {
                const k = h.kriteria[sumber.kode];
                if (!k) { bisa = false; catatan = 'Kriteria tidak ada di publikasi ini'; }
                else {
                    const [st, ketLama, ditinjau] = k;
                    const [lbl, cls] = LABEL[st] || [st, 'bg-secondary-lt'];
                    if (st === 'TIDAK SESUAI' && (ketLama || '').trim() === ket) { bisa = false; catatan = 'Sudah sama'; }
                    catatan = `<span class="badge ${cls}">${ditinjau ? '' : 'Otomatis: '}${lbl}</span>` +
                              (st === 'TIDAK SESUAI' && ketLama ? ` <span class="text-secondary">${esc(ketLama)}</span>` : '') +
                              (!bisa ? ' · <span class="text-secondary">sudah sama</span>' : '');
                }
            } else if (h.catatan.includes(kunci(sumber.item, ket))) {
                bisa = false; catatan = '<span class="text-secondary">Catatan yang sama sudah ada</span>';
            }
            return `
                <label class="list-group-item d-flex align-items-center gap-2 ${bisa ? '' : 'text-secondary'}">
                    <input type="checkbox" class="form-check-input m-0 sl-pilih" value="${h.id}" ${bisa ? 'checked' : 'disabled'}>
                    <div class="flex-fill" style="min-width:0;">
                        <div class="fw-semibold text-truncate">${esc(h.judul)}</div>
                        ${catatan ? `<div class="small">${catatan}</div>` : ''}
                    </div>
                </label>`;
        }).join('');
        hitung();
    }

    function hitung() {
        const n = document.querySelectorAll('.sl-pilih:checked').length;
        $('slSimpan').disabled = !n;
        $('slSimpanLbl').textContent = n ? `Salin ke ${n} publikasi` : 'Salin';
    }

    async function simpan() {
        const ket = $('slKet').value.trim();
        if (!ket) { $('slInfo').className = 'me-auto small text-danger'; $('slInfo').textContent = 'Keterangan wajib diisi.'; return; }
        const ids = [...document.querySelectorAll('.sl-pilih:checked')].map(cb => +cb.value);
        if (!ids.length) return;

        $('slSimpan').disabled = true;
        $('slInfo').className = 'me-auto small text-secondary';
        $('slInfo').textContent = 'Menyimpan…';
        const hasil = await Promise.all(ids.map(async id => {
            try {
                const res = jenis === 'ts'
                    ? await fetch(URL_TS.replace('__ID__', id), {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ items: [{ kriteria_id: sumber.kode, status: 'TIDAK SESUAI', keterangan: ket }] }),
                    })
                    : await fetch(URL_CAT.replace('__ID__', id), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ kategori: sumber.kategori, item: sumber.item, flag_level: sumber.flag_level, keterangan: ket }),
                    });
                if (!res.ok) {
                    const d = await res.json().catch(() => ({}));
                    throw new Error(d.message || `Server membalas ${res.status}`);
                }
                // perbarui peta supaya daftar tujuan langsung menunjukkan status baru
                const h = PETA.find(x => x.id === id);
                if (jenis === 'ts') h.kriteria[sumber.kode] = ['TIDAK SESUAI', ket, true];
                else h.catatan.push(kunci(sumber.item, ket));
                return null;
            } catch (e) {
                return `${PETA.find(x => x.id === id)?.judul}: ${e.message}`;
            }
        }));
        const gagal = hasil.filter(Boolean);
        const ok = ids.length - gagal.length;
        if (ok) berubah = true;
        renderTujuan();
        $('slInfo').className = 'me-auto small ' + (gagal.length ? 'text-danger' : 'text-success');
        $('slInfo').textContent = (ok ? `Tersalin ke ${ok} publikasi.` : '') + (gagal.length ? ` Gagal: ${gagal.join('; ')}` : '');
    }

    document.addEventListener('click', e => {
        const ts  = e.target.closest('[data-salin-ts]');
        const cat = e.target.closest('[data-salin-cat]');
        if (ts)  buka('ts',  JSON.parse(ts.dataset.salinTs));
        if (cat) buka('cat', JSON.parse(cat.dataset.salinCat));
    });

    document.addEventListener('DOMContentLoaded', () => {
        $('slTujuan').addEventListener('change', e => { if (e.target.matches('.sl-pilih')) hitung(); });
        $('slKet').addEventListener('input', () => {
            // status "sudah sama" bergantung keterangan — hitung ulang; yang sengaja di-uncheck tetap tidak dicentang
            const dilepas = new Set([...document.querySelectorAll('.sl-pilih:not(:disabled):not(:checked)')].map(cb => cb.value));
            renderTujuan();
            document.querySelectorAll('.sl-pilih:not(:disabled)').forEach(cb => { cb.checked = !dilepas.has(cb.value); });
            hitung();
        });
        // muat ulang setelah ada salinan; publikasi sumber dibuka kembali
        $('modalSalin').addEventListener('hidden.bs.modal', () => {
            if (!berubah) return;
            try { sessionStorage.setItem('riwayat.bukaPub', sumber.hasil); } catch { /* abaikan */ }
            location.reload();
        });
        try {
            const id = sessionStorage.getItem('riwayat.bukaPub');
            if (id && document.getElementById('detail-' + id)) {
                sessionStorage.removeItem('riwayat.bukaPub');
                togglePub(+id);
                document.getElementById('pub-' + id)?.scrollIntoView({ block: 'start' });
            }
        } catch { /* abaikan */ }
    });

    return { simpan };
})();
@endif

@if($kirimSipotret)
const Sipotret = (() => {
    const URL_PREVIEW = @json(route('checker.riwayat.sipotret', $sesi));
    const URL_KIRIM   = @json(route('checker.riwayat.sipotret.kirim', $sesi));
    const CSRF        = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const $ = id => document.getElementById(id);
    let data = null, terkirim = false;
    let hanya = null; // id hasil bila dibuka dari tombol per publikasi; null = semua publikasi di sesi

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const periode = () => ({ bulan: $('spBulan').value, tahun: $('spTahun').value });
    const BULAN = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    const STATUS = {
        baru:          ['bg-green-lt',     'Akan dikirim'],
        sudah_ada:     ['bg-secondary-lt', 'Sudah ada di SIPOTRET'],
        tidak_dikenal: ['bg-yellow-lt',    'Item tidak dikenal di SIPOTRET'],
    };

    /** @param {number} [hasilId] hanya publikasi ini; tanpa argumen = semua publikasi di sesi */
    function buka(hasilId) {
        hanya = Number.isInteger(hasilId) ? hasilId : null;
        $('spJudul').textContent = hanya ? '' : '· semua publikasi di sesi ini';
        bootstrap.Modal.getOrCreateInstance($('modalSipotret')).show();
        muat();
    }

    async function muat() {
        $('spBody').innerHTML = '<div class="text-center text-secondary py-4"><i class="ti ti-loader-2 icon-spin"></i> Membaca SIPOTRET…</div>';
        $('spKirim').disabled = true;
        $('spInfo').textContent = '';
        try {
            const res = await fetch(URL_PREVIEW + '?' + new URLSearchParams(periode()), { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (!res.ok || json.error) throw new Error(json.error || json.message || 'Server membalas ' + res.status);
            data = json;
            render();
        } catch (e) {
            $('spBody').innerHTML = `<div class="alert alert-danger">${esc(e.message)}</div>`;
        }
    }

    function render() {
        // hanya publikasi yang punya temuan Tidak Sesuai atau tidak bisa dikirim karena alasan selain "tidak ada temuan"
        const list = data.hasil
            .filter(h => hanya === null || h.id === hanya)
            .filter(h => h.items.length || (h.alasan && !h.alasan.startsWith('Tidak ada kriteria')));
        if (!list.length) {
            $('spBody').innerHTML = '<div class="text-center text-secondary py-4">Tidak ada kriteria Tidak Sesuai di sesi ini.</div>';
            return;
        }
        $('spBody').innerHTML = list.map(h => {
            const baru = h.items.filter(i => i.status === 'baru').length;
            const bisa = h.bisa && baru > 0;
            const terdaftar = h.periode.length
                ? ` · terdaftar di periode ${h.periode.map(p => `<a href="#" data-periode="${p.bulan}/${p.tahun}">${BULAN[p.bulan - 1]} ${p.tahun}</a>`).join(', ')}`
                : '';
            const tujuan = !h.bisa ? `<span class="text-danger">${esc(h.alasan)}</span>`
                : (h.pasca_ada
                    ? `Publikasi ditemukan di SIPOTRET${h.cocok_judul ? ' (dicocokkan lewat judul)' : ''}` +
                      (h.history_ada ? ' · sudah ada di periode ini' : ' · <span class="text-warning">akan ditambahkan ke periode baru ini</span>') + terdaftar
                    : 'Publikasi belum ada di SIPOTRET — akan dibuat beserta periodenya');
            return `
            <div class="card mb-3">
                <div class="card-header py-2">
                    <label class="form-check m-0 d-flex align-items-center gap-2 w-100">
                        <input type="checkbox" class="form-check-input sp-pilih m-0" value="${h.id}" ${bisa ? 'checked' : 'disabled'}>
                        <div class="flex-fill" style="min-width:0;">
                            <div class="fw-semibold text-truncate">${esc(h.judul)}</div>
                            <div class="small text-secondary">${tujuan}${h.terkirim_at ? ` · terakhir dikirim ${esc(h.terkirim_at)}` : ''}</div>
                        </div>
                        <span class="badge bg-green-lt">${baru} baru</span>
                    </label>
                </div>
                ${h.items.length ? `
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter card-table mb-0 small">
                        <thead><tr><th style="width:70px">Kode</th><th>Item</th><th>Keterangan</th><th style="width:80px">Level</th><th style="width:170px">Status</th></tr></thead>
                        <tbody>${h.items.map(i => `
                            <tr>
                                <td class="font-monospace text-secondary">${esc(i.kode)}</td>
                                <td><div>${esc(i.nama)}</div><div class="text-secondary">${esc(i.kategori)}</div></td>
                                <td>${esc(i.keterangan)}</td>
                                <td>${esc(i.flag ?? '-')}</td>
                                <td><span class="badge ${STATUS[i.status][0]}">${STATUS[i.status][1]}</span></td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>` : ''}
            </div>`;
        }).join('');
        hitung();
    }

    function hitung() {
        const pilih = [...document.querySelectorAll('.sp-pilih:checked')];
        const item = pilih.reduce((n, cb) => n + data.hasil.find(h => h.id == cb.value).items.filter(i => i.status === 'baru').length, 0);
        $('spKirim').disabled = !pilih.length;
        $('spKirimLabel').textContent = pilih.length ? `Kirim ${item} temuan (${pilih.length} publikasi)` : 'Kirim';
    }

    async function kirim() {
        const ids = [...document.querySelectorAll('.sp-pilih:checked')].map(cb => cb.value);
        if (!ids.length) return;
        $('spKirim').disabled = true;
        $('spKirimLabel').textContent = 'Mengirim…';
        try {
            const res = await fetch(URL_KIRIM, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ ...periode(), hasil_ids: ids }),
            });
            const json = await res.json();
            if (!res.ok || json.error) throw new Error(json.error || json.message || 'Server membalas ' + res.status);
            terkirim = true;
            const ok = json.laporan.filter(l => !l.error);
            const gagal = json.laporan.filter(l => l.error);
            const tambah = ok.reduce((n, l) => n + l.ditambah, 0);
            await muat(); // tampilkan status terbaru (yang terkirim jadi "Sudah ada")
            $('spBody').insertAdjacentHTML('afterbegin', `
                <div class="alert ${gagal.length ? 'alert-warning' : 'alert-success'}">
                    ${tambah} temuan ditambahkan ke SIPOTRET dari ${ok.length} publikasi.
                    ${gagal.map(l => `<div class="text-danger small mt-1">${esc(l.judul)}: ${esc(l.error)}</div>`).join('')}
                </div>`);
        } catch (e) {
            $('spInfo').textContent = 'Gagal mengirim: ' + e.message;
            hitung();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        $('spBulan').addEventListener('change', muat);
        $('spTahun').addEventListener('change', muat);
        $('spBody').addEventListener('change', e => { if (e.target.matches('.sp-pilih')) hitung(); });
        // klik periode yang sudah terdaftar → pakai periode itu
        $('spBody').addEventListener('click', e => {
            const a = e.target.closest('[data-periode]');
            if (!a) return;
            e.preventDefault();
            const [b, t] = a.dataset.periode.split('/');
            $('spBulan').value = b; $('spTahun').value = t;
            muat();
        });
        // muat ulang halaman setelah ada kiriman supaya penanda "SIPOTRET" di daftar ikut tampil
        $('modalSipotret').addEventListener('hidden.bs.modal', () => { if (terkirim) location.reload(); });
    });

    return { buka, kirim };
})();
@endif

function togglePub(id) {
    const body  = document.getElementById('detail-' + id);
    const chev  = document.querySelector('.pub-chevron-' + id);
    const open  = body.style.display === 'none';
    body.style.display    = open ? 'block' : 'none';
    chev.style.transform  = open ? 'rotate(180deg)' : '';
}
</script>
@endpush