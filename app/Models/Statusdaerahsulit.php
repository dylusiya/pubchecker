<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusDaerahSulit extends Model
{
    use SoftDeletes;
    
    protected $table = 'status_daerah_sulit';
    
    public $timestamps = true;
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
    protected $fillable = [
        'master_sls_id',
        'tahun_anggaran',
        'is_daerah_sulit',
        'perkiraan_biaya',
        'metode_transportasi',
        'waktu_tempuh_menit',
        'kondisi_akses',
        'keterangan',
        'kegiatan',
        'file_pendukung',
        'status_approval',
        'catatan_approval',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'is_daerah_sulit' => 'boolean',
        'perkiraan_biaya' => 'decimal:2',
        'waktu_tempuh_menit' => 'integer',
        'tahun_anggaran' => 'integer',
        'approved_at' => 'datetime',
    ];

    /**
     * Relasi: Master SLS
     */
    public function masterSls()
    {
        return $this->belongsTo(MasterSls::class, 'master_sls_id');
    }

    /**
     * Relasi: User yang membuat
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi: User yang mengupdate
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relasi: User yang approve
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relasi: History perubahan
     */
    public function histories()
    {
        return $this->hasMany(HistoryStatusDaerahSulit::class, 'status_daerah_sulit_id')
                    ->latest('created_at');
    }

    /**
     * Scope: Filter by tahun anggaran
     */
    public function scopeByTahun($query, $tahun)
    {
        return $query->where('tahun_anggaran', $tahun);
    }

    /**
     * Scope: Only daerah sulit
     */
    public function scopeSulitOnly($query)
    {
        return $query->where('is_daerah_sulit', true);
    }

    /**
     * Scope: Only tidak sulit
     */
    public function scopeTidakSulitOnly($query)
    {
        return $query->where('is_daerah_sulit', false);
    }

    /**
     * Scope: By status approval
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status_approval', $status);
    }

    /**
     * Scope: Only approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status_approval', 'disetujui');
    }

    /**
     * Scope: Only draft
     */
    public function scopeDraft($query)
    {
        return $query->where('status_approval', 'draft');
    }

    /**
     * Scope: Only menunggu review
     */
    public function scopePendingReview($query)
    {
        return $query->where('status_approval', 'menunggu_review');
    }

    /**
     * Scope: Filter by kabupaten
     */
    public function scopeByKabupaten($query, $kdkab)
    {
        return $query->whereHas('masterSls', function($q) use ($kdkab) {
            $q->where('kdkab', $kdkab);
        });
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        return match($this->status_approval) {
            'disetujui' => 'badge-opacity-success',
            'ditolak' => 'badge-opacity-danger',
            'menunggu_review' => 'badge-opacity-warning',
            'draft' => 'badge-opacity-secondary',
            default => 'badge-opacity-secondary',
        };
    }

    /**
     * Get status display
     */
    public function getStatusDisplayAttribute()
    {
        return match($this->status_approval) {
            'draft' => 'Draft',
            'pending' => 'Menunggu Review',  // ✅ Ganti key
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            default => ucfirst($this->status_approval),
        };
    }

    /**
     * Get kesulitan badge class
     */
    public function getKesulitanBadgeClassAttribute()
    {
        return $this->is_daerah_sulit 
            ? 'badge-opacity-danger' 
            : 'badge-opacity-success';
    }

    /**
     * Get kesulitan display text
     */
    public function getKesulitanDisplayAttribute()
    {
        return $this->is_daerah_sulit 
            ? 'Daerah Sulit' 
            : 'Tidak Sulit';
    }

    /**
     * Get formatted biaya
     */
    public function getBiayaFormattedAttribute()
    {
        return 'Rp ' . number_format($this->perkiraan_biaya, 0, ',', '.');
    }

    /**
     * Get waktu tempuh display
     */
    public function getWaktuTempuhDisplayAttribute()
    {
        if ($this->waktu_tempuh_menit < 60) {
            return $this->waktu_tempuh_menit . ' menit';
        }
        
        $hours = floor($this->waktu_tempuh_menit / 60);
        $minutes = $this->waktu_tempuh_menit % 60;
        
        if ($minutes == 0) {
            return $hours . ' jam';
        }
        
        return $hours . ' jam ' . $minutes . ' menit';
    }

    /**
     * Check if can be edited
     */
    public function canEdit()
    {
        return in_array($this->status_approval, ['draft', 'ditolak']);
    }

    /**
     * Check if can be deleted
     */
    public function canDelete()
    {
        return $this->status_approval === 'draft';
    }

    /**
     * Check if status can be submitted
     */
    public function canSubmit()
    {
        return in_array($this->status_approval, ['draft', 'ditolak']);
    }

    /**
     * Check if status can be approved/rejected
     */
    public function canApprove()
    {
        return $this->status_approval === 'pending';
    }

    /**
     * Get file path
     */
    public function getFilePathAttribute()
    {
        if (!$this->file_pendukung) {
            return null;
        }

        return storage_path('app/public/' . $this->file_pendukung);
    }

    /**
     * Get file URL
     */
    public function getFileUrlAttribute()
    {
        if (!$this->file_pendukung) {
            return null;
        }

        return asset('storage/' . $this->file_pendukung);
    }

    /**
     * Check if has file
     */
    public function hasFile()
    {
        return $this->file_pendukung !== null;
    }
}