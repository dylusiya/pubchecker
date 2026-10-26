<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Temuan petugas di luar daftar kriteria — di SIPOTRET menjadi "Item Lainnya (Custom)". */
class CatatanTambahan extends Model
{
    protected $table = 'catatan_tambahan';

    public const FLAG_LEVELS = ['minor', 'moderate', 'major'];

    /** Batas panjang nama item = kolom item_periksa_custom SIPOTRET (varchar 50). */
    public const MAX_ITEM = 50;

    protected $fillable = ['hasil_id', 'kategori', 'item', 'keterangan', 'flag_level', 'halaman', 'pos_x', 'pos_y', 'dibuat_oleh'];

    /** pos_x/pos_y: posisi komentar di halaman (pecahan 0–1 dari lebar/tinggi), null = tanpa posisi. */
    protected $casts = ['halaman' => 'integer', 'pos_x' => 'float', 'pos_y' => 'float'];

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
            'keterangan' => $this->keterangan,
            'flag_level' => $this->flag_level,
            'halaman'    => $this->halaman,
            'pos_x'      => $this->pos_x,
            'pos_y'      => $this->pos_y,
            'oleh'       => $this->dibuat_oleh,
        ];
    }
}
