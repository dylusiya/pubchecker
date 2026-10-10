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
    Route::post('/callback', [SsoController::class, 'callback']);
});

Route::post('/login/local', [SsoController::class, 'loginLocal'])->name('login.local');
Route::post('/logout',      [SsoController::class, 'logout'])->name('logout');
Route::get('/logout',       [SsoController::class, 'logout'])->name('sso.logout');

/*
|--------------------------------------------------------------------------
| Protected Routes — harus login
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard & Profile
    Route::get('/dashboard', [HomeController::class,    'index'])->name('dashboard');
    Route::get('/profile',   [ProfileController::class, 'index'])->name('profile.index');

    // Publication Checker
    Route::prefix('checker')->name('checker.')->group(function () {
        Route::get('/',                      [CheckerController::class, 'index'])        ->name('index');
        Route::post('/sesi',                 [CheckerController::class, 'buatSesi'])     ->name('sesi.create');
        Route::post('/check',                [CheckerController::class, 'check'])        ->name('check');
        Route::post('/export',               [CheckerController::class, 'export'])       ->name('export');
        Route::get('/riwayat',               [CheckerController::class, 'riwayat'])      ->name('riwayat');
        Route::get('/riwayat/{sesi}',        [CheckerController::class, 'riwayatDetail'])->name('riwayat.detail');
        Route::get('/riwayat/{sesi}/export', [CheckerController::class, 'exportSesi'])   ->name('riwayat.export');
        Route::get('/riwayat/{sesi}/tinjau', [CheckerController::class, 'tinjauSesi'])   ->name('riwayat.tinjau');
        Route::get('/riwayat/{sesi}/sipotret',  [SipotretController::class, 'preview'])  ->name('riwayat.sipotret');
        Route::post('/riwayat/{sesi}/sipotret', [SipotretController::class, 'kirim'])    ->name('riwayat.sipotret.kirim');
        Route::delete('/riwayat',            [CheckerController::class, 'deleteSesiBulk'])->name('riwayat.delete-bulk');
        Route::delete('/riwayat/{sesi}',     [CheckerController::class, 'deleteSesi'])   ->name('riwayat.delete');
        Route::patch('/hasil/{hasil}/review', [CheckerController::class, 'reviewDetail'])->name('hasil.review');
        Route::patch('/hasil/{hasil}/review-kategori', [CheckerController::class, 'reviewKategori'])->name('hasil.review_kategori');
        Route::get('/hasil/{hasil}/tinjau',  [CheckerController::class, 'tinjau'])        ->name('hasil.tinjau');
        Route::get('/hasil/{hasil}/pdf',     [CheckerController::class, 'pdf'])           ->name('hasil.pdf');
        Route::get('/hasil/{hasil}/catatan',    [CatatanTambahanController::class, 'index'])  ->name('hasil.catatan');
        Route::post('/hasil/{hasil}/catatan',   [CatatanTambahanController::class, 'store'])  ->name('hasil.catatan.store');
        Route::patch('/catatan-tambahan/{catatan}',  [CatatanTambahanController::class, 'update']) ->name('catatan.update');
        Route::delete('/catatan-tambahan/{catatan}', [CatatanTambahanController::class, 'destroy'])->name('catatan.destroy');
        Route::get('/catatan-tambahan/saran',   [CatatanTambahanController::class, 'saran'])  ->name('catatan.saran');
        Route::get('/contoh-kategori',          [ContohKategoriController::class, 'json'])  ->name('contoh.json');
        Route::get('/contoh/{contoh}/gambar',   [ContohKategoriController::class, 'gambar'])->name('contoh.gambar');
    });

    
    Route::get('/checker/bps/pdf-proxy', [BpsImportController::class, 'pdfProxy'])
        ->name('checker.bps.pdf_proxy');
    Route::get('/checker/bps/stream', [CheckerController::class, 'stream'])
        ->name('checker.bps.stream');

    // BPS Import
    Route::prefix('checker/bps-import')->name('checker.bps.')->group(function () {
        Route::get('/',           [BpsImportController::class, 'index'])  ->name('index');
        Route::post('/search',    [BpsImportController::class, 'search']) ->name('search');
        Route::post('/run',       [BpsImportController::class, 'run'])    ->name('run');
        
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
    });
    

});