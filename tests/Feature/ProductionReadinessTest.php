<?php

use App\Models\CourierOrder;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InvoiceSequence;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(ExpenseCategorySeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

test('login page renders cleanly via Inertia without any demo credentials', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/Login')
        ->has('canResetPassword')
    );

    // Verify source template does not contain demo fill strings
    $authScreenCode = file_get_contents(resource_path('js/components/tasa/TasaAuthScreen.vue'));
    expect($authScreenCode)->not->toContain('fillCredentials');
    expect($authScreenCode)->not->toContain('One-Click Login Fill');
    expect($authScreenCode)->not->toContain('owner@tasa.com');
    expect($authScreenCode)->not->toContain('staff@tasa.com');
    expect($authScreenCode)->not->toContain('Password: password');
});

test('normal owner login works with proper credentials', function () {
    $owner = User::factory()->create([
        'email' => 'owner_test_ready@tasa.com',
        'password' => Hash::make('OwnerSecure2026!'),
    ]);
    $owner->assignSingleRole('Owner');

    $response = $this->post(route('login.store'), [
        'email' => 'owner_test_ready@tasa.com',
        'password' => 'OwnerSecure2026!',
    ]);

    $this->assertAuthenticatedAs($owner);
    $response->assertRedirect(route('dashboard'));

    // Bad password fails
    $this->post(route('logout'));
    $badResponse = $this->post(route('login.store'), [
        'email' => 'owner_test_ready@tasa.com',
        'password' => 'wrong-password',
    ]);
    $this->assertGuest();
});

test('normal staff login works and permissions remain strictly enforced', function () {
    $staff = User::factory()->create([
        'email' => 'staff_test_ready@tasa.com',
        'password' => Hash::make('StaffSecure2026!'),
    ]);
    $staff->assignSingleRole('Staff');

    $response = $this->post(route('login.store'), [
        'email' => 'staff_test_ready@tasa.com',
        'password' => 'StaffSecure2026!',
    ]);

    $this->assertAuthenticatedAs($staff);
    $response->assertRedirect(route('dashboard'));

    // Staff without 'settings.view' cannot access settings
    $settingsResponse = $this->actingAs($staff)->get('/settings/general');
    $settingsResponse->assertForbidden();

    // Owner CAN access settings
    $owner = User::factory()->create();
    $owner->assignSingleRole('Owner');
    $ownerSettingsResponse = $this->actingAs($owner)->get('/settings/general');
    $ownerSettingsResponse->assertOk();
});

test('pathao configuration and service base url remain intact', function () {
    $baseUrl = config('services.pathao.base_url');
    expect($baseUrl)->toBe('https://api-hermes.pathao.com');

    // Confirm Pathao settings group exists
    $pathaoSettings = Setting::where('group', 'pathao')->count();
    expect($pathaoSettings)->toBeGreaterThanOrEqual(1);
});

test('purge command safely removes test transactions while restoring opening stock', function () {
    // 1. Create a genuine product with opening stock
    $category = ProductCategory::firstOrCreate(['name' => 'Wallets']);
    $product = Product::create([
        'name' => 'Bifold Wallet Test',
        'sku' => 'BFW-TEST',
        'product_category_id' => $category->id,
        'cost_price' => 500.00,
        'selling_price' => 1200.00,
        'stock_qty' => 5,
        'min_stock' => 2,
        'has_variants' => true,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'BFW-TEST-BLK',
        'color_name' => 'Black',
        'attribute_name' => 'Color',
        'attribute_value' => 'Black',
        'cost_price' => 500.00,
        'selling_price' => 1200.00,
        'stock_qty' => 4, // 5 opening minus 1 test sale
    ]);

    // Genuine opening stock movement
    ProductStockMovement::create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'type' => 'opening',
        'qty' => 5,
        'balance_after' => 5,
        'movement_date' => now()->toDateString(),
        'note' => 'Opening stock',
    ]);

    // Test sale movement (deducted 1)
    ProductStockMovement::create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'type' => 'sale',
        'qty' => -1,
        'balance_after' => 4,
        'movement_date' => now()->toDateString(),
        'note' => 'Test sale',
    ]);

    // Test customer, sale and invoice sequence
    $customer = Customer::create([
        'name' => 'Test Customer Demo',
        'phone' => '01799999999',
        'address' => 'Dhaka, Bangladesh',
    ]);

    $user = User::factory()->create();

    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-9999',
        'idempotency_token' => (string) \Illuminate\Support\Str::uuid(),
        'customer_id' => $customer->id,
        'customer_name' => 'Test Customer Demo',
        'customer_phone' => '01799999999',
        'customer_address' => 'Dhaka, Bangladesh',
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 1200.00,
        'grand_total' => 1200.00,
        'payment_status' => 'paid',
        'status' => 'completed',
        'created_by' => $user->id,
    ]);

    InvoiceSequence::updateOrCreate(
        ['period' => '2610'],
        ['last_number' => 9999]
    );

    // Run purge command
    $exitCode = Artisan::call('app:purge-test-data', ['--force' => true]);
    expect($exitCode)->toBe(0);

    // Sales are cleaned
    expect(Sale::where('invoice_no', 'TASA-2610-9999')->exists())->toBeFalse();

    // Invoice sequence reset
    $seq = InvoiceSequence::where('period', '2610')->first();
    expect($seq->last_number)->toBe(0);

    // Variant stock restored to genuine opening stock (5)
    $variant->refresh();
    expect($variant->stock_qty)->toBe(5);

    // Test stock movement deleted
    expect(ProductStockMovement::where('type', 'sale')->count())->toBe(0);
    // Opening stock movement preserved
    expect(ProductStockMovement::where('type', 'opening')->count())->toBeGreaterThanOrEqual(1);

    // Idempotent test: run purge again
    $exitCode2 = Artisan::call('app:purge-test-data', ['--force' => true]);
    expect($exitCode2)->toBe(0);
});
