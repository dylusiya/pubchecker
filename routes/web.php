<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DaerahSulitController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Web Routes - Sistem Usulan Biaya Daerah Sulit
|--------------------------------------------------------------------------
|
| Sistem untuk tracking dan approval status daerah sulit
|
*/

Route::get('/', [SsoController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| SSO & Authentication Routes
|--------------------------------------------------------------------------
*/

Route::get('/login', [SsoController::class, 'login'])->name('login');

// SSO Keycloak Routes
Route::get('/auth/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
Route::get('/auth/callback', [SsoController::class, 'callback'])->name('sso.callback');

// Local Login (Backup) - Rate limited to prevent brute force attacks
Route::post('/login/local', [SsoController::class, 'loginLocal'])
    ->middleware('throttle:5,1')
    ->name('login.local');

// Logout
Route::get('/logout', [SsoController::class, 'logout'])->name('sso.logout');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Profile Management
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');

    /*
    |--------------------------------------------------------------------------
    | Daerah Sulit Management
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('daerah-sulit')->name('daerah-sulit.')->group(function () {

        Route::get('/search-sls', [DaerahSulitController::class, 'searchSls'])->name('search-sls');
        
        Route::get('/import', [DaerahSulitController::class, 'importForm'])->name('import.form');
        Route::post('/import', [DaerahSulitController::class, 'import'])->name('import');
        Route::get('/download-template', [DaerahSulitController::class, 'downloadTemplate'])->name('download-template');
        Route::get('/download-template-excel', [DaerahSulitController::class, 'downloadTemplateExcel'])->name('download-template-excel');

        // Bulk submit
        Route::get('/pending-submit', [DaerahSulitController::class, 'pendingSubmit'])
            ->name('pending-submit');
        Route::post('/bulk-submit', [DaerahSulitController::class, 'bulkSubmit'])
            ->name('bulk-submit');
        Route::post('/bulk-delete', [DaerahSulitController::class, 'bulkDelete'])
            ->name('bulk-delete');
        
        // Halaman bulk approval
        Route::get('/pending-approval', [DaerahSulitController::class, 'pendingApproval'])
            ->name('pending-approval');
        Route::post('/bulk-approve', [DaerahSulitController::class, 'bulkApprove'])
            ->name('bulk-approve');
        Route::post('/bulk-reject', [DaerahSulitController::class, 'bulkReject'])
            ->name('bulk-reject');

        // Main CRUD Operations
        Route::get('/', [DaerahSulitController::class, 'index'])->name('index');
        Route::get('/create', [DaerahSulitController::class, 'create'])->name('create');
        Route::post('/store', [DaerahSulitController::class, 'store'])->name('store');
        Route::get('/{id}', [DaerahSulitController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [DaerahSulitController::class, 'edit'])->name('edit');
        Route::put('/{id}', [DaerahSulitController::class, 'update'])->name('update');
        Route::delete('/{id}', [DaerahSulitController::class, 'destroy'])->name('destroy');
        
        // Workflow Actions
        Route::post('/{id}/submit', [DaerahSulitController::class, 'submit'])->name('submit');
        Route::post('/{id}/approve', [DaerahSulitController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [DaerahSulitController::class, 'reject'])->name('reject');

    });
    
    // History View (outside prefix for cleaner URL structure)
    Route::get('/history', [DaerahSulitController::class, 'history'])->name('daerah-sulit.history');
    
    // Bulk Operations
    Route::post('/daerah-sulit/copy-year', [DaerahSulitController::class, 'copyFromPreviousYear'])
        ->name('daerah-sulit.copy-year');

    /*
    |--------------------------------------------------------------------------
    | Admin Routes - User Management
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/create', [AdminController::class, 'create'])->name('create');
        Route::post('/store', [AdminController::class, 'store'])->name('store');
        Route::get('/edit/{id}', [AdminController::class, 'edit'])->name('edit');
        Route::put('/update/{id}', [AdminController::class, 'update'])->name('update');
        Route::delete('/destroy/{id}', [AdminController::class, 'destroy'])->name('destroy');
        
        // Search user from SSO (if using Keycloak user search)
        Route::post('/search-user', [SsoController::class, 'searchUser'])->name('search-user');
    });

    /*
    |--------------------------------------------------------------------------
    | Master SLS Routes (Admin Only)
    |--------------------------------------------------------------------------
    */

    Route::prefix('master-sls')->name('master-sls.')->middleware('auth')->group(function () {
        Route::get('/', [App\Http\Controllers\MasterSlsController::class, 'index'])->name('index');
        Route::get('/import', [App\Http\Controllers\MasterSlsController::class, 'importForm'])->name('import');
        Route::post('/import', [App\Http\Controllers\MasterSlsController::class, 'import'])->name('import.process');
        Route::get('/export', [App\Http\Controllers\MasterSlsController::class, 'export'])->name('export');
        Route::get('/template-excel', [App\Http\Controllers\MasterSlsController::class, 'exportTemplate'])->name('template.excel');
        Route::delete('/{id}', [App\Http\Controllers\MasterSlsController::class, 'destroy'])->name('destroy');
    });

});