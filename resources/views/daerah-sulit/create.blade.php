@extends('layouts.admin')

@section('title', 'Tambah Data Daerah Sulit')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="card-title text-primary">Tambah Status Daerah Sulit</h4>
                        <p class="card-description">Input status kesulitan akses per SLS (Satuan Lingkungan Setempat)</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('daerah-sulit.import') }}" class="btn btn-success btn-icon-text btn-sm">
                            <i class="mdi mdi-upload btn-icon-prepend"></i> Import Data
                        </a>
                        <a href="{{ route('daerah-sulit.index') }}" class="btn btn-outline-secondary btn-icon-text btn-sm">
                            <i class="mdi mdi-arrow-left btn-icon-prepend"></i> Kembali
                        </a>
                    </div>
                </div>

                <form action="{{ route('daerah-sulit.store') }}" method="POST" enctype="multipart/form-data" class="forms-sample">
                    @csrf

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label class="fw-bold">Tahun <span class="text-danger">*</span></label>
                                <select name="tahun_anggaran" class="form-select text-dark @error('tahun_anggaran') is-invalid @enderror" required>
                                    <option value="">-- Pilih Tahun --</option>
                                    @for($y = date('Y') + 1; $y >= 2020; $y--)
                                        <option value="{{ $y }}" {{ old('tahun_anggaran') == $y ? 'selected' : '' }}>
                                            {{ $y }}
                                        </option>
                                    @endfor
                                </select>
                                @error('tahun_anggaran')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="form-group mb-3">
                                <label class="fw-bold text-dark">Kegiatan Terkait <span class="text-danger">*</span></label>
                                <input type="text" name="kegiatan" class="form-control @error('kegiatan') is-invalid @enderror" 
                                       placeholder="Contoh: Sensus Ekonomi 2026 / Pemutakhiran Kerangka Geospasial" 
                                       value="{{ old('kegiatan') }}" required>
                                @error('kegiatan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label class="fw-bold">Pilih SLS <span class="text-danger">*</span></label>
                                <select name="master_sls_id" id="master_sls_id" class="form-control" required>
                                    <option value="">-- Ketik ID, Nama SLS, atau Desa --</option>
                                </select>
                                @error('master_sls_id')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group mb-4">
                        <label class="fw-bold d-block mb-2">Status Kesulitan Akses <span class="text-danger">*</span> <span id="req-keterangan" class="text-danger" style="display: none;"></span></label>
                        <div class="d-flex gap-4">
                            <div class="form-check form-check-danger">
                                <label class="form-check-label">
                                    <input type="radio" class="form-check-input" name="is_daerah_sulit" id="sulit" value="1" {{ old('is_daerah_sulit') == '1' ? 'checked' : '' }} required>
                                    Daerah Sulit <i class="input-helper"></i>
                                </label>
                            </div>
                            <div class="form-check form-check-success">
                                <label class="form-check-label">
                                    <input type="radio" class="form-check-input" name="is_daerah_sulit" id="tidak_sulit" value="0" {{ old('is_daerah_sulit') == '0' ? 'checked' : '' }}>
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
                                        <input type="number" name="perkiraan_biaya" class="form-control" value="{{ old('perkiraan_biaya') }}" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>Moda Transportasi Utama <span class="text-danger">*</span></label>
                                    <select name="metode_transportasi" class="form-select text-dark">
                                        <option value="">-- Pilih --</option>
                                        @foreach(['Sepeda Motor', 'Mobil', 'Perahu', 'Speedboat', 'Jalan Kaki'] as $metode)
                                            <option value="{{ $metode }}" {{ old('metode_transportasi') == $metode ? 'selected' : '' }}>{{ $metode }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>Waktu Tempuh (Menit)</label>
                                    <input type="number" name="waktu_tempuh_menit" class="form-control" value="{{ old('waktu_tempuh_menit') }}" placeholder="Contoh: 120">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="fw-bold">Keterangan / Alasan <span id="label-req" class="text-danger" style="display: none;">*</span></label>
                        <textarea name="keterangan" id="keterangan" class="form-control @error('keterangan') is-invalid @enderror" rows="4" placeholder="Jelaskan kondisi akses secara detail...">{{ old('keterangan') }}</textarea>
                        <small class="text-muted">Wajib diisi minimal 20 karakter jika status 'Daerah Sulit'.</small>
                    </div>

                    <div class="form-group mb-4">
                        <label class="fw-bold">File Pendukung</label>
                        <input type="file" name="file_pendukung" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">PDF/JPG/PNG (Max 2MB).</small>
                    </div>

                    <div class="mt-3 text-end">
                        <a href="{{ route('daerah-sulit.index') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary btn-icon-text px-4 shadow-sm">
                            <i class="mdi mdi-content-save btn-icon-prepend"></i> Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    select.form-select, select.form-control { color: #495057 !important; }
    .select2-container--bootstrap-5 .select2-selection { min-height: 45px; padding-top: 7px; }
</style>

@push('scripts')
<script>
$(document).ready(function() {
    $('#master_sls_id').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Cari ID SLS, Nama SLS, atau Wilayah...',
        allowClear: true,
        ajax: {
            url: '{{ route("daerah-sulit.search-sls") }}',
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return {
                    q: params.term,
                    tahun: $('select[name="tahun_anggaran"]').val()
                };
            },
            processResults: function (data) {
                return {
                    results: data.map(function(item) {
                        return {
                            id: item.id,
                            text: item.idsls + ' - ' + item.nmsls + ' (' + item.nmdesa + ', ' + item.nmkec + ')'
                        };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 3
    });

    $('input[name="is_daerah_sulit"]').on('change', function() {
        const isSulit = $(this).val() == '1';
        if (isSulit) {
            $('#form-daerah-sulit').slideDown();
            $('input[name="perkiraan_biaya"]').attr('required', true);
            $('select[name="metode_transportasi"]').attr('required', true);
            $('#keterangan').attr('required', true);
            $('#label-req').show();
        } else {
            $('#form-daerah-sulit').slideUp();
            $('input[name="perkiraan_biaya"]').attr('required', false);
            $('select[name="metode_transportasi"]').attr('required', false);
            $('#keterangan').attr('required', false);
            $('#label-req').hide(); 
        }
    });

    // Initial check
    if ($('input[name="is_daerah_sulit"]:checked').val() == '1') {
        $('#form-daerah-sulit').show();
        $('#keterangan').attr('required', true);
        $('#label-req').show();
    }
});
</script>
@endpush
@endsection