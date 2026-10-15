<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\Inventory\ProductImportService;
use App\Services\Sales\SaleService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->owner = User::factory()->create([
        'name' => 'Tasa Owner',
        'email' => 'owner@tasa.com',
    ]);
    $this->owner->assignSingleRole('Owner');

    $this->category = ProductCategory::create(['name' => 'Eyewear Accessories']);
});

test('can create a product with Brown and Black color variations with unique SKUs and individual stock', function () {
    $payload = [
        'name' => 'Eyewear Case Series 1',
        'product_category_id' => $this->category->id,
        'has_variants' => true,
        'variation_type' => 'Color',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'min_stock' => 5,
        'is_active' => true,
        'variants' => [
            [
                'color_name' => 'Brown',
                'sku' => 'EWC-S1-BRN',
                'cost_price' => 500.00,
                'selling_price' => 850.00,
                'opening_stock' => 12,
                'min_stock' => 3,
            ],
            [
                'color_name' => 'Black',
                'sku' => 'EWC-S1-BLK',
                'cost_price' => 500.00,
                'selling_price' => 850.00,
                'opening_stock' => 8,
                'min_stock' => 2,
            ],
        ],
    ];

    $response = $this->actingAs($this->owner)->post('/inventory/products', $payload);
    $response->assertRedirect();

    $product = Product::where('name', 'Eyewear Case Series 1')->first();
    expect($product)->not->toBeNull()
        ->and($product->has_variants)->toBeTrue()
        ->and($product->stock_qty)->toBe(20) // 12 + 8
        ->and($product->variants)->toHaveCount(2);

    $brown = $product->variants()->where('attribute_value', 'Brown')->first();
    $black = $product->variants()->where('attribute_value', 'Black')->first();

    expect($brown)->not->toBeNull()
        ->and($brown->sku)->toBe('EWC-S1-BRN')
        ->and($brown->stock_qty)->toBe(12)
        ->and($brown->color_name)->toBe('Brown')
        ->and($black)->not->toBeNull()
        ->and($black->sku)->toBe('EWC-S1-BLK')
        ->and($black->stock_qty)->toBe(8)
        ->and($black->color_name)->toBe('Black');
});

test('simple product without variations continues working seamlessly with direct stock', function () {
    $payload = [
        'name' => 'Standard Eyewear Cloth',
        'sku' => 'EWC-CLOTH-01',
        'product_category_id' => $this->category->id,
        'has_variants' => false,
        'cost_price' => 50.00,
        'selling_price' => 150.00,
        'opening_stock' => 50,
        'min_stock' => 10,
        'is_active' => true,
    ];

    $response = $this->actingAs($this->owner)->post('/inventory/products', $payload);
    $response->assertRedirect();

    $product = Product::where('sku', 'EWC-CLOTH-01')->first();
    expect($product)->not->toBeNull()
        ->and($product->has_variants)->toBeFalse()
        ->and($product->stock_qty)->toBe(50)
        ->and($product->variants)->toHaveCount(0);
});

