<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SesiPemeriksaan extends Model
{
    protected $table = 'sesi_pemeriksaan';

    protected $fillable = [
        'uuid',
        'nama_sesi',
        'total_file',
        'total_ok',
        'total_warn',
        'total_err',
        'dibuat_oleh',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function hasilPemeriksaan()
    {
        return $this->hasMany(HasilPemeriksaan::class, 'sesi_id');
    }

    /**
     * Hitung ulang summary dari hasil yang ada, lalu simpan.
     */
    public function recalcSummary(): void
    {
        $hasil = $this->hasilPemeriksaan()->get();

        $this->total_file = $hasil->count();
        $this->total_ok   = $hasil->where('status_akhir', 'ok')->count();
        $this->total_warn = $hasil->where('status_akhir', 'perlu_dicek')->count();
        $this->total_err  = $hasil->where('status_akhir', 'masalah')->count();
        $this->save();
    }
}