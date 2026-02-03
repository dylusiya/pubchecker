<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\Admin\SurveyAdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HomeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Root - redirect ke login jika belum auth
Route::get('/', [SsoController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Public Survey Routes
|--------------------------------------------------------------------------
*/
Route::prefix('survey')->name('survey.')->group(function () {
    Route::get('/', [SurveyController::class, 'index'])->name('index');
    Route::post('/autosave', [SurveyController::class, 'autosave'])->name('autosave');
    Route::post('/submit', [SurveyController::class, 'store'])->name('store');
    Route::post('/continue', [SurveyController::class, 'continueDraft'])->name('continue');
    Route::get('/count', [SurveyController::class, 'getCount'])->name('count');
});

/*
|--------------------------------------------------------------------------
| SSO & Authentication Routes
|--------------------------------------------------------------------------
*/

Route::get('/login', [SsoController::class, 'login'])->name('login');

// SSO Keycloak Routes
Route::prefix('sso')->name('sso.')->group(function () {
    Route::get('/redirect', [SsoController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [SsoController::class, 'callback'])->name('callback');
    Route::post('/callback', [SsoController::class, 'callback']); // Support POST juga
});

// Local Login (Backup)
Route::post('/login/local', [SsoController::class, 'loginLocal'])->name('login.local');

// Logout
Route::post('/logout', [SsoController::class, 'logout'])->name('logout');
Route::get('/logout', [SsoController::class, 'logout'])->name('sso.logout'); // Support GET juga

/*
|--------------------------------------------------------------------------
| Dashboard & Profile
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');

/*
|--------------------------------------------------------------------------
| Admin Routes - Survey Management
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    
    // Survey Management
    Route::prefix('survey')->name('survey.')->group(function () {
        Route::get('/', [SurveyAdminController::class, 'index'])->name('index');
        Route::get('/dashboard', [SurveyAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/export/excel', [SurveyAdminController::class, 'export'])->name('export');
        Route::get('/{id}', [SurveyAdminController::class, 'show'])->name('show');
        Route::delete('/{id}', [SurveyAdminController::class, 'destroy'])->name('destroy');
    });
    
});


// User Management (untuk Admin saja)
Route::prefix('admin/users')->name('admin.users.')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\UserManagementController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\Admin\UserManagementController::class, 'create'])->name('create');
    Route::post('/store', [App\Http\Controllers\Admin\UserManagementController::class, 'store'])->name('store');
    Route::get('/edit/{id}', [App\Http\Controllers\Admin\UserManagementController::class, 'edit'])->name('edit');
    Route::put('/update/{id}', [App\Http\Controllers\Admin\UserManagementController::class, 'update'])->name('update');
    Route::delete('/destroy/{id}', [App\Http\Controllers\Admin\UserManagementController::class, 'destroy'])->name('destroy');
});