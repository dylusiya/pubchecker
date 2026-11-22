<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Ceklis langkah 5 pra rilis: PDF final publikasi SIPOTRET (sipotret_pub_id) sudah diupload ke portal publikasi BPS,
 * ditandai oleh user_id pada created_at. Tidak ada baris = belum ditandai.
 */
class PraRilisPortal extends Model
{
    protected $table = 'pra_rilis_portal';

    protected $fillable = ['sipotret_pub_id', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Tabel sudah dibuat (migrasi/SQL sudah dijalankan); belum → ceklis portal tidak tersedia. */
    public static function aktif(): bool
    {
        static $ada = null;
        return $ada ??= Schema::hasTable('pra_rilis_portal');
    }

    /** @return Collection<int, self> tanda per pub_id SIPOTRET */
    public static function untuk(iterable $pubIds): Collection
    {
        $ids = collect($pubIds)->filter()->map(fn($v) => (int) $v)->unique()->values();
        if ($ids->isEmpty() || !static::aktif()) return collect();
        return static::with('user:id,name,first_name,last_name')->whereIn('sipotret_pub_id', $ids)->get()->keyBy('sipotret_pub_id');
    }
}
