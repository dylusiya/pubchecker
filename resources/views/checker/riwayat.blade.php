@extends('layouts.app')

@section('title', 'Riwayat Pemeriksaan')
@section('topbar-title', 'Riwayat Pemeriksaan')

@section('content')
<div class="row">
  <div class="col-12">

    <div class="card mb-3">
      <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h4 class="card-title mb-1">
              <i class="mdi mdi-history text-primary me-2"></i>Riwayat Pemeriksaan
            </h4>
            <p class="text-muted mb-0 small">Daftar semua sesi pemeriksaan yang pernah dilakukan</p>
          </div>
          <a href="{{ route('checker.index') }}" class="btn btn-primary btn-sm">
            <i class="mdi mdi-plus me-1"></i> Pemeriksaan Baru
          </a>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-0">
        @if($sesiList->isEmpty())
          <div class="py-5 text-center text-muted">
            <i class="mdi mdi-history" style="font-size:48px; opacity:.3;"></i>
            <p class="mt-2">Belum ada riwayat pemeriksaan</p>
            <a href="{{ route('checker.index') }}" class="btn btn-primary btn-sm">Mulai Pemeriksaan</a>
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px;">
              <thead style="background:#f8f8ff;">
                <tr>
                  <th class="ps-3" style="width:50px;">#</th>
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
                  <td class="ps-3 text-muted">{{ $sesiList->firstItem() + $i }}</td>
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
                              <i class="mdi mdi-clipboard-check-outline"></i> {{ $hasil->ditinjau_count }}/{{ $hasil->detail_count }}
                            </small>
                            @if($hasil->hasPdf())
                              <a href="{{ route('checker.hasil.tinjau', $hasil) }}"
                                 class="btn btn-sm {{ $selesai ? 'btn-outline-success' : 'btn-primary' }} py-0 px-2 text-nowrap">
                                <i class="mdi mdi-{{ $selesai ? 'check' : 'play' }}"></i>
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
                    <span class="badge bg-label-primary">{{ $sesi->total_file }}</span>
                  </td>
                  <td class="text-center">
                    @if($sesi->total_ok > 0)
                      <span class="badge" style="background:rgba(113,221,55,.12); color:#429d20;">{{ $sesi->total_ok }}</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if($sesi->total_warn > 0)
                      <span class="badge" style="background:rgba(255,171,0,.12); color:#b37800;">{{ $sesi->total_warn }}</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if($sesi->total_err > 0)
                      <span class="badge" style="background:rgba(255,62,29,.12); color:#c73016;">{{ $sesi->total_err }}</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                      <a href="{{ route('checker.riwayat.detail', $sesi) }}"
                         class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                        <i class="mdi mdi-eye"></i>
                      </a>
                      <a href="{{ route('checker.riwayat.export', $sesi) }}"
                         class="btn btn-sm btn-outline-success" title="Export Excel">
                        <i class="mdi mdi-microsoft-excel"></i>
                      </a>
                      <form action="{{ route('checker.riwayat.delete', $sesi) }}" method="POST"
                            onsubmit="return confirm('Hapus sesi ini beserta semua hasilnya?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                          <i class="mdi mdi-delete-outline"></i>
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
@endsection