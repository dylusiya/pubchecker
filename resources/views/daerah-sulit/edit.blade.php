@extends('layouts.admin')

@section('title', 'Edit Status Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title text-primary">Edit Status Daerah Sulit</h4>
                        <p class="card-description">Perbarui informasi status kesulitan akses SLS</p>
                    </div>
                    <a href="{{ route('daerah-sulit.show', $status->id) }}" class="btn btn-outline-secondary btn-icon-text">
                        <i class="mdi mdi-arrow-left btn-icon-prepend"></i> Kembali
                    </a>
                </div>

                <!-- Informasi SLS (Read Only) -->
                <div class="card mb-4 bg-light">
                    <div class="card-body">
                        <h5 class="text-primary mb-3"><i class="mdi mdi-map-marker me-2"></i>Informasi SLS</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>ID SLS:</strong> {{ $status->masterSls->idsls }}</p>
                                <p><strong>Nama SLS:</strong> {{ $status->masterSls->nmsls }}</p>
                                <p><strong>Kabupaten:</strong> {{ $status->masterSls->nmkab }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Kecamatan:</strong> {{ $status->masterSls->nmkec }}</p>
                                <p><strong>Desa:</strong> {{ $status->masterSls->nmdesa }}</p>
                                <p><strong>Tahun:</strong> {{ $status->tahun_anggaran }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <form action="{{ route('daerah-sulit.update', $status->id) }}" method="POST" enctype="multipart/form-data" class="forms-sample">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label class="fw-bold">Kegiatan Terkait <span class="text-danger">*</span></label>
                                <input type="text" name="kegiatan" class="form-control @error('kegiatan') is-invalid @enderror" 
                                       placeholder="Contoh: Sensus Ekonomi 2026 / Pemutakhiran Kerangka Geospasial" 
                                       value="{{ old('kegiatan', $status->kegiatan) }}" required>
                                @error('kegiatan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group mb-4">
                        <label class="fw-bold d-block mb-2">Status Kesulitan Akses <span class="text-danger">*</span></label>
                        <div class="d-flex gap-4">
                            <div class="form-check form-check-danger">
                                <label class="form-check-label">
                                    <input type="radio" class="form-check-input" name="is_daerah_sulit" id="sulit" value="1" 
                                           {{ old('is_daerah_sulit', $status->is_daerah_sulit) == '1' ? 'checked' : '' }} required>
                                    Daerah Sulit <i class="input-helper"></i>
                                </label>
                            </div>
                            <div class="form-check form-check-success">
                                <label class="form-check-label">
                                    <input type="radio" class="form-check-input" name="is_daerah_sulit" id="tidak_sulit" value="0" 
                                           {{ old('is_daerah_sulit', $status->is_daerah_sulit) == '0' ? 'checked' : '' }}>
                                    Tidak Sulit <i class="input-helper"></i>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="form-daerah-sulit" style="display: none;" class="p-4 rounded bg-light border mb-4">
                        <h6 class="text-primary mb-3"><i class="mdi mdi-map-marker-distance me-2"></i>Informasi Detail Kesulitan</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>Perkiraan Biaya <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-primary text-white">Rp</span>
                                        <input type="number" name="perkiraan_biaya" class="form-control" 
                                               value="{{ old('perkiraan_biaya', $status->perkiraan_biaya) }}" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>Moda Transportasi Utama <span class="text-danger">*</span></label>
                                    <select name="metode_transportasi" class="form-select text-dark">
                                        <option value="">-- Pilih --</option>
                                        @foreach(['Sepeda Motor', 'Mobil', 'Perahu', 'Speedboat', 'Ojek Sepeda Motor', 'Jalan Kaki'] as $metode)
                                            <option value="{{ $metode }}" 
                                                    {{ old('metode_transportasi', $status->metode_transportasi) == $metode ? 'selected' : '' }}>
                                                {{ $metode }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>Waktu Tempuh (Menit)</label>
                                    <input type="number" name="waktu_tempuh_menit" class="form-control" 
                                           value="{{ old('waktu_tempuh_menit', $status->waktu_tempuh_menit) }}" 
                                           placeholder="Contoh: 120">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="fw-bold">Keterangan / Alasan <span class="text-danger">*</span></label>
                        <textarea name="keterangan" id="keterangan" class="form-control @error('keterangan') is-invalid @enderror" 
                                  rows="4" placeholder="Jelaskan kondisi akses secara detail..." required>{{ old('keterangan', $status->keterangan) }}</textarea>
                        <small class="text-muted">Minimal 20 karakter.</small>
                        @error('keterangan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label class="fw-bold">File Pendukung</label>
                        
                        @if($status->file_pendukung)
                            <div class="alert alert-info mb-2">
                                <i class="mdi mdi-file me-2"></i>File saat ini: 
                                <a href="{{ asset('storage/' . $status->file_pendukung) }}" target="_blank" class="alert-link">
                                    Lihat File
                                </a>
                            </div>
                        @endif

                        <input type="file" name="file_pendukung" class="form-control @error('file_pendukung') is-invalid @enderror" 
                            accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">PDF/JPG/PNG (Max 2MB). <strong>Opsional - upload jika ingin mengganti file.</strong></small>
                        @error('file_pendukung')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label class="fw-bold">Alasan Perubahan <span class="text-danger">*</span></label>
                        <textarea name="alasan_perubahan" class="form-control @error('alasan_perubahan') is-invalid @enderror" 
                                  rows="3" placeholder="Jelaskan alasan melakukan perubahan data (minimal 20 karakter)..." 
                                  required minlength="20">{{ old('alasan_perubahan') }}</textarea>
                        <small class="text-muted">Minimal 20 karakter. <strong>Wajib diisi untuk dokumentasi perubahan.</strong></small>
                        @error('alasan_perubahan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mt-4 text-end">
                        <a href="{{ route('daerah-sulit.show', $status->id) }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary btn-icon-text px-4 shadow-sm">
                            <i class="mdi mdi-content-save btn-icon-prepend"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    select.form-select, select.form-control { color: #495057 !important; }
</style>

@push('scripts')
<script>
$(document).ready(function() {
    // Toggle form daerah sulit
    function toggleFormSulit() {
        const isSulit = $('input[name="is_daerah_sulit"]:checked').val() == '1';
        if (isSulit) {
            $('#form-daerah-sulit').slideDown();
            $('input[name="perkiraan_biaya"]').attr('required', true);
            $('select[name="metode_transportasi"]').attr('required', true);
        } else {
            $('#form-daerah-sulit').slideUp();
            $('input[name="perkiraan_biaya"]').attr('required', false);
            $('select[name="metode_transportasi"]').attr('required', false);
        }
    }

    // Event listener
    $('input[name="is_daerah_sulit"]').on('change', toggleFormSulit);

    // Initial check
    toggleFormSulit();
});
</script>
@endpush
@endsection