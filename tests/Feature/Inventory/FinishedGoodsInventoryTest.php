<?php

use App\Models\ImportLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
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

    $this->staff = User::factory()->create([
        'name' => 'Tasa Staff',
        'email' => 'staff@tasa.com',
    ]);
    $this->staff->assignSingleRole('Staff');
});

test('owner can view finished products with full metrics and cost price', function () {
    $category = ProductCategory::create(['name' => 'Wallets']);
    $product = Product::create([
        'product_category_id' => $category->id,
        'name' => 'Bifold Wallet – Tan',
        'sku' => 'P-000001',
        'cost_price' => 850.00,
        'selling_price' => 1450.00,
        'stock_qty' => 15,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)->get('/inventory/products');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Products')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Bifold Wallet – Tan')
        ->where('products.data.0.cost_price', 850)
        ->where('products.data.0.selling_price', 1450)
        ->where('canViewCost', true)
        ->where('canManageProducts', true)
        ->where('canDeleteProducts', true)
    );
});

test('staff user CANNOT see cost prices in products list props', function () {
    $product = Product::create([
        'name' => 'Executive Leather Bag',
        'sku' => 'P-000002',
        'cost_price' => 2500.00,
        'selling_price' => 4500.00,
        'stock_qty' => 8,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->staff)->get('/inventory/products');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Products')
        ->has('products.data', 1)
        ->where('canViewCost', false)
        ->where('canDeleteProducts', false)
        // Ensure cost_price is either null or stripped
        ->where('products.data.0.cost_price', null)
    );

    // Verify raw JSON response does not contain cost_price
    $props = $response->viewData('page')['props'];
    expect($props['canViewCost'])->toBeFalse();
    if (isset($props['products']['data'][0]['cost_price'])) {
        expect($props['products']['data'][0]['cost_price'])->toBeNull();
    }
});

test('auto-generates sequential SKU P-000123 format when SKU is blank', function () {
    $res1 = $this->actingAs($this->owner)->post('/inventory/products', [
        'name' => 'Classic Card Holder',
        'sku' => '',
        'selling_price' => 650.00,
        'cost_price' => 350.00,
        'min_stock' => 5,
        'opening_stock' => 0,
        'is_active' => true,
    ]);
    $res1->assertRedirect();

    $product1 = Product::where('name', 'Classic Card Holder')->first();
    expect($product1)->not->toBeNull();
    expect($product1->sku)->toBe('P-000001');

    $res2 = $this->actingAs($this->owner)->post('/inventory/products', [
        'name' => 'Classic Belt 35mm',
        'sku' => '   ',
        'selling_price' => 1200.00,
        'cost_price' => 700.00,
        'min_stock' => 3,
        'opening_stock' => 10,
        'is_active' => true,
    ]);
    $res2->assertRedirect();

    $product2 = Product::where('name', 'Classic Belt 35mm')->first();
    expect($product2)->not->toBeNull();
    expect($product2->sku)->toBe('P-000002');
    expect($product2->stock_qty)->toBe(10);

    // Check opening stock created matching movement
    $movement = ProductStockMovement::where('product_id', $product2->id)->first();
    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe('opening');
    expect($movement->qty)->toBe(10);
    expect($movement->balance_after)->toBe(10);

    // Custom SKU works when provided
    $res3 = $this->actingAs($this->owner)->post('/inventory/products', [
        'name' => 'Custom Monogram Wallet',
        'sku' => 'CUSTOM-099',
        'selling_price' => 2000.00,
        'cost_price' => 1100.00,
        'min_stock' => 2,
        'is_active' => true,
    ]);
    $res3->assertRedirect();

    $product3 = Product::where('name', 'Custom Monogram Wallet')->first();
    expect($product3->sku)->toBe('CUSTOM-099');
});

test('validates non-negative cost and selling prices', function () {
    $response = $this->actingAs($this->owner)->post('/inventory/products', [
        'name' => 'Invalid Price Product',
        'sku' => 'P-BAD',
        'selling_price' => -50.00,
        'cost_price' => -20.00,
        'min_stock' => -1,
    ]);

    $response->assertSessionHasErrors(['selling_price', 'cost_price', 'min_stock']);
    expect(Product::where('sku', 'P-BAD')->exists())->toBeFalse();
});

