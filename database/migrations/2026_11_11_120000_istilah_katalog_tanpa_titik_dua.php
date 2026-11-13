<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Istilah dwibahasa di halaman Katalog ("Penerbit/ Publisher", "Dicetak oleh/Printed by", …) sering ditulis
 * tanpa titik dua setelah istilah bahasa Inggris, padahal istilahnya sudah benar. Pola lama mewajibkan ":".
 * Pola baru: bentuk dwibahasa (dengan garis miring) titik duanya opsional; bentuk satu bahasa tetap wajib ":"
 * supaya kata "penerbit" biasa di dalam kalimat tidak dianggap label.
 * Hanya diganti bila kalimat gagalnya masih bawaan (lihat 2026_11_11_100000); down() mengembalikan.
 */
return new class extends Migration
{
    /** kode => [istilah Indonesia (regex), istilah Inggris (regex), kalimat gagal bawaan] */
    private function istilah(): array
    {
        return [
            'P-068' => ['Jumlah\s+Halaman', 'Number\s+of\s+Pages', 'Istilah "Jumlah Halaman" / "Jumlah Halaman/Number of Pages" seharusnya dicantumkan'],
            'P-074' => ['Penyusun\s+Naskah', 'Manuscript\s+Drafter', 'Istilah "Penyusun Naskah" / "Penyusun Naskah/Manuscript Drafter" seharusnya dicantumkan'],
            'P-079' => ['Penyunting', 'Editor', 'Istilah "Penyunting" / "Penyunting/Editor" seharusnya dicantumkan'],
            'P-082' => ['Pembuat\s+Kover', 'Cover\s+Designer', 'Istilah "Pembuat Kover" / "Pembuat Kover/Cover Designer" seharusnya dicantumkan'],
            'P-087' => ['Penerbit', 'Publisher', 'Istilah "Penerbit" / "Penerbit/Publisher" seharusnya dicantumkan'],
            'P-091' => ['Dicetak\s+oleh', 'Printed\s+by', 'Istilah "Dicetak oleh" / "Dicetak oleh/Printed by" seharusnya dicantumkan (abaikan jika komponen memang dihapus)'],
            'P-097' => ['Sumber\s+Ilustrasi', 'Illustration\s+Source', 'Istilah "Sumber Ilustrasi" / "Sumber Ilustrasi/Illustration Source" seharusnya dicantumkan'],
        ];
    }

    private function pola(string $id, string $en, bool $baru): string
    {
        return $baru ? "{$id}(?:\\s*/\\s*{$en}\\s*:?|\\s*:)" : "{$id}(?:\\s*/\\s*{$en})?\\s*:";
    }

    private function terapkan(bool $baru): void
    {
        foreach ($this->istilah() as $kode => [$id, $en, $pesan]) {
            DB::table('kriteria_pemeriksaan')->where(['kode' => $kode, 'tipe_cek' => 'regex', 'pesan_gagal' => $pesan])->update([
                'parameter'  => json_encode(['pattern' => $this->pola($id, $en, $baru), 'flags' => 'iu'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }
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
