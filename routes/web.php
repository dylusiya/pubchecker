<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CheckerController;
use App\Http\Controllers\KriteriaController;
use App\Http\Controllers\BpsImportController;
use App\Http\Controllers\ContohKategoriController;
use App\Http\Controllers\SipotretController;
use App\Http\Controllers\CatatanTambahanController;

/*
|--------------------------------------------------------------------------
| Root
|--------------------------------------------------------------------------
*/
Route::get('/', [SsoController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| SSO & Authentication — tanpa auth middleware
|--------------------------------------------------------------------------
*/
Route::get('/login', [SsoController::class, 'login'])->name('login');

Route::prefix('sso')->name('sso.')->group(function () {
    Route::get('/redirect',  [SsoController::class, 'redirect'])->name('redirect');
    Route::get('/callback',  [SsoController::class, 'callback'])->name('callback');
});

Route::post('/login/local', [SsoController::class, 'loginLocal'])->name('login.local');
Route::post('/logout',      [SsoController::class, 'logout'])->name('logout');
Route::get('/logout',       [SsoController::class, 'logout'])->name('sso.logout');

/*
|--------------------------------------------------------------------------
| Protected Routes — harus login
|--------------------------------------------------------------------------
*/
// Halaman tunggu untuk user SSO baru (role 'pending') sampai disetujui admin
Route::get('/menunggu-persetujuan', function () {
    return auth()->user()->isPending() ? view('auth.pending') : redirect()->route('dashboard');
})->middleware('auth')->name('pending');

// 'approved': user 'pending' diarahkan ke halaman tunggu (EnsureUserApproved)
Route::middleware(['auth', 'approved'])->group(function () {

    // Dashboard & Profile
    Route::get('/dashboard', [HomeController::class,    'index'])->name('dashboard');
    Route::get('/dashboard/sipotret/{wilayah}', [HomeController::class, 'sipotretMasalah'])
        ->whereNumber('wilayah')->name('dashboard.sipotret');
    Route::get('/dashboard/sipotret/file/{history}', [HomeController::class, 'sipotretFile'])
        ->whereNumber('history')->name('dashboard.sipotret.file');
    Route::post('/dashboard/sipotret/approve/{history?}', [HomeController::class, 'sipotretApprove'])
        ->whereNumber('history')->name('dashboard.sipotret.approve')->middleware('can:periksa');
    Route::get('/profile',   [ProfileController::class, 'index'])->name('profile.index');

    // Publication Checker
    Route::prefix('checker')->name('checker.')->group(function () {
        Route::get('/',                      [CheckerController::class, 'index'])        ->name('index')->middleware('can:periksa');
        Route::post('/sesi',                 [CheckerController::class, 'buatSesi'])     ->name('sesi.create')->middleware('can:periksa');
        Route::post('/check',                [CheckerController::class, 'check'])        ->name('check')->middleware('can:periksa');
        Route::post('/export',               [CheckerController::class, 'export'])       ->name('export')->middleware('can:periksa');
        Route::get('/riwayat',               [CheckerController::class, 'riwayat'])      ->name('riwayat');
        Route::get('/riwayat/{sesi}',        [CheckerController::class, 'riwayatDetail'])->name('riwayat.detail');
        Route::get('/riwayat/{sesi}/export', [CheckerController::class, 'exportSesi'])   ->name('riwayat.export');
        Route::get('/riwayat/{sesi}/tinjau', [CheckerController::class, 'tinjauSesi'])   ->name('riwayat.tinjau')->middleware('can:periksa');
        Route::get('/riwayat/{sesi}/sipotret',  [SipotretController::class, 'preview'])  ->name('riwayat.sipotret')->middleware('can:periksa');
        Route::post('/riwayat/{sesi}/sipotret', [SipotretController::class, 'kirim'])    ->name('riwayat.sipotret.kirim')->middleware('can:periksa');
        // acuan isian awal dari catatan SIPOTRET periode tertentu (dipilih petugas, disimpan per hasil)
        Route::get('/sipotret/isian',           [SipotretController::class, 'isian'])    ->name('sipotret.isian')->middleware('can:periksa');
        // salin catatan periode sebelumnya ke periode pemeriksaan (belum upload / belum approve) lalu langsung tercatat
        Route::post('/sipotret/salin-sebelumnya', [SipotretController::class, 'salinSebelumnya'])->name('sipotret.salin')->middleware('can:periksa');
        Route::patch('/hasil/{hasil}/acuan-sipotret', [SipotretController::class, 'acuan'])->name('hasil.acuan_sipotret')->middleware(['can:periksa', 'tugas']);
        Route::delete('/riwayat',            [CheckerController::class, 'deleteSesiBulk'])->name('riwayat.delete-bulk')->middleware('can:periksa');
        Route::delete('/riwayat/{sesi}',     [CheckerController::class, 'deleteSesi'])   ->name('riwayat.delete')->middleware('can:periksa');
        Route::patch('/hasil/{hasil}/review', [CheckerController::class, 'reviewDetail'])->name('hasil.review')->middleware(['can:periksa', 'tugas']);
        Route::patch('/hasil/{hasil}/review-kategori', [CheckerController::class, 'reviewKategori'])->name('hasil.review_kategori')->middleware(['can:periksa', 'tugas']);
        Route::patch('/hasil/{hasil}/pin',   [CheckerController::class, 'simpanPin'])     ->name('hasil.pin')->middleware(['can:periksa', 'tugas']);
        Route::get('/hasil/{hasil}/tinjau',  [CheckerController::class, 'tinjau'])        ->name('hasil.tinjau')->middleware(['can:periksa', 'tugas']);
        // jalankan lagi cek otomatis (kriteria terbaru) dari panel tinjauan; penilaian petugas tidak ditimpa
        Route::post('/hasil/{hasil}/scan-ulang', [BpsImportController::class, 'scanUlang'])->name('hasil.scan_ulang')->middleware(['can:periksa', 'tugas']);
        Route::get('/hasil/{hasil}/pdf',     [CheckerController::class, 'pdf'])           ->name('hasil.pdf');
        Route::get('/hasil/{hasil}/catatan',    [CatatanTambahanController::class, 'index'])  ->name('hasil.catatan');
        Route::post('/hasil/{hasil}/catatan',   [CatatanTambahanController::class, 'store'])  ->name('hasil.catatan.store')->middleware(['can:periksa', 'tugas']);
        Route::patch('/catatan-tambahan/{catatan}',  [CatatanTambahanController::class, 'update']) ->name('catatan.update')->middleware(['can:periksa', 'tugas']);
        Route::delete('/catatan-tambahan/{catatan}', [CatatanTambahanController::class, 'destroy'])->name('catatan.destroy')->middleware(['can:periksa', 'tugas']);
        Route::get('/catatan-tambahan/saran',   [CatatanTambahanController::class, 'saran'])  ->name('catatan.saran')->middleware('can:periksa');
        Route::get('/contoh-kategori',          [ContohKategoriController::class, 'json'])  ->name('contoh.json');
        Route::get('/contoh/{contoh}/gambar',   [ContohKategoriController::class, 'gambar'])->name('contoh.gambar');
    });

    
    Route::get('/checker/bps/pdf-proxy', [BpsImportController::class, 'pdfProxy'])
        ->name('checker.bps.pdf_proxy');
    Route::get('/checker/bps/cover-proxy', [BpsImportController::class, 'coverProxy'])
        ->name('checker.bps.cover_proxy');
    Route::get('/checker/bps/stream', [CheckerController::class, 'stream'])
        ->name('checker.bps.stream')->middleware('can:periksa');

    // Penugasan pemeriksaan per publikasi (admin): siapa yang boleh memeriksa publikasi BPS tertentu
    Route::post('/checker/penugasan', [App\Http\Controllers\PenugasanController::class, 'simpan'])
        ->name('checker.penugasan.simpan');
    // Tugas Saya: publikasi yang ditugaskan ke pemeriksa ini + progres per bulan (admin: semua pemeriksa)
    Route::get('/checker/tugas', [App\Http\Controllers\PenugasanController::class, 'index'])
        ->name('checker.tugas')->middleware('can:periksa');

    // BPS Import
    Route::prefix('checker/bps-import')->name('checker.bps.')->group(function () {
        Route::get('/',           [BpsImportController::class, 'index'])  ->name('index');
        Route::post('/search',    [BpsImportController::class, 'search']) ->name('search');
        Route::post('/run',       [BpsImportController::class, 'run'])    ->name('run')->middleware('can:periksa');
        // draf hasil: tinjau & tambah ketidaksesuaian sambil PDF diunduh/OCR/dicek
        Route::post('/draf',      [BpsImportController::class, 'draf'])   ->name('draf')->middleware('can:periksa');
        
        // Detail Publikasi
        Route::get('/detail',     [BpsImportController::class, 'show'])   ->name('detail.show');
        Route::post('/detail',    [BpsImportController::class, 'detail']) ->name('detail');
    });


    // Admin — Gambar contoh yang benar per kategori kriteria
    Route::prefix('admin/kriteria/contoh')->name('admin.kriteria.contoh.')->group(function () {
        Route::get('/',            [ContohKategoriController::class, 'index'])  ->name('index');
        Route::post('/',           [ContohKategoriController::class, 'store'])  ->name('store');
        Route::patch('/{contoh}',  [ContohKategoriController::class, 'update']) ->name('update');
        Route::delete('/{contoh}', [ContohKategoriController::class, 'destroy'])->name('destroy');
    });

    // Admin — Kriteria Pemeriksaan
    Route::prefix('admin/kriteria')->name('admin.kriteria.')->group(function () {
        Route::get('/',                    [KriteriaController::class, 'index'])   ->name('index');
        Route::get('/create',              [KriteriaController::class, 'create'])  ->name('create');
        Route::post('/',                   [KriteriaController::class, 'store'])   ->name('store');
        Route::get('/{kriteria}/edit',     [KriteriaController::class, 'edit'])    ->name('edit');
        Route::put('/{kriteria}',          [KriteriaController::class, 'update'])  ->name('update');
        Route::delete('/{kriteria}',       [KriteriaController::class, 'destroy']) ->name('destroy');
        Route::patch('/{kriteria}/toggle', [KriteriaController::class, 'toggle'])  ->name('toggle');
        Route::post('/reorder',            [KriteriaController::class, 'reorder']) ->name('reorder');
        Route::post('/import',             [KriteriaController::class, 'import']) ->name('import');
        Route::get('/import/template',     [KriteriaController::class, 'importTemplate'])->name('import.template');
    });

    // Admin — User Management
    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/',                [App\Http\Controllers\Admin\UserManagementController::class, 'index'])  ->name('index');
        Route::get('/create',          [App\Http\Controllers\Admin\UserManagementController::class, 'create']) ->name('create');
        Route::post('/store',          [App\Http\Controllers\Admin\UserManagementController::class, 'store'])  ->name('store');
        Route::get('/edit/{id}',       [App\Http\Controllers\Admin\UserManagementController::class, 'edit'])   ->name('edit');
        Route::put('/update/{id}',     [App\Http\Controllers\Admin\UserManagementController::class, 'update']) ->name('update');
        Route::delete('/destroy/{id}', [App\Http\Controllers\Admin\UserManagementController::class, 'destroy'])->name('destroy');
        Route::post('/approve/{id}',   [App\Http\Controllers\Admin\UserManagementController::class, 'approve'])->name('approve');
    });
    

});