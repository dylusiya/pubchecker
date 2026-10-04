@extends('layouts.app')

@section('title', 'Kelola Kriteria Pemeriksaan')

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Header --}}
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="card-title mb-1">
                            <i class="mdi mdi-format-list-checks text-primary me-2"></i>
                            Kelola Kriteria Pemeriksaan
                        </h4>
                        <p class="text-muted mb-0 small">
                            {{ $items->total() }} kriteria terdaftar —
                            urutan menentukan tampilan di hasil pemeriksaan
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm border">
                            <i class="mdi mdi-home"></i>
                        </a>
                        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="mdi mdi-file-import-outline me-1"></i> Import
                        </button>
                        <a href="{{ route('admin.kriteria.create') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-plus me-1"></i> Tambah Kriteria
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Alert --}}
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
            <i class="mdi mdi-check-circle me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
            <i class="mdi mdi-alert-circle me-1"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        {{-- Filter & Search --}}
        <div class="card card-rounded mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('admin.kriteria.index') }}"
                      class="d-flex gap-2 align-items-center flex-wrap">
                    <div class="input-group input-group-sm" style="width:260px;">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="mdi mdi-magnify text-muted"></i>
                        </span>
                        <input type="text" name="q" value="{{ $q }}"
                               class="form-control border-start-0 ps-0"
                               placeholder="Cari kode, kategori, deskripsi...">
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit">
                        <i class="mdi mdi-filter me-1"></i> Cari
                    </button>
                    @if($q)
                    <a href="{{ route('admin.kriteria.index') }}" class="btn btn-light btn-sm border">
                        <i class="mdi mdi-close me-1"></i> Reset
                    </a>
                    @endif
                </form>
            </div>
        </div>

        {{-- Tabel --}}
        <div class="card card-rounded">
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
                                            <i class="mdi {{ $item->aktif ? 'mdi-check' : 'mdi-close' }}"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.kriteria.edit', $item) }}"
                                       class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                        <i class="mdi mdi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.kriteria.destroy', $item) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('Hapus kriteria {{ $item->kode }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="btn btn-sm btn-outline-danger py-0 px-2" title="Hapus">
                                            <i class="mdi mdi-trash-can-outline"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="mdi mdi-format-list-checks d-block mb-2"
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
                        <i class="mdi mdi-file-import-outline me-1"></i> Import Kriteria dari Excel/CSV
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
                        <i class="mdi mdi-download"></i> Download template contoh
                    </a>
                    <input type="file" name="file" class="form-control form-control-sm"
                           accept=".xlsx,.xls,.csv" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="mdi mdi-upload me-1"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection