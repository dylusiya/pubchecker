<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Publikasi BPS (pub_id API) yang ditugaskan ke satu pemeriksa. Selama ditugaskan, hanya pemeriksa itu
 * dan admin yang boleh memeriksa, meninjau, dan mengirim ke SIPOTRET. Publikasi tanpa penugasan bebas.
 */
class PenugasanPemeriksaan extends Model
{
    protected $table = 'penugasan_pemeriksaan';

    protected $fillable = ['pub_id_api', 'judul', 'pub_domain', 'user_id', 'ditugaskan_oleh'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function penugas()
    {
        return $this->belongsTo(User::class, 'ditugaskan_oleh');
    }

    /** @return Collection<string, self> penugasan per pub_id untuk daftar pub_id ini */
    public static function untuk(iterable $pubIds): Collection
    {
        $ids = collect($pubIds)->filter()->map(fn($v) => (string) $v)->unique()->values();
        if ($ids->isEmpty()) return collect();
        return static::with('user:id,name,first_name,last_name')->whereIn('pub_id_api', $ids)->get()->keyBy('pub_id_api');
    }

    /** Boleh memeriksa publikasi ini: admin, tidak ditugaskan, atau ditugaskan ke dia sendiri. */
    public static function boleh(?User $user, ?string $pubId, ?self $tugas = null): bool
    {
        if (!$user?->canPeriksa()) return false;
        if (!$pubId || $user->isAdmin()) return true;
        $tugas ??= static::where('pub_id_api', $pubId)->first();
        return !$tugas || (int) $tugas->user_id === (int) $user->id;
    }

    /** Ringkasan untuk ditampilkan di halaman (JSON): siapa yang ditugaskan & apakah user ini boleh memeriksa. */
    public function ringkas(?User $user): array
    {
        return [
            'user_id' => (int) $this->user_id,
            'nama'    => $this->user?->full_name ?? '(user dihapus)',
            'saya'    => $user && (int) $this->user_id === (int) $user->id,
            'boleh'   => static::boleh($user, $this->pub_id_api, $this),
        ];
    }

    /** Pesan penolakan untuk pemeriksa lain. */
    public function pesanTolak(): string
    {
        return 'Publikasi ini ditugaskan ke ' . ($this->user?->full_name ?? 'pemeriksa lain') . '. Hanya pemeriksa yang ditugaskan atau admin yang bisa memeriksanya.';
    }
}
