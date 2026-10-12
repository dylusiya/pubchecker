<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s = new App\Services\PdfCheckerService;
$s->checkFromPath($argv[1], 'x', 1);
$p = new ReflectionProperty($s, 'pageTexts');
$p->setAccessible(true);
$hal = $p->getValue($s);
$semua = implode("\n", $hal);

echo "== P-354 desimal bertitik (kandidat):\n";
$pola = '/(?<![\w.,\/:-])(?<!Tabel\s)(?<!Gambar\s)(?<!Lampiran\s)(?<!BAB\s)(?<!Bab\s)\d{1,3}\.\d{1,2}(?![\d.,\/:])(?!\s+\p{Lu})/u';
preg_match_all($pola, $semua, $m, PREG_OFFSET_CAPTURE);
echo count($m[0]), " temuan\n";
foreach (array_slice($m[0], 0, 15) as [$t, $o]) echo '   "', str_replace("\n", ' / ', substr($semua, max(0, $o - 30), 70)), "\"\n";

echo "== Daftar Pustaka (hal. 55):\n", substr($hal[54], 0, 1500), "\n";

echo "== Baris berupa angka saja (calon nomor halaman), hal 13..66:\n";
foreach ($hal as $i => $t) {
    if ($i < 12) continue;
    $baris = array_values(array_filter(array_map('trim', preg_split('/\R/u', $t)), 'strlen'));
    $calon = array_filter(array_merge(array_slice($baris, 0, 2), array_slice($baris, -2)), fn($b) => preg_match('/^\d{1,3}$/', $b));
    if ($calon) echo ($i + 1), ':', implode(',', $calon), '  ';
}
echo "\n";
