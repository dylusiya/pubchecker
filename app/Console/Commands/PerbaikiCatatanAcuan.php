<?php

namespace App\Console\Commands;

use App\Services\SipotretService;
use Illuminate\Console\Command;

/**
 * Rapikan catatan tambahan lama yang diambil dari catatan SIPOTRET sebelum disamakan dengan "Dari History":
 * item master yang tersimpan sebagai item custom, nama item master yang terpotong 50 karakter,
 * dan flag kosong yang dipaksa jadi "minor".
 * Bawaan hanya menampilkan rencana (tidak mengubah apa pun).
 */
class PerbaikiCatatanAcuan extends Command
{
    protected $signature = 'pubchecker:perbaiki-catatan-acuan
                            {--simpan : Terapkan perubahan (tanpa ini hanya pratinjau)}
                            {--sipotret : Ikut betulkan baris yang sudah terkirim ke SIPOTRET}
                            {--yakin : Lewati konfirmasi (untuk Cron Job cPanel yang tidak bisa menjawab pertanyaan)}';

    protected $description = 'Samakan catatan tambahan lama dari acuan SIPOTRET (item master, nama terpotong & flag kosong)';

    public function handle(SipotretService $sipotret): int
    {
        if (!$sipotret->enabled()) {
            $this->error('Koneksi SIPOTRET belum diatur (SIPOTRET_DB_* di .env).');
            return self::FAILURE;
        }

        $simpan = (bool) $this->option('simpan');
        $kirim  = (bool) $this->option('sipotret');
        if ($simpan && !$this->option('yakin') && !$this->confirm('Terapkan perubahan' . ($kirim ? ' di pubchecker DAN di SIPOTRET' : ' di pubchecker') . '?')) {
            return self::SUCCESS;
        }

        $laporan = $sipotret->perbaikiCatatanAcuan($simpan, $kirim);
        if (!$laporan) {
            $this->info('Tidak ada catatan yang perlu dibetulkan.');
            return self::SUCCESS;
        }

        $this->table(
            array_merge(['Hasil', 'Catatan', 'Item', 'Perubahan'], $kirim ? ['SIPOTRET'] : []),
            array_map(fn($r) => array_merge(
                [$r['hasil_id'], $r['catatan_id'], $r['item'], implode('; ', $r['ubah'])],
                $kirim ? [$r['sipotret'] ?? 'belum pernah dikirim'] : []
            ), $laporan)
        );
        $this->line(count($laporan) . ' catatan ' . ($simpan ? 'dibetulkan.' : 'akan dibetulkan. Jalankan lagi dengan --simpan untuk menerapkan.'));
        return self::SUCCESS;
    }
}
