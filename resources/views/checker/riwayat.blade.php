@extends('layouts.app')

@section('title', 'Riwayat Pemeriksaan')
@section('pretitle', 'Publication Checker')
@section('page-title', 'Riwayat Pemeriksaan')
@section('page-subtitle', 'Daftar semua sesi pemeriksaan yang pernah dilakukan')
@section('page-actions')
  <a href="{{ route('checker.index') }}" class="btn btn-primary">
    <i class="ti ti-plus"></i> Pemeriksaan Baru
  </a>
@endsection

@section('content')
<div class="row">
  <div class="col-12">

    {{-- Form hapus massal: checkbox di tabel terhubung lewat atribut form="bulkDeleteForm"
         (tiap baris sudah punya form hapus sendiri, form tidak boleh bersarang) --}}
    <form id="bulkDeleteForm" action="{{ route('checker.riwayat.delete-bulk') }}" method="POST" class="d-none">
      @csrf @method('DELETE')
      <input type="hidden" name="page" value="{{ $sesiList->currentPage() }}">
    </form>

    <div class="card">
      @if($sesiList->isNotEmpty())
      <div class="card-header" id="bulkBar">
        <div class="text-secondary small" id="bulkInfo">Centang sesi untuk menghapus beberapa sekaligus</div>
        <div class="card-actions">
          <button type="button" class="btn btn-danger btn-sm" id="btnBulkDelete" disabled onclick="konfirmasiHapus()">
            <i class="ti ti-trash"></i> <span>Hapus Terpilih (<span id="bulkCount">0</span>)</span>
          </button>
        </div>
      </div>
      @endif
      <div class="card-body p-0">
        @if($sesiList->isEmpty())
          <div class="py-5 text-center text-muted">
            <i class="ti ti-history" style="font-size:48px; opacity:.3;"></i>
            <p class="mt-2">Belum ada riwayat pemeriksaan</p>
            <a href="{{ route('checker.index') }}" class="btn btn-primary btn-sm">Mulai Pemeriksaan</a>
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px;">
              <thead style="background:#f8f8ff;">
                <tr>
                  <th class="ps-3 w-1">
                    <input type="checkbox" class="form-check-input m-0 align-middle" id="checkAll"
                           title="Pilih semua di halaman ini" aria-label="Pilih semua">
                  </th>
                  <th style="width:50px;">#</th>
                  <th>Tanggal</th>
                  <th style="min-width:280px;">Publikasi</th>
                  <th class="text-center">Total File</th>
                  <th class="text-center">✓ OK</th>
                  <th class="text-center">⚠ Perlu Dicek</th>
                  <th class="text-center">✗ Masalah</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @foreach($sesiList as $i => $sesi)
                <tr>
                  <td class="ps-3">
                    <input type="checkbox" class="form-check-input m-0 align-middle check-sesi" form="bulkDeleteForm"
                           name="ids[]" value="{{ $sesi->id }}" aria-label="Pilih sesi #{{ $sesi->id }}">
                  </td>
                  <td class="text-muted">{{ $sesiList->firstItem() + $i }}</td>
                  <td>
                    <div style="font-size:13px; color:#566a7f; font-weight:500;">
                      {{ $sesi->created_at->format('d M Y') }}
                    </div>
                    <small class="text-muted">{{ $sesi->created_at->format('H:i') }}</small>
                  </td>
                  <td style="white-space:normal; max-width:420px;">
                    @forelse($sesi->hasilPemeriksaan as $hasil)
                      @php $selesai = $hasil->detail_count > 0 && $hasil->ditinjau_count >= $hasil->detail_count; @endphp
                      <div class="{{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
                        <a href="{{ route('checker.riwayat.detail', $sesi) }}#pub-{{ $hasil->id }}"
                           class="fw-semibold text-dark text-decoration-none d-block" style="line-height:1.35;">
                          {{ $hasil->judul }}
                        </a>
                        <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                          @if($hasil->sumber)
                            <span class="badge {{ $hasil->sumber === 'BPS' ? 'bg-info text-dark' : 'bg-secondary' }} fw-normal">{{ $hasil->sumber }}</span>
                          @endif
                          @if($hasil->total_halaman)
                            <small class="text-muted">{{ $hasil->total_halaman }} hal.</small>
                          @endif
                          @if($hasil->detail_count > 0)
                            <small class="text-muted" title="Kriteria yang sudah ditinjau manual">
                              <i class="ti ti-clipboard-check"></i> {{ $hasil->ditinjau_count }}/{{ $hasil->detail_count }}
                            </small>
                            @if($hasil->hasPdf())
                              <a href="{{ route('checker.hasil.tinjau', $hasil) }}"
                                 class="btn btn-sm {{ $selesai ? 'btn-outline-success' : 'btn-primary' }} py-0 px-2 text-nowrap">
                                <i class="ti ti-{{ $selesai ? 'check' : 'player-play' }}"></i>
                                {{ $selesai ? 'Selesai' : ($hasil->ditinjau_count ? 'Lanjutkan' : 'Mulai Tinjauan') }}
                              </a>
                            @else
                              <small class="text-muted fst-italic" title="Pemeriksaan lama — file PDF tidak disimpan">PDF tidak tersimpan</small>
                            @endif
                          @endif
                        </div>
                      </div>
                    @empty
                      <span class="text-muted">—</span>
                    @endforelse
                  </td>
                  <td class="text-center">
                    <span class="badge bg-blue-lt">{{ $sesi->total_file }}</span>
                  </td>
                  <td class="text-center">
                    @if($sesi->total_ok > 0)
                      <span class="badge bg-green-lt">{{ $sesi->total_ok }}</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if($sesi->total_warn > 0)
                      <span class="badge bg-yellow-lt">{{ $sesi->total_warn }}</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if($sesi->total_err > 0)
                      <span class="badge bg-red-lt">{{ $sesi->total_err }}</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                      <a href="{{ route('checker.riwayat.detail', $sesi) }}"
                         class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                        <i class="ti ti-eye"></i>
                      </a>
                      <a href="{{ route('checker.riwayat.export', $sesi) }}"
                         class="btn btn-sm btn-outline-success" title="Export Excel">
                        <i class="ti ti-file-spreadsheet"></i>
                      </a>
                      <form action="{{ route('checker.riwayat.delete', $sesi) }}" method="POST"
                            onsubmit="return confirm('Hapus sesi ini beserta semua hasilnya?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                          <i class="ti ti-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="px-3 py-3">
            {{ $sesiList->links() }}
          </div>
        @endif
      </div>
    </div>

  </div>
