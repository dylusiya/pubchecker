@extends('layouts.admin')

@section('title', 'Import Master SLS')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title">Import Master SLS</h4>
                        <p class="card-description">Upload file CSV atau Excel untuk import data Master SLS</p>
                    </div>
                    <a href="{{ route('master-sls.index') }}" class="btn btn-secondary">
                        <i class="mdi mdi-arrow-left"></i> Kembali
                    </a>
                </div>

                <!-- Alerts -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <!-- Instructions -->
                <div class="alert alert-info">
                    <h5 class="alert-heading"><i class="mdi mdi-information"></i> Petunjuk Import:</h5>
                    <ol class="mb-0 ps-3">
                        <li>File harus berformat <strong>CSV</strong> atau <strong>Excel (.xlsx, .xls)</strong></li>
                        <li>Ukuran file maksimal <strong>10 MB</strong></li>
                        <li>File harus memiliki header dengan urutan kolom sebagai berikut:
                            <ul>
                                <li><code>idsls, nmsls, nama_ketua, jenis, kdprov, nmprov, kdkab, nmkab, kdkec, nmkec, kddesa, nmdesa, kdsls, latitude, longitude</code></li>
                            </ul>
                        </li>
                        <li>Format kode:
                            <ul>
                                <li><strong>idsls:</strong> 14 digit (kdprov + kdkab + kdkec + kddesa + kdsls)</li>
                                <li><strong>kdprov:</strong> 2 digit (63 untuk Kalsel)</li>
                                <li><strong>kdkab:</strong> 2 digit (01, 02, dst)</li>
                                <li><strong>kdkec:</strong> 3 digit (010, 020, dst)</li>
                                <li><strong>kddesa:</strong> 3 digit (001, 002, dst)</li>
                                <li><strong>kdsls:</strong> 4 digit (0001-9999)</li>
                            </ul>
                        </li>
                        <li>Jenis SLS yang valid:
                            <ul>
                                <li><strong>SLS</strong> (untuk RT/RW)</li>
                                <li><strong>NONSLS_BUKAN_PEMUKIMAN</strong></li>
                                <li><strong>NONSLS_BUKAN_PERTANIAN</strong></li>
                                <li><strong>NONSLS_LAHAN_TERBUKA</strong></li>
                                <li><strong>NONSLS_PEMUKIMAN</strong></li>
                                <li><strong>NONSLS_PERAIRAN</strong></li>
                                <li><strong>NONSLS_PERTANIAN</strong></li>
                            </ul>
                        </li>
                        <li>Jika <code>idsls</code> sudah ada, data akan <strong>diupdate</strong></li>
                        <li>Jika <code>idsls</code> belum ada, data akan <strong>ditambahkan</strong></li>
                        <li>Field <code>nama_ketua</code> boleh kosong</li>
                        <li>Field <code>latitude</code> dan <code>longitude</code> boleh kosong</li>
                    </ol>
                </div>

                <!-- Template Download -->
                <div class="alert alert-warning">
                    <h5 class="alert-heading"><i class="mdi mdi-download"></i> Download Template:</h5>
                    <p class="mb-2">Download template file untuk memudahkan import data:</p>
                    <div class="d-flex gap-2">
                        <a href="{{ route('master-sls.export') }}" class="btn btn-sm btn-success">
                            <i class="mdi mdi-file-delimited"></i> Download Template CSV
                        </a>
                        <a href="{{ route('master-sls.template.excel') }}" class="btn btn-sm btn-primary">
                            <i class="mdi mdi-file-excel"></i> Download Template Excel
                        </a>
                    </div>
                    <small class="d-block mt-2 text-muted">
                        <i class="mdi mdi-lightbulb-outline"></i> 
                        Template berisi contoh format data yang benar. Anda bisa mengedit dan mengisinya dengan data baru.
                    </small>
                </div>

                <!-- Upload Form -->
                <form action="{{ route('master-sls.import.process') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="form-group">
                        <label for="file">Upload File <span class="text-danger">*</span></label>
                        <input type="file" 
                               name="file" 
                               id="file" 
                               class="form-control @error('file') is-invalid @enderror" 
                               accept=".csv,.txt,.xlsx,.xls"
                               required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">
                            Format yang diterima: CSV, TXT, XLSX, XLS (Maksimal 10MB)
                        </small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-upload"></i> Import Data
                        </button>
                        <a href="{{ route('master-sls.index') }}" class="btn btn-light">
                            Batal
                        </a>
                    </div>
                </form>

                <!-- Example Data Format -->
                <div class="mt-5">
                    <h5>Contoh Format Data CSV:</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>idsls</th>
                                    <th>nmsls</th>
                                    <th>nama_ketua</th>
                                    <th>jenis</th>
                                    <th>kdprov</th>
                                    <th>nmprov</th>
                                    <th>kdkab</th>
                                    <th>nmkab</th>
                                    <th>kdkec</th>
                                    <th>nmkec</th>
                                    <th>kddesa</th>
                                    <th>nmdesa</th>
                                    <th>kdsls</th>
                                    <th>latitude</th>
                                    <th>longitude</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>63010100010001</td>
                                    <td>RT 001 RW 004</td>
                                    <td>IDERUS</td>
                                    <td>SLS</td>
                                    <td>63</td>
                                    <td>KALIMANTAN SELATAN</td>
                                    <td>01</td>
                                    <td>TANAH LAUT</td>
                                    <td>010</td>
                                    <td>PANYIPATAN</td>
                                    <td>001</td>
                                    <td>BATAKAN</td>
                                    <td>0001</td>
                                    <td>-3.7234</td>
                                    <td>114.7986</td>
                                </tr>
                                <tr>
                                    <td>63010100010002</td>
                                    <td>RT 002 RW 005</td>
                                    <td>MASTAN</td>
                                    <td>SLS</td>
                                    <td>63</td>
                                    <td>KALIMANTAN SELATAN</td>
                                    <td>01</td>
                                    <td>TANAH LAUT</td>
                                    <td>010</td>
                                    <td>PANYIPATAN</td>
                                    <td>001</td>
                                    <td>BATAKAN</td>
                                    <td>0002</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>63010100019999</td>
                                    <td>Hutan Bakau</td>
                                    <td></td>
                                    <td>NONSLS_BUKAN_PEMUKIMAN</td>
                                    <td>63</td>
                                    <td>KALIMANTAN SELATAN</td>
                                    <td>01</td>
                                    <td>TANAH LAUT</td>
                                    <td>010</td>
                                    <td>PANYIPATAN</td>
                                    <td>001</td>
                                    <td>BATAKAN</td>
                                    <td>9999</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3">
                        <strong><i class="mdi mdi-information"></i> Penjelasan Kode:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>idsls</strong> = kdprov + kdkab + kdkec + kddesa + kdsls</li>
                            <li>Contoh: <code>63010100010001</code> = <code>63</code> + <code>01</code> + <code>010</code> + <code>001</code> + <code>0001</code></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection