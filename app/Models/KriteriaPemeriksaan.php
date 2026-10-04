<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KriteriaPemeriksaan extends Model
{
    protected $table = 'kriteria_pemeriksaan';

    protected $fillable = [
        'kode', 'kategori', 'deskripsi',
        'tipe_cek', 'parameter', 'target',
        'status_gagal', 'pesan_ok', 'pesan_gagal',
        'aktif', 'urutan',
    ];

    protected $casts = [
        'parameter' => 'array',
        'aktif'     => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────
    public function scopeAktif($q)
    {
        return $q->where('aktif', true)->orderBy('urutan');
    }

    // ── Helpers ───────────────────────────────────────────────
    public static function tipeOptions(): array
    {
        return [
            'regex'        => 'Regex — cocokkan pola teks',
            'contains'     => 'Contains — cari teks tertentu',
            'not_contains' => 'Not Contains — teks TIDAK boleh ada',
            'posisi_area'  => 'Posisi Area — cek letak kata di halaman',
            'min_pages'    => 'Jumlah Halaman — cek min. halaman PDF',
            'manual'       => 'Manual — selalu PERLU DICEK (cek manusia)',
        ];
    }

    public static function targetOptions(): array
    {
        return [
            'cover' => 'Halaman 1 (Kover)',
            'page2' => 'Halaman 2',
            'all'   => 'Semua halaman',
        ];
    }

    public static function areaOptions(): array
    {
        return [
            'top'           => 'Atas (30% teratas)',
            'top_right'     => 'Kanan atas',
            'bottom'        => 'Bawah (40% terbawah)',
            'bottom_left'   => 'Kiri bawah',
            'below_katalog' => 'Di bawah Nomor Katalog',
        ];
    }
}