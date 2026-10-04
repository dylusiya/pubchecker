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
        'ditinjau_at',
    ];

    protected $casts = [
        'ditinjau_at' => 'datetime',
    ];

    public function hasil()
    {
        return $this->belongsTo(HasilPemeriksaan::class, 'hasil_id');
    }

    // ── Konstanta status ──────────────────────────────────────
    const STATUS_OK              = 'OK';
    const STATUS_PERLU_DICEK     = 'PERLU DICEK';
    const STATUS_TIDAK_ADA       = 'TIDAK ADA';
    const STATUS_TIDAK_DIPERIKSA = 'TIDAK DIPERIKSA';
}