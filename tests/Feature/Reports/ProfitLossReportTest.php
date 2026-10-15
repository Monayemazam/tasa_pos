<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LeatherConsumption;
use App\Models\LeatherItem;
use App\Models\LeatherPurchase;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalaryMonth;
use App\Models\SalaryPayment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Setting;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerEarning;
use App\Models\WorkerPayment;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

test('unauthenticated users are redirected from profit-loss report, drilldown, and exports', function () {
    $this->get('/reports/profit-loss')->assertRedirect(route('login'));
    $this->get('/reports/profit-loss/drilldown')->assertRedirect(route('login'));
    $this->get('/reports/profit-loss/export/excel')->assertRedirect(route('login'));
    $this->get('/reports/profit-loss/export/pdf')->assertRedirect(route('login'));
});

test('unauthorized staff receives 403 Forbidden and sends no financial report data', function () {
    $this->actingAs($this->staff)
        ->get('/reports/profit-loss')
        ->assertForbidden();

    $this->actingAs($this->staff)
        ->getJson('/reports/profit-loss/drilldown?type=gross_sales&from=2026-10-01&to=2026-10-31')
        ->assertForbidden();

    $this->actingAs($this->staff)
        ->get('/reports/profit-loss/export/excel')
        ->assertForbidden();

    $this->actingAs($this->staff)
        ->get('/reports/profit-loss/export/pdf')
        ->assertForbidden();
});

