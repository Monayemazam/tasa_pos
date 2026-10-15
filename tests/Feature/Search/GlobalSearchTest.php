<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(ExpenseCategorySeeder::class);

    $this->owner = User::factory()->create([
        'name' => 'Tasa Owner',
        'email' => 'owner@tasa.com',
        'is_active' => true,
    ]);
    $this->owner->assignRole('Owner');

    $this->category = ProductCategory::create([
        'name' => 'Wallets',
        'slug' => 'wallets',
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'product_category_id' => $this->category->id,
        'name' => 'Premium Bi-fold Leather Wallet',
        'sku' => 'WL-PREM-001',
        'cost_price' => 750.00,
        'selling_price' => 1950.00,
        'stock_qty' => 18,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'name' => 'Kamal Hossain',
        'phone' => '01711998877',
        'city_name' => 'Dhaka',
        'address' => 'Mirpur-10, Dhaka',
    ]);

    $this->sale = Sale::create([
        'invoice_no' => 'TASA-2610-0088',
        'idempotency_token' => (string) \Illuminate\Support\Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 1950.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00,
        'grand_total' => 1950.00,
        'paid_total' => 1950.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);
});

test('global search returns matching invoices, customers, and products for owner', function () {
    $response = $this->actingAs($this->owner)->getJson('/search?q=Kamal');
    $response->assertOk();

    $json = $response->json();
    expect($json['query'])->toBe('Kamal')
        ->and($json['invoices'])->toHaveCount(1)
        ->and($json['invoices'][0]['invoice_no'])->toBe('TASA-2610-0088')
        ->and($json['customers'])->toHaveCount(1)
        ->and($json['customers'][0]['name'])->toBe('Kamal Hossain')
        ->and($json['elapsed_ms'])->toBeLessThan(300);
});

test('global search by product SKU returns matching product in under 300ms', function () {
    $startTime = microtime(true);
    $response = $this->actingAs($this->owner)->getJson('/search?q=WL-PREM');
    $elapsedMs = (microtime(true) - $startTime) * 1000;

    $response->assertOk();
    $json = $response->json();

    expect($json['products'])->toHaveCount(1)
        ->and($json['products'][0]['sku'])->toBe('WL-PREM-001')
        ->and($json['products'][0]['selling_price'])->toEqual(1950.0)
        ->and($elapsedMs)->toBeLessThan(300);
});

test('global search NEVER exposes cost_price or profit data in results', function () {
    $response = $this->actingAs($this->owner)->getJson('/search?q=Premium');
    $response->assertOk();

    $json = $response->json();
    $product = $json['products'][0];

    // Ensure cost_price is strictly absent from array keys
    expect(array_key_exists('cost_price', $product))->toBeFalse()
        ->and(array_key_exists('cost', $product))->toBeFalse()
        ->and(array_key_exists('profit', $product))->toBeFalse()
        ->and(array_key_exists('margin', $product))->toBeFalse();
});

test('staff without sales.view cannot see invoices in global search', function () {
    $staff = User::factory()->create(['is_active' => true]);
    $role = Role::create(['name' => 'CustomStaff', 'guard_name' => 'web']);
    $role->givePermissionTo(['products.view', 'customers.view']);
    $staff->assignRole('CustomStaff');

    $response = $this->actingAs($staff)->getJson('/search?q=TASA-2610');
    $response->assertOk();

    $json = $response->json();
    // Invoices must be empty because user lacks sales.view
    expect($json['invoices'])->toBeEmpty();
});

test('staff without products.view cannot see products in global search', function () {
    $staff = User::factory()->create(['is_active' => true]);
    $role = Role::create(['name' => 'SalesOnlyStaff', 'guard_name' => 'web']);
    $role->givePermissionTo(['sales.view']);
    $staff->assignRole('SalesOnlyStaff');

    $response = $this->actingAs($staff)->getJson('/search?q=Wallet');
    $response->assertOk();

    $json = $response->json();
    // Products must be empty because user lacks products.view
    expect($json['products'])->toBeEmpty();
});

test('empty query returns zero results and 0 elapsed_ms', function () {
    $response = $this->actingAs($this->owner)->getJson('/search?q=');
    $response->assertOk();

    $json = $response->json();
    expect($json['total_count'])->toBe(0)
        ->and($json['invoices'])->toBeEmpty()
        ->and($json['customers'])->toBeEmpty()
        ->and($json['products'])->toBeEmpty();
});
