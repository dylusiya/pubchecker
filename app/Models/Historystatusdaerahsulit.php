<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryStatusDaerahSulit extends Model
{
    use HasFactory;

    protected $table = 'history_status_daerah_sulit';

    const UPDATED_AT = null;

    protected $fillable = [
        'status_daerah_sulit_id',
        'master_sls_id',
        'tahun_anggaran',
        'aksi',
        'field_changed',
        'status_sebelum',
        'status_sesudah',
        'alasan_perubahan',
        'file_pendukung',
        'changed_by',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'status_sebelum' => 'array',
        'status_sesudah' => 'array',
    ];

    // Relations
    public function statusDaerahSulit()
    {
        return $this->belongsTo(StatusDaerahSulit::class, 'status_daerah_sulit_id');
    }

    public function masterSls()
    {
        return $this->belongsTo(MasterSls::class, 'master_sls_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // Scopes
    public function scopeByTahun($query, $tahun)
    {
        return $query->where('tahun_anggaran', $tahun);
    }

    public function scopeByAksi($query, $aksi)
    {
        return $query->where('aksi', $aksi);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('changed_by', $userId);
    }

    public function scopeBySls($query, $slsId)
    {
        return $query->where('master_sls_id', $slsId);
    }

    /**
     * Get aksi badge class
     */
    public function getAksiBadgeClassAttribute()
    {
        return match($this->aksi) {
            'create' => 'badge-opacity-success',
            'update' => 'badge-opacity-warning',
            'delete' => 'badge-opacity-danger',
            'approval' => 'badge-opacity-info',    
            'rejection' => 'badge-opacity-danger',  
            default => 'badge-opacity-secondary',
        };
    }

    /**
     * Get aksi display text
     */
    public function getAksiDisplayAttribute()
    {
        return match($this->aksi) {
            'create' => 'Tambah',
            'update' => 'Update',
            'delete' => 'Hapus',
            'approval' => 'Disetujui',
            'rejection' => 'Ditolak',
            default => ucfirst($this->aksi),
        };
    }

    /**
     * Get aksi icon
     */
    public function getAksiIconAttribute()
    {
        return match($this->aksi) {
            'create' => 'mdi-plus',
            'update' => 'mdi-pencil',
            'delete' => 'mdi-delete',
            'approval' => 'mdi-check',
            'rejection' => 'mdi-close',
            default => 'mdi-circle',
        };
    }

    /**
     * Get changed fields as array
     */
    public function getChangedFieldsArray()
    {
        if (!$this->field_changed) {
            return [];
        }

        return explode(',', $this->field_changed);
    }

    /**
     * Get formatted diff between before and after
     */
    public function getFormattedDiff()
    {
        if (!$this->status_sebelum || !$this->status_sesudah) {
            return [];
        }

        $diff = [];
        $before = $this->status_sebelum;
        $after = $this->status_sesudah;

        foreach ($after as $key => $value) {
            if (isset($before[$key]) && $before[$key] != $value) {
                $diff[$key] = [
                    'before' => $before[$key],
                    'after' => $value,
                ];
            } elseif (!isset($before[$key])) {
                $diff[$key] = [
                    'before' => null,
                    'after' => $value,
                ];
            }
        }

        return $diff;
    }

    /**
     * Get field label
     */
    public function getFieldLabel($field)
    {
        return match($field) {
            'is_daerah_sulit' => 'Status Kesulitan',
            'perkiraan_biaya' => 'Perkiraan Biaya',
            'metode_transportasi' => 'Metode Transportasi',
            'waktu_tempuh_menit' => 'Waktu Tempuh',
            'kondisi_akses' => 'Kondisi Akses',
            'keterangan' => 'Keterangan',
            'kegiatan' => 'Kegiatan',
            'status_approval' => 'Status Approval',
            'catatan_approval' => 'Catatan Approval',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
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

    /**
     * Static method to log history
     */
    public static function logHistory(
        $statusDaerahSulit,
        $aksi,
        $fieldChanged = null,
        $statusSebelum = null,
        $statusSesudah = null,
        $alasanPerubahan = null,
        $filePendukung = null
    ) {
        return static::create([
            'status_daerah_sulit_id' => $statusDaerahSulit->id ?? null,
            'master_sls_id' => $statusDaerahSulit->master_sls_id,
            'tahun_anggaran' => $statusDaerahSulit->tahun_anggaran,
            'aksi' => $aksi,
            'field_changed' => $fieldChanged,
            'status_sebelum' => $statusSebelum,
            'status_sesudah' => $statusSesudah,
            'alasan_perubahan' => $alasanPerubahan,
            'file_pendukung' => $filePendukung,
            'changed_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}