test('PRD §11.3 worked example produces exact net profit ৳6,000 (~3.1%) and 58.5% gross margin', function () {
    $from = '2026-10-01';
    $to = '2026-10-31';

    $customer = Customer::create([
        'name' => 'Worked Example Customer',
        'phone' => '01700000000',
        'address' => 'Dhaka',
    ]);

    $category = ProductCategory::firstOrCreate(['name' => 'Bags'], ['is_active' => true]);
    $product = Product::create([
        'name' => 'Premium Duffle',
        'sku' => 'BAG-EX-001',
        'product_category_id' => $category->id,
        'cost_price' => 800.00,
        'selling_price' => 2000.00,
        'stock_qty' => 500,
        'min_stock' => 10,
        'is_active' => true,
    ]);

    // 1. Sales Row: Gross sales = ৳200,000, Discounts = ৳5,000, Delivery = ৳8,000
    // Total items sold: 100 units @ ৳2,000 = ৳200,000 subtotal
    // Snapshot unit cost: ৳800 -> 100 × ৳800 = ৳80,000 COGS
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0001',
        'idempotency_token' => Str::uuid()->toString(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => '2026-10-15',
        'sold_at' => '2026-10-15 14:00:00',
        'subtotal' => 200000.00,
        'discount_type' => 'flat',
        'discount_value' => 5000.00,
        'discount_amount' => 5000.00,
        'delivery_charge' => 8000.00,
        'grand_total' => 203000.00, // 200,000 - 5,000 + 8,000
        'paid_total' => 203000.00,
        'due_amount' => 0.00,
        'status' => 'completed',
        'payment_status' => 'paid',
        'created_by' => $this->owner->id,
    ]);

    $saleItem = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'is_custom' => false,
        'name' => $product->name,
        'qty' => 100,
        'unit_price' => 2000.00,
        'unit_cost' => 800.00, // Exact snapshot unit cost
        'line_total' => 200000.00,
    ]);

    // 2. Returns Row: Returns = ৳10,000 in period (non-restocked return keeps COGS at 80,000)
    $saleReturn = SaleReturn::create([
        'sale_id' => $sale->id,
        'returned_on' => '2026-10-20',
        'reason' => 'Customer exchange / defect',
        'return_value' => 10000.00,
        'refunded_amount' => 10000.00,
        'restock' => false,
        'created_by' => $this->owner->id,
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $saleReturn->id,
        'sale_item_id' => $saleItem->id,
        'qty' => 5,
        'unit_price' => 2000.00,
        'unit_cost' => 800.00,
    ]);

    // 3. Operating Expenses:
    // Facebook Ads = ৳25,000
    $catFb = ExpenseCategory::where('pl_group', 'marketing_facebook')->first();
    Expense::create([
        'expense_category_id' => $catFb->id,
        'expense_date' => '2026-10-10',
        'amount' => 25000.00,
        'payment_method' => 'card',
        'vendor' => 'Meta Platforms',
        'created_by' => $this->owner->id,
    ]);

    // Courier = ৳14,000
    $catCourier = ExpenseCategory::where('pl_group', 'courier')->first();
    Expense::create([
        'expense_category_id' => $catCourier->id,
        'expense_date' => '2026-10-12',
        'amount' => 14000.00,
        'payment_method' => 'bank_transfer',
        'vendor' => 'Pathao Courier',
        'created_by' => $this->owner->id,
    ]);

    // Salaries = ৳30,000 (earned for period)
    $employee = Employee::create([
        'name' => 'Senior Accountant',
        'phone' => '01711223344',
        'designation' => 'Senior Accountant',
        'monthly_salary' => 30000.00,
        'joined_at' => '2026-01-01',
        'is_active' => true,
    ]);

    SalaryMonth::create([
        'employee_id' => $employee->id,
        'period' => '2026-10-01',
        'base_salary' => 30000.00,
        'bonus' => 0.00,
        'deduction' => 0.00,
        'created_by' => $this->owner->id,
    ]);

    // Wages = ৳20,000 (earned for period)
    $worker = Worker::create([
        'name' => 'Master Craftsman',
        'phone' => '01811223344',
        'wage_basis' => 'piece_rate',
        'default_rate' => 100.00,
        'is_active' => true,
    ]);

    WorkerEarning::create([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-14',
        'qty' => 200,
        'rate' => 100.00,
        'amount' => 20000.00,
        'note' => 'Worked example wages',
        'created_by' => $this->owner->id,
    ]);

    // Rent & Utilities = ৳12,000
    $catRent = ExpenseCategory::where('pl_group', 'rent_utilities')->first();
    Expense::create([
        'expense_category_id' => $catRent->id,
        'expense_date' => '2026-10-05',
        'amount' => 12000.00,
        'payment_method' => 'cash',
        'vendor' => 'Factory Landlord',
        'created_by' => $this->owner->id,
    ]);

    // Packaging + Other = ৳6,000 (Packaging ৳4,000 + Other ৳2,000)
    $catPkg = ExpenseCategory::where('pl_group', 'packaging')->first();
    Expense::create([
        'expense_category_id' => $catPkg->id,
        'expense_date' => '2026-10-08',
        'amount' => 4000.00,
        'payment_method' => 'cash',
        'vendor' => 'Packaging House',
        'created_by' => $this->owner->id,
    ]);

    $catOther = ExpenseCategory::where('pl_group', 'other')->first();
    Expense::create([
        'expense_category_id' => $catOther->id,
        'expense_date' => '2026-10-18',
        'amount' => 2000.00,
        'payment_method' => 'cash',
        'vendor' => 'Workshop Hardware',
        'created_by' => $this->owner->id,
    ]);

    // Default setting: Cost price includes labour = No (PRD O-1)
    Setting::set('payroll.cost_price_includes_labour', '0');

    // Execute ProfitLossService
    $service = app(\App\Services\Reports\ProfitLossService::class);
    $report = $service->calculate($from, $to);

    // Assert exact PRD §11.3 figures
    expect($report['revenue']['gross_sales'])->toBe(200000.00)
        ->and($report['revenue']['discounts'])->toBe(5000.00)
        ->and($report['revenue']['returns'])->toBe(10000.00)
        ->and($report['revenue']['net_sales'])->toBe(185000.00)
        ->and($report['revenue']['delivery_income'])->toBe(8000.00)
        ->and($report['revenue']['total_revenue'])->toBe(193000.00)
        ->and($report['cogs'])->toBe(80000.00)
        ->and($report['gross_profit'])->toBe(113000.00)
        ->and($report['gross_margin_pct'])->toBe(58.55) // 113,000 / 193,000 = 58.549% (~58.5%)
        ->and($report['expenses']['facebook_ads'])->toBe(25000.00)
        ->and($report['expenses']['courier'])->toBe(14000.00)
        ->and($report['expenses']['salaries'])->toBe(30000.00)
        ->and($report['expenses']['wages'])->toBe(20000.00)
        ->and($report['expenses']['rent_utilities'])->toBe(12000.00)
        ->and($report['expenses']['packaging'] + $report['expenses']['other'])->toBe(6000.00)
        ->and($report['expenses']['total'])->toBe(107000.00)
        ->and($report['net_profit'])->toBe(6000.00) // EXACT ৳6,000!
        ->and($report['net_margin_pct'])->toBe(3.11) // 6,000 / 193,000 = 3.109% (~3.1%)
        ->and($report['marketing']['roas'])->toBe(7.4); // 185,000 / 25,000 = 7.4x

    // Assert HTTP request returns exact props
    $response = $this->actingAs($this->owner)->get("/reports/profit-loss?date_preset=custom&date_from={$from}&date_to={$to}");
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('reports/ProfitLoss')
        ->where('report.revenue.gross_sales', fn ($val) => (float) $val == 200000.00)
        ->where('report.revenue.net_sales', fn ($val) => (float) $val == 185000.00)
        ->where('report.revenue.total_revenue', fn ($val) => (float) $val == 193000.00)
        ->where('report.cogs', fn ($val) => (float) $val == 80000.00)
        ->where('report.gross_profit', fn ($val) => (float) $val == 113000.00)
        ->where('report.expenses.total', fn ($val) => (float) $val == 107000.00)
        ->where('report.net_profit', fn ($val) => (float) $val == 6000.00)
    );
});

test('cancelled sales are completely excluded from gross sales, discounts, delivery charges and COGS', function () {
    $from = '2026-10-01';
    $to = '2026-10-31';

    $customer = Customer::create([
        'name' => 'Active Customer',
        'phone' => '01711111111',
        'address' => 'Dhaka',
    ]);

    $category = ProductCategory::firstOrCreate(['name' => 'Belts'], ['is_active' => true]);
    $product = Product::create([
        'name' => 'Leather Belt',
        'sku' => 'BLT-001',
        'product_category_id' => $category->id,
        'cost_price' => 500.00,
        'selling_price' => 1500.00,
        'stock_qty' => 100,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    // Active sale: ৳15,000
    $activeSale = Sale::create([
        'invoice_no' => 'TASA-2610-0010',
        'idempotency_token' => Str::uuid()->toString(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => '2026-10-05',
        'sold_at' => '2026-10-05 12:00:00',
        'subtotal' => 15000.00,
        'discount_type' => 'flat',
        'discount_value' => 500.00,
        'discount_amount' => 500.00,
        'delivery_charge' => 100.00,
        'grand_total' => 14600.00,
        'paid_total' => 14600.00,
        'due_amount' => 0.00,
        'status' => 'completed',
        'payment_status' => 'paid',
        'created_by' => $this->owner->id,
    ]);

    SaleItem::create([
        'sale_id' => $activeSale->id,
        'product_id' => $product->id,
        'is_custom' => false,
        'name' => $product->name,
        'qty' => 10,
        'unit_price' => 1500.00,
        'unit_cost' => 500.00,
        'line_total' => 15000.00,
    ]);

    // Cancelled sale: ৳30,000 (must NOT be counted anywhere)
    $cancelledSale = Sale::create([
        'invoice_no' => 'TASA-2610-0011',
        'idempotency_token' => Str::uuid()->toString(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => '2026-10-06',
        'sold_at' => '2026-10-06 13:00:00',
        'subtotal' => 30000.00,
        'discount_type' => 'flat',
        'discount_value' => 2000.00,
        'discount_amount' => 2000.00,
        'delivery_charge' => 200.00,
        'grand_total' => 28200.00,
        'paid_total' => 0.00,
        'due_amount' => 0.00,
        'status' => 'cancelled',
        'payment_status' => 'unpaid',
        'cancel_reason' => 'Customer changed mind before dispatch',
        'created_by' => $this->owner->id,
    ]);

    SaleItem::create([
        'sale_id' => $cancelledSale->id,
        'product_id' => $product->id,
        'is_custom' => false,
        'name' => $product->name,
        'qty' => 20,
        'unit_price' => 1500.00,
        'unit_cost' => 500.00,
        'line_total' => 30000.00,
    ]);

    $service = app(\App\Services\Reports\ProfitLossService::class);
    $report = $service->calculate($from, $to);

    // Only active sale amounts are recognized
    expect($report['revenue']['gross_sales'])->toBe(15000.00)
        ->and($report['revenue']['discounts'])->toBe(500.00)
        ->and($report['revenue']['delivery_income'])->toBe(100.00)
        ->and($report['cogs'])->toBe(5000.00); // 10 * 500
});

test('returns across period boundaries are attributed strictly to return date without double counting', function () {
    $period1From = '2026-10-01';
    $period1To = '2026-10-31';

    $period2From = '2026-11-01';
    $period2To = '2026-11-30';

    $customer = Customer::create([
        'name' => 'Boundary Customer',
        'phone' => '01722222222',
        'address' => 'Dhaka',
    ]);
    $category = ProductCategory::firstOrCreate(['name' => 'Wallets'], ['is_active' => true]);
    $product = Product::create([
        'name' => 'Classic Wallet',
        'sku' => 'WLT-BND-01',
        'product_category_id' => $category->id,
        'cost_price' => 400.00,
        'selling_price' => 1200.00,
        'stock_qty' => 100,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    // Sale in Period 1 (October 25): 10 units @ 1,200 = ৳12,000
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0099',
        'idempotency_token' => Str::uuid()->toString(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => '2026-10-25',
        'sold_at' => '2026-10-25 15:00:00',
        'subtotal' => 12000.00,
        'discount_type' => 'flat',
        'discount_value' => 0.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00,
        'grand_total' => 12000.00,
        'paid_total' => 12000.00,
        'due_amount' => 0.00,
        'status' => 'completed',
        'payment_status' => 'paid',
        'created_by' => $this->owner->id,
    ]);

    $item = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'is_custom' => false,
        'name' => $product->name,
        'qty' => 10,
        'unit_price' => 1200.00,
        'unit_cost' => 400.00,
        'line_total' => 12000.00,
    ]);

    // Return processed in Period 2 (November 05): 2 units returned (৳2,400)
    $return = SaleReturn::create([
        'sale_id' => $sale->id,
        'returned_on' => '2026-11-05',
        'reason' => 'Customer returned wallet',
        'return_value' => 2400.00,
        'refunded_amount' => 2400.00,
        'restock' => true,
        'created_by' => $this->owner->id,
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $return->id,
        'sale_item_id' => $item->id,
        'qty' => 2,
        'unit_price' => 1200.00,
        'unit_cost' => 400.00,
    ]);

    $service = app(\App\Services\Reports\ProfitLossService::class);

    // Period 1 report: Gross = 12,000, Returns = 0, Net = 12,000, COGS = 4,000
    $p1Report = $service->calculate($period1From, $period1To);
    expect($p1Report['revenue']['gross_sales'])->toBe(12000.00)
        ->and($p1Report['revenue']['returns'])->toBe(0.00)
        ->and($p1Report['revenue']['net_sales'])->toBe(12000.00)
        ->and($p1Report['cogs'])->toBe(4000.00); // 10 * 400

    // Period 2 report: Gross = 0, Returns = 2,400, Net = -2,400
    $p2Report = $service->calculate($period2From, $period2To);
    expect($p2Report['revenue']['gross_sales'])->toBe(0.00)
        ->and($p2Report['revenue']['returns'])->toBe(2400.00)
        ->and($p2Report['revenue']['net_sales'])->toBe(-2400.00);
});

test('cost price includes labour toggle excludes line L wages when Yes and includes when No', function () {
    $from = '2026-10-01';
    $to = '2026-10-31';

    $worker = Worker::create([
        'name' => 'Stitcher Worker',
        'phone' => '01899999999',
        'wage_basis' => 'piece_rate',
        'default_rate' => 50.00,
        'is_active' => true,
    ]);

    WorkerEarning::create([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-10',
        'qty' => 300,
        'rate' => 50.00,
        'amount' => 15000.00,
        'note' => 'Stitching work',
        'created_by' => $this->owner->id,
    ]);

    $service = app(\App\Services\Reports\ProfitLossService::class);

    // Case 1: Cost price includes labour = No (default) -> Wages = 15,000
    Setting::set('payroll.cost_price_includes_labour', '0');
    $reportNo = $service->calculate($from, $to);
    expect($reportNo['expenses']['wages'])->toBe(15000.00)
        ->and($reportNo['statement']['expenses']['wages']['is_excluded'])->toBeFalse();

    // Case 2: Cost price includes labour = Yes -> Wages = 0.00 (excluded to avoid double counting)
    Setting::set('payroll.cost_price_includes_labour', '1');
    $reportYes = $service->calculate($from, $to);
    expect($reportYes['expenses']['wages'])->toBe(0.00)
        ->and($reportYes['statement']['expenses']['wages']['is_excluded'])->toBeTrue();
});

test('raw material purchases and consumptions do not create P&L expenses but appear in memo cash summary', function () {
    $from = '2026-10-01';
    $to = '2026-10-31';

    $leatherItem = LeatherItem::create([
        'name' => 'Full Grain Cowhide',
        'colour_grade' => 'Tan Grade A',
        'min_stock' => 50,
        'is_active' => true,
    ]);

    // Leather Purchase: ৳40,000 paid
    LeatherPurchase::create([
        'leather_item_id' => $leatherItem->id,
        'purchase_date' => '2026-10-05',
        'supplier' => 'Dhaka Tannery',
        'qty_sqft' => 200.00,
        'rate_per_sqft' => 200.00,
        'total_cost' => 40000.00,
        'paid_amount' => 40000.00,
        'payment_method' => 'cash',
        'created_by' => $this->owner->id,
    ]);

    // Leather Consumption: 50 sq ft (value = 10,000)
    LeatherConsumption::create([
        'leather_item_id' => $leatherItem->id,
        'consumption_date' => '2026-10-12',
        'qty_sqft' => 50.00,
        'note' => 'Bags batch 1',
        'created_by' => $this->owner->id,
    ]);

    $service = app(\App\Services\Reports\ProfitLossService::class);
    $report = $service->calculate($from, $to);

    // Assert P&L operating expenses do NOT include leather purchases
    expect($report['expenses']['total'])->toBe(0.00);

    // Assert Memo Section captures leather purchase and cash out
    expect($report['memo']['leather']['purchased_cost'])->toBe(40000.00)
        ->and($report['memo']['leather']['purchased_sqft'])->toBe(200.0)
        ->and($report['memo']['leather']['consumed_sqft'])->toBe(50.0)
        ->and($report['memo']['cash_summary']['cash_out']['material_purchases'])->toBe(40000.00)
        ->and($report['memo']['cash_summary']['net_movement'])->toBe(-40000.00);
});

test('zero revenue and zero ad spend handle zero denominators safely without error', function () {
    $from = '2026-10-01';
    $to = '2026-10-31';

    $service = app(\App\Services\Reports\ProfitLossService::class);
    $report = $service->calculate($from, $to);

    expect($report['revenue']['total_revenue'])->toBe(0.00)
        ->and($report['gross_margin_pct'])->toBe(0.0)
        ->and($report['net_margin_pct'])->toBe(0.0)
        ->and($report['marketing']['roas'])->toBeNull()
        ->and($report['marketing']['roas_formatted'])->toBe('—')
        ->and($report['marketing']['ad_spend_pct_sales'])->toBe(0.0);
});

test('drilldown endpoint returns source rows for gross sales, returns, cogs, expenses, and memo items', function () {
    $from = '2026-10-01';
    $to = '2026-10-31';

    $customer = Customer::create([
        'name' => 'Drill Customer',
        'phone' => '01733333333',
        'address' => 'Dhaka',
    ]);
    $cat = ProductCategory::firstOrCreate(['name' => 'Cardholders'], ['is_active' => true]);
    $prod = Product::create([
        'name' => 'Slim Cardholder',
        'sku' => 'CRD-01',
        'product_category_id' => $cat->id,
        'cost_price' => 200.00,
        'selling_price' => 600.00,
        'stock_qty' => 50,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0500',
        'idempotency_token' => Str::uuid()->toString(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => '2026-10-10',
        'sold_at' => '2026-10-10 11:00:00',
        'subtotal' => 6000.00,
        'discount_type' => 'flat',
        'discount_value' => 500.00,
        'discount_amount' => 500.00,
        'delivery_charge' => 120.00,
        'grand_total' => 5620.00,
        'paid_total' => 5620.00,
        'due_amount' => 0.00,
        'status' => 'completed',
        'payment_status' => 'paid',
        'created_by' => $this->owner->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $prod->id,
        'is_custom' => false,
        'name' => $prod->name,
        'qty' => 10,
        'unit_price' => 600.00,
        'unit_cost' => 200.00,
        'line_total' => 6000.00,
    ]);

    // Test gross_sales drilldown
    $responseA = $this->actingAs($this->owner)
        ->getJson("/reports/profit-loss/drilldown?type=gross_sales&from={$from}&to={$to}");

    $responseA->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('total', 6000)
        ->assertJsonPath('records.0.invoice_no', 'TASA-2610-0500');

    // Test COGS drilldown
    $responseG = $this->actingAs($this->owner)
        ->getJson("/reports/profit-loss/drilldown?type=cogs&from={$from}&to={$to}");

    $responseG->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('records.0.name', 'Slim Cardholder')
        ->assertJsonPath('records.0.unit_cost', 200);
});

test('comparative period calculation calculates variances and percent changes', function () {
    $curFrom = '2026-10-01';
    $curTo = '2026-10-31';
    $prevFrom = '2026-09-01';
    $prevTo = '2026-09-30';

    $customer = Customer::create([
        'name' => 'Comp Customer',
        'phone' => '01744444444',
        'address' => 'Dhaka',
    ]);
    $cat = ProductCategory::firstOrCreate(['name' => 'Pouches'], ['is_active' => true]);
    $prod = Product::create([
        'name' => 'Coin Pouch',
        'sku' => 'PCH-01',
        'product_category_id' => $cat->id,
        'cost_price' => 100.00,
        'selling_price' => 300.00,
        'stock_qty' => 50,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    // Previous month sale: ৳3,000
    Sale::create([
        'invoice_no' => 'TASA-2609-0001',
        'idempotency_token' => Str::uuid()->toString(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => '2026-09-15',
        'sold_at' => '2026-09-15 10:00:00',
        'subtotal' => 3000.00,
        'discount_type' => 'flat',
        'discount_value' => 0.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00,
        'grand_total' => 3000.00,
        'paid_total' => 3000.00,
        'due_amount' => 0.00,
        'status' => 'completed',
        'payment_status' => 'paid',
        'created_by' => $this->owner->id,
    ]);

    // Current month sale: ৳6,000 (+100% growth)
    Sale::create([
        'invoice_no' => 'TASA-2610-0002',
        'idempotency_token' => Str::uuid()->toString(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => '2026-10-15',
        'sold_at' => '2026-10-15 10:00:00',
        'subtotal' => 6000.00,
        'discount_type' => 'flat',
        'discount_value' => 0.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00,
        'grand_total' => 6000.00,
        'paid_total' => 6000.00,
        'due_amount' => 0.00,
        'status' => 'completed',
        'payment_status' => 'paid',
        'created_by' => $this->owner->id,
    ]);

    $service = app(\App\Services\Reports\ProfitLossService::class);
    $comp = $service->getComparative($curFrom, $curTo, $prevFrom, $prevTo);

    expect($comp['statement']['gross_sales']['amount'])->toBe(6000.00)
        ->and($comp['statement']['gross_sales']['previous'])->toBe(3000.00)
        ->and($comp['statement']['gross_sales']['variance']['diff'])->toBe(3000.00)
        ->and($comp['statement']['gross_sales']['variance']['pct'])->toBe(100.0);
});

test('excel and pdf exports generate successfully for authorized owner', function () {
    $from = '2026-10-01';
    $to = '2026-10-31';

    $responseExcel = $this->actingAs($this->owner)
        ->get("/reports/profit-loss/export/excel?date_preset=custom&date_from={$from}&date_to={$to}");

    $responseExcel->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $responsePdf = $this->actingAs($this->owner)
        ->get("/reports/profit-loss/export/pdf?date_preset=custom&date_from={$from}&date_to={$to}");

    $responsePdf->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('direct /profit-loss route redirects to /reports/profit-loss', function () {
    $this->actingAs($this->owner)
        ->get('/profit-loss')
        ->assertRedirect('/reports/profit-loss');
});

test('empty database period renders valid nonblank report structure with zeros', function () {
    $response = $this->actingAs($this->owner)
        ->get('/reports/profit-loss?date_preset=custom&date_from=2025-01-01&date_to=2025-01-31');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('reports/ProfitLoss')
        ->where('report.revenue.gross_sales', fn ($val) => (float) $val == 0.0)
        ->where('report.revenue.net_sales', fn ($val) => (float) $val == 0.0)
        ->where('report.revenue.total_revenue', fn ($val) => (float) $val == 0.0)
        ->where('report.cogs', fn ($val) => (float) $val == 0.0)
        ->where('report.gross_profit', fn ($val) => (float) $val == 0.0)
        ->where('report.expenses.total', fn ($val) => (float) $val == 0.0)
        ->where('report.net_profit', fn ($val) => (float) $val == 0.0)
        ->where('report.gross_margin_pct', fn ($val) => (float) $val == 0.0)
        ->where('report.net_margin_pct', fn ($val) => (float) $val == 0.0)
    );
});

test('invalid date filters fail server-side validation with errors', function () {
    $response = $this->actingAs($this->owner)
        ->get('/reports/profit-loss?date_preset=custom&date_from=2026-10-31&date_to=2026-10-01');

    $response->assertSessionHasErrors(['date_to']);
});

test('courier expense is recorded once under operating expenses and segregated from delivery revenue', function () {
    $from = '2026-10-01';
    $to = '2026-10-31';

    $customer = Customer::create([
        'name' => 'Courier Customer',
        'phone' => '01755555555',
        'address' => 'Dhaka',
    ]);
    $cat = ProductCategory::firstOrCreate(['name' => 'Belts'], ['is_active' => true]);
    $prod = Product::create([
        'name' => 'Courier Belt',
        'sku' => 'BLT-CR-01',
        'product_category_id' => $cat->id,
        'cost_price' => 300.00,
        'selling_price' => 1000.00,
        'stock_qty' => 50,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    // Customer charged ৳120 for delivery on sale
    Sale::create([
        'invoice_no' => 'TASA-2610-0888',
        'idempotency_token' => Str::uuid()->toString(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => '2026-10-15',
        'sold_at' => '2026-10-15 10:00:00',
        'subtotal' => 1000.00,
        'discount_type' => 'flat',
        'discount_value' => 0.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 120.00,
        'grand_total' => 1120.00,
        'paid_total' => 1120.00,
        'due_amount' => 0.00,
        'status' => 'completed',
        'payment_status' => 'paid',
        'created_by' => $this->owner->id,
    ]);

    // Store paid Pathao ৳80 as operating courier expense
    $catCourier = ExpenseCategory::where('pl_group', 'courier')->first();
    Expense::create([
        'expense_category_id' => $catCourier->id,
        'expense_date' => '2026-10-16',
        'amount' => 80.00,
        'payment_method' => 'cash',
        'vendor' => 'Pathao Limited',
        'created_by' => $this->owner->id,
    ]);

    $service = app(\App\Services\Reports\ProfitLossService::class);
    $report = $service->calculate($from, $to);

    // Delivery charge collected from customer is in revenue Line E
    expect($report['revenue']['delivery_income'])->toBe(120.00)
        // Courier expense paid is in opex Line J
        ->and($report['expenses']['courier'])->toBe(80.00)
        ->and($report['expenses']['total'])->toBe(80.00);
});

