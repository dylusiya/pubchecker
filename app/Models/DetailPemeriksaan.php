<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPemeriksaan extends Model
{
    protected $table      = 'detail_pemeriksaan';
    public    $timestamps = false;

    protected $fillable = [
        'hasil_id',
        'kriteria_id',
        'kategori',
        'deskripsi',
        'status',
        'catatan',
        'keterangan',
        'ditinjau_at',
        'lokasi',
    ];

    protected $casts = [
        'ditinjau_at' => 'datetime',
        'lokasi'      => 'array',
    ];

    /** Untuk insert massal (insert() tidak melewati cast). */
    public static function encodeLokasi(?array $lokasi): ?string
    {
        return $lokasi ? json_encode(array_values($lokasi), JSON_UNESCAPED_UNICODE) : null;
    }

    public function hasil()
    {
        return $this->belongsTo(HasilPemeriksaan::class, 'hasil_id');
    }

    // ── Konstanta status ──────────────────────────────────────
    // Hasil cek otomatis: OK / PERLU DICEK / TIDAK ADA / TIDAK DIPERIKSA.
    // Verifikasi petugas: OK (Sesuai) / TIDAK SESUAI (wajib keterangan) / TIDAK DIPERIKSA (Skip).
    const STATUS_OK              = 'OK';
    const STATUS_PERLU_DICEK     = 'PERLU DICEK';
    const STATUS_TIDAK_ADA       = 'TIDAK ADA';
    const STATUS_TIDAK_SESUAI    = 'TIDAK SESUAI';
    const STATUS_TIDAK_DIPERIKSA = 'TIDAK DIPERIKSA';

    /** Status yang bisa dipilih petugas di panel tinjauan. */
    const STATUS_VERIFIKASI = [self::STATUS_OK, self::STATUS_TIDAK_SESUAI, self::STATUS_TIDAK_DIPERIKSA];

    /** Status yang dihitung sebagai masalah (kolom total_tidak_ada / status akhir "masalah"). */
    const STATUS_MASALAH = [self::STATUS_TIDAK_ADA, self::STATUS_TIDAK_SESUAI];
}