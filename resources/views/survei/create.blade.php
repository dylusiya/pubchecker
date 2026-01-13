@extends('layouts.admin')

@section('title', 'Tambah Survei')

@section('content')
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card card-rounded">
            <div class="card-body">
                <h4 class="card-title">Tambah Survei Baru</h4>
                <p class="card-description">Isi form untuk menambahkan survei baru</p>
                
                <form action="{{ route('survei.store') }}" method="POST" class="forms-sample">
                    @csrf

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="kode">Kode Survei <span class="text-danger">*</span></label>
                                <input type="text" 
                                       name="kode" 
                                       id="kode" 
                                       class="form-control @error('kode') is-invalid @enderror" 
                                       value="{{ old('kode') }}" 
                                       placeholder="Contoh: SUSENAS"
                                       required>
                                <small class="text-muted">Kode unik survei (huruf kapital, tanpa spasi)</small>
                                @error('kode')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tahun">Tahun</label>
                                <input type="number" 
                                       name="tahun" 
                                       id="tahun" 
                                       class="form-control @error('tahun') is-invalid @enderror" 
                                       value="{{ old('tahun', date('Y')) }}" 
                                       min="1900"
                                       max="2100"
                                       placeholder="Tahun survei">
                                @error('tahun')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="nama">Nama Survei <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="nama" 
                               id="nama" 
                               class="form-control @error('nama') is-invalid @enderror" 
                               value="{{ old('nama') }}" 
                               placeholder="Nama lengkap survei"
                               required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="deskripsi">Deskripsi</label>
                        <textarea name="deskripsi" 
                                  id="deskripsi" 
                                  class="form-control @error('deskripsi') is-invalid @enderror" 
                                  rows="3"
                                  placeholder="Deskripsi singkat tentang survei">{{ old('deskripsi') }}</textarea>
                        @error('deskripsi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="keterangan">Keterangan</label>
                        <textarea name="keterangan" 
                                  id="keterangan" 
                                  class="form-control @error('keterangan') is-invalid @enderror" 
                                  rows="3"
                                  placeholder="Keterangan tambahan">{{ old('keterangan') }}</textarea>
                        @error('keterangan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="alert alert-info">
                        <i class="mdi mdi-information"></i>
                        <strong>Info:</strong> Setelah menyimpan, Anda dapat mengupload file pendukung (kuesioner, layout, surat) di halaman detail survei. Bisa upload lebih dari satu file untuk setiap jenis.
                    </div>

                    <button type="submit" class="btn btn-primary me-2">
                        <i class="mdi mdi-content-save"></i> Simpan
                    </button>
                    <a href="{{ route('survei.index') }}" class="btn btn-light">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection