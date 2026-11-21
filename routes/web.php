<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentsController;
use App\Http\Controllers\GroupReportController;
use App\Http\Controllers\LedgersController;
use App\Http\Controllers\ManagerFlashController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ScopeController;
use App\Http\Controllers\TrialBalanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:20,1');
});

Route::post('/locale', [PreferencesController::class, 'locale']);

Route::middleware(['auth', 'group'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/group-report', GroupReportController::class)->name('group-report');
    Route::get('/manager-flash', ManagerFlashController::class)->name('manager-flash');
    Route::get('/trial-balance', TrialBalanceController::class)->name('trial-balance');
    Route::get('/ledgers', LedgersController::class)->name('ledgers');
    Route::get('/documents', [DocumentsController::class, 'index'])->name('documents');
    Route::post('/documents/{document}/reprocess', [DocumentsController::class, 'reprocess']);

    // Hotel switch: all hotels (consolidated) or one hotel; applies to every page.
    Route::post('/scope', [ScopeController::class, 'update']);
    Route::get('/hotels/{property}', [ScopeController::class, 'show'])->name('hotels.show');
    Route::redirect('/financials', '/trial-balance');

    Route::post('/groups/{group}/switch', [PreferencesController::class, 'switchGroup']);
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
