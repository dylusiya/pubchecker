@extends('layouts.app')

@section('title', 'Import dari API BPS')

@section('content')
<div class="row">
    <div class="col-sm-12">

        {{-- Header --}}
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="card-title mb-1">
                            <i class="mdi mdi-cloud-download-outline text-primary me-2"></i>
                            Import Publikasi dari API BPS
                        </h4>
                        <p class="text-muted mb-0 small">
                            Cari publikasi dari portal BPS, lalu buka publikasinya untuk diperiksa satu per satu
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('checker.index') }}" class="btn btn-light btn-sm border">
                            <i class="mdi mdi-upload me-1"></i> Upload Manual
                        </a>
                        <a href="{{ route('checker.riwayat') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-history me-1"></i> Riwayat
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- API tidak terkonfigurasi --}}
        @if(!$configured)
        <div class="card card-rounded mb-3 border-danger">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <i class="mdi mdi-alert-circle text-danger" style="font-size:32px; flex-shrink:0;"></i>
                    <div>
                        <h5 class="text-danger mb-1">API Key BPS Belum Dikonfigurasi</h5>
                        <p class="text-muted small mb-2">
                            Tambahkan baris berikut ke file <code>.env</code> kamu, lalu jalankan
                            <code>php artisan config:clear</code>:
                        </p>
                        <pre class="bg-light rounded p-2 small mb-2">BPS_API_KEY=isi_api_key_kamu_di_sini
