<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
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
        'user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            // pemilik sesi = user yang sedang login (kolom user_id ada setelah migrasi 2026_11_22_100000)
            if (empty($model->user_id) && auth()->check() && static::adaKolomUser()) {
                $model->user_id = auth()->id();
            }
        });
    }

    /** Kolom user_id sudah ada (migrasi sudah dijalankan); belum → kepemilikan dicocokkan lewat nama (dibuat_oleh). */
    public static function adaKolomUser(): bool
    {
        static $ada = null;
        return $ada ??= Schema::hasColumn('sesi_pemeriksaan', 'user_id');
    }

    /** Sesi yang boleh dilihat user ini: admin semua; selain itu milik sendiri (user_id, atau nama untuk sesi lama). */
    public function scopeMilik(Builder $q, ?User $user): Builder
    {
        if ($user?->isAdmin()) return $q;
        if (!$user) return $q->whereRaw('1 = 0');
        return $q->where(function ($w) use ($user) {
            if (static::adaKolomUser()) {
                $w->where('user_id', $user->id)
                  ->orWhere(fn($x) => $x->whereNull('user_id')->where('dibuat_oleh', $user->name));
            } else {
                $w->where('dibuat_oleh', $user->name);
            }
        });
    }

    /** Boleh dilihat / diolah user ini (lihat scopeMilik). */
    public function boleh(?User $user): bool
    {
        if ($user?->isAdmin()) return true;
        if (!$user) return false;
        if (static::adaKolomUser() && $this->user_id) return (int) $this->user_id === (int) $user->id;
        return $this->dibuat_oleh === $user->name;
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