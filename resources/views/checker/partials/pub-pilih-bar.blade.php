{{--
    Bar pilihan publikasi (Publikasi Bermasalah & Tugas Saya), dipakai bersama js/periksa-terpilih.js.
    Variabel: $bisaApprove  boleh approve beberapa sekaligus
              $pemeriksa    daftar pemeriksa (admin) untuk penugasan; kosong = tanpa penugasan
              $periodePilih opsional ['bulan','tahun']: periode pemeriksaan bisa dipilih (Publikasi Bermasalah)
              $periodeInfo  teks penjelasan periode (opsional, HTML aman dari Blade pemanggil)
--}}
@php $bulanNama ??= ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']; @endphp
<div class="alert alert-info d-flex align-items-center flex-wrap gap-2 py-2 mb-3">
    @isset($periodePilih)
        {{-- Periode pemeriksaan ulang — hasilnya nanti dikirim ke SIPOTRET pada periode ini --}}
        <div class="d-flex align-items-center flex-wrap gap-2 w-100 pb-2 mb-1 border-bottom">
            <i class="ti ti-calendar"></i>
            <label class="small fw-semibold" for="periodeBulan">Periode pemeriksaan</label>
            <select id="periodeBulan" class="form-select form-select-sm w-auto">
                @foreach(range(1, 12) as $b)
                    <option value="{{ $b }}" @selected($b === $periodePilih['bulan'])>{{ $bulanNama[$b] }}</option>
                @endforeach
            </select>
            <input type="number" id="periodeTahun" class="form-control form-control-sm" style="width:90px;"
                   value="{{ $periodePilih['tahun'] }}" min="2000" max="2100">
            @isset($periodeInfo)<small class="text-secondary">{!! $periodeInfo !!}</small>@endisset
        </div>
    @endisset
    {{-- urutan kartu: bawaan (wilayah/judul) atau revisi terakhir (portal BPS / file perbaikan SIPOTRET) — PeriksaTerpilih.urutkan --}}
    <div class="d-flex align-items-center gap-1 w-100 pb-2 mb-1 border-bottom">
        <i class="ti ti-arrows-sort"></i>
        <label class="small fw-semibold" for="urutKartu">Urutkan</label>
        <select id="urutKartu" class="form-select form-select-sm w-auto" onchange="urutkanKartu(this.value)">
            <option value="">Bawaan (wilayah, judul)</option>
            <option value="revisi-baru">Revisi terakhir: terbaru dulu</option>
            <option value="revisi-lama">Revisi terakhir: terlama dulu</option>
        </select>
        <small class="text-secondary" id="urutInfo"></small>
    </div>
    <i class="ti ti-checkbox"></i>
    <span class="flex-fill small" id="selectionInfo"></span>
    <button type="button" class="btn btn-sm btn-ghost-primary" onclick="pilihSemua()">
        <i class="ti ti-checks"></i> <span>Pilih Semua</span>
    </button>
    <button type="button" class="btn btn-sm btn-ghost-secondary" id="btnClearSel" onclick="clearSelection()" hidden>
        <i class="ti ti-x"></i> <span>Batal Pilih</span>
    </button>
    @if($bisaApprove)
        {{-- Approve beberapa sekaligus: yang dicentang & statusnya menunggu approve --}}
        <form method="post" action="{{ route('dashboard.sipotret.approve') }}" id="formApprove" class="m-0">
            @csrf
            <button type="button" class="btn btn-sm btn-success" id="btnApproveSel" onclick="approveTerpilih()" hidden>
                <i class="ti ti-circle-check"></i> <span id="btnApproveSelLabel">Approve</span>
            </button>
        </form>
    @endif
    @if($pemeriksa)
        {{-- admin: tugaskan publikasi yang dicentang ke satu pemeriksa --}}
        <div class="d-flex align-items-center gap-1" id="tugasBar" hidden>
            <select id="tugasUser" class="form-select form-select-sm w-auto" aria-label="Pemeriksa">
                <option value="">Pilih pemeriksa…</option>
                @foreach($pemeriksa as $u)
                    <option value="{{ $u['id'] }}">{{ $u['nama'] }}{{ $u['role'] === 'admin' ? ' (admin)' : '' }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-sm btn-purple" onclick="tugaskanTerpilih()" title="Tugaskan publikasi yang dicentang ke pemeriksa ini">
                <i class="ti ti-user-check"></i> <span id="btnTugasLabel">Tugaskan</span>
            </button>
            <button type="button" class="btn btn-sm btn-ghost-secondary" onclick="tugaskanTerpilih(true)" title="Lepas penugasan publikasi yang dicentang">
                <i class="ti ti-user-off"></i>
            </button>
        </div>
    @endif
    @can('periksa')
        {{-- belum upload / belum approve: ketidaksesuaian bulan lalu langsung disalin ke periode pemeriksaan di SIPOTRET --}}
        <button type="button" class="btn btn-sm btn-outline-danger" id="btnSalinSel" onclick="salinTerpilih()" hidden
                title="Salin catatan SIPOTRET bulan sebelumnya ke periode pemeriksaan, untuk publikasi yang belum upload perbaikan atau belum di-approve (tanpa memeriksa PDF)">
            <i class="ti ti-copy"></i> <span id="btnSalinSelLabel">Salin bulan lalu</span>
        </button>
    @endcan
    <button type="button" class="btn btn-sm btn-primary" id="btnCheckSel" onclick="startCheck()" disabled @cannot('periksa') hidden @endcannot>
        <i class="ti ti-rosette-discount-check"></i> <span id="btnCheckSelLabel">Periksa Terpilih</span>
    </button>
</div>

@can('periksa')
{{-- Pratinjau & hasil "Salin bulan lalu" (PeriksaTerpilih.salinTerpilih) --}}
<div class="modal modal-blur fade" id="modalSalinLalu" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-copy me-1"></i> Salin ketidaksesuaian bulan lalu <span class="text-secondary fw-normal" id="slPeriode"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="small text-secondary mb-2">
          Untuk publikasi yang perbaikannya <strong>belum diunggah</strong> atau <strong>belum di-approve</strong>, catatan SIPOTRET
          periode sebelumnya disalin apa adanya ke periode pemeriksaan (seperti "Dari History" di SIPOTRET), tanpa memeriksa PDF.
          Yang sudah di-approve dilewati; catatan yang sudah ada di periode pemeriksaan tidak didobel.
          <strong>Keputusan pemeriksa</strong> di hasil pemeriksaan periode ini tetap dipakai: kriteria yang diputuskan
          Sesuai/Skip tidak disalin, yang diputuskan Tidak Sesuai disalin dengan keterangan pemeriksa.
        </div>
        <div id="slBody"></div>
      </div>
      <div class="modal-footer">
        <span class="me-auto small" id="slInfo"></span>
        <button type="button" class="btn" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-danger" id="slKirim" disabled onclick="salinTerpilih(true)">
          <i class="ti ti-send"></i> <span id="slKirimLabel">Salin &amp; kirim</span>
        </button>
      </div>
    </div>
  </div>
</div>
@endcan