BPS_DOMAIN=6300</pre>
                        <p class="text-muted small mb-0">
                            Daftar dan dapatkan API Key di
                            <a href="https://webapi.bps.go.id/developer" target="_blank">
                                webapi.bps.go.id/developer <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Form Pencarian --}}
        <div class="card card-rounded mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3">Cari Publikasi BPS</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Domain / Kantor BPS</label>
                        <select id="searchDomain" class="form-select form-select-sm text-black">
                            @foreach($domains as $d)
                                <option value="{{ $d['domain_id'] }}"
                                    {{ $d['domain_id'] === $domain ? 'selected' : '' }}>
                                    {{ $d['domain_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Kata Kunci</label>
                        <input type="text" id="searchKeyword" class="form-control form-control-sm text-black"
                               placeholder="Contoh: statistik daerah">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">Tahun</label>
                        <select id="searchYear" class="form-select form-select-sm text-black">
                            <option value="">Semua</option>
                            @for($y = now()->year; $y >= 2015; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">Bulan</label>
                        <select id="searchMonth" class="form-select form-select-sm text-black">
                            <option value="">Semua</option>
                            @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-primary btn-sm w-100" onclick="doSearch(1)"
                                id="btnSearch" {{ !$configured ? 'disabled' : '' }}>
                            <i class="mdi mdi-magnify"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hasil Pencarian --}}
        <div id="searchSection" style="display:none;">
            <div class="card card-rounded mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="card-title mb-0">
                            Hasil Pencarian
                            <span class="badge bg-primary ms-1" id="searchTotal">0</span>
                        </h5>
                        <div class="d-flex gap-2 align-items-center">
                            <small class="text-muted" id="searchMeta"></small>
                        </div>
                    </div>

                    <div id="searchResults"></div>

                    {{-- Pagination --}}
                    <div id="paginationBar" class="d-flex justify-content-center gap-2 mt-3"
                         style="display:none !important;"></div>
                </div>
            </div>
        </div>

    </div>
</div>


@endsection

@push('styles')
<style>
    .pub-card { 
        border:1px solid #e8e9f0; 
        border-radius:8px; 
        padding:12px; 
        background:#fff; 
        transition:border-color .15s;
    }
    .pub-card:hover { border-color:#667eea; }
    .pub-card .pub-cover { 
        width:50px; 
        height:70px; 
        object-fit:cover; 
        border-radius:4px; 
        background:#f0f0f0; 
        flex-shrink:0; 
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .pub-card {
            flex-direction: column !important;
            gap: 10px !important;
        }
        
        .pub-card .pub-cover {
            width: 100%;
            height: 120px;
            max-width: 150px;
            margin: 0 auto;
        }
        
        .pub-card .flex-grow-1 {
            min-width: 100% !important;
        }
        
        .pub-card .fw-semibold {
            white-space: normal !important;
            text-overflow: initial !important;
            overflow: visible !important;
        }
        
        .pub-card .flex-shrink-0 {
            width: 100%;
            justify-content: center !important;
            flex-wrap: wrap;
        }
        
    }
    
    /* Tablet */
    @media (min-width: 769px) and (max-width: 1024px) {
        .pub-card .fw-semibold {
            font-size: 0.85rem;
        }
        
        .pub-card .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
    }
    
</style>
@endpush

@push('scripts')
<script>
const CSRF      = document.querySelector('meta[name=csrf-token]')?.content ?? '';
let currentPage = 1;
let searchItems = []; // hasil search terakhir

// ── SEARCH ────────────────────────────────────────────────────
async function doSearch(page = 1) {
    currentPage = page;
    const btn = document.getElementById('btnSearch');
    btn.disabled = true;
    btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i>';

    const body = {
        domain:  document.getElementById('searchDomain').value,
        keyword: document.getElementById('searchKeyword').value || null,
        year:    document.getElementById('searchYear').value    || null,
        month:   document.getElementById('searchMonth').value   || null,
        page,
        _token: CSRF,
    };

    try {
        const res  = await fetch('{{ route("checker.bps.search") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(body),
        });
        const data = await res.json();

        if (data.error) { showAlert('danger', data.error); return; }

        searchItems = data.items ?? [];
        renderSearchResults(data.meta, searchItems);
    } catch(e) {
        showAlert('danger', 'Pencarian gagal: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="mdi mdi-magnify"></i>';
    }
}

function renderSearchResults(meta, items) {
    document.getElementById('searchSection').style.display = 'block';
    document.getElementById('searchTotal').textContent = meta.total ?? items.length;
    document.getElementById('searchMeta').textContent =
        `Hal. ${meta.page ?? 1} / ${meta.pages ?? 1} (${meta.per_page ?? 10} per halaman)`;

    const box = document.getElementById('searchResults');

    if (!items.length) {
        box.innerHTML = `<div class="text-center py-4 text-muted">
            <i class="mdi mdi-file-search-outline d-block mb-2" style="font-size:36px;opacity:.3;"></i>
            Tidak ada publikasi ditemukan</div>`;
        renderPagination(meta);
        return;
    }

    box.innerHTML = items.map((pub, i) => {
        // Pemeriksaan dilakukan satu per satu di halaman detail publikasi
        const detailUrl = `{{ route('checker.bps.detail.show') }}?pub_id=${pub.pub_id}&domain=${pub.domain || document.getElementById('searchDomain').value}`;
        return `
    <div class="pub-card mb-2" id="pubcard-${i}">
        <div class="d-flex gap-3 align-items-start flex-wrap" style="cursor:pointer;"
             onclick="window.location='${detailUrl}'">
            ${pub.cover
                ? `<img src="${pub.cover}" class="pub-cover" alt="cover" onerror="this.style.display='none'">`
                : `<div class="pub-cover d-flex align-items-center justify-content-center text-muted" style="font-size:20px;"><i class="mdi mdi-file-pdf-box text-danger"></i></div>`
            }
            <div class="flex-grow-1 min-w-0" style="min-width:0;">
                <div class="fw-semibold small mb-1" style="word-break: break-word; overflow-wrap: break-word;" title="${pub.title}">
                    ${pub.title}
                </div>
                <div class="d-flex flex-wrap gap-2">
                    ${pub.issn ? `<span class="badge bg-secondary fw-normal">${pub.issn}</span>` : ''}
                    ${pub.rl_date ? `<small class="text-muted">${pub.rl_date}</small>` : ''}
                    ${pub.size ? `<small class="text-muted">${pub.size}</small>` : ''}
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center justify-content-end mt-2 flex-wrap">
            ${pub.has_pdf
                ? `<span class="badge bg-success fw-normal"><i class="mdi mdi-file-pdf-box me-1"></i>PDF</span>`
                : `<span class="badge bg-secondary fw-normal">Tanpa PDF</span>`
            }
            <a href="${detailUrl}" class="btn btn-primary btn-sm" title="Buka publikasi untuk diperiksa">
                <i class="mdi mdi-check-decagram"></i>
                <span class="d-none d-md-inline ms-1">Buka &amp; Periksa</span>
            </a>
            ${pub.has_pdf
                ? `<a href="${pub.pdf}" download class="btn btn-outline-success btn-sm" onclick="event.stopPropagation()" title="Download PDF">
                    <i class="mdi mdi-download"></i>
                    <span class="d-none d-md-inline ms-1">PDF</span>
                </a>`
                : ''
            }
        </div>
    </div>`
    }).join('');

    renderPagination(meta);
}

function renderPagination(meta) {
    const bar   = document.getElementById('paginationBar');
    const pages = meta.pages ?? 1;
    const page  = meta.page  ?? 1;
    if (pages <= 1) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    bar.innerHTML = '';
    const start = Math.max(1, page - 2);
    const end   = Math.min(pages, page + 2);
    if (page > 1) bar.innerHTML += `<button class="btn btn-sm btn-outline-primary" onclick="doSearch(${page-1})">‹</button>`;
    for (let p = start; p <= end; p++) {
        bar.innerHTML += `<button class="btn btn-sm ${p===page?'btn-primary':'btn-outline-primary'}" onclick="doSearch(${p})">${p}</button>`;
    }
    if (page < pages) bar.innerHTML += `<button class="btn btn-sm btn-outline-primary" onclick="doSearch(${page+1})">›</button>`;
}

function showAlert(type, msg) {
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show`;
    div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.querySelector('.content-wrapper').prepend(div);
    setTimeout(() => div.remove(), 6000);
}

// Auto-search jika API sudah dikonfigurasi
@if($configured)
document.addEventListener('DOMContentLoaded', () => doSearch(1));
@endif
</script>
@endpush