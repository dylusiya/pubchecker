@extends('layouts.app')

@section('title', 'Detail Survey Kepuasan')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title text-primary">Detail Survey Kepuasan</h4>
                        <p class="card-description mb-0">ID: #{{ $response->id }}</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.survey.index') }}" class="btn btn-outline-secondary btn-icon-text">
                            <i class="mdi mdi-arrow-left btn-icon-prepend"></i> Kembali
                        </a>
                    </div>
                </div>

                <div class="mb-4">
                    <span class="badge badge-opacity-{{ $response->status == 'completed' ? 'success' : 'warning' }} px-3 py-2">
                        <i class="mdi mdi-{{ $response->status == 'completed' ? 'check-circle' : 'file-document-edit' }} me-1"></i>
                        {{ $response->status == 'completed' ? 'SURVEY SELESAI' : 'DRAFT / BELUM SELESAI' }}
                    </span>
                    
                    <span class="badge badge-opacity-info px-3 py-2 ms-2">
                        <i class="mdi mdi-calendar-clock me-1"></i>
                        @if($response->tanggal_submit)
                            {{ $response->tanggal_submit->format('d M Y, H:i') }}
                        @else
                            Update: {{ $response->updated_at->format('d M Y, H:i') }}
                        @endif
                    </span>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="mdi mdi-account-box text-primary me-2"></i>Data Responden</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr>
                                        <td width="130" class="text-muted">Nama</td>
                                        <td class="fw-bold">: {{ $response->nama }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">No. HP</td>
                                        <td>: {{ $response->nomor_hp }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Email</td>
                                        <td>: {{ $response->email }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">JK / Umur</td>
                                        <td>: {{ $response->jenis_kelamin }} / {{ $response->kelompok_umur }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Pendidikan</td>
                                        <td>: {{ $response->pendidikan }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Pekerjaan</td>
                                        <td>: {{ $response->pekerjaan }} {{ $response->pekerjaan_lainnya ? '('.$response->pekerjaan_lainnya.')' : '' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Instansi</td>
                                        <td>: {{ $response->nama_instansi }} <br> <small class="text-muted">({{ $response->kategori_instansi }})</small></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="mdi mdi-briefcase-check text-primary me-2"></i>Informasi Layanan</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-1">Jenis Layanan</small>
                                    <h5 class="mb-0 text-dark">{{ $response->jenis_layanan }}</h5>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-1">Tujuan Penggunaan</small>
                                    <p class="mb-0 fw-bold">{{ $response->tujuan_penggunaan }}</p>
                                    @if($response->tujuan_lainnya) <small class="text-muted">({{ $response->tujuan_lainnya }})</small> @endif
                                </div>
                                <div>
                                    <small class="text-muted d-block mb-1">Sarana yang digunakan</small>
                                    @foreach(explode(',', $response->sarana_layanan) as $item)
                                        <span class="badge badge-outline-primary mb-1">{{ trim($item) }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($response->status == 'completed')
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="mdi mdi-chart-bar me-2"></i>Ringkasan Skor Penilaian</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-md-4 border-end">
                                <h4 class="text-success fw-bold">{{ number_format($response->average_kepuasan, 2) }} / 10</h4>
                                <small class="text-muted">Rata-rata Kepuasan</small>
                            </div>
                            <div class="col-md-4 border-end">
                                <h4 class="text-primary fw-bold">{{ number_format($response->average_kepentingan, 2) }} / 10</h4>
                                <small class="text-muted">Rata-rata Kepentingan</small>
                            </div>
                            <div class="col-md-4">
                                @php
                                    $skor = $response->average_kepuasan;
                                    $mutu = $skor >= 9 ? 'A (Sangat Baik)' : ($skor >= 7.5 ? 'B (Baik)' : ($skor >= 6 ? 'C (Kurang Baik)' : 'D (Buruk)'));
                                    $icon = $skor >= 7.5 ? 'emoticon-happy' : 'emoticon-sad';
                                @endphp
                                <h4 class="text-dark fw-bold"><i class="mdi mdi-{{ $icon }} text-warning me-1"></i> {{ $mutu }}</h4>
                                <small class="text-muted">Predikat Mutu Pelayanan</small>
                            </div>
                        </div>
                        <hr>
                        <div>
                            <strong>Catatan Tambahan Responden:</strong>
                            <p class="mt-2 mb-0 fst-italic text-justify">
                                "{{ $response->catatan_tambahan ?: 'Tidak ada catatan tambahan.' }}"
                            </p>
                        </div>
                    </div>
                </div>
                @endif

                <div class="card border mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="mdi mdi-clipboard-list text-primary me-2"></i>Rincian Jawaban Unsur Pelayanan</h6>
                        <span class="badge badge-secondary">16 Unsur</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th width="50">No</th>
                                        <th>Unsur Pelayanan</th>
                                        <th class="text-center">Nilai Kepentingan</th>
                                        <th class="text-center">Nilai Kepuasan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $unsur = [
                                        'informasi_pelayanan' => 'Ketersediaan Informasi Pelayanan',
                                        'persyaratan' => 'Kemudahan Persyaratan',
                                        'prosedur' => 'Kemudahan Prosedur/Alur',
                                        'jangka_waktu' => 'Ketepatan Jangka Waktu',
                                        'biaya' => 'Kesesuaian Biaya',
                                        'produk' => 'Kesesuaian Produk Layanan',
                                        'sarana' => 'Kenyamanan Sarana & Prasarana',
                                        'akses_data' => 'Kemudahan Akses Data',
                                        'respons_petugas' => 'Respons Petugas',
                                        'informasi_petugas' => 'Kejelasan Informasi Petugas',
                                        'fasilitas_pengaduan' => 'Fasilitas Pengaduan',
                                        'diskriminasi' => 'Tidak Ada Diskriminasi',
                                        'kecurangan' => 'Tidak Ada Kecurangan',
                                        'gratifikasi' => 'Tidak Ada Gratifikasi',
                                        'pungli' => 'Tidak Ada Pungli',
                                        'percaloan' => 'Tidak Ada Percaloan',
                                    ];
                                    @endphp

                                    @foreach($unsur as $key => $label)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>{{ $label }}</td>
                                        <td class="text-center">
                                            @if(isset($response->{$key . '_kepentingan'}))
                                                <span class="badge badge-opacity-info">{{ $response->{$key . '_kepentingan'} }}</span>
                                            @else - @endif
                                        </td>
                                        <td class="text-center">
                                            @if(isset($response->{$key . '_kepuasan'}))
                                                @php $val = $response->{$key . '_kepuasan'}; @endphp
                                                <span class="badge badge-opacity-{{ $val >= 8 ? 'success' : ($val >= 6 ? 'warning' : 'danger') }}">
                                                    {{ $val }}
                                                </span>
                                            @else - @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if($response->dataEntries->count() > 0)
                <div class="card border">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="mdi mdi-database text-primary me-2"></i>Data yang Diakses / Dibutuhkan</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama Data</th>
                                        <th>Tahun</th>
                                        <th>Status</th>
                                        <th>Kepuasan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($response->dataEntries as $entry)
                                    <tr>
                                        <td class="fw-bold">{{ $entry->nama_data }}</td>
                                        <td>{{ $entry->tahun }}</td>
                                        <td>
                                            @if(str_contains(strtolower($entry->status_perolehan), 'sesuai'))
                                                <span class="badge badge-success badge-sm">{{ $entry->status_perolehan }}</span>
                                            @else
                                                <span class="badge badge-warning badge-sm">{{ $entry->status_perolehan }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($entry->tingkat_kepuasan)
                                                <i class="mdi mdi-star text-warning"></i> {{ $entry->tingkat_kepuasan }}
                                            @else - @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                <div class="mt-4 text-end">
                    <small class="text-muted d-block">IP Address: {{ $response->ip_address }}</small>
                    <small class="text-muted d-block">User Agent: {{ Str::limit($response->user_agent, 80) }}</small>
                </div>

                <div class="d-flex gap-2 justify-content-end mt-4 border-top pt-3">
                    <button type="button" class="btn btn-danger btn-icon-text text-white" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="mdi mdi-delete btn-icon-prepend"></i> Hapus Data
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.survey.destroy', $response->id) }}" method="POST">
                @csrf @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="mdi mdi-trash-can me-2"></i>Hapus Data</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="mdi mdi-delete-forever text-danger display-1 mb-3"></i>
                    <p class="lead">Data yang dihapus tidak dapat dikembalikan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger text-white">Hapus Permanen</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
/* Style tambahan menyesuaikan referensi */
.text-justify { text-align: justify; }
.badge-opacity-success { background: rgba(25, 135, 84, 0.2); color: #198754; }
.badge-opacity-warning { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
.badge-opacity-danger { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
.badge-opacity-info { background: rgba(13, 202, 240, 0.2); color: #0dcaf0; }
.card-header { border-bottom: 1px solid #e3e3e3; }
</style>
@endpush