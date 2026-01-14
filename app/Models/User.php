<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'username',
        'nip',
        'nip_baru',
        'kode_organisasi',
        'kode_provinsi',
        'kode_kabupaten',
        'provinsi',
        'kabupaten',
        'golongan',
        'jabatan',
        'eselon',
        'foto',
        'alamat_kantor',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relasi: Status Daerah Sulit yang dibuat oleh user ini
     */
    public function createdStatusDaerahSulit()
    {
        return $this->hasMany(StatusDaerahSulit::class, 'created_by');
    }

    /**
     * Relasi: Status Daerah Sulit yang diupdate oleh user ini
     */
    public function updatedStatusDaerahSulit()
    {
        return $this->hasMany(StatusDaerahSulit::class, 'updated_by');
    }

    /**
     * Relasi: Status Daerah Sulit yang diapprove oleh user ini
     */
    public function approvedStatusDaerahSulit()
    {
        return $this->hasMany(StatusDaerahSulit::class, 'approved_by');
    }

    /**
     * Relasi: History yang dicatat atas nama user ini
     */
    public function historyStatusDaerahSulit()
    {
        return $this->hasMany(HistoryStatusDaerahSulit::class, 'user_id');
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is approver
     */
    public function isApprover()
    {
        return $this->role === 'approver';
    }

    /**
     * Check if user can approve
     */
    public function canApproveDaerahSulit()
    {
        return in_array($this->role, ['admin', 'approver']);
    }

    /**
     * Check if user is from provinsi level
     */
    public function isProvinsi()
    {
        return $this->kode_kabupaten === null || $this->kode_kabupaten === '00';
    }

    /**
     * Check if user is from kabupaten/kota level
     */
    public function isKabupaten()
    {
        return $this->kode_kabupaten !== null && $this->kode_kabupaten !== '00';
    }

    /**
     * Get accessible SLS based on user's kabupaten
     * Jika user dari provinsi, bisa akses semua
     * Jika user dari kabupaten, hanya bisa akses SLS di kabupatennya
     */
    public function accessibleSls()
    {
        $query = MasterSls::query()->where('is_active', true);

        // Jika user bukan admin dan dari kabupaten tertentu
        if (!$this->isAdmin() && $this->isKabupaten()) {
            $query->where('kdkab', $this->kode_kabupaten);
        }

        return $query;
    }

    /**
     * Get accessible Status Daerah Sulit based on user's kabupaten
     */
    public function accessibleStatusDaerahSulit($tahun = null)
    {
        $query = StatusDaerahSulit::query()->whereNull('deleted_at');

        // Filter by tahun if provided
        if ($tahun) {
            $query->where('tahun_anggaran', $tahun);
        }

        // Jika user bukan admin dan dari kabupaten tertentu
        if (!$this->isAdmin() && $this->isKabupaten()) {
            $query->whereHas('masterSls', function($q) {
                $q->where('kdkab', $this->kode_kabupaten);
            });
        }

        return $query;
    }

    /**
     * Get full name (first_name + last_name or name)
     */
    public function getFullNameAttribute()
    {
        if ($this->first_name && $this->last_name) {
            return $this->first_name . ' ' . $this->last_name;
        }
        return $this->name;
    }

    /**
     * Get user's wilayah display name
     */
    public function getWilayahDisplayAttribute()
    {
        if ($this->isKabupaten()) {
            return $this->kabupaten;
        }
        return $this->provinsi ?? 'Kalimantan Selatan';
    }

    /**
     * Get provinsi code untuk query MasterSLS (2 digit)
     */
    public function getKdProvAttribute()
    {
        if (!$this->kode_provinsi) {
            return null;
        }
        // 6300 atau 6301 -> 63
        return substr($this->kode_provinsi, 0, 2);
    }

    /**
     * Get kabupaten code untuk query MasterSLS (2 digit)
     */
    public function getKdKabAttribute()
    {
        if (!$this->kode_kabupaten) {
            return null;
        }
        // 6301 -> 01
        return substr($this->kode_kabupaten, 2, 2);
    }

    /**
     * Scope untuk mendapatkan SLS yang bisa diakses user
     */
    public function scopeAccessibleSls($query)
    {
        return $this->accessibleSlsQuery();
    }

    /**
     * Query builder untuk SLS yang accessible
     */
    public function accessibleSlsQuery()
    {
        if ($this->isAdmin()) {
            return \App\Models\MasterSls::query();
        }
        
        $slsQuery = \App\Models\MasterSls::query();
        
        if ($this->kode_kabupaten) {
            $slsQuery->where('kdkab', $this->kdkab);
        } elseif ($this->kode_provinsi) {
            $slsQuery->where('kdprov', $this->kdprov);
        }
        
        return $slsQuery;
    }
}