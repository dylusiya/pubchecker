<?php
/**
 * Buat file SQL (untuk dijalankan di phpMyAdmin pada server yang tidak bisa `php artisan migrate`)
 * dari migration aplikasi, tanpa menyentuh database: migration dijalankan dalam mode pretend,
 * query + binding dicatat lalu digabung menjadi SQL utuh.
 *
 * Pemakaian (dari folder proyek):
 *   php database/sql/_buat_sql.php                       → semua migration 2026_*
 *   php database/sql/_buat_sql.php 2026_10_12_100000     → hanya migration yang namanya diawali teks ini
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn   = DB::connection();
$pdo    = $conn->getPdo();
$dir    = __DIR__;
$filter = $argv[1] ?? '';

/** Sisipkan binding ke query; tanda ? di dalam literal kutip dilewati. */
$isi = function (string $sql, array $bindings) use ($pdo): string {
    $out = '';
    $kutip = null;
    $i = 0;
    for ($p = 0, $n = strlen($sql); $p < $n; $p++) {
        $c = $sql[$p];
        if ($kutip) {
            $out .= $c;
            if ($c === '\\') { $out .= $sql[++$p] ?? ''; continue; }
            if ($c === $kutip) $kutip = null;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') { $kutip = $c; $out .= $c; continue; }
        if ($c === '?' && array_key_exists($i, $bindings)) {
            $v = $bindings[$i++];
            $out .= match (true) {
                $v === null                     => 'NULL',
                is_bool($v)                     => $v ? '1' : '0',
                is_int($v) || is_float($v)      => (string) $v,
                $v instanceof DateTimeInterface => $pdo->quote($v->format('Y-m-d H:i:s')),
                default                         => $pdo->quote((string) $v),
            };
            continue;
        }
        $out .= $c;
    }
    return $out;
};

$file = glob(base_path('database/migrations/2026_*.php'));
sort($file);
foreach ($file as $path) {
    $nama = basename($path, '.php');
    if ($filter !== '' && !str_starts_with($nama, $filter)) continue;
    $migration = require $path;

    $kueri = $conn->pretend(fn() => $migration->up());
    $q = $pdo->quote($nama);
    $baris = [
        "-- Migration: {$nama}",
        "-- Jalankan HANYA bila baris {$nama} belum ada di tabel `migrations`.",
    ];
    if ($nama === '2026_10_10_120000_move_uploads_to_private_disk') {
        $baris[] = '-- Tidak ada perubahan database. Migration ini MEMINDAHKAN FILE (lakukan manual di cPanel File Manager):';
        $baris[] = '--   storage/app/public/pdf-hasil        ->  storage/app/pdf-hasil';
        $baris[] = '--   storage/app/public/contoh-kategori  ->  storage/app/contoh-kategori';
    }
    foreach ($kueri as $k) {
        $baris[] = rtrim($isi($k['query'], $k['bindings']), ';') . ';';
    }
    // catat di tabel migrations (sekali saja) supaya aplikasi menganggap migration ini sudah berjalan
    $baris[] = "INSERT INTO `migrations` (`migration`, `batch`) SELECT m.migration, m.batch FROM "
             . "(SELECT {$q} AS migration, IFNULL(MAX(`batch`), 0) + 1 AS batch FROM `migrations`) AS m "
             . "WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = {$q});";

    file_put_contents("{$dir}/{$nama}.sql", implode("\n", $baris) . "\n");
    printf("%-75s %2d query\n", $nama, count($kueri));
}
