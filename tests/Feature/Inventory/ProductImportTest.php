<?php

namespace Tests\Feature\Inventory;

use App\Models\ImportLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->owner = User::factory()->create(['email' => 'owner@tasa.com']);
    $this->owner->assignSingleRole('Owner');

    $this->staff = User::factory()->create(['email' => 'staff@tasa.com']);
    $this->staff->assignSingleRole('Staff');

    $this->category = ProductCategory::create(['name' => 'Wallets']);
});

test('1. template download is available in both xlsx and csv format', function () {
    $xlsxResponse = $this->actingAs($this->owner)->get('/inventory/products/import/template?format=xlsx');
    $xlsxResponse->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $csvResponse = $this->actingAs($this->owner)->get('/inventory/products/import/template?format=csv');
    $csvResponse->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('2. preview endpoint validates uploaded rows, detects duplicate SKUs and missing names', function () {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['Name', 'SKU', 'Category', 'Cost price', 'Selling price', 'Opening stock', 'Min stock'],
        ['Minimalist Cardholder', 'SKU-CARD-01', 'Wallets', '350.00', '650.00', '10', '2'],
        ['Duplicate SKU Item', 'SKU-CARD-01', 'Wallets', '400.00', '700.00', '5', '1'], // duplicate in file!
        ['', 'SKU-NONAME-01', 'Wallets', '200.00', '500.00', '10', '2'], // missing name!
        ['Negative Price Item', 'SKU-NEG-01', 'Wallets', '-50.00', '100.00', '5', '1'], // negative cost!
    ]);

    $tempFile = tempnam(sys_get_temp_dir(), 'import_test_') . '.xlsx';
    (new Xlsx($spreadsheet))->save($tempFile);

    $uploadedFile = new UploadedFile($tempFile, 'products.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->actingAs($this->owner)->postJson('/inventory/products/import/preview', [
        'file' => $uploadedFile,
    ]);

    $response->assertOk();
    $data = $response->json();

    expect($data['total_rows'])->toBe(4)
        ->and($data['invalid_count'])->toBe(3)
        ->and($data['valid_count'])->toBe(1);

    // Row 1 is valid
    expect($data['rows'][0]['is_valid'])->toBeTrue();
    // Row 2 has duplicate SKU error
    expect($data['rows'][1]['is_valid'])->toBeFalse()
        ->and(implode(' ', $data['rows'][1]['errors']))->toContain('Duplicate SKU');
    // Row 3 has missing name error
    expect($data['rows'][2]['is_valid'])->toBeFalse()
        ->and(implode(' ', $data['rows'][2]['errors']))->toContain('name is required');
    // Row 4 has negative price error
    expect($data['rows'][3]['is_valid'])->toBeFalse()
        ->and(implode(' ', $data['rows'][3]['errors']))->toContain('cannot be negative');

    @unlink($tempFile);
});

test('3. commit imports valid products, records opening stock movements, and creates import log', function () {
    $validRows = [
        [
            'name' => 'Bifold Wallet Vintage Tan',
            'sku' => 'SKU-VINTAGE-TAN',
            'category' => 'Wallets',
            'cost_price' => 850.00,
            'selling_price' => 1600.00,
            'opening_stock' => 15,
            'min_stock' => 3,
            'is_valid' => true,
            'errors' => [],
        ],
        [
            'name' => 'Leather Key Fob Dark Brown',
            'sku' => '', // Auto-generate SKU
            'category' => 'Wallets',
            'cost_price' => 120.00,
            'selling_price' => 250.00,
            'opening_stock' => 50,
            'min_stock' => 10,
            'is_valid' => true,
            'errors' => [],
        ],
    ];

    $response = $this->actingAs($this->owner)->postJson('/inventory/products/import/commit', [
        'filename' => 'products.xlsx',
        'rows' => $validRows,
    ]);

    $response->assertOk();
    expect($response->json('result.success_count'))->toBe(2);

    // Verify products created
    $product1 = Product::where('name', 'Bifold Wallet Vintage Tan')->first();
    expect($product1)->not->toBeNull()
        ->and($product1->sku)->toBe('SKU-VINTAGE-TAN')
        ->and((float) $product1->selling_price)->toBe(1600.00)
        ->and((int) $product1->stock_qty)->toBe(15);

    $product2 = Product::where('name', 'Leather Key Fob Dark Brown')->first();
    expect($product2)->not->toBeNull()
        ->and($product2->sku)->toStartWith('P-')
        ->and((int) $product2->stock_qty)->toBe(50);

    // Verify stock movements created for opening stock
    $movement1 = ProductStockMovement::where('product_id', $product1->id)->where('type', 'opening')->first();
    expect($movement1)->not->toBeNull()
        ->and((int) $movement1->qty)->toBe(15)
        ->and((int) $movement1->balance_after)->toBe(15);

    // Verify import log entry exists
    $log = ImportLog::latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($this->owner->id)
        ->and($log->success_rows)->toBe(2);
});

test('4. staff user without permission cannot commit product import', function () {
    $rows = [
        [
            'name' => 'Staff Test Wallet',
            'sku' => 'SKU-STAFF-01',
            'category' => 'Wallets',
            'cost_price' => 500.00,
            'selling_price' => 1000.00,
            'opening_stock' => 5,
            'min_stock' => 1,
            'is_valid' => true,
            'errors' => [],
        ],
    ];

    // Staff without inventory.create / products.import permission
    $response = $this->actingAs($this->staff)->postJson('/inventory/products/import/commit', [
        'filename' => 'products.xlsx',
        'rows' => $rows,
    ]);

    $response->assertStatus(403);
    expect(Product::where('sku', 'SKU-STAFF-01')->exists())->toBeFalse();
});