test('selling Brown variant decrements only Brown stock and parent total, not Black stock', function () {
    $product = Product::create([
        'name' => 'Eyewear Case Series 1',
        'sku' => 'EWC-S1',
        'product_category_id' => $this->category->id,
        'has_variants' => true,
        'variation_type' => 'Color',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $brown = ProductVariant::create([
        'product_id' => $product->id,
        'attribute_name' => 'Color',
        'attribute_value' => 'Brown',
        'sku' => 'EWC-S1-BRN',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 12,
        'min_stock' => 3,
        'is_active' => true,
    ]);

    $black = ProductVariant::create([
        'product_id' => $product->id,
        'attribute_name' => 'Color',
        'attribute_value' => 'Black',
        'sku' => 'EWC-S1-BLK',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 8,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $salePayload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Test Customer',
        'customer_phone' => '01712345678',
        'customer_address' => 'House 12, Road 5, Dhanmondi, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => $brown->id,
                'variant_name' => 'Brown',
                'variant_sku' => 'EWC-S1-BRN',
                'name' => 'Eyewear Case Series 1',
                'qty' => 3,
                'unit_price' => 850.00,
            ],
        ],
        'paid_amount' => 2550.00,
        'payment_method' => 'cash',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $salePayload);
    $response->assertOk();

    $brown->refresh();
    $black->refresh();
    $product->refresh();

    // Brown stock should be 12 - 3 = 9
    expect($brown->stock_qty)->toBe(9);
    // Black stock should be untouched = 8
    expect($black->stock_qty)->toBe(8);
    // Parent total stock should be 9 + 8 = 17
    expect($product->stock_qty)->toBe(17);

    // Verify stock movement was recorded for the variant
    $movement = ProductStockMovement::where('product_id', $product->id)
        ->where('product_variant_id', $brown->id)
        ->latest('id')
        ->first();
    expect($movement)->not->toBeNull()
        ->and($movement->qty)->toBe(-3)
        ->and($movement->balance_after)->toBe(9);
});

test('insufficient variant stock prevents checkout and raises validation error', function () {
    $product = Product::create([
        'name' => 'Eyewear Case Series 1',
        'sku' => 'EWC-S1',
        'product_category_id' => $this->category->id,
        'has_variants' => true,
        'variation_type' => 'Color',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 5,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $black = ProductVariant::create([
        'product_id' => $product->id,
        'attribute_name' => 'Color',
        'attribute_value' => 'Black',
        'sku' => 'EWC-S1-BLK',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 2,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $salePayload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Test Customer',
        'customer_phone' => '01712345678',
        'customer_address' => 'House 12, Road 5, Dhanmondi, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => $black->id,
                'name' => 'Eyewear Case Series 1',
                'qty' => 5, // Only 2 in stock
                'unit_price' => 850.00,
            ],
        ],
        'paid_amount' => 4250.00,
        'payment_method' => 'cash',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $salePayload);
    $response->assertUnprocessable();

    $black->refresh();
    expect($black->stock_qty)->toBe(2); // Stock unchanged
});

test('can purchase both Brown and Black variants simultaneously in a single transaction', function () {
    $product = Product::create([
        'name' => 'Eyewear Case Series 1',
        'sku' => 'EWC-S1',
        'product_category_id' => $this->category->id,
        'has_variants' => true,
        'variation_type' => 'Color',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $brown = ProductVariant::create([
        'product_id' => $product->id,
        'attribute_name' => 'Color',
        'attribute_value' => 'Brown',
        'sku' => 'EWC-S1-BRN',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 12,
        'min_stock' => 3,
        'is_active' => true,
    ]);

    $black = ProductVariant::create([
        'product_id' => $product->id,
        'attribute_name' => 'Color',
        'attribute_value' => 'Black',
        'sku' => 'EWC-S1-BLK',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 8,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $salePayload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Multi Variant Buyer',
        'customer_phone' => '01712345678',
        'customer_address' => 'House 12, Road 5, Dhanmondi, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => $brown->id,
                'variant_name' => 'Brown',
                'variant_sku' => 'EWC-S1-BRN',
                'name' => 'Eyewear Case Series 1',
                'qty' => 2,
                'unit_price' => 850.00,
            ],
            [
                'product_id' => $product->id,
                'product_variant_id' => $black->id,
                'variant_name' => 'Black',
                'variant_sku' => 'EWC-S1-BLK',
                'name' => 'Eyewear Case Series 1',
                'qty' => 3,
                'unit_price' => 850.00,
            ],
        ],
        'paid_amount' => 4250.00,
        'payment_method' => 'cash',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $salePayload);
    $response->assertOk();

    $brown->refresh();
    $black->refresh();
    $product->refresh();

    expect($brown->stock_qty)->toBe(10) // 12 - 2
        ->and($black->stock_qty)->toBe(5) // 8 - 3
        ->and($product->stock_qty)->toBe(15); // 10 + 5

    $saleId = $response->json('sale.id');
    $items = SaleItem::where('sale_id', $saleId)->get();
    expect($items)->toHaveCount(2);

    $brownItem = $items->firstWhere('product_variant_id', $brown->id);
    expect($brownItem->variant_name)->toBe('Brown')
        ->and($brownItem->variant_sku)->toBe('EWC-S1-BRN')
        ->and($brownItem->qty)->toBe(2);

    $blackItem = $items->firstWhere('product_variant_id', $black->id);
    expect($blackItem->variant_name)->toBe('Black')
        ->and($blackItem->variant_sku)->toBe('EWC-S1-BLK')
        ->and($blackItem->qty)->toBe(3);
});

test('canceling a sale restores stock to the exact variant originally sold', function () {
    $product = Product::create([
        'name' => 'Eyewear Case Series 1',
        'sku' => 'EWC-S1',
        'product_category_id' => $this->category->id,
        'has_variants' => true,
        'variation_type' => 'Color',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $brown = ProductVariant::create([
        'product_id' => $product->id,
        'attribute_name' => 'Color',
        'attribute_value' => 'Brown',
        'sku' => 'EWC-S1-BRN',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 12,
        'min_stock' => 3,
        'is_active' => true,
    ]);

    $black = ProductVariant::create([
        'product_id' => $product->id,
        'attribute_name' => 'Color',
        'attribute_value' => 'Black',
        'sku' => 'EWC-S1-BLK',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 8,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $saleService = app(SaleService::class);
    $result = $saleService->createSale([
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Return Customer',
        'customer_phone' => '01712345678',
        'customer_address' => 'House 12, Road 5, Dhanmondi, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => $brown->id,
                'variant_name' => 'Brown',
                'variant_sku' => 'EWC-S1-BRN',
                'name' => 'Eyewear Case Series 1',
                'qty' => 4,
                'unit_price' => 850.00,
            ],
        ],
        'paid_amount' => 3400.00,
        'payment_method' => 'cash',
    ], $this->owner);

    $brown->refresh();
    expect($brown->stock_qty)->toBe(8); // 12 - 4

    // Now cancel sale
    $saleService->cancelSale($result['sale'], ['reason' => 'Customer changed mind'], $this->owner);

    $brown->refresh();
    $black->refresh();
    $product->refresh();

    // Brown stock should be fully restored to 12
    expect($brown->stock_qty)->toBe(12)
        ->and($black->stock_qty)->toBe(8)
        ->and($product->stock_qty)->toBe(20);
});

test('deactivated variant cannot be sold', function () {
    $product = Product::create([
        'name' => 'Eyewear Case Series 1',
        'sku' => 'EWC-S1',
        'product_category_id' => $this->category->id,
        'has_variants' => true,
        'variation_type' => 'Color',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 10,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $brown = ProductVariant::create([
        'product_id' => $product->id,
        'attribute_name' => 'Color',
        'attribute_value' => 'Brown',
        'sku' => 'EWC-S1-BRN',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 10,
        'min_stock' => 2,
        'is_active' => false, // Inactive!
    ]);

    $salePayload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Test Customer',
        'customer_phone' => '01712345678',
        'customer_address' => 'House 12, Road 5, Dhanmondi, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => $brown->id,
                'name' => 'Eyewear Case Series 1',
                'qty' => 1,
                'unit_price' => 850.00,
            ],
        ],
        'paid_amount' => 850.00,
        'payment_method' => 'cash',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $salePayload);
    $response->assertUnprocessable();
});

test('search controller returns product when searched by variant color or variant SKU', function () {
    $product = Product::create([
        'name' => 'Eyewear Case Series 1',
        'sku' => 'EWC-S1',
        'product_category_id' => $this->category->id,
        'has_variants' => true,
        'variation_type' => 'Color',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    ProductVariant::create([
        'product_id' => $product->id,
        'attribute_name' => 'Color',
        'attribute_value' => 'Brown',
        'sku' => 'EWC-S1-BRN',
        'cost_price' => 500.00,
        'selling_price' => 850.00,
        'stock_qty' => 12,
        'min_stock' => 3,
        'is_active' => true,
    ]);

    // Search by variant SKU
    $response = $this->actingAs($this->owner)->getJson('/search?q=EWC-S1-BRN');
    $response->assertOk();
    $products = $response->json('products');
    expect(collect($products)->contains(fn ($p) => $p['id'] === $product->id))->toBeTrue();

    // Search by variant color name "Brown"
    $response2 = $this->actingAs($this->owner)->getJson('/search?q=Brown');
    $response2->assertOk();
    $products2 = $response2->json('products');
    expect(collect($products2)->contains(fn ($p) => $p['id'] === $product->id))->toBeTrue();
});

test('CSV product import creates parent product with variants', function () {
    $importService = app(ProductImportService::class);

    $rows = [
        [
            'name' => 'Eyewear Case Series 2',
            'variation_attribute' => 'Color',
            'variation_value' => 'Brown',
            'sku' => 'EWC-S2-BRN',
            'category' => 'Eyewear Accessories',
            'cost_price' => 500.00,
            'selling_price' => 850.00,
            'opening_stock' => 15,
            'min_stock' => 3,
            'is_valid' => true,
            'errors' => [],
        ],
        [
            'name' => 'Eyewear Case Series 2',
            'variation_attribute' => 'Color',
            'variation_value' => 'Black',
            'sku' => 'EWC-S2-BLK',
            'category' => 'Eyewear Accessories',
            'cost_price' => 500.00,
            'selling_price' => 850.00,
            'opening_stock' => 10,
            'min_stock' => 2,
            'is_valid' => true,
            'errors' => [],
        ],
    ];

    $result = $importService->commit($rows, 'products.xlsx', $this->owner);
    expect($result['success_count'])->toBe(2);

    $product = Product::where('name', 'Eyewear Case Series 2')->first();
    expect($product)->not->toBeNull()
        ->and($product->has_variants)->toBeTrue()
        ->and($product->stock_qty)->toBe(25) // 15 + 10
        ->and($product->variants)->toHaveCount(2);

    $brown = $product->variants()->where('attribute_value', 'Brown')->first();
    expect($brown)->not->toBeNull()
        ->and($brown->sku)->toBe('EWC-S2-BRN')
        ->and($brown->stock_qty)->toBe(15);
});
