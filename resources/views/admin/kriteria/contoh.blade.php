@extends('layouts.app')

@section('title', 'Contoh per Kategori')
@section('pretitle', 'Administrator')
@section('page-title', 'Contoh per Kategori')
@section('page-subtitle', 'Contoh yang benar (gambar atau PDF), ditampilkan di panel tinjauan saat petugas memeriksa kategori tersebut')
@section('page-actions')
    <a href="{{ route('admin.kriteria.index') }}" class="btn">
        <i class="ti ti-arrow-left"></i> Kelola Kriteria
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">

        @if($errors->any())
        <div class="alert alert-danger py-2 small mb-3">
            <i class="ti ti-alert-circle me-1"></i>
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
        @endif

        {{-- Form Upload --}}
        <div class="card mb-3" id="formUpload">
            <div class="card-body">
                <h5 class="card-title mb-3">Tambah Contoh</h5>
                <form action="{{ route('admin.kriteria.contoh.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small" for="kategori">Kategori</label>
                            <select name="kategori" id="kategori" class="form-select form-select-sm text-black" required>
                                @foreach($kategoriList as $k)
                                    <option value="{{ $k }}" {{ old('kategori', request('kategori')) === $k ? 'selected' : '' }}>{{ $k }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small" for="gambar">File (bisa pilih beberapa)</label>
                            <input type="file" name="gambar[]" id="gambar" class="form-control form-control-sm"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf" multiple required>
                            <small class="text-muted">Gambar JPG / PNG / WEBP atau PDF, maks. 10 MB per file</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small" for="keterangan">Keterangan (opsional)</label>
                            <input type="text" name="keterangan" id="keterangan" class="form-control form-control-sm"
                                   maxlength="255" value="{{ old('keterangan') }}"
                                   placeholder="Mis. Contoh kover depan publikasi daerah">
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="ti ti-upload"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Daftar per kategori --}}
        @foreach($kategoriList as $k)
            @php $items = $contoh->get($k, collect()); @endphp
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h6 class="mb-0 fw-semibold">
                            {{ $k }}
                            <span class="badge {{ $items->count() ? 'bg-primary' : 'bg-light text-muted border' }} ms-1">
                                {{ $items->count() }} contoh
                            </span>
                        </h6>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0" onclick="pilihKategori(@js($k))">
                            <i class="ti ti-plus me-1"></i> Tambah
                        </button>
                    </div>

                    @if($items->isEmpty())
                        <small class="text-muted fst-italic">Belum ada contoh.</small>
                    @else
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($items as $c)
                            <div class="border rounded p-2" style="width:200px;">
                                <a href="{{ route('checker.contoh.gambar', $c) }}" target="_blank" class="text-decoration-none">
                                    @if($c->isPdf())
                                        <div class="contoh-thumb contoh-thumb-pdf mb-2">
                                            <i class="ti ti-file-type-pdf"></i>
                                            <span class="small">Buka PDF</span>
                                        </div>
                                    @else
                                        <img src="{{ route('checker.contoh.gambar', $c) }}" alt="contoh" class="contoh-thumb mb-2">
                                    @endif
                                </a>
                                <form action="{{ route('admin.kriteria.contoh.update', $c) }}" method="POST" class="mb-1">
                                    @csrf @method('PATCH')
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="keterangan" class="form-control" maxlength="255"
                                               value="{{ $c->keterangan }}" placeholder="Keterangan">
                                        <button class="btn btn-outline-secondary" title="Simpan keterangan">
                                            <i class="ti ti-device-floppy"></i>
                                        </button>
                                    </div>
                                </form>
                                <form action="{{ route('admin.kriteria.contoh.destroy', $c) }}" method="POST"
                                      onsubmit="return confirm('Hapus contoh ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm w-100 py-0">
                                        <i class="ti ti-trash me-1"></i> Hapus
                                    </button>
                                </form>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

    </div>
</div>
@endsection

@push('scripts')
<script>
function pilihKategori(k) {
    document.getElementById('kategori').value = k;
    document.getElementById('formUpload').scrollIntoView({ behavior: 'smooth', block: 'start' });
    setTimeout(() => document.getElementById('gambar').click(), 400);
}
</script>
@endpush
