@extends('layouts.app')

@section('title', isset($kriteria) ? 'Edit Kriteria ' . $kriteria->kode : 'Tambah Kriteria')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">

        {{-- Header --}}
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-1">
                            <i class="mdi mdi-{{ isset($kriteria) ? 'pencil' : 'plus-circle' }} text-primary me-2"></i>
                            {{ isset($kriteria) ? 'Edit Kriteria ' . $kriteria->kode : 'Tambah Kriteria Baru' }}
                        </h4>
                        <p class="text-muted mb-0 small">
                            Konfigurasi kriteria pemeriksaan kover publikasi BPS
                        </p>
                    </div>
                    <a href="{{ route('admin.kriteria.index') }}" class="btn btn-light btn-sm border">
                        <i class="mdi mdi-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            </div>
        </div>

        @if($errors->any())
        <div class="alert alert-danger py-2 small mb-3">
            <i class="mdi mdi-alert me-1"></i>
            <strong>Ada kesalahan:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Form --}}
        <form method="POST"
              action="{{ isset($kriteria) ? route('admin.kriteria.update', $kriteria) : route('admin.kriteria.store') }}">
            @csrf
            @if(isset($kriteria)) @method('PUT') @endif

            {{-- Identitas --}}
            <div class="card card-rounded mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">Identitas Kriteria</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Kode <span class="text-danger">*</span></label>
                            <input type="text" name="kode" class="form-control form-control-sm @error('kode') is-invalid @enderror"
                                   value="{{ old('kode', $kriteria->kode ?? '') }}"
                                   placeholder="K5.0" maxlength="20" required>
                            <div class="invalid-feedback">{{ $errors->first('kode') }}</div>
                            <small class="text-muted">Contoh: K5.0, K19.2</small>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Kategori <span class="text-danger">*</span></label>
                            <input type="text" name="kategori" class="form-control form-control-sm @error('kategori') is-invalid @enderror"
                                   value="{{ old('kategori', $kriteria->kategori ?? '') }}"
                                   placeholder="Kover – Nomor Katalog" maxlength="100" required>
                            <div class="invalid-feedback">{{ $errors->first('kategori') }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Urutan</label>
                            <input type="number" name="urutan" class="form-control form-control-sm"
                                   value="{{ old('urutan', $kriteria->urutan ?? 0) }}"
                                   min="0" step="10">
                            <small class="text-muted">Kelipatan 10 direkomendasikan</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Deskripsi <span class="text-danger">*</span></label>
                            <input type="text" name="deskripsi" class="form-control form-control-sm @error('deskripsi') is-invalid @enderror"
                                   value="{{ old('deskripsi', $kriteria->deskripsi ?? '') }}"
                                   placeholder="Penjelasan singkat apa yang dicek" maxlength="255" required>
                            <div class="invalid-feedback">{{ $errors->first('deskripsi') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Konfigurasi --}}
            <div class="card card-rounded mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">Konfigurasi Pengecekan</h5>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Tipe Cek <span class="text-danger">*</span></label>
                            <select name="tipe_cek" id="tipe_cek"
                                    class="form-select form-select-sm" onchange="showParamPanel()">
                                @foreach($tipes as $val => $label)
                                <option value="{{ $val }}"
                                    {{ old('tipe_cek', $kriteria->tipe_cek ?? '') === $val ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Target Halaman</label>
                            <select name="target" class="form-select form-select-sm">
                                @foreach($targets as $val => $label)
                                <option value="{{ $val }}"
                                    {{ old('target', $kriteria->target ?? 'cover') === $val ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Status Jika Gagal</label>
                            <select name="status_gagal" class="form-select form-select-sm">
                                <option value="TIDAK ADA"   {{ old('status_gagal', $kriteria->status_gagal ?? '') === 'TIDAK ADA'   ? 'selected' : '' }}>TIDAK ADA (merah)</option>
                                <option value="PERLU DICEK" {{ old('status_gagal', $kriteria->status_gagal ?? '') === 'PERLU DICEK' ? 'selected' : '' }}>PERLU DICEK (kuning)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Panel parameter dinamis --}}
                    <div class="mt-3" id="paramPanels">

                        {{-- REGEX --}}
                        <div id="panel_regex" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="mdi mdi-code-braces me-1 text-info"></i>Parameter Regex</p>
                            <div class="row g-2">
                                <div class="col-md-9">
                                    <label class="form-label small mb-1">Pattern (tanpa delimiter)</label>
                                    <input type="text" name="param_pattern" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_pattern', ($kriteria->parameter['pattern'] ?? '')) }}"
                                           placeholder="Katalog(?:\/Catalogue)?:\s*[\d.]+">
                                    <small class="text-muted">Contoh: <code>ISSN\s*\d{4}-\d{4}</code></small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Flags</label>
                                    <input type="text" name="param_flags" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_flags', ($kriteria->parameter['flags'] ?? '')) }}"
                                           placeholder="i" maxlength="10">
                                    <small class="text-muted"><code>i</code> = case-insensitive</small>
                                </div>
                            </div>
                        </div>

                        {{-- CONTAINS / NOT_CONTAINS --}}
                        <div id="panel_contains" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="mdi mdi-text-search me-1 text-primary"></i>Parameter Contains</p>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-9">
                                    <label class="form-label small mb-1">Teks yang dicari</label>
                                    <input type="text" name="param_text" class="form-control form-control-sm"
                                           value="{{ old('param_text', ($kriteria->parameter['text'] ?? '')) }}"
                                           placeholder="BADAN PUSAT STATISTIK">
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="param_case"
                                               id="param_case" value="1"
                                               {{ old('param_case', ($kriteria->parameter['case'] ?? false)) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="param_case">Case-sensitive</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- POSISI_AREA --}}
                        <div id="panel_posisi_area" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="mdi mdi-crosshairs-gps me-1 text-warning"></i>Parameter Posisi Area</p>
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Kata yang dicari posisinya</label>
                                    <input type="text" name="param_word" class="form-control form-control-sm"
                                           value="{{ old('param_word', ($kriteria->parameter['word'] ?? '')) }}"
                                           placeholder="Katalog">
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small mb-1">Area yang diharapkan</label>
                                    <select name="param_area" class="form-select form-select-sm">
                                        <option value="">— Pilih area —</option>
                                        @foreach($areas as $val => $label)
                                        <option value="{{ $val }}"
                                            {{ old('param_area', ($kriteria->parameter['area'] ?? '')) === $val ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- MIN_PAGES --}}
                        <div id="panel_min_pages" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="mdi mdi-file-multiple-outline me-1 text-dark"></i>Parameter Halaman</p>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Minimum halaman</label>
                                    <input type="number" name="param_min" class="form-control form-control-sm"
                                           value="{{ old('param_min', ($kriteria->parameter['min'] ?? '')) }}"
                                           min="1" placeholder="2">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Maks karakter halaman 2 (opsional)</label>
                                    <input type="number" name="param_max_chars_p2" class="form-control form-control-sm"
                                           value="{{ old('param_max_chars_p2', ($kriteria->parameter['max_chars_page2'] ?? '')) }}"
                                           min="0" placeholder="50">
                                    <small class="text-muted">Kosongkan jika tidak perlu cek hal. 2</small>
                                </div>
                            </div>
                        </div>

                        {{-- MANUAL --}}
                        <div id="panel_manual" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small text-muted mb-0">
                                <i class="mdi mdi-information-outline me-1"></i>
                                Tipe <strong>manual</strong> tidak butuh parameter — kriteria ini selalu menghasilkan
                                <span class="badge bg-warning text-dark">PERLU DICEK</span> dan harus diperiksa manusia.
                            </p>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Pesan --}}
            <div class="card card-rounded mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">Pesan Hasil</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">
                                <span class="text-success">✓</span> Pesan jika OK
                            </label>
                            <input type="text" name="pesan_ok" class="form-control form-control-sm"
                                   value="{{ old('pesan_ok', $kriteria->pesan_ok ?? '') }}"
                                   placeholder="Format benar" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">
                                <span class="text-danger">✗</span> Pesan jika Gagal
                            </label>
                            <input type="text" name="pesan_gagal" class="form-control form-control-sm"
                                   value="{{ old('pesan_gagal', $kriteria->pesan_gagal ?? '') }}"
                                   placeholder="Kriteria tidak terpenuhi" maxlength="255">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Aktif + submit --}}
            <div class="card card-rounded mb-4">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="aktif" id="aktif" value="1"
                               {{ old('aktif', $kriteria->aktif ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="aktif">
                            Aktifkan kriteria ini
                        </label>
                        <small class="text-muted d-block">Kriteria nonaktif tidak dijalankan saat pemeriksaan</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.kriteria.index') }}" class="btn btn-light border">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-content-save me-1"></i>
                            {{ isset($kriteria) ? 'Simpan Perubahan' : 'Tambah Kriteria' }}
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const currentTipe = '{{ old('tipe_cek', $kriteria->tipe_cek ?? 'manual') }}';

function showParamPanel() {
    const tipe = document.getElementById('tipe_cek').value;
    document.querySelectorAll('.param-panel').forEach(p => p.style.display = 'none');

    const map = {
        regex:        'panel_regex',
        contains:     'panel_contains',
        not_contains: 'panel_contains',
        posisi_area:  'panel_posisi_area',
        min_pages:    'panel_min_pages',
        manual:       'panel_manual',
    };
    const target = map[tipe];
    if (target) document.getElementById(target).style.display = 'block';
}

// Jalankan saat load
showParamPanel();
// Pastikan jika JS terlambat mount
document.getElementById('tipe_cek').value = currentTipe;
showParamPanel();
</script>
@endpush