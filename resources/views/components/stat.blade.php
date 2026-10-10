{{--
    Kartu statistik gaya Tabler: label kecil kapital, angka besar, ikon berwarna lembut.
    Contoh: <x-stat label="Total Publikasi" :value="$n" icon="files" color="blue" sub="File diperiksa" />
    `value-id` dipakai bila angka diperbarui lewat JS.
--}}
@props(['label', 'value' => 0, 'icon' => null, 'color' => 'blue', 'sub' => null, 'valueId' => null])

<div {{ $attributes->merge(['class' => 'card card-stat']) }}>
    <div class="card-body">
        <div class="d-flex align-items-center">
            <div class="subheader">{{ $label }}</div>
            @if($icon)
                <span class="ms-auto avatar avatar-sm bg-{{ $color }}-lt"><i class="ti ti-{{ $icon }} fs-2"></i></span>
            @endif
        </div>
        <div class="h1 mb-0 mt-2" @if($valueId) id="{{ $valueId }}" @endif>{{ $value }}</div>
        @if($sub)
            <div class="text-secondary mt-1">{{ $sub }}</div>
        @endif
    </div>
</div>
