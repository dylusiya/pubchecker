<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Temuan petugas di luar daftar kriteria — di SIPOTRET menjadi "Item Lainnya (Custom)". */
class CatatanTambahan extends Model
{
    protected $table = 'catatan_tambahan';

    public const FLAG_LEVELS = ['minor', 'moderate', 'major'];

    /** Panjang nama item lama (sebelum kolom dilebarkan): penanda nama item master yang dulu terpotong. */
    public const MAX_ITEM = 50;

    /**
     * Panjang maksimal nama item (item master maupun custom). Di SIPOTRET nama lengkapnya tersimpan di kolom
     * `catatan` (TEXT); item_periksa_custom hanya dipotong bila kolomnya lebih sempit (SipotretService::kirim).
     */
    public const MAX_NAMA = 2000;

    protected $fillable = ['hasil_id', 'kategori', 'item', 'item_periksa_id', 'keterangan', 'flag_level', 'halaman', 'pos_x', 'pos_y', 'dibuat_oleh'];

    /**
     * pos_x/pos_y: posisi komentar di halaman (pecahan 0–1 dari lebar/tinggi), null = tanpa posisi.
     * item_periksa_id: item master SIPOTRET (tanpa kriteria di pubchecker), null = item custom.
     * flag_level null = tidak perlu perbaikan (seperti flag kosong di SIPOTRET).
     */
    protected $casts = ['halaman' => 'integer', 'pos_x' => 'float', 'pos_y' => 'float', 'item_periksa_id' => 'integer'];

    public function hasil()
    {
        return $this->belongsTo(HasilPemeriksaan::class, 'hasil_id');
    }

    public function toReviewArray(): array
    {
        return [
            'id'         => $this->id,
            'kategori'   => $this->kategori,
            'item'       => $this->item,
            'item_periksa_id' => $this->item_periksa_id,
            'keterangan' => $this->keterangan,
            'flag_level' => $this->flag_level,
            'halaman'    => $this->halaman,
            'pos_x'      => $this->pos_x,
            'pos_y'      => $this->pos_y,
            'oleh'       => $this->dibuat_oleh,
        ];
    }
}
