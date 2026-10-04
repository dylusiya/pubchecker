<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P-017 (larangan singkatan wilayah di kover) salah menangkap "kalsel" dari alamat web
 * "kalsel.bps.go.id" yang tercetak di kover. Singkatan yang menjadi bagian dari domain diabaikan.
 */
return new class extends Migration
{
    private const OLD = '\b(?:Kab|Prov|Kec|Kel|Kep)\.|\b(?:Kalsel|Kalteng|Kaltim|Kalbar|Kaltara|Jatim|Jateng|Jabar|Sumut|Sumbar|Sumsel|Sulsel|Sulteng|Sultra|Sulut|Sulbar|Kepri|Babel|HSS|HST|HSU|Batola)\b';
    private const NEW = '\b(?:Kab|Prov|Kec|Kel|Kep)\.|(?<![\w./])(?:Kalsel|Kalteng|Kaltim|Kalbar|Kaltara|Jatim|Jateng|Jabar|Sumut|Sumbar|Sumsel|Sulsel|Sulteng|Sultra|Sulut|Sulbar|Kepri|Babel|HSS|HST|HSU|Batola)\b(?!\.[\w-]+\.go\.id|\.go\.id)';

    public function up(): void
    {
        $this->setPattern(self::NEW);
    }

    public function down(): void
    {
        $this->setPattern(self::OLD);
    }

    private function setPattern(string $pattern): void
    {
        DB::table('kriteria_pemeriksaan')->where('kode', 'P-017')->where('tipe_cek', 'not_regex')->update([
            'parameter'  => json_encode(['pattern' => $pattern, 'flags' => 'iu'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }
};
