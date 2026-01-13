<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterSls extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'master_sls';

    protected $fillable = [
        'idsls',
        'nmsls',
        'nama_ketua',
        'jenis',
        'kdprov',
        'kdkab',
        'kdkec',
        'kddesa',
        'kdsls',
        'nmprov',
        'nmkab',
        'nmkec',
        'nmdesa',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Relasi: Status Daerah Sulit untuk SLS ini
     */
    public function statusDaerahSulit()
    {
        return $this->hasMany(StatusDaerahSulit::class, 'master_sls_id');
    }

    /**
     * Relasi: Status Daerah Sulit untuk tahun tertentu
     */
    public function statusByYear($tahun)
    {
        return $this->hasOne(StatusDaerahSulit::class, 'master_sls_id')
                    ->where('tahun_anggaran', $tahun)
                    ->whereNull('deleted_at');
    }

    /**
     * Relasi: History perubahan untuk SLS ini
     */
    public function historyStatusDaerahSulit()
    {
        return $this->hasMany(HistoryStatusDaerahSulit::class, 'master_sls_id');
    }

    /**
     * Scope: Filter by kabupaten
     */
    public function scopeByKabupaten($query, $kdkab)
    {
        return $query->where('kdkab', $kdkab);
    }

    /**
     * Scope: Filter by kecamatan
     */
    public function scopeByKecamatan($query, $kdkec)
    {
        return $query->where('kdkec', $kdkec);
    }

    /**
     * Scope: Filter by desa
     */
    public function scopeByDesa($query, $kddesa)
    {
        return $query->where('kddesa', $kddesa);
    }

    /**
     * Scope: Only active SLS
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only SLS type (bukan Non SLS)
     */
    public function scopeSlsOnly($query)
    {
        return $query->where('jenis', 'SLS');
    }

    /**
     * Scope: Only Non SLS
     */
    public function scopeNonSlsOnly($query)
    {
        return $query->where('jenis', '!=', 'SLS');
    }

    /**
     * Scope: By jenis
     */
    public function scopeByJenis($query, $jenis)
    {
        return $query->where('jenis', $jenis);
    }

    /**
     * Get jenis display name
     */
    public function getJenisDisplayAttribute()
    {
        return match($this->jenis) {
            'SLS' => 'SLS',
            'NONSLS_BUKAN_PEMUKIMAN' => 'Non SLS - Bukan Pemukiman',
            'NONSLS_BUKAN_PERTANIAN' => 'Non SLS - Bukan Pertanian',
            'NONSLS_LAHAN_TERBUKA' => 'Non SLS - Lahan Terbuka',
            'NONSLS_PEMUKIMAN' => 'Non SLS - Pemukiman',
            'NONSLS_PERAIRAN' => 'Non SLS - Perairan',
            'NONSLS_PERTANIAN' => 'Non SLS - Pertanian',
            default => $this->jenis,
        };
    }

    /**
     * Get full hierarchical name
     */
    public function getFullHierarchyAttribute()
    {
        return "{$this->nmprov} » {$this->nmkab} » Kec. {$this->nmkec} » {$this->nmdesa} » {$this->nmsls}";
    }

    /**
     * Get short wilayah (Kabupaten - Kecamatan - Desa)
     */
    public function getWilayahShortAttribute()
    {
        return "{$this->nmkab} » Kec. {$this->nmkec} » {$this->nmdesa}";
    }

    /**
     * Check if SLS has coordinates
     */
    public function hasCoordinates()
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Get coordinate display
     */
    public function getCoordinateDisplayAttribute()
    {
        if ($this->hasCoordinates()) {
            return "{$this->latitude}, {$this->longitude}";
        }
        return 'Koordinat belum diisi';
    }
}