// Simpan sebagai test_gs.php di root project, akses via browser
<?php
$gs = '';
$baseDir = 'C:\\Program Files\\gs';
$dirs = glob($baseDir . '\\gs*', GLOB_ONLYDIR);
rsort($dirs);
foreach ($dirs as $dir) {
    $bin = $dir . '\\bin\\gswin64c.exe';
    if (file_exists($bin)) { $gs = $bin; break; }
}

echo "GS found: " . ($gs ?: 'TIDAK DITEMUKAN') . "<br>";
echo "file_exists: " . (file_exists($gs) ? 'YES' : 'NO') . "<br>";

// Test exec
$out = []; $code = -1;
exec('gswin64c --version 2>&1', $out, $code);
echo "exec gswin64c --version: code=$code, out=" . implode(',', $out) . "<br>";

// Test dengan full path
if ($gs) {
    $out2 = []; $code2 = -1;
    exec('"' . $gs . '" --version 2>&1', $out2, $code2);
    echo "exec full path: code=$code2, out=" . implode(',', $out2) . "<br>";
}

// Cek disable_functions
echo "disable_functions: " . ini_get('disable_functions') . "<br>";
echo "sys_get_temp_dir: " . sys_get_temp_dir() . "<br>";