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
                  <th>UUID Sesi</th>
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
                  <td>
                    <code style="font-size:11px; color:#9155fd;">{{ $sesi->uuid }}</code>
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