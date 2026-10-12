@extends('layouts.app')

@section('title', 'Kelola Kriteria Pemeriksaan')
@section('pretitle', 'Administrator')
@section('page-title', 'Kelola Kriteria Pemeriksaan')
@section('page-subtitle', $items->total() . ' kriteria terdaftar — urutan menentukan tampilan di hasil pemeriksaan')
@section('page-actions')
    <a href="{{ route('admin.kriteria.contoh.index') }}" class="btn">
        <i class="ti ti-photo"></i> Contoh per Kategori
    </a>
    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#importModal">
        <i class="ti ti-file-import"></i> Import
    </button>
    <a href="{{ route('admin.kriteria.create') }}" class="btn btn-primary">
        <i class="ti ti-plus"></i> Tambah Kriteria
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Filter & Search --}}
        <div class="card mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('admin.kriteria.index') }}"
                      class="d-flex gap-2 align-items-center flex-wrap">
                    <div class="input-group input-group-sm" style="width:260px;">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="ti ti-search text-muted"></i>
                        </span>
                        <input type="text" name="q" value="{{ $q }}"
                               class="form-control border-start-0 ps-0"
                               placeholder="Cari kode, kategori, deskripsi...">
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit">
                        <i class="ti ti-filter me-1"></i> Cari
                    </button>
                    @if($q)
                    <a href="{{ route('admin.kriteria.index') }}" class="btn btn-light btn-sm border">
                        <i class="ti ti-x me-1"></i> Reset
                    </a>
                    @endif
                </form>
            </div>
        </div>

        {{-- Tabel --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:13px;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;" class="text-center ps-3">#</th>
                                <th style="width:90px;">Kode</th>
                                <th style="width:180px;">Kategori</th>
                                <th style="min-width:200px; max-width:320px;">Deskripsi</th>
                                <th style="width:120px;">Tipe Cek</th>
                                <th style="width:90px;">Target</th>
                                <th style="width:110px;">Jika Gagal</th>
                                <th style="width:70px;" class="text-center">Aktif</th>
                                <th style="width:110px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                            <tr>
                                <td class="text-center text-muted ps-3">{{ $item->urutan }}</td>
                                <td>
                                    <code class="text-primary">{{ $item->kode }}</code>
                                </td>
                                <td class="text-muted small">{{ $item->kategori }}</td>
                                <td style="max-width:320px; white-space:normal;" title="{{ $item->deskripsi }}">
                                    {{ \Illuminate\Support\Str::limit($item->deskripsi, 120) }}
                                </td>
                                <td>
                                    @php
                                        $tipeBadge = [
                                            'regex'        => 'bg-info text-dark',
                                            'not_regex'    => 'bg-info text-dark',
                                            'contains'     => 'bg-primary',
                                            'not_contains' => 'bg-secondary',
                                            'posisi_area'  => 'bg-warning text-dark',
                                            'min_pages'    => 'bg-dark',
                                            'manual'       => 'bg-danger',
                                        ][$item->tipe_cek] ?? 'bg-secondary';
                                    @endphp
                                    <span class="badge {{ $tipeBadge }} fw-normal">
                                        {{ $item->tipe_cek }}
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    {{ $item->target }}
                                </td>
                                <td>
                                    <span class="badge {{ $item->status_gagal === 'TIDAK ADA' ? 'bg-danger' : 'bg-warning text-dark' }} fw-normal">
                                        {{ $item->status_gagal }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <form action="{{ route('admin.kriteria.toggle', $item) }}"
                                          method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="btn btn-sm {{ $item->aktif ? 'btn-success' : 'btn-outline-secondary' }} py-0 px-2"
                                                title="{{ $item->aktif ? 'Aktif — klik untuk nonaktifkan' : 'Nonaktif — klik untuk aktifkan' }}">
                                            <i class="ti {{ $item->aktif ? 'ti-check' : 'ti-x' }}"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.kriteria.edit', $item) }}"
                                       class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                        <i class="ti ti-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.kriteria.destroy', $item) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('Hapus kriteria {{ $item->kode }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="btn btn-sm btn-outline-danger py-0 px-2" title="Hapus">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="ti ti-list-check d-block mb-2"
                                       style="font-size:36px; opacity:.3;"></i>
                                    Belum ada kriteria
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($items->hasPages())
                <div class="px-3 py-2 border-top">
                    {{ $items->links() }}
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

{{-- Import Modal --}}
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.kriteria.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ti ti-file-import me-1"></i> Import Kriteria dari Excel/CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Baris pertama harus header kolom:
                        <code>kode, kategori, deskripsi, tipe_cek, target, status_gagal, pesan_ok, pesan_gagal, aktif, urutan</code>,
                        ditambah kolom parameter sesuai <code>tipe_cek</code>:
                        <code>param_pattern, param_flags, param_text, param_case, param_word, param_area, param_min, param_max_chars_page2</code>.
                    </p>
                    <p class="small text-muted mb-3">
                        Kriteria dengan <code>kode</code> yang sudah ada akan diperbarui (upsert), kode baru akan ditambahkan.
                    </p>
                    <a href="{{ route('admin.kriteria.import.template') }}" class="small d-inline-flex align-items-center gap-1 mb-3">
                        <i class="ti ti-download"></i> Download template contoh
                    </a>
                    <input type="file" name="file" class="form-control form-control-sm"
                           accept=".xlsx,.xls,.csv" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="ti ti-upload me-1"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection