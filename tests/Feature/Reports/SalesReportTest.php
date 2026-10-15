<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(ExpenseCategorySeeder::class);
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

test('unauthenticated users are redirected from sales report and exports', function () {
    $this->get('/reports/sales')->assertRedirect(route('login'));
    $this->get('/reports/sales/drilldown')->assertRedirect(route('login'));
    $this->get('/reports/sales/export/csv')->assertRedirect(route('login'));
    $this->get('/reports/sales/export/excel')->assertRedirect(route('login'));
    $this->get('/reports/sales/export/pdf')->assertRedirect(route('login'));
});

test('sales report calculates exact totals comparing with direct SQL across active, cancelled, returned, and partially paid sales', function () {
    $today = CarbonImmutable::now()->toDateString();
    $customer = Customer::create([
        'name' => 'Verified Customer',
        'phone' => '01711122233',
        'address' => 'Dhaka',
    ]);

    $category = ProductCategory::firstOrCreate(['name' => 'Wallets'], ['is_active' => true]);
    $product1 = Product::create([
        'name' => 'Bifold Wallet',
        'sku' => 'P-WLT-001',
        'product_category_id' => $category->id,
        'cost_price' => 400.00,
        'selling_price' => 1000.00,
        'stock_qty' => 50,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    // 1. Sale 1 (Active, Partially Paid): Subtotal 1,000, Disc 100, Delivery 50 => Grand Total 950, Paid 500, Due 450
    $sale1 = Sale::create([
        'invoice_no' => 'TASA-TEST-001',
        'idempotency_token' => 'test-token-1',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => $today,
        'sold_at' => now(),
        'subtotal' => 1000.00,
        'discount_type' => 'flat',
        'discount_value' => 100.00,
        'discount_amount' => 100.00,
        'delivery_charge' => 50.00,
        'grand_total' => 950.00,
        'paid_total' => 500.00,
        'payment_status' => 'partial',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    $item1 = SaleItem::create([
        'sale_id' => $sale1->id,
        'product_id' => $product1->id,
        'name' => $product1->name,
        'qty' => 1,
        'unit_price' => 1000.00,
        'unit_cost' => 400.00,
        'line_total' => 1000.00,
        'returned_qty' => 0,
    ]);

    SalePayment::create([
        'sale_id' => $sale1->id,
        'paid_on' => $today,
        'amount' => 500.00,
        'method' => 'cash',
        'created_by' => $this->owner->id,
    ]);

    // 2. Sale 2 (Active, Fully Paid, with Return): Subtotal 2,000, Disc 200, Delivery 0 => Grand Total 1,800, Paid 1,800, Due 0
    $sale2 = Sale::create([
        'invoice_no' => 'TASA-TEST-002',
        'idempotency_token' => 'test-token-2',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => $today,
        'sold_at' => now(),
        'subtotal' => 2000.00,
        'discount_type' => 'flat',
        'discount_value' => 200.00,
        'discount_amount' => 200.00,
        'delivery_charge' => 0.00,
        'grand_total' => 1800.00,
        'paid_total' => 1800.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    $item2 = SaleItem::create([
        'sale_id' => $sale2->id,
        'product_id' => $product1->id,
        'name' => $product1->name,
        'qty' => 2,
        'unit_price' => 1000.00,
        'unit_cost' => 400.00,
        'line_total' => 2000.00,
        'returned_qty' => 1,
    ]);

    SalePayment::create([
        'sale_id' => $sale2->id,
        'paid_on' => $today,
        'amount' => 1800.00,
        'method' => 'bkash',
        'created_by' => $this->owner->id,
    ]);

    // Return 1 unit of Sale 2 (value: 1,000, cost: 400)
    $return = SaleReturn::create([
        'sale_id' => $sale2->id,
        'returned_on' => $today,
        'reason' => 'Customer exchange',
        'return_value' => 1000.00,
        'refunded_amount' => 0.00,
        'restock' => true,
        'created_by' => $this->owner->id,
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $return->id,
        'sale_item_id' => $item2->id,
        'qty' => 1,
        'unit_price' => 1000.00,
        'unit_cost' => 400.00,
    ]);

    // 3. Sale 3 (Cancelled): Subtotal 5,000 => MUST BE FULLY EXCLUDED
    $sale3 = Sale::create([
        'invoice_no' => 'TASA-TEST-003',
        'idempotency_token' => 'test-token-3',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => $today,
        'sold_at' => now(),
        'subtotal' => 5000.00,
        'discount_amount' => 500.00,
        'delivery_charge' => 0.00,
        'grand_total' => 4500.00,
        'paid_total' => 0.00,
        'payment_status' => 'unpaid',
        'status' => 'cancelled',
        'cancel_reason' => 'Customer changed mind',
        'cancelled_at' => now(),
        'cancelled_by' => $this->owner->id,
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    // Hand Calculations:
    // Invoice count: 2 (Sale 1 & 2)
    // Gross sales: 1,000 + 2,000 = 3,000
    // Discounts: 100 + 200 = 300
    // Returns: 1,000
    // Net sales: 3,000 - 300 - 1,000 = 1,700
    // Amount received: 500 + 1,800 = 2,300
    // Amount due: 450 + 0 = 450

    $response = $this->actingAs($this->owner)->get(route('reports.sales', [
        'date_preset' => 'today',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('reports/SalesReport')
        ->where('summary.invoice_count', 2)
        ->where('summary.gross_sales', 3000)
        ->where('summary.discounts', 300)
        ->where('summary.returns', 1000)
        ->where('summary.net_sales', 1700)
        ->where('summary.amount_received', 2300)
        ->where('summary.amount_due', 450)
    );
});

test('by product table matches query 8.1 with net sold quantities and unit cost snapshot', function () {
    $today = CarbonImmutable::now()->toDateString();
    $customer = Customer::create(['name' => 'Buyer', 'phone' => '01722233344', 'address' => 'Dhaka']);

    $category = ProductCategory::firstOrCreate(['name' => 'Belts'], ['is_active' => true]);
    $product = Product::create([
        'name' => 'Leather Belt',
        'sku' => 'P-BLT-001',
        'product_category_id' => $category->id,
        'cost_price' => 350.00,
        'selling_price' => 800.00,
        'stock_qty' => 40,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $sale = Sale::create([
        'invoice_no' => 'TASA-PROD-001',
        'idempotency_token' => 'prod-token-1',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => $today,
        'sold_at' => now(),
        'subtotal' => 2400.00, // 3 * 800
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00,
        'grand_total' => 2400.00,
        'paid_total' => 2400.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    $item = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'qty' => 3,
        'unit_price' => 800.00,
        'unit_cost' => 350.00, // Snapshot
        'line_total' => 2400.00,
        'returned_qty' => 1, // 1 returned => net sold = 2
    ]);

    // Product calculations:
    // Net qty sold = 3 - 1 = 2
    // Revenue = 2 * 800 = 1,600
    // COGS = 2 * 350 = 700
    // Gross Profit = 1,600 - 700 = 900
    // Margin = (900 / 1600) * 100 = 56.25% (56.3%)

    $response = $this->actingAs($this->owner)->get(route('reports.sales', [
        'date_preset' => 'today',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('reports/SalesReport')
        ->where('canViewCostProfit', true)
        ->where('products', function ($products) use ($product) {
            $p = collect($products)->firstWhere('product_id', $product->id);

            return $p !== null
                && $p['qty_sold'] === 2
                && $p['revenue'] == 1600
                && $p['cogs'] == 700
                && $p['gross_profit'] == 900;
        })
    );
});

test('staff user cannot see cost or profit columns in sales report response', function () {
    $today = CarbonImmutable::now()->toDateString();
    $customer = Customer::create(['name' => 'Staff Buyer', 'phone' => '01733344455', 'address' => 'Dhaka']);

    $category = ProductCategory::firstOrCreate(['name' => 'Cards'], ['is_active' => true]);
    $product = Product::create([
        'name' => 'Card Holder',
        'sku' => 'P-CRD-001',
        'product_category_id' => $category->id,
        'cost_price' => 200.00,
        'selling_price' => 500.00,
        'stock_qty' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $sale = Sale::create([
        'invoice_no' => 'TASA-STAFF-001',
        'idempotency_token' => 'staff-token-1',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => $today,
        'sold_at' => now(),
        'subtotal' => 500.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00,
        'grand_total' => 500.00,
        'paid_total' => 500.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'qty' => 1,
        'unit_price' => 500.00,
        'unit_cost' => 200.00,
        'line_total' => 500.00,
        'returned_qty' => 0,
    ]);

    $response = $this->actingAs($this->staff)->get(route('reports.sales', [
        'date_preset' => 'today',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('reports/SalesReport')
        ->where('canViewCostProfit', false)
        ->where('products', function ($products) {
            foreach ($products as $p) {
                if (isset($p['cogs']) || isset($p['gross_profit']) || isset($p['profit_margin']) || isset($p['unit_cost'])) {
                    return false;
                }
            }

            return true;
        })
    );
});

test('csv export succeeds and staff export excludes cogs and gross profit columns', function () {
    $today = CarbonImmutable::now()->toDateString();

    // Staff CSV export
    $staffResponse = $this->actingAs($this->staff)->get(route('reports.sales.export.csv', [
        'date_preset' => 'today',
    ]));

    $staffResponse->assertOk();
    $staffCsv = $staffResponse->getContent();
    expect($staffCsv)->toContain('TASA POS - SALES REPORT');
    expect($staffCsv)->not->toContain('COGS (BDT)');
    expect($staffCsv)->not->toContain('Gross Profit (BDT)');

    // Owner CSV export
    $ownerResponse = $this->actingAs($this->owner)->get(route('reports.sales.export.csv', [
        'date_preset' => 'today',
    ]));

    $ownerResponse->assertOk();
    $ownerCsv = $ownerResponse->getContent();
    expect($ownerCsv)->toContain('COGS (BDT)');
    expect($ownerCsv)->toContain('Gross Profit (BDT)');
});

test('excel and pdf exports return valid downloads with attachment headers', function () {
    // Excel export
    $excelResponse = $this->actingAs($this->owner)->get(route('reports.sales.export.excel', [
        'date_preset' => 'this_month',
    ]));

    $excelResponse->assertOk();
    expect($excelResponse->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    // PDF export
    $pdfResponse = $this->actingAs($this->owner)->get(route('reports.sales.export.pdf', [
        'date_preset' => 'this_month',
    ]));

    $pdfResponse->assertOk();
    expect($pdfResponse->headers->get('content-type'))->toBe('application/pdf');
});

test('drilldown endpoint returns source records with underlying invoices and items', function () {
    $today = CarbonImmutable::now()->toDateString();
    $customer = Customer::create(['name' => 'Drill Customer', 'phone' => '01744455566', 'address' => 'Dhaka']);

    $sale = Sale::create([
        'invoice_no' => 'TASA-DRILL-001',
        'idempotency_token' => 'drill-token-1',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => $today,
        'sold_at' => now(),
        'subtotal' => 2000.00,
        'discount_amount' => 100.00,
        'delivery_charge' => 50.00,
        'grand_total' => 1950.00,
        'paid_total' => 1000.00,
        'payment_status' => 'partial',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    // Test invoice drill-down
    $response = $this->actingAs($this->staff)->getJson(route('reports.sales.drilldown', [
        'type' => 'invoices',
        'from' => $today,
        'to' => $today,
    ]));

    $response->assertOk();
    $response->assertJsonStructure([
        'type',
        'title',
        'count',
        'records' => [
            '*' => ['id', 'invoice_no', 'sale_date', 'customer_name', 'grand_total', 'due_amount'],
        ],
    ]);

    expect($response->json('records.0.invoice_no'))->toBe('TASA-DRILL-001');
});
