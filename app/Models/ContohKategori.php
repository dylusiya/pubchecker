<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Contoh yang benar (gambar atau PDF) untuk satu kategori kriteria (mis. "1.Kover Depan"),
 * ditampilkan di panel tinjauan saat petugas memeriksa kategori tersebut.
 */
class ContohKategori extends Model
{
    protected $table = 'contoh_kategori';

    /** Disk privat (storage/app) — lihat HasilPemeriksaan::DISK. */
    public const DISK = 'local';

    protected $fillable = [
        'kategori',
        'path',
        'keterangan',
        'urutan',
    ];

    /** Contoh berupa PDF (selain gambar JPG/PNG/WEBP), dilihat dari ekstensi file tersimpan. */
    public function isPdf(): bool
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function scopeUrut($q)
    {
        return $q->orderBy('kategori')->orderBy('urutan')->orderBy('id');
    }
}
