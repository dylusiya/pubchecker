@extends('layouts.admin')

@section('title', 'Detail Survei')

@section('content')
<div class="row">
    <div class="col-12">
        <!-- Header Info Survei -->
        <div class="card card-rounded mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="card-title card-title-dash mb-2">{{ $survei->nama }}</h4>
                        <p class="card-subtitle card-subtitle-dash mb-0">
                            <span class="badge badge-opacity-primary me-2">{{ $survei->kode }}</span>
                            @if($survei->tahun)
                                <span class="badge badge-opacity-info">Tahun {{ $survei->tahun }}</span>
                            @endif
                        </p>
                        @if($survei->deskripsi)
                            <p class="text-muted mt-2 mb-1"><strong>Deskripsi:</strong> {{ $survei->deskripsi }}</p>
                        @endif
                        @if($survei->keterangan)
                            <p class="text-muted mb-0"><strong>Keterangan:</strong> {{ $survei->keterangan }}</p>
                        @endif
                    </div>
                    <div>
                        <a href="{{ route('survei.edit', $survei->id) }}" class="btn btn-primary me-2">
                            <i class="mdi mdi-pencil"></i> Edit
                        </a>
                        <a href="{{ route('survei.index') }}" class="btn btn-light">
                            <i class="mdi mdi-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- File Pendukung -->
        <div class="card card-rounded mb-4">
            <div class="card-body">
                <h4 class="card-title card-title-dash">
                    <i class="mdi mdi-file-document text-primary"></i> File Pendukung
                </h4>
                <p class="card-subtitle card-subtitle-dash">Upload file kuesioner, layout, dan surat untuk survei ini. Bisa upload lebih dari satu file untuk setiap jenis.</p>
                
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <i class="mdi mdi-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                
                <div class="row mt-4">
                    @php
                        $attachmentTypes = [
                            'kuesioner' => ['label' => 'Kuesioner', 'icon' => 'mdi-file-document-outline', 'color' => 'primary'],
                            'layout' => ['label' => 'Layout', 'icon' => 'mdi-file-chart-outline', 'color' => 'success'],
                            'surat' => ['label' => 'Surat', 'icon' => 'mdi-email-outline', 'color' => 'warning'],
                            'lainnya' => ['label' => 'Lainnya', 'icon' => 'mdi-file-multiple-outline', 'color' => 'secondary'],
                        ];
                    @endphp
                    
                    @foreach($attachmentTypes as $type => $config)
                    <div class="col-md-6 mb-3">
                        <div class="card" style="border: 2px dashed #e0e0e0;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="mb-0">
                                        <i class="mdi {{ $config['icon'] }} text-{{ $config['color'] }} me-2"></i>
                                        {{ $config['label'] }}
                                        @if($type == 'lainnya')
                                            <small class="text-muted">(optional)</small>
                                        @endif
                                    </h5>
                                    <span class="badge badge-opacity-{{ $config['color'] }}">
                                        {{ $survei->getAttachmentsByType($type)->count() }} file
                                    </span>
                                </div>
                                
                                @php
                                    $attachments = $survei->getAttachmentsByType($type);
                                @endphp
                                
                                @if($attachments->count() > 0)
                                    <!-- File-file yang sudah ada -->
                                    <div class="mb-3" style="max-height: 200px; overflow-y: auto;">
                                        @foreach($attachments as $attachment)
                                        <div class="alert alert-success alert-sm mb-2 p-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <small>
                                                        <strong>{{ $attachment->file_name }}</strong><br>
                                                        {{ $attachment->file_size_human }} • {{ $attachment->created_at->format('d M Y') }}
                                                    </small>
                                                </div>
                                                <div class="ms-2">
                                                    <a href="{{ route('survei.download-attachment', [$survei->id, $attachment->id]) }}" 
                                                    class="btn btn-xs btn-success me-1"
                                                    title="Download">
                                                        <i class="mdi mdi-download"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-xs btn-danger"
                                                            onclick="deleteAttachment({{ $survei->id }}, {{ $attachment->id }})"
                                                            title="Hapus">
                                                        <i class="mdi mdi-delete"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="alert alert-warning mb-3 p-2">
                                        <small><i class="mdi mdi-alert-circle-outline me-1"></i> Belum ada file</small>
                                    </div>
                                @endif
                                
                                <!-- Form Upload -->
                                <form class="upload-form" data-type="{{ $type }}" onsubmit="return uploadFile(event, {{ $survei->id }}, '{{ $type }}')">
                                    @csrf
                                    <div class="input-group input-group-sm">
                                        <input type="file" 
                                            class="form-control form-control-sm" 
                                            name="file" 
                                            id="file-{{ $type }}"
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                                        <button type="submit" class="btn btn-{{ $config['color'] }} btn-sm">
                                            <i class="mdi mdi-upload"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Max 10MB</small>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Data Mikro yang Menggunakan Survei Ini -->
        <div class="card card-rounded">
            <div class="card-body">
                <h4 class="card-title card-title-dash">
                    <i class="mdi mdi-database text-primary"></i> Data Mikro Terkait
                </h4>
                <p class="card-subtitle card-subtitle-dash">Data mikro yang menggunakan survei ini ({{ $survei->dataMikro->count() }} data)</p>
                
                @if($survei->dataMikro->count() > 0)
                    <div class="table-responsive mt-3">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama Data</th>
                                    <th>Wilayah</th>
                                    <th>Tahun</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($survei->dataMikro as $data)
                                <tr>
                                    <td>{{ $data->nama_data }}</td>
                                    <td>
                                        @if($data->wilayah)
                                            <span class="badge badge-opacity-primary">{{ $data->wilayah->nama }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-opacity-info">{{ $data->tahun }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('data-mikro.show', $data->id) }}" class="btn btn-sm btn-info text-white">
                                            <i class="mdi mdi-eye"></i> Lihat
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="mdi mdi-database-off" style="font-size: 48px; color: #ccc;"></i>
                        <p class="text-muted mt-2">Belum ada data mikro yang menggunakan survei ini</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function uploadFile(event, surveiId, type) {
    event.preventDefault();
    
    const form = event.target;
    const fileInput = form.querySelector('input[type="file"]');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Pilih file terlebih dahulu');
        return false;
    }

    if (file.size > 10 * 1024 * 1024) {
        alert('Ukuran file maksimal 10MB');
        return false;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);
    formData.append('_token', '{{ csrf_token() }}');
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i>';
    
    // Pakai route helper - auto include base path
    const uploadUrl = '{{ route("survei.upload-attachment", ":id") }}'.replace(':id', surveiId);
    
    fetch(uploadUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || 'Terjadi kesalahan');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('File berhasil diupload!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    });
    
    return false;
}

function deleteAttachment(surveiId, attachmentId) {
    if (!confirm('Yakin ingin menghapus file ini?')) {
        return;
    }
    
    // Pakai route helper
    const deleteUrl = '{{ route("survei.delete-attachment", [":surveiId", ":attachmentId"]) }}'
        .replace(':surveiId', surveiId)
        .replace(':attachmentId', attachmentId);
    
    fetch(deleteUrl, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('File berhasil dihapus');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat menghapus file');
    });
}
</script>
@endpush