test('production entry adds stock and records signed movement atomically', function () {
    $product = Product::create([
        'name' => 'Keychain Loop',
        'sku' => 'P-000010',
        'cost_price' => 100.00,
        'selling_price' => 250.00,
        'stock_qty' => 5,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    // Initial movement
    ProductStockMovement::create([
        'product_id' => $product->id,
        'type' => 'opening',
        'qty' => 5,
        'balance_after' => 5,
        'movement_date' => now()->toDateString(),
        'note' => 'Initial',
        'created_by' => $this->owner->id,
    ]);

    $response = $this->actingAs($this->staff)->post("/inventory/products/{$product->id}/add-stock", [
        'qty' => 25,
        'date' => '2026-10-12',
        'note' => 'Batch 3 workshop production',
    ]);

    $response->assertRedirect();
    $product->refresh();

    expect($product->stock_qty)->toBe(30);

    $latestMovement = ProductStockMovement::where('product_id', $product->id)
        ->latest('id')
        ->first();

    expect($latestMovement->type)->toBe('production');
    expect($latestMovement->qty)->toBe(25);
    expect($latestMovement->balance_after)->toBe(30);
    expect($latestMovement->movement_date->toDateString())->toBe('2026-10-12');
    expect($latestMovement->note)->toBe('Batch 3 workshop production');
    expect($latestMovement->created_by)->toBe($this->staff->id);

    // Verify append-only protection
    expect(function () use ($latestMovement) {
        $latestMovement->update(['qty' => 99]);
    })->toThrow(RuntimeException::class);

    expect(function () use ($latestMovement) {
        $latestMovement->delete();
    })->toThrow(RuntimeException::class);
});

test('stock adjustments write adjustment and movement rows atomically', function () {
    $product = Product::create([
        'name' => 'Passport Cover',
        'sku' => 'P-000020',
        'cost_price' => 400.00,
        'selling_price' => 850.00,
        'stock_qty' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    ProductStockMovement::create([
        'product_id' => $product->id,
        'type' => 'opening',
        'qty' => 20,
        'balance_after' => 20,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->owner->id,
    ]);

    // Damaged adjustment: -3
    $response = $this->actingAs($this->staff)->post("/inventory/products/{$product->id}/adjust-stock", [
        'qty' => -3,
        'adjusted_on' => '2026-10-12',
        'reason' => 'damaged',
        'note' => 'Water leak in shelf A',
    ]);

    $response->assertRedirect();
    $product->refresh();

    expect($product->stock_qty)->toBe(17);

    $adjustment = StockAdjustment::where('item_type', 'product')
        ->where('item_id', $product->id)
        ->first();

    expect($adjustment)->not->toBeNull();
    expect((float) $adjustment->qty)->toBe(-3.0);
    expect($adjustment->reason)->toBe('damaged');
    expect($adjustment->note)->toBe('Water leak in shelf A');

    $movement = ProductStockMovement::where('product_id', $product->id)
        ->latest('id')
        ->first();

    expect($movement->type)->toBe('adjustment');
    expect($movement->qty)->toBe(-3);
    expect($movement->balance_after)->toBe(17);
    expect($movement->reference_type)->toBe('stock_adjustments');
    expect($movement->reference_id)->toBe($adjustment->id);

    // Reason 'other' requires note
    $invalidResponse = $this->actingAs($this->staff)->post("/inventory/products/{$product->id}/adjust-stock", [
        'qty' => 1,
        'adjusted_on' => '2026-10-12',
        'reason' => 'other',
        'note' => '',
    ]);

    $invalidResponse->assertSessionHasErrors('note');
});

test('negative stock is prohibited when allow_negative_stock is 0', function () {
    Setting::set('inventory', 'allow_negative_stock', '0');

    $product = Product::create([
        'name' => 'Leather Coaster Set',
        'sku' => 'P-000030',
        'cost_price' => 200.00,
        'selling_price' => 500.00,
        'stock_qty' => 4,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    ProductStockMovement::create([
        'product_id' => $product->id,
        'type' => 'opening',
        'qty' => 4,
        'balance_after' => 4,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->owner->id,
    ]);

    // Attempting adjustment of -5 would drop stock to -1
    $response = $this->actingAs($this->staff)->post("/inventory/products/{$product->id}/adjust-stock", [
        'qty' => -5,
        'adjusted_on' => '2026-10-12',
        'reason' => 'lost',
        'note' => 'Lost count',
    ]);

    $response->assertSessionHasErrors('qty');
    $product->refresh();
    expect($product->stock_qty)->toBe(4);

    // Enabling setting allows negative stock
    Setting::set('inventory', 'allow_negative_stock', '1');

    $allowedResponse = $this->actingAs($this->staff)->post("/inventory/products/{$product->id}/adjust-stock", [
        'qty' => -5,
        'adjusted_on' => '2026-10-12',
        'reason' => 'lost',
        'note' => 'Lost count',
    ]);

    $allowedResponse->assertRedirect();
    $product->refresh();
    expect($product->stock_qty)->toBe(-1);
});

test('inline minimum stock edit updates value and validates input', function () {
    $product = Product::create([
        'name' => 'Watch Strap 20mm',
        'sku' => 'P-000040',
        'cost_price' => 300.00,
        'selling_price' => 750.00,
        'stock_qty' => 10,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->staff)->patchJson("/inventory/products/{$product->id}/min-stock", [
        'min_stock' => 8,
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'min_stock' => 8,
    ]);

    $product->refresh();
    expect($product->min_stock)->toBe(8);

    // Negative min stock rejected
    $badResponse = $this->actingAs($this->staff)->patchJson("/inventory/products/{$product->id}/min-stock", [
        'min_stock' => -3,
    ]);
    $badResponse->assertUnprocessable();
});

test('movements history endpoint returns chronological audit ledger', function () {
    $product = Product::create([
        'name' => 'Desk Mat',
        'sku' => 'P-000050',
        'cost_price' => 1200.00,
        'selling_price' => 2200.00,
        'stock_qty' => 10,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    ProductStockMovement::create([
        'product_id' => $product->id,
        'type' => 'opening',
        'qty' => 10,
        'balance_after' => 10,
        'movement_date' => '2026-10-01',
        'note' => 'Initial stock',
        'created_by' => $this->owner->id,
    ]);

    ProductStockMovement::create([
        'product_id' => $product->id,
        'type' => 'production',
        'qty' => 5,
        'balance_after' => 15,
        'movement_date' => '2026-10-05',
        'note' => 'Batch 2',
        'created_by' => $this->staff->id,
    ]);

    $response = $this->actingAs($this->staff)->getJson("/inventory/products/{$product->id}/movements");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(2);
    expect($data[0]['type'])->toBe('production');
    expect($data[0]['qty'])->toBe(5);
    expect($data[0]['balance_after'])->toBe(15);
    expect($data[0]['creator_name'])->toBe('Tasa Staff');
});

test('download import template supports both XLSX and CSV', function () {
    $xlsxResponse = $this->actingAs($this->owner)->get('/inventory/products/import/template?format=xlsx');
    $xlsxResponse->assertOk();
    expect($xlsxResponse->headers->get('content-type'))->toContain('spreadsheetml');

    $csvResponse = $this->actingAs($this->owner)->get('/inventory/products/import/template?format=csv');
    $csvResponse->assertOk();
    expect($csvResponse->headers->get('content-type'))->toContain('text/csv');
});

test('excel import previews row errors and duplicate SKUs and commits valid rows', function () {
    // Existing product in database to test DB collision
    Product::create([
        'name' => 'Existing Product',
        'sku' => 'P-EXISTS',
        'selling_price' => 500.00,
        'stock_qty' => 5,
        'min_stock' => 1,
    ]);

    $csvContent = implode("\n", [
        'Name,SKU,Category,Cost price,Selling price,Opening stock,Min stock',
        'Valid Imported Wallet,P-IMP001,Wallets,600.00,1200.00,10,3',
        'Duplicate SKU in DB,P-EXISTS,Wallets,500.00,1000.00,5,2',
        ',P-NONAME,Wallets,400.00,800.00,5,2',
        'Negative Price Item,P-BADPRICE,Wallets,-10.00,-50.00,5,2',
        'Duplicate SKU in File 1,P-DUPFILE,Wallets,500.00,900.00,5,2',
        'Duplicate SKU in File 2,P-DUPFILE,Wallets,500.00,900.00,5,2',
    ]);

    $file = UploadedFile::fake()->createWithContent('products_test.csv', $csvContent);

    // Preview
    $previewResponse = $this->actingAs($this->owner)->post('/inventory/products/import/preview', [
        'file' => $file,
    ]);

    $previewResponse->assertOk();
    $previewData = $previewResponse->json();

    expect($previewData['total_rows'])->toBe(6);
    expect($previewData['valid_count'])->toBe(2); // Valid Imported Wallet & Duplicate SKU in File 1 (first occurrence)
    expect($previewData['invalid_count'])->toBe(4);

    // Commit only valid rows
    $validRows = array_filter($previewData['rows'], fn ($r) => $r['is_valid']);

    $commitResponse = $this->actingAs($this->owner)->postJson('/inventory/products/import/commit', [
        'filename' => 'products_test.csv',
        'rows' => array_values($validRows),
    ]);

    $commitResponse->assertOk();
    $commitResult = $commitResponse->json('result');
    expect($commitResult['success_count'])->toBe(2);

    $imported = Product::where('sku', 'P-IMP001')->first();
    expect($imported)->not->toBeNull();
    expect($imported->name)->toBe('Valid Imported Wallet');
    expect($imported->stock_qty)->toBe(10);
    expect($imported->category->name)->toBe('Wallets');

    // Check opening movement created
    $movement = ProductStockMovement::where('product_id', $imported->id)->first();
    expect($movement->type)->toBe('opening');
    expect($movement->qty)->toBe(10);
    expect($movement->balance_after)->toBe(10);

    // Check ImportLog created
    $importLog = ImportLog::where('filename', 'products_test.csv')->first();
    expect($importLog)->not->toBeNull();
    expect($importLog->success_rows)->toBe(2);
});

test('soft-delete archives product without breaking movements or invoices', function () {
    $product = Product::create([
        'name' => 'To Archive Product',
        'sku' => 'P-ARCHIVE',
        'selling_price' => 500.00,
        'stock_qty' => 5,
        'min_stock' => 1,
    ]);

    ProductStockMovement::create([
        'product_id' => $product->id,
        'type' => 'opening',
        'qty' => 5,
        'balance_after' => 5,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->owner->id,
    ]);

    $response = $this->actingAs($this->owner)->delete("/inventory/products/{$product->id}");
    $response->assertRedirect();

    expect(Product::find($product->id))->toBeNull();
    expect(Product::withTrashed()->find($product->id))->not->toBeNull();

    // Movement remains intact in database
    expect(ProductStockMovement::where('product_id', $product->id)->count())->toBe(1);

    // Staff cannot delete products (Owner only by default)
    $staffProduct = Product::create([
        'name' => 'Staff Product',
        'sku' => 'P-STAFF',
        'selling_price' => 300.00,
        'stock_qty' => 1,
        'min_stock' => 1,
    ]);

    $forbiddenResponse = $this->actingAs($this->staff)->delete("/inventory/products/{$staffProduct->id}");
    $forbiddenResponse->assertForbidden();
});

test('inventory:verify-stock artisan command verifies stock_qty equals movement ledger sum', function () {
    $product = Product::create([
        'name' => 'Integrity Check Product',
        'sku' => 'P-INTEGRITY',
        'selling_price' => 1000.00,
        'stock_qty' => 15,
        'min_stock' => 2,
    ]);

    ProductStockMovement::create([
        'product_id' => $product->id,
        'type' => 'opening',
        'qty' => 10,
        'balance_after' => 10,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->owner->id,
    ]);

    ProductStockMovement::create([
        'product_id' => $product->id,
        'type' => 'production',
        'qty' => 5,
        'balance_after' => 15,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->owner->id,
    ]);

    // Command should pass with 0 discrepancies
    $this->artisan('inventory:verify-stock')
        ->expectsOutputToContain('100% of products match movement ledger sum')
        ->assertSuccessful();

    // Corrupt cached stock_qty manually to test discrepancy detection and --fix
    DB::table('products')->where('id', $product->id)->update(['stock_qty' => 999]);

    $this->artisan('inventory:verify-stock')
        ->expectsOutputToContain('Found 1 discrepancies')
        ->assertFailed();

    // Run with --fix
    $this->artisan('inventory:verify-stock', ['--fix' => true])
        ->expectsOutputToContain('All discrepancies repaired successfully')
        ->assertSuccessful();

    $product->refresh();
    expect($product->stock_qty)->toBe(15);
});
