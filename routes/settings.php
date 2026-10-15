<?php

use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SystemSettingController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('settings', fn () => redirect('/settings/profile'));

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // User profile & security
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    // Users Management
    Route::prefix('settings/users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('settings.users');
        Route::post('/', [UserController::class, 'store'])->name('settings.users.store');
        Route::put('/{user}', [UserController::class, 'update'])->name('settings.users.update');
        Route::post('/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('settings.users.toggle-active');
        Route::post('/{user}/force-reset-password', [UserController::class, 'forcePasswordReset'])->name('settings.users.force-reset-password');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('settings.users.destroy');
    });

    // Roles & Permissions Matrix
    Route::prefix('settings/roles')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('settings.roles');
        Route::post('/', [RoleController::class, 'store'])->name('settings.roles.store');
        Route::put('/{role}', [RoleController::class, 'update'])->name('settings.roles.update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('settings.roles.destroy');
    });

    // General Shop & Categories Settings
    Route::prefix('settings/general')->group(function () {
        Route::get('/', [SystemSettingController::class, 'general'])->name('settings.general');
        Route::post('/', [SystemSettingController::class, 'updateGeneral'])->name('settings.general.update');
    });

    Route::post('settings/categories', [SystemSettingController::class, 'storeCategory'])->name('settings.categories.store');
    Route::delete('settings/categories/{category}', [SystemSettingController::class, 'destroyCategory'])->name('settings.categories.destroy');

    // Invoice Branding & Templates
    Route::prefix('settings/invoice')->group(function () {
        Route::get('/', [SystemSettingController::class, 'invoice'])->name('settings.invoice');
        Route::post('/', [SystemSettingController::class, 'updateInvoice'])->name('settings.invoice.update');
    });

    // Pathao Courier Settings
    Route::prefix('settings/pathao')->group(function () {
        Route::get('/', [SystemSettingController::class, 'pathao'])->name('settings.pathao');
        Route::post('/', [SystemSettingController::class, 'updatePathao'])->name('settings.pathao.update');
        Route::get('/test-connection', [SystemSettingController::class, 'testPathaoConnection'])->name('settings.pathao.test-connection');
        Route::post('/stores', [SystemSettingController::class, 'createPathaoStore'])->name('settings.pathao.stores.store');
    });

    // Audit Logs (Strictly Read-Only, Never Expose Update/Delete Routes)
    Route::get('settings/audit-logs', [AuditLogController::class, 'index'])->name('settings.audit-logs');
});
