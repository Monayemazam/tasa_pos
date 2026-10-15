<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

test('design system preview route is accessible', function () {
    $response = $this->get(route('design'));
    $response->assertOk();
});

test('application timezone is configured as Asia/Dhaka', function () {
    expect(config('app.timezone'))->toBe('Asia/Dhaka');
});

test('self hosted font files and stylesheet exist in public fonts directory', function () {
    expect(file_exists(public_path('fonts/fonts.css')))->toBeTrue();
    expect(file_exists(public_path('fonts/hanken-grotesk-400-0.woff2')))->toBeTrue();
    expect(file_exists(public_path('fonts/noto-sans-bengali-400-0.woff2')))->toBeTrue();
});

test('authenticated user can visit dashboard with tasa shell', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();
});

test('shell placeholder routes are defined and reachable for authenticated users', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignSingleRole('Owner');

    $routes = [
        'sales.create',
        'sales.invoices',
        'sales.customers',
        'sales.courier',
        'inventory.products',
        'inventory.leather',
        'inventory.accessories',
        'inventory.adjustments',
        'expenses',
        'payroll.employees',
        'payroll.workers',
        'reports.sales',
        'reports.profit-loss',
        'settings.roles',
        'settings.invoice',
        'settings.pathao',
        'settings.general',
    ];

    foreach ($routes as $routeName) {
        $response = $this->actingAs($user)->get(route($routeName));
        $response->assertOk();
    }
});
