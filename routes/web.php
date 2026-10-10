<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : inertia('Welcome');
})->name('home');

// Dedicated Design System & Foundation Preview Route (Design & UX Guideline §13 item 1)
Route::inertia('/design', 'DesignSystem')->name('design');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/dashboard', 'Dashboard')->name('dashboard');

    // Sales Module Shell Placeholders
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::inertia('/create', 'PlaceholderPage', [
            'title' => 'New Sale (POS Screen)',
            'moduleName' => 'Sales',
            'targetPhase' => 'Phase 3 (Prompt 7)',
            'icon' => 'sales',
        ])->name('create');

        Route::inertia('/invoices', 'PlaceholderPage', [
            'title' => 'Invoices / Sales List',
            'moduleName' => 'Sales',
            'targetPhase' => 'Phase 3 (Prompt 8)',
            'icon' => 'receipt',
        ])->name('invoices');

        Route::inertia('/customers', 'PlaceholderPage', [
            'title' => 'Customers Management',
            'moduleName' => 'Sales',
            'targetPhase' => 'Phase 3 (Prompt 7)',
            'icon' => 'customers',
        ])->name('customers');

        Route::inertia('/courier', 'PlaceholderPage', [
            'title' => 'Courier Orders (Pathao)',
            'moduleName' => 'Sales / Courier',
            'targetPhase' => 'Phase 4 (Prompt 9)',
            'icon' => 'courier',
        ])->name('courier');
    });

    // Inventory Module Shell Placeholders
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::inertia('/products', 'PlaceholderPage', [
            'title' => 'Finished Products (Inventory)',
            'moduleName' => 'Inventory',
            'targetPhase' => 'Phase 2 (Prompt 4)',
            'icon' => 'products',
        ])->name('products');

        Route::inertia('/leather', 'PlaceholderPage', [
            'title' => 'Leather Stock (Raw Materials)',
            'moduleName' => 'Inventory',
            'targetPhase' => 'Phase 2 (Prompt 5)',
            'icon' => 'leather',
        ])->name('leather');

        Route::inertia('/accessories', 'PlaceholderPage', [
            'title' => 'Accessories Stock (Raw Materials)',
            'moduleName' => 'Inventory',
            'targetPhase' => 'Phase 2 (Prompt 6)',
            'icon' => 'accessories',
        ])->name('accessories');

        Route::inertia('/adjustments', 'PlaceholderPage', [
            'title' => 'Universal Stock Adjustments',
            'moduleName' => 'Inventory',
            'targetPhase' => 'Phase 2 (Prompt 6)',
            'icon' => 'adjustments',
        ])->name('adjustments');
    });

    // Expenses Module Shell Placeholder
    Route::inertia('/expenses', 'PlaceholderPage', [
        'title' => 'Business Expenses',
        'moduleName' => 'Expenses',
        'targetPhase' => 'Phase 5 (Prompt 11)',
        'icon' => 'expenses',
    ])->name('expenses');

    // Payroll Module Shell Placeholders
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::inertia('/employees', 'PlaceholderPage', [
            'title' => 'Salaried Employees (Monthly Sheets)',
            'moduleName' => 'Payroll',
            'targetPhase' => 'Phase 5 (Prompt 12)',
            'icon' => 'payroll',
        ])->name('employees');

        Route::inertia('/workers', 'PlaceholderPage', [
            'title' => 'Wage Workers (Ledger & Statement)',
            'moduleName' => 'Payroll',
            'targetPhase' => 'Phase 5 (Prompt 13)',
            'icon' => 'payroll',
        ])->name('workers');
    });

    // Reports Module Shell Placeholders
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::inertia('/sales', 'PlaceholderPage', [
            'title' => 'Sales Analytics & Reports',
            'moduleName' => 'Reports',
            'targetPhase' => 'Phase 6 (Prompt 14)',
            'icon' => 'reports',
        ])->name('sales');

        Route::inertia('/profit-loss', 'PlaceholderPage', [
            'title' => 'Profit & Loss Statement (Senior Accountant View)',
            'moduleName' => 'Reports',
            'targetPhase' => 'Phase 6 (Prompt 15)',
            'icon' => 'receipt',
        ])->name('profit-loss');
    });

    // Settings Module Shell Placeholders
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::inertia('/roles', 'PlaceholderPage', [
            'title' => 'Users & Role Permission Matrix',
            'moduleName' => 'Settings',
            'targetPhase' => 'Phase 1 (Prompt 3)',
            'icon' => 'shield',
        ])->name('roles');

        Route::inertia('/invoice', 'PlaceholderPage', [
            'title' => 'Invoice Branding & Templates',
            'moduleName' => 'Settings',
            'targetPhase' => 'Phase 3 (Prompt 8)',
            'icon' => 'printer',
        ])->name('invoice');

        Route::inertia('/pathao', 'PlaceholderPage', [
            'title' => 'Pathao Courier Integration Settings',
            'moduleName' => 'Settings',
            'targetPhase' => 'Phase 4 (Prompt 9)',
            'icon' => 'courier',
        ])->name('pathao');

        Route::inertia('/general', 'PlaceholderPage', [
            'title' => 'General Shop Information & Categories',
            'moduleName' => 'Settings',
            'targetPhase' => 'Phase 1 (Prompt 3)',
            'icon' => 'settings',
        ])->name('general');
    });
});

require __DIR__.'/settings.php';
