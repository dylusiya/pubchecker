@extends('layouts.admin')

@section('title', 'Edit Data Mikro')

@section('content')
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card card-rounded">
            <div class="card-body">
                <h4 class="card-title">Edit Data Mikro</h4>
                <p class="card-description">{{ $dataMikro->nama_data }}</p>
                
                <form action="{{ route('data-mikro.update', $dataMikro->id) }}" method="POST" class="forms-sample">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nama_data">Nama Data <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control @error('nama_data') is-invalid @enderror" 
                                       id="nama_data" 
                                       name="nama_data" 
                                       value="{{ old('nama_data', $dataMikro->nama_data) }}" 
                                       required>
                                @error('nama_data')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="survei_id">Survei</label>
                                <select class="form-control @error('survei_id') is-invalid @enderror" 
                                        id="survei_id" 
                                        name="survei_id">
                                    <option value="">-- Pilih Survei --</option>
                                    @foreach($surveiList as $survei)
                                        <option value="{{ $survei->id }}" 
                                                {{ old('survei_id', $dataMikro->survei_id) == $survei->id ? 'selected' : '' }}>
                                            [{{ $survei->kode }}] {{ $survei->nama }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Pilih survei untuk mengakses file pendukung</small>
                                @error('survei_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="wilayah_id">Wilayah <span class="text-danger">*</span></label>
                                <select class="form-control @error('wilayah_id') is-invalid @enderror" 
                                        id="wilayah_id" 
                                        name="wilayah_id" 
                                        required>
                                    <option value="">-- Pilih Wilayah --</option>
                                    @foreach($wilayahList as $wilayah)
                                        <option value="{{ $wilayah->id }}" 
                                                {{ old('wilayah_id', $dataMikro->wilayah_id) == $wilayah->id ? 'selected' : '' }}>
                                            [{{ $wilayah->kode }}] 
                                            @if($wilayah->jenis == 'provinsi')
                                                {{ $wilayah->nama }}
                                            @elseif($wilayah->jenis == 'kota')
                                                Kota {{ $wilayah->nama }}
                                            @else
                                                Kabupaten {{ $wilayah->nama }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('wilayah_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tahun">Tahun <span class="text-danger">*</span></label>
                                <input type="number" 
                                       class="form-control @error('tahun') is-invalid @enderror" 
                                       id="tahun" 
                                       name="tahun" 
                                       value="{{ old('tahun', $dataMikro->tahun) }}" 
                                       min="2000" 
                                       max="{{ date('Y') + 1 }}"
                                       required>
                                @error('tahun')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    <h5 class="mb-3">Link Data</h5>
                    
                    <div class="form-group">
                        <label for="data_link">Link Data <span class="text-danger">*</span></label>
                        <input type="url" 
                               class="form-control @error('data_link') is-invalid @enderror" 
                               id="data_link" 
                               name="data_link" 
                               value="{{ old('data_link', $dataMikro->data_link) }}" 
                               required>
                        <small class="text-muted">Link download data mikro (Google Drive, OneDrive, dll)</small>
                        @error('data_link')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="keterangan">Keterangan</label>
                        <textarea class="form-control @error('keterangan') is-invalid @enderror" 
                                  id="keterangan" 
                                  name="keterangan" 
                                  rows="3">{{ old('keterangan', $dataMikro->keterangan) }}</textarea>
                        @error('keterangan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="alert alert-info">
                        <i class="mdi mdi-information"></i>
                        <strong>Info:</strong> File pendukung (kuesioner, layout, surat, keterangan) dikelola melalui menu Survei.
                    </div>
                    
                    <hr class="my-4">
                    <h5 class="mb-3">Assign User</h5>
                    <p class="text-muted small">Pilih user yang dapat mengakses data ini</p>

                    <div class="form-group">
                        <label for="assigned_users">User yang Memiliki Akses</label>
                        <select class="form-control @error('assigned_users') is-invalid @enderror" 
                                id="assigned_users" 
                                name="assigned_users[]" 
                                multiple="multiple" 
                                style="width: 100%;">
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" 
                                        data-kode="{{ $user->kode_provinsi }}"
                                        data-wilayah="{{ $user->kabupaten }}"
                                        {{ in_array($user->id, old('assigned_users', $assignedUserIds)) ? 'selected' : '' }}>
                                    {{ $user->display_name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">
                            <i class="mdi mdi-information-outline"></i> 
                            Ketik nama, username, kode wilayah untuk mencari
                        </small>
                        @error('assigned_users')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary me-2">
                        <i class="mdi mdi-content-save"></i> Update
                    </button>
                    <a href="{{ route('data-mikro.show', $dataMikro->id) }}" class="btn btn-light">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#wilayah_id').select2({
        theme: 'bootstrap-5',
        placeholder: 'Pilih wilayah...',
        width: '100%'
    });

    $('#survei_id').select2({
        theme: 'bootstrap-5',
        placeholder: 'Pilih survei...',
        allowClear: true,
        width: '100%'
    });
    
    $('#assigned_users').select2({
        theme: 'bootstrap-5',
        placeholder: 'Pilih user...',
        allowClear: true,
        width: '100%',
        matcher: function(params, data) {
            if ($.trim(params.term) === '') {
                return data;
            }

            if (typeof data.text === 'undefined') {
                return null;
            }

            var searchTerm = params.term.toLowerCase();
            var text = data.text.toLowerCase();
            var kode = $(data.element).data('kode');
            var wilayah = $(data.element).data('wilayah');
            
            if (text.indexOf(searchTerm) > -1) {
                return data;
            }
            
            if (kode && kode.toString().indexOf(searchTerm) > -1) {
                return data;
            }
            
            if (wilayah && wilayah.toLowerCase().indexOf(searchTerm) > -1) {
                return data;
            }

            return null;
        }
    });
});
</script>
@endpush