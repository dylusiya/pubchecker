@extends('layouts.app')

@section('title', isset($kriteria) ? 'Edit Kriteria ' . $kriteria->kode : 'Tambah Kriteria')
@section('pretitle', 'Kelola Kriteria')
@section('page-title', isset($kriteria) ? 'Edit Kriteria ' . $kriteria->kode : 'Tambah Kriteria Baru')
@section('page-subtitle', 'Konfigurasi kriteria pemeriksaan publikasi BPS')
@section('page-actions')
    <a href="{{ route('admin.kriteria.index') }}" class="btn">
        <i class="ti ti-arrow-left"></i> Kembali
    </a>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">

        @if($errors->any())
        <div class="alert alert-danger py-2 small mb-3">
            <i class="ti ti-alert-triangle me-1"></i>
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
            <div class="card mb-3">
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
            <div class="card mb-3">
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
                            <p class="small fw-semibold mb-2"><i class="ti ti-code me-1 text-info"></i>Parameter Regex</p>
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
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Syarat (opsional, khusus not_regex)</label>
                                    <input type="text" name="param_syarat" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_syarat', ($kriteria->parameter['syarat'] ?? '')) }}"
                                           placeholder="\bISSN\b">
                                    <small class="text-muted">Bila pola ini tidak ada di target, hasilnya Skip (bukan OK)</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Pesan bila syarat tidak terpenuhi</label>
                                    <input type="text" name="param_pesan_syarat" class="form-control form-control-sm"
                                           value="{{ old('param_pesan_syarat', ($kriteria->parameter['pesan_syarat'] ?? '')) }}"
                                           placeholder="Tidak ada ISSN di halaman ini" maxlength="200">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1">Abaikan (opsional)</label>
                                    <input type="text" name="param_abaikan" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_abaikan', ($kriteria->parameter['abaikan'] ?? '')) }}"
                                           placeholder="(?:Volume|Edisi)\s*\d+[^\n]{0,15}?(?:19|20)\d{2}">
                                    <small class="text-muted">Bagian teks yang cocok pola ini diabaikan sebelum dicek (mis. tahun pada edisi terbitan bukan tahun judul)</small>
                                </div>
                            </div>
                        </div>

                        {{-- SAMA_KOVER --}}
                        <div id="panel_sama_kover" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="ti ti-equal me-1 text-info"></i>Parameter Sama dengan Kover</p>
                            <div class="row g-2">
                                <div class="col-md-9">
                                    <label class="form-label small mb-1">Pola isian (grup tangkap = nilai yang dibandingkan)</label>
                                    <input type="text" name="param_pola_kover" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_pola_kover', ($kriteria->tipe_cek ?? '') === 'sama_kover' ? ($kriteria->parameter['pattern'] ?? '') : '') }}"
                                           placeholder="\bISSN\s*:?\s*(\d{4}\s*-\s*\d{3}[\dXx])">
                                    <small class="text-muted">Nilai di halaman target dibandingkan dengan nilai di kover (spasi & tanda hubung diabaikan)</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Flags</label>
                                    <input type="text" name="param_flags_kover" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_flags_kover', ($kriteria->tipe_cek ?? '') === 'sama_kover' ? ($kriteria->parameter['flags'] ?? 'iu') : 'iu') }}" maxlength="10">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1">Pesan bila isian tidak ada di halaman</label>
                                    <input type="text" name="param_pesan_kover" class="form-control form-control-sm"
                                           value="{{ old('param_pesan_kover', ($kriteria->tipe_cek ?? '') === 'sama_kover' ? ($kriteria->parameter['pesan_syarat'] ?? '') : '') }}"
                                           placeholder="Tidak ada ISSN di halaman Daftar Isi" maxlength="200">
                                </div>
                            </div>
                        </div>

                        {{-- URUTAN --}}
                        <div id="panel_urutan" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="ti ti-list-numbers me-1 text-info"></i>Parameter Urutan</p>
                            <label class="form-label small mb-1">Komponen sesuai urutan (satu pola regex per baris)</label>
                            <textarea name="param_urutan" rows="4" class="form-control form-control-sm font-monospace"
                                      placeholder="Nomor\s+Publikasi&#10;Jumlah\s+Halaman">{{ old('param_urutan', implode("\n", (array) ($kriteria->parameter['label'] ?? []))) }}</textarea>
                            <small class="text-muted">Setiap komponen harus muncul setelah komponen di baris sebelumnya. Bila ada yang tidak ditemukan, hasilnya Skip (komponen opsional).</small>
                        </div>

                        {{-- PARITAS --}}
                        <div id="panel_paritas" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="ti ti-arrows-left-right me-1 text-info"></i>Parameter Halaman Ganjil/Genap</p>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Bagian (target) harus dimulai di halaman</label>
                                    <select name="param_posisi" class="form-select form-select-sm">
                                        @foreach(['ganjil' => 'Ganjil', 'genap' => 'Genap'] as $val => $label)
                                        <option value="{{ $val }}" @selected(old('param_posisi', $kriteria->parameter['posisi'] ?? 'ganjil') === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <small class="text-muted d-block mt-md-4">Nomor halaman = urutan halaman di PDF (kover = 1). Pilih target halaman khusus, mis. Katalog / Daftar Isi.</small>
                                </div>
                            </div>
                        </div>

                        {{-- JUMLAH_MIN & NOMOR_URUT --}}
                        <div id="panel_pola_hitung" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="ti ti-list-numbers me-1 text-info"></i>Parameter Pola</p>
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label class="form-label small mb-1">Pola entri</label>
                                    <input type="text" name="param_pola_hitung" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_pola_hitung', in_array($kriteria->tipe_cek ?? '', ['jumlah_min', 'nomor_urut', 'abjad']) ? ($kriteria->parameter['pattern'] ?? '') : '') }}"
                                           placeholder="^\s*(?:Tabel|Table)\s+(\d+(?:\.\d+)*)">
                                    <small class="text-muted">Nomor Berurutan: grup tangkap pertama = nomornya (mis. <code>2.3</code>). Urut Abjad: grup tangkap pertama = kunci urutan (nama/istilah).</small>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Flags</label>
                                    <input type="text" name="param_flags_hitung" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_flags_hitung', in_array($kriteria->tipe_cek ?? '', ['jumlah_min', 'nomor_urut', 'abjad']) ? ($kriteria->parameter['flags'] ?? 'imu') : 'imu') }}" maxlength="10">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Minimal (khusus Jumlah Minimal)</label>
                                    <input type="number" name="param_min_hitung" class="form-control form-control-sm" min="1"
                                           value="{{ old('param_min_hitung', $kriteria->parameter['min'] ?? 3) }}">
                                </div>
                            </div>
                        </div>

                        {{-- BAB --}}
                        <div id="panel_bab" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="ti ti-book me-1 text-info"></i>Parameter Bab</p>
                            <select name="param_cek_bab" class="form-select form-select-sm" style="max-width:360px;">
                                @foreach(['ganjil' => 'Setiap bab dimulai di halaman ganjil', 'berurutan' => 'Nomor bab berurutan (1, 2, 3, …)'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('param_cek_bab', $kriteria->parameter['cek'] ?? 'ganjil') === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">Awal bab dikenali dari baris "BAB n" / "CHAPTER n" di bagian atas halaman. Target diabaikan.</small>
                        </div>

                        {{-- BULAN_RILIS --}}
                        <div id="panel_bulan_rilis" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small text-muted mb-0">
                                <i class="ti ti-info-circle me-1"></i>
                                Tidak butuh parameter — mencari "Kota, Bulan Tahun" terakhir di target (mis. Kata Pengantar) dan
                                membandingkannya dengan tanggal rilis dari API BPS: harus bulan rilis atau sebulan sebelumnya.
                                Untuk hasil Upload Manual hasilnya Skip (tanggal rilis tidak diketahui).
                            </p>
                        </div>

                        {{-- JUDUL_KOVER --}}
                        <div id="panel_judul_kover" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small text-muted mb-0">
                                <i class="ti ti-info-circle me-1"></i>
                                Tidak butuh parameter — kata-kata judul di kover (tanpa nama penerbit, katalog, ISSN, edisi) minimal 70%
                                harus ada di halaman target, begitu juga tahun judulnya.
                            </p>
                        </div>

                        {{-- ROMAWI_GENAP --}}
                        <div id="panel_romawi_genap" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small text-muted mb-0">
                                <i class="ti ti-info-circle me-1"></i>
                                Tidak butuh parameter — mencari jumlah halaman "romawi + arab" (mis. <code>x+52 halaman</code>)
                                di target dan memeriksa angka romawinya genap.
                            </p>
                        </div>

                        {{-- CONTAINS / NOT_CONTAINS --}}
                        <div id="panel_contains" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="ti ti-text-scan-2 me-1 text-primary"></i>Parameter Contains</p>
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
                            <p class="small fw-semibold mb-2"><i class="ti ti-current-location me-1 text-warning"></i>Parameter Posisi Area</p>
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
                            <p class="small fw-semibold mb-2"><i class="ti ti-files me-1 text-dark"></i>Parameter Halaman</p>
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

                        {{-- URUTAN_TEKS --}}
                        <div id="panel_urutan_teks" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="mdi mdi-sort-ascending me-1 text-success"></i>Parameter Urutan Teks</p>
                            <p class="small text-muted mb-2">Kemunculan pertama pola "Sebelum" harus ada di posisi lebih awal daripada pola "Sesudah".</p>
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Pola "Sebelum" (harus muncul duluan)</label>
                                    <input type="text" name="param_before" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_before', ($kriteria->parameter['before'] ?? '')) }}"
                                           placeholder="Penyusun\s+Naskah">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Pola "Sesudah" (harus muncul belakangan)</label>
                                    <input type="text" name="param_after" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_after', ($kriteria->parameter['after'] ?? '')) }}"
                                           placeholder="Penyunting">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Flags</label>
                                    <input type="text" name="param_flags" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_flags', ($kriteria->parameter['flags'] ?? 'iu')) }}"
                                           placeholder="iu" maxlength="10">
                                </div>
                            </div>
                        </div>

                        {{-- SYARAT_MIN --}}
                        <div id="panel_syarat_min" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small fw-semibold mb-2"><i class="mdi mdi-counter me-1 text-secondary"></i>Parameter Syarat Minimal</p>
                            <p class="small text-muted mb-2">
                                Hitung kemunculan "Pola Item" di seluruh dokumen. Kalau jumlahnya &ge; Minimum, baru
                                "Pola Wajib Ada" diperiksa di Target Halaman di atas.
                            </p>
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Pola item yang dihitung</label>
                                    <input type="text" name="param_item_pattern" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_item_pattern', ($kriteria->parameter['item_pattern'] ?? '')) }}"
                                           placeholder="\b(?:Tabel|Table)\s+\d">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Minimum</label>
                                    <input type="number" name="param_min" class="form-control form-control-sm"
                                           value="{{ old('param_min', ($kriteria->parameter['min'] ?? 3)) }}"
                                           min="1">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Pola yang wajib ada jika syarat terpenuhi</label>
                                    <input type="text" name="param_require_pattern" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_require_pattern', ($kriteria->parameter['require_pattern'] ?? '')) }}"
                                           placeholder="Daftar\s+Tabel">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Flags</label>
                                    <input type="text" name="param_flags" class="form-control form-control-sm font-monospace"
                                           value="{{ old('param_flags', ($kriteria->parameter['flags'] ?? 'iu')) }}"
                                           placeholder="iu" maxlength="10">
                                </div>
                            </div>
                        </div>

                        {{-- MANUAL --}}
                        <div id="panel_manual" class="param-panel border rounded p-3 bg-light" style="display:none;">
                            <p class="small text-muted mb-0">
                                <i class="ti ti-info-circle me-1"></i>
                                Tipe <strong>manual</strong> tidak butuh parameter — kriteria ini selalu menghasilkan
                                <span class="badge bg-warning text-dark">PERLU DICEK</span> dan harus diperiksa manusia.
                            </p>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Pesan --}}
            <div class="card mb-3">
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
            <div class="card mb-4">
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
                            <i class="ti ti-device-floppy me-1"></i>
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

    // Beberapa panel memakai name field yang sama (mis. param_flags di regex/urutan_teks/syarat_min) —
    // nonaktifkan input di panel yang disembunyikan supaya tidak ikut terkirim dan menimpa nilai aktif.
    document.querySelectorAll('.param-panel').forEach(p => {
        p.style.display = 'none';
        p.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
    });

    const map = {
        regex:        'panel_regex',
        not_regex:    'panel_regex',
        contains:     'panel_contains',
        not_contains: 'panel_contains',
        posisi_area:  'panel_posisi_area',
        min_pages:    'panel_min_pages',
        urutan_teks:  'panel_urutan_teks',
        syarat_min:   'panel_syarat_min',
        manual:       'panel_manual',
        urutan:       'panel_urutan',
        sama_kover:   'panel_sama_kover',
        paritas:      'panel_paritas',
        romawi_genap: 'panel_romawi_genap',
        jumlah_min:   'panel_pola_hitung',
        nomor_urut:   'panel_pola_hitung',
        bab:          'panel_bab',
        bulan_rilis:  'panel_bulan_rilis',
        abjad:        'panel_pola_hitung',
        judul_kover:  'panel_judul_kover',
    };
    const target = map[tipe];
    if (target) {
        const panel = document.getElementById(target);
        panel.style.display = 'block';
        panel.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
    }
}

// Jalankan saat load
showParamPanel();
// Pastikan jika JS terlambat mount
document.getElementById('tipe_cek').value = currentTipe;
showParamPanel();
</script>
@endpush