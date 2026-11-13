<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Format jumlah halaman (P-069, P-072) juga menerima singkatan & bentuk dwibahasa/Inggris:
 * "x+52 halaman", "x+52 hal.", "x+52 pages", "x+52 halaman/pages", "x+52 hal/pages".
 * Pola lama \b sesudah "hal." tidak cocok (titik bukan karakter kata), jadi akhir pola memakai (?!\p{L}).
 * Hanya diganti bila kalimat gagalnya masih bawaan (tanpa membaca data dulu, supaya bisa dibuat SQL-nya); down() mengembalikan.
 */
return new class extends Migration
{
    private const SATUAN = '(?:halaman|hal\.?|hlm\.?|pages?)(?:\s*/\s*(?:pages?|halaman|hal\.?))?(?!\p{L})';

    private function aturan(): array
    {
        return [
            'P-069' => [
                'lama' => ['pattern' => '\b[ivxlcdm]+\s*\+\s*\d+\s*(?:halaman|hlm\.?|pages?)\b', 'flags' => 'iu'],
                'baru' => ['pattern' => '\b[ivxlcdm]+\s*\+\s*\d+\s*' . self::SATUAN, 'flags' => 'iu'],
                'pesan_lama' => 'Jumlah halaman seharusnya ditulis dengan format romawi + arab (mis. x+52 halaman)',
                'pesan_baru' => 'Jumlah halaman seharusnya ditulis dengan format romawi + arab, mis. "x+52 halaman" atau dwibahasa "x+52 halaman/pages"',
            ],
            'P-072' => [
                'lama' => ['pattern' => '\b[ivxlcdm]+\+\d+\s+(?:halaman|pages)\b', 'flags' => 'iu'],
                'baru' => ['pattern' => '\b[ivxlcdm]+\+\d+\s+' . self::SATUAN, 'flags' => 'iu'],
                'pesan_lama' => 'Jumlah halaman seharusnya ditulis seperti "xii+90 halaman" (tanpa spasi di sekitar +)',
                'pesan_baru' => 'Jumlah halaman seharusnya ditulis tanpa spasi di sekitar +, mis. "xii+90 halaman" atau dwibahasa "xii+90 halaman/pages"',
            ],
        ];
    }

    private function ganti(string $dari, string $ke): void
    {
        foreach ($this->aturan() as $kode => $a) {
            DB::table('kriteria_pemeriksaan')->where(['kode' => $kode, 'pesan_gagal' => $a['pesan_' . $dari]])->update([
                'parameter'   => json_encode($a[$ke], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'pesan_gagal' => $a['pesan_' . $ke],
                'updated_at'  => now(),
            ]);
        }
    }

    public function up(): void
    {
        $this->ganti('lama', 'baru');
    }

    public function down(): void
    {
        $this->ganti('baru', 'lama');
    }
};
