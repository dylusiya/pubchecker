<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P-069 (jumlah halaman romawi + arab): teks PDF kadang terbaca berjarak per huruf, mis. "x x + 12 2 hal/ pages"
 * untuk "xx + 122 hal/pages". Pola menerima satu spasi di dalam angka romawi & arab.
 * Hanya diganti bila kalimat gagalnya masih bawaan (lihat 2026_11_11_110000); down() mengembalikan.
 */
return new class extends Migration
{
    private const SATUAN = '(?:halaman|hal\.?|hlm\.?|pages?)(?:\s*/\s*(?:pages?|halaman|hal\.?))?(?!\p{L})';
    private const PESAN  = 'Jumlah halaman seharusnya ditulis dengan format romawi + arab, mis. "x+52 halaman" atau dwibahasa "x+52 halaman/pages"';

    private function pola(bool $baru): string
    {
        return $baru
            ? '\b(?:[ivxlcdm] ?)*[ivxlcdm]\s*\+\s*\d(?: ?\d)*\s*' . self::SATUAN
            : '\b[ivxlcdm]+\s*\+\s*\d+\s*' . self::SATUAN;
    }

    private function terapkan(bool $baru): void
    {
        DB::table('kriteria_pemeriksaan')->where(['kode' => 'P-069', 'tipe_cek' => 'regex', 'pesan_gagal' => self::PESAN])->update([
            'parameter'  => json_encode(['pattern' => $this->pola($baru), 'flags' => 'iu'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    public function up(): void
    {
        $this->terapkan(true);
    }

    public function down(): void
    {
        $this->terapkan(false);
    }
};
