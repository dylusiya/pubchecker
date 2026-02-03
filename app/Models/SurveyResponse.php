<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'email',
        'nomor_hp',
        'jenis_kelamin',
        'kelompok_umur',
        'pendidikan',
        'pekerjaan',
        'pekerjaan_lainnya',
        'kategori_instansi',
        'kategori_instansi_lainnya',
        'nama_instansi',
        'penyandang_disabilitas',
        'jenis_disabilitas',
        'tujuan_penggunaan',
        'tujuan_lainnya',
        'jenis_layanan',
        'sarana_layanan',
        'sarana_lainnya',
        
        // Rating Kepentingan
        'informasi_pelayanan_kepentingan',
        'persyaratan_kepentingan',
        'prosedur_kepentingan',
        'jangka_waktu_kepentingan',
        'biaya_kepentingan',
        'produk_kepentingan',
        'sarana_kepentingan',
        'akses_data_kepentingan',
        'respons_petugas_kepentingan',
        'informasi_petugas_kepentingan',
        'fasilitas_pengaduan_kepentingan',
        'diskriminasi_kepentingan',
        'kecurangan_kepentingan',
        'gratifikasi_kepentingan',
        'pungli_kepentingan',
        'percaloan_kepentingan',
        
        // Rating Kepuasan
        'informasi_pelayanan_kepuasan',
        'persyaratan_kepuasan',
        'prosedur_kepuasan',
        'jangka_waktu_kepuasan',
        'biaya_kepuasan',
        'produk_kepuasan',
        'sarana_kepuasan',
        'akses_data_kepuasan',
        'respons_petugas_kepuasan',
        'informasi_petugas_kepuasan',
        'fasilitas_pengaduan_kepuasan',
        'diskriminasi_kepuasan',
        'kecurangan_kepuasan',
        'gratifikasi_kepuasan',
        'pungli_kepuasan',
        'percaloan_kepuasan',
        
        'catatan_tambahan',
        'tanggal_submit',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'tanggal_submit' => 'datetime',
    ];

    /**
     * Check if response is draft
     */
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    /**
     * Check if response is completed
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'tanggal_submit' => now(),
        ]);
    }

    /**
     * Relasi ke data entries
     */
    public function dataEntries()
    {
        return $this->hasMany(SurveyDataEntry::class);
    }

    /**
     * Hitung rata-rata kepentingan
     */
    public function getAverageKepentinganAttribute()
    {
        $fields = [
            'informasi_pelayanan_kepentingan',
            'persyaratan_kepentingan',
            'prosedur_kepentingan',
            'jangka_waktu_kepentingan',
            'biaya_kepentingan',
            'produk_kepentingan',
            'sarana_kepentingan',
            'akses_data_kepentingan',
            'respons_petugas_kepentingan',
            'informasi_petugas_kepentingan',
            'fasilitas_pengaduan_kepentingan',
            'diskriminasi_kepentingan',
            'kecurangan_kepentingan',
            'gratifikasi_kepentingan',
            'pungli_kepentingan',
            'percaloan_kepentingan',
        ];

        $total = 0;
        foreach ($fields as $field) {
            $total += $this->$field;
        }

        return round($total / count($fields), 2);
    }

    /**
     * Hitung rata-rata kepuasan
     */
    public function getAverageKepuasanAttribute()
    {
        $fields = [
            'informasi_pelayanan_kepuasan',
            'persyaratan_kepuasan',
            'prosedur_kepuasan',
            'jangka_waktu_kepuasan',
            'biaya_kepuasan',
            'produk_kepuasan',
            'sarana_kepuasan',
            'akses_data_kepuasan',
            'respons_petugas_kepuasan',
            'informasi_petugas_kepuasan',
            'fasilitas_pengaduan_kepuasan',
            'diskriminasi_kepuasan',
            'kecurangan_kepuasan',
            'gratifikasi_kepuasan',
            'pungli_kepuasan',
            'percaloan_kepuasan',
        ];

        $total = 0;
        foreach ($fields as $field) {
            $total += $this->$field;
        }

        return round($total / count($fields), 2);
    }

    /**
     * Scope untuk filter berdasarkan tanggal
     */
    public function scopeFilterByDate($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('tanggal_submit', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('tanggal_submit', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope untuk filter berdasarkan kategori instansi
     */
    public function scopeFilterByInstansi($query, $kategori)
    {
        if ($kategori) {
            $query->where('kategori_instansi', $kategori);
        }

        return $query;
    }
}