</div>

{{-- Konfirmasi hapus massal --}}
<div class="modal modal-blur fade" id="modalHapus" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      <div class="modal-status bg-danger"></div>
      <div class="modal-body text-center py-4">
        <i class="ti ti-alert-triangle text-danger mb-2" style="font-size:2.5rem;"></i>
        <h3>Hapus <span id="modalHapusCount">0</span> sesi?</h3>
        <div class="text-secondary">
          Semua hasil pemeriksaan, catatan tinjauan, dan file PDF upload dalam sesi tersebut ikut terhapus
          dan tidak bisa dikembalikan.
        </div>
      </div>
      <div class="modal-footer">
        <div class="w-100">
          <div class="row">
            <div class="col"><button type="button" class="btn w-100" data-bs-dismiss="modal">Batal</button></div>
            <div class="col">
              <button type="submit" form="bulkDeleteForm" class="btn btn-danger w-100">Hapus</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const all    = document.getElementById('checkAll');
  const boxes  = () => [...document.querySelectorAll('.check-sesi')];
  const update = () => {
    const n = boxes().filter(b => b.checked).length;
    document.getElementById('bulkCount').textContent = n;
    document.getElementById('btnBulkDelete').disabled = n === 0;
    document.getElementById('bulkInfo').textContent = n
      ? `${n} sesi dipilih`
      : 'Centang sesi untuk menghapus beberapa sekaligus';
    if (all) {
      all.checked       = n > 0 && n === boxes().length;
      all.indeterminate = n > 0 && n < boxes().length;
    }
    boxes().forEach(b => b.closest('tr').classList.toggle('table-active', b.checked));
  };

  all?.addEventListener('change', () => { boxes().forEach(b => { b.checked = all.checked; }); update(); });
  boxes().forEach(b => b.addEventListener('change', update));

  window.konfirmasiHapus = () => {
    document.getElementById('modalHapusCount').textContent = boxes().filter(b => b.checked).length;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalHapus')).show();
  };
})();
</script>
@endpush