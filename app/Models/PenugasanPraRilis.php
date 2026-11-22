<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Publikasi SIPOTRET (publikasi.pub_id) yang pemeriksaan pra rilisnya ditugaskan ke satu pemeriksa. Selama
 * ditugaskan, hanya pemeriksa itu dan admin yang boleh memeriksa drafnya, mengirim hasil ke SIPOTRET, memutus SPRP,
 * dan menandai/membatalkan rilis. Publikasi tanpa penugasan bebas. Pasangan pasca rilis: PenugasanPemeriksaan.
 */
class PenugasanPraRilis extends Model
{
    protected $table = 'penugasan_pra_rilis';

    protected $fillable = ['sipotret_pub_id', 'judul', 'tgl_rilis', 'user_id', 'ditugaskan_oleh'];

    protected $casts = ['tgl_rilis' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function penugas()
    {
        return $this->belongsTo(User::class, 'ditugaskan_oleh');
    }

    /** Tabel sudah dibuat (migrasi/SQL sudah dijalankan); belum → fitur penugasan pra rilis dilewati. */
    public static function aktif(): bool
    {
        static $ada = null;
        return $ada ??= Schema::hasTable('penugasan_pra_rilis');
    }

    /** @return Collection<int, self> penugasan per pub_id SIPOTRET */
    public static function untuk(iterable $pubIds): Collection
    {
        $ids = collect($pubIds)->filter()->map(fn($v) => (int) $v)->unique()->values();
        if ($ids->isEmpty() || !static::aktif()) return collect();
        return static::with('user:id,name,first_name,last_name')->whereIn('sipotret_pub_id', $ids)->get()->keyBy('sipotret_pub_id');
    }

    /** Boleh memeriksa publikasi ini: admin, tidak ditugaskan, atau ditugaskan ke dia sendiri. */
    public static function boleh(?User $user, ?int $pubId, ?self $tugas = null): bool
    {
        if (!$user?->canPeriksa()) return false;
        if (!$pubId || $user->isAdmin() || !static::aktif()) return true;
        $tugas ??= static::where('sipotret_pub_id', $pubId)->first();
        return !$tugas || (int) $tugas->user_id === (int) $user->id;
    }

    /** Pesan penolakan bila tidak boleh; null = boleh. */
    public static function tolak(?User $user, ?int $pubId): ?string
    {
        if (static::boleh($user, $pubId)) return null;
        return static::where('sipotret_pub_id', $pubId)->with('user')->first()?->pesanTolak()
            ?? 'Publikasi ini ditugaskan ke pemeriksa lain.';
    }

    /** Ringkasan untuk halaman (bentuk sama dengan PenugasanPemeriksaan::ringkas, dipakai js/penugasan.js). */
    public function ringkas(?User $user): array
    {
        return [
            'user_id' => (int) $this->user_id,
            'nama'    => $this->user?->full_name ?? '(user dihapus)',
            'saya'    => $user && (int) $this->user_id === (int) $user->id,
            'boleh'   => static::boleh($user, (int) $this->sipotret_pub_id, $this),
        ];
    }

    public function pesanTolak(): string
    {
        return 'Pemeriksaan pra rilis publikasi ini ditugaskan ke ' . ($this->user?->full_name ?? 'pemeriksa lain')
            . '. Hanya pemeriksa yang ditugaskan atau admin yang bisa memeriksanya.';
    }
}
