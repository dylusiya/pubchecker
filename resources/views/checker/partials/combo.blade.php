{{-- Combobox pencarian (js/combo.js): ketik kode atau nama, nilai yang dikirim = kode dari master.
     Parameter: $name, $value, $data (kode => nama), $placeholder, $kosong (teks pilihan kosongkan), $wajib (true = tanpa pilihan kosongkan),
     $id (opsional), $kode (false = kode cuma id, tampilkan & cari nama saja; mis. user). --}}
@php
    $value = (string) ($value ?? '');
    $kode  = $kode ?? true;
    $teks  = $value === '' ? '' : (isset($data[$value])
        ? ($kode ? $value . ($data[$value] ? ' · ' . $data[$value] : '') : ($data[$value] ?: $value))
        : $value . ' (tidak ada di master)');
@endphp
<div class="combo" @isset($id) id="{{ $id }}" @endisset data-combo='@json((object) $data)' data-kosong="{{ $kosong ?? 'Kosongkan' }}" data-kode="{{ $kode ? 1 : 0 }}" data-wajib="{{ !empty($wajib) ? 1 : 0 }}">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" class="combo-nilai">
    <div class="input-icon">
        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="text" class="form-control combo-cari" autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list"
               placeholder="{{ $placeholder ?? 'Ketik untuk mencari…' }}" value="{{ $teks }}">
    </div>
    <div class="combo-daftar list-group shadow" role="listbox" hidden></div>
</div>
@once
    @push('styles')
    <style>
        .combo { position: relative; }
        .combo-daftar { position: absolute; z-index: 1050; left: 0; right: 0; top: 100%; margin-top: 2px; max-height: 300px; overflow: auto; background: var(--tblr-bg-surface); }
        .combo-daftar mark { padding: 0; background: var(--tblr-yellow-lt); }
    </style>
    @endpush
    @push('scripts')
    <script src="{{ asset('js/combo.js') }}?v={{ filemtime(public_path('js/combo.js')) }}"></script>
    @endpush
@endonce
