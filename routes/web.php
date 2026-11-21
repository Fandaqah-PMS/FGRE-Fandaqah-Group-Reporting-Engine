<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentsController;
use App\Http\Controllers\FinancialsController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\PreferencesController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:20,1');
});

Route::post('/locale', [PreferencesController::class, 'locale']);

Route::middleware(['auth', 'group'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/hotels/{property}', [HotelController::class, 'show'])->name('hotels.show');
    Route::get('/financials', FinancialsController::class)->name('financials');
    Route::get('/documents', [DocumentsController::class, 'index'])->name('documents');
    Route::post('/documents/{document}/reprocess', [DocumentsController::class, 'reprocess']);
    Route::post('/groups/{group}/switch', [PreferencesController::class, 'switchGroup']);
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
