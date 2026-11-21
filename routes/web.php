<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentsController;
use App\Http\Controllers\LedgersController;
use App\Http\Controllers\ManagerFlashController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ScopeController;
use App\Http\Controllers\Settings\GroupController;
use App\Http\Controllers\Settings\HotelsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RolesController;
use App\Http\Controllers\Settings\UsersController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TrialBalanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:6,1');
    Route::get('/reset-password/{token}', [PasswordController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->middleware('throttle:10,1');
});

Route::post('/locale', [PreferencesController::class, 'locale']);

Route::middleware(['auth', 'group'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/group-report', fn () => redirect('/manager-flash?view=compare'))->name('group-report');
    Route::get('/manager-flash', ManagerFlashController::class)->name('manager-flash');
    Route::get('/trial-balance', TrialBalanceController::class)->name('trial-balance');
    Route::get('/ledgers', LedgersController::class)->name('ledgers');
    Route::get('/documents', [DocumentsController::class, 'index'])->name('documents');
    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets');
    Route::get('/budgets/template', [BudgetController::class, 'template']);
    Route::post('/budgets/import', [BudgetController::class, 'import']);
    Route::post('/documents/{document}/reprocess', [DocumentsController::class, 'reprocess']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions');
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::put('/subscriptions/{subscription}', [SubscriptionController::class, 'update']);
    Route::delete('/subscriptions/{subscription}', [SubscriptionController::class, 'destroy']);
    Route::post('/subscriptions/{subscription}/send', [SubscriptionController::class, 'send'])->middleware('throttle:6,1');
    Route::get('/subscriptions/{subscription}/preview', [SubscriptionController::class, 'preview']);

    // Hotel switch: all hotels (consolidated) or one hotel; applies to every page.
    Route::post('/scope', [ScopeController::class, 'update']);

    // Settings
    Route::redirect('/settings', '/settings/profile');
    Route::get('/settings/profile', [ProfileController::class, 'show'])->name('settings.profile');
    Route::put('/settings/profile', [ProfileController::class, 'update']);
    Route::put('/settings/password', [ProfileController::class, 'password'])->middleware('throttle:6,1');
    Route::get('/settings/users', [UsersController::class, 'index'])->name('settings.users');
    Route::post('/settings/users', [UsersController::class, 'store'])->middleware('throttle:30,1');
    Route::put('/settings/users/{user}', [UsersController::class, 'update']);
    Route::delete('/settings/users/{user}', [UsersController::class, 'destroy']);
    Route::post('/settings/users/{user}/invite', [UsersController::class, 'invite'])->middleware('throttle:6,1');
    Route::get('/settings/roles', [RolesController::class, 'index'])->name('settings.roles');
    Route::put('/settings/roles/{role}', [RolesController::class, 'update']);
    Route::delete('/settings/roles/{role}', [RolesController::class, 'reset']);
    Route::get('/settings/hotels', [HotelsController::class, 'index'])->name('settings.hotels');
    Route::post('/settings/hotels', [HotelsController::class, 'store']);
    Route::put('/settings/hotels/{property}', [HotelsController::class, 'update']);
    Route::get('/settings/group', [GroupController::class, 'edit'])->name('settings.group');
    Route::put('/settings/group', [GroupController::class, 'update']);
    Route::get('/settings/activity', [GroupController::class, 'activity'])->name('settings.activity');
    Route::get('/hotels/{property}', [ScopeController::class, 'show'])->name('hotels.show');
    Route::redirect('/financials', '/trial-balance');

    Route::post('/groups/{group}/switch', [PreferencesController::class, 'switchGroup']);
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
