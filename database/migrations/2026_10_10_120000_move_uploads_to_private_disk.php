<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Pindahkan PDF hasil upload & file contoh per kategori dari disk "public" (storage/app/public)
 * ke disk privat "local" (storage/app). Path relatif tetap sama, jadi kolom pdf_path / path
 * di database tidak berubah. Disk public bisa terbuka ke umum bila `php artisan storage:link`
 * dijalankan; disk local hanya diakses lewat route aplikasi yang wajib login.
 */
return new class extends Migration
{
    private const DIRS = ['pdf-hasil', 'contoh-kategori'];

    public function up(): void
    {
        $this->move('public', 'local');
    }

    public function down(): void
    {
        $this->move('local', 'public');
    }

    private function move(string $from, string $to): void
    {
        $src = Storage::disk($from);
        $dst = Storage::disk($to);

        foreach (self::DIRS as $dir) {
            foreach ($src->files($dir) as $path) {
                if (!$dst->exists($path)) {
                    $dst->writeStream($path, $src->readStream($path));
                }
                if ($dst->exists($path) && $dst->size($path) === $src->size($path)) {
                    $src->delete($path);
                }
            }
        }
    }
};
