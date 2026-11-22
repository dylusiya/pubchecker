<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class DetailPemeriksaan extends Model
{
    protected $table      = 'detail_pemeriksaan';
    public    $timestamps = false;

    protected $fillable = [
        'hasil_id',
        'kriteria_id',
        'kategori',
        'deskripsi',
        'status',
        'catatan',
        'keterangan',
        'ditinjau_at',
        'lokasi',
        'pin_halaman',
        'pin_x',
        'pin_y',
    ];

    protected $casts = [
        'ditinjau_at' => 'datetime',
        'pin_halaman' => 'integer',
        'pin_x'       => 'float',
        'pin_y'       => 'float',
    ];

    /** Penanda Tidak Sesuai di halaman PDF untuk panel tinjauan: { hal, x, y } atau null. */
    public function pinArray(): ?array
    {
        return $this->pin_halaman ? ['hal' => $this->pin_halaman, 'x' => $this->pin_x, 'y' => $this->pin_y] : null;
    }

    /** Maks. penanda per kriteria. */
    public const MAKS_PIN = 30;

    /** Kolom pins sudah ada (migrasi 2026_11_23_100000); belum → hanya satu penanda (pin_halaman/x/y). */
    public static function adaKolomPins(): bool
    {
        static $ada = null;
        return $ada ??= \Illuminate\Support\Facades\Schema::hasColumn('detail_pemeriksaan', 'pins');
    }

    /** Semua penanda kriteria ini: [{hal, x, y}, …] (kolom pins, atau penanda tunggal lama). */
    public function daftarPin(): array
    {
        $pins = self::adaKolomPins() ? json_decode((string) ($this->attributes['pins'] ?? ''), true) : null;
        if (is_array($pins)) return self::rapikanPin($pins);
        return ($p = $this->pinArray()) ? [$p] : [];
    }

    /** Penanda valid saja ({hal ≥ 1, x & y 0–1}), maks. MAKS_PIN. */
    public static function rapikanPin(array $pins): array
    {
        $hasil = [];
        foreach ($pins as $p) {
            if (!is_array($p) || !is_numeric($p['hal'] ?? null) || !is_numeric($p['x'] ?? null) || !is_numeric($p['y'] ?? null)) continue;
            if ((int) $p['hal'] < 1 || $p['x'] < 0 || $p['x'] > 1 || $p['y'] < 0 || $p['y'] > 1) continue;
            $hasil[] = ['hal' => (int) $p['hal'], 'x' => round((float) $p['x'], 4), 'y' => round((float) $p['y'], 4)];
        }
        return array_slice($hasil, 0, self::MAKS_PIN);
    }

    /**
     * Kolom yang diisi untuk menyimpan penanda (query builder: pins sebagai teks JSON). Penanda pertama juga
     * disimpan di pin_halaman/x/y supaya data & kode lama tetap terbaca.
     */
    public static function isianPin(array $pins): array
    {
        $pins = self::rapikanPin($pins);
        $awal = $pins[0] ?? null;
        return [
            'pin_halaman' => $awal['hal'] ?? null,
            'pin_x'       => $awal['x'] ?? null,
            'pin_y'       => $awal['y'] ?? null,
        ] + (self::adaKolomPins() ? ['pins' => $pins ? json_encode($pins) : null] : []);
    }

    /**
     * Lokasi temuan [{hal, teks}] sebagai array. Teks JSON yang ter-encode dua kali (draf "tinjau sambil diproses"
     * sebelum diperbaiki, 11 Nov 2026) ikut dibaca benar; isian berupa array/teks JSON keduanya diterima.
     */
    protected function lokasi(): Attribute
    {
        return Attribute::make(
            get: function ($v) {
                for ($i = 0; is_string($v) && $i < 2; $i++) $v = json_decode($v, true);
                return is_array($v) ? array_values(array_filter($v, 'is_array')) : null;
            },
            set: fn($v) => is_array($v) ? self::encodeLokasi($v) : ($v === '' ? null : $v),
        );
    }

    /** Untuk insert massal (insert() tidak melewati cast). */
    public static function encodeLokasi(?array $lokasi): ?string
    {
        return $lokasi ? json_encode(array_values($lokasi), JSON_UNESCAPED_UNICODE) : null;
    }

    public function hasil()
    {
        return $this->belongsTo(HasilPemeriksaan::class, 'hasil_id');
    }

    // ── Konstanta status ──────────────────────────────────────
    // Hasil cek otomatis: OK / PERLU DICEK / TIDAK ADA / TIDAK DIPERIKSA.
    // Verifikasi petugas: OK (Sesuai) / TIDAK SESUAI (wajib keterangan) / TIDAK DIPERIKSA (Skip).
    const STATUS_OK              = 'OK';
    const STATUS_PERLU_DICEK     = 'PERLU DICEK';
    const STATUS_TIDAK_ADA       = 'TIDAK ADA';
    const STATUS_TIDAK_SESUAI    = 'TIDAK SESUAI';
    const STATUS_TIDAK_DIPERIKSA = 'TIDAK DIPERIKSA';
    /** Draf (ditinjau sambil diproses): cek otomatis belum selesai. Diganti hasil cek saat selesai. */
    const STATUS_MENUNGGU        = 'MENUNGGU';

    /** Status yang bisa dipilih petugas di panel tinjauan. */
    const STATUS_VERIFIKASI = [self::STATUS_OK, self::STATUS_TIDAK_SESUAI, self::STATUS_TIDAK_DIPERIKSA];

    /** Status yang dihitung sebagai masalah (kolom total_tidak_ada / status akhir "masalah"). */
    const STATUS_MASALAH = [self::STATUS_TIDAK_ADA, self::STATUS_TIDAK_SESUAI];
}