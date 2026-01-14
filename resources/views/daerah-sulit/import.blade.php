@extends('layouts.admin')

@section('title', 'Import Data Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title text-primary"><i class="mdi mdi-upload me-2"></i>Import Data Daerah Sulit</h4>
                        <p class="card-description">Gunakan halaman ini untuk mengunggah data klasifikasi kesulitan akses secara massal.</p>
                    </div>                    
                    <a href="{{ route('daerah-sulit.index') }}" class="btn btn-outline-secondary btn-icon-text btn-sm">
                        <i class="mdi mdi-arrow-left btn-icon-prepend"></i> Kembali
                    </a>
                </div>

                <div class="row">
                    <div class="col-lg-7">
                        <div class="alert alert-info border-0 shadow-none bg-light-info">
                            <h5 class="alert-heading fw-bold"><i class="mdi mdi-information-outline"></i> Petunjuk Import:</h5>
                            <ol class="mb-0 ps-3 small text-dark">
                                <li class="mb-1">Pastikan file berformat <strong>.xlsx</strong>, <strong>.xls</strong>, atau <strong>.csv</strong>.</li>
                                <li class="mb-1">Tahun dan Nama Kegiatan yang diisi di bawah akan diterapkan ke <strong>seluruh data</strong> dalam file.</li>
                                <li class="mb-1">Kolom <code>is_sulit</code> diisi angka <strong>1</strong> (Ya) atau <strong>0</strong> (Tidak).</li>
                                <li class="mb-1">Kolom <code>biaya</code> dan <code>moda</code> wajib diisi jika status adalah Daerah Sulit (1).</li>
                                <li class="mb-1"><strong>File Pendukung:</strong> Jika diunggah, otomatis disematkan ke semua SLS dalam file data.</li>
                            </ol>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card border-warning bg-light-warning shadow-none mb-3">
                            <div class="card-body p-3 text-center">
                                <h6 class="fw-bold mb-2 text-dark"><i class="mdi mdi-file-download-outline me-1"></i>Template Form</h6>
                                <div class="d-grid gap-2 mt-3">
                                    <a href="{{ route('daerah-sulit.download-template-excel') }}" class="btn btn-primary btn-sm text-white">
                                        <i class="mdi mdi-file-excel me-1"></i> Template Excel (.xlsx)
                                    </a>
                                    <a href="{{ route('daerah-sulit.download-template') }}" class="btn btn-outline-success btn-sm">
                                        <i class="mdi mdi-file-delimited me-1"></i> Template CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 border-top pt-4">
                    <form action="{{ route('daerah-sulit.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label class="fw-bold">Tahun Anggaran <span class="text-danger">*</span></label>
                                    <select name="tahun_import" class="form-select border-primary text-dark" required>
                                        <option value="" selected disabled>-- Pilih Tahun --</option>
                                        @for($y = date('Y') + 1; $y >= 2020; $y--)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-5">
                                <div class="form-group mb-3">
                                    <label class="fw-bold text-dark">Kegiatan Terkait <span class="text-danger">*</span></label>
                                    <input type="text" name="kegiatan_import" class="form-control border-primary" 
                                           placeholder="Contoh: Sensus Ekonomi 2026" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label class="fw-bold">File Excel/CSV <span class="text-danger">*</span></label>
                                    <input type="file" name="file" class="form-control" accept=".csv,.xlsx,.xls" required>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-12">
                                <div class="form-group p-3 border rounded bg-light border-primary border-opacity-25">
                                    <label class="fw-bold text-primary"><i class="mdi mdi-paperclip me-1"></i>File Pendukung Kolektif (Opsional)</label>
                                    <input type="file" name="file_pendukung" class="form-control">
                                    <div class="form-text mt-2 text-muted italic">
                                        <i class="mdi mdi-lightbulb-on-outline"></i> <strong>Informasi:</strong> Unggah file ini jika semua data dalam file memiliki satu dasar hukum/bukti yang sama.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="reset" class="btn btn-light me-2">Reset Form</button>
                            <button type="submit" class="btn btn-primary btn-lg px-5 text-white shadow">
                                <i class="mdi mdi-cloud-upload me-2"></i>Mulai Proses Import
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-5">
                    <h6 class="fw-bold text-muted mb-3 italic">Struktur Kolom File Data:</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped small text-center">
                            <thead class="table-dark">
                                <tr>
                                    <th class="py-2">idsls (14 Digit)</th>
                                    <th class="py-2">is_sulit (0/1)</th>
                                    <th class="py-2">biaya</th>
                                    <th class="py-2">moda</th>
                                    <th class="py-2">waktu_menit</th>
                                    <th class="py-2">keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-primary fw-bold">63010100010001</td>
                                    <td>1</td>
                                    <td>500000</td>
                                    <td>Perahu</td>
                                    <td>120</td>
                                    <td>Akses sungai meluap</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-light-info { background-color: #e3f2fd; }
    .bg-light-warning { background-color: #fffde7; }
</style>
@endsection