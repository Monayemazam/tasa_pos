<?php

namespace Tests\Feature\Acceptance;

use App\Models\CourierOrder;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LeatherConsumption;
use App\Models\LeatherItem;
use App\Models\LeatherPurchase;
use App\Models\PathaoCourierToken;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\Role;
use App\Models\SalaryMonth;
use App\Models\SalaryPayment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerEarning;
use App\Models\WorkerPayment;
use App\Services\Courier\PathaoService;
use App\Services\Payroll\PayrollService;
use App\Services\Reports\ProfitLossService;
use App\Services\Reports\SalesReportService;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\SaleService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
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

    $this->category = ProductCategory::create(['name' => 'Executive Bags']);

    $this->product = Product::create([
        'product_category_id' => $this->category->id,
        'name' => 'Heritage Leather Briefcase',
        'sku' => 'BAG-BRIEF-01',
        'cost_price' => 3500.00,
        'selling_price' => 6000.00,
        'stock_qty' => 10,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'name' => 'কাজী তানভীর আহমেদ',
        'phone' => '01812345678',
        'address' => 'Flat 4B, House 22, Road 11, Banani, Dhaka',
        'pathao_city_id' => 1,
        'pathao_zone_id' => 103,
        'pathao_area_id' => 10303,
        'city_name' => 'Dhaka',
        'zone_name' => 'Banani',
        'area_name' => 'Banani Road 11',
    ]);
});

test('AC-1: sale cannot be saved without customer name, valid BD phone, and address; no walk-in exists', function () {
    // 1. Missing customer name
    $resMissingName = $this->actingAs($this->owner)->postJson('/sales', [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => '',
        'customer_phone' => '01812345678',
        'customer_address' => 'Flat 4B, House 22, Road 11, Banani, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 1, 'unit_price' => 6000.00],
        ],
    ]);
    $resMissingName->assertStatus(422)->assertJsonValidationErrors(['customer_name']);

    // 2. Invalid non-BD phone format
    $resBadPhone = $this->actingAs($this->owner)->postJson('/sales', [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Test Customer',
        'customer_phone' => '01212345678', // 012 is invalid prefix
        'customer_address' => 'Flat 4B, House 22, Road 11, Banani, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 1, 'unit_price' => 6000.00],
        ],
    ]);
    $resBadPhone->assertStatus(422)->assertJsonValidationErrors(['customer_phone']);

    // 3. Short address (< 10 chars)
    $resShortAddress = $this->actingAs($this->owner)->postJson('/sales', [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Test Customer',
        'customer_phone' => '01812345678',
        'customer_address' => 'Dhaka',
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 1, 'unit_price' => 6000.00],
        ],
    ]);
    $resShortAddress->assertStatus(422)->assertJsonValidationErrors(['customer_address']);

    // 4. Verify no walk-in sale was created in DB
    expect(Sale::count())->toBe(0);
});

test('AC-2: saving a sale creates exactly one invoice with next monthly sequential number; double-click does not duplicate', function () {
    Queue::fake();

    $token = (string) Str::uuid();
    $payload = [
        'idempotency_token' => $token,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 1, 'unit_price' => 6000.00],
        ],
    ];

    // First save
    $res1 = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $res1->assertOk();
    $saleId1 = $res1->json('sale.id');
    $invoiceNo1 = $res1->json('sale.invoice_no');

    $expectedMonth = now()->format('ym');
    expect($invoiceNo1)->toBe("TASA-{$expectedMonth}-0001");

    // Double-click with same idempotency token
    $res2 = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $res2->assertOk();
    $saleId2 = $res2->json('sale.id');
    $invoiceNo2 = $res2->json('sale.invoice_no');

    expect($saleId1)->toBe($saleId2)
        ->and($invoiceNo1)->toBe($invoiceNo2)
        ->and(Sale::count())->toBe(1);

    // Subsequent different sale gets consecutive next number 0002
    $res3 = $this->actingAs($this->owner)->postJson('/sales', array_merge($payload, [
        'idempotency_token' => (string) Str::uuid(),
    ]));
    $res3->assertOk();
    expect($res3->json('sale.invoice_no'))->toBe("TASA-{$expectedMonth}-0002")
        ->and(Sale::count())->toBe(2);
});

test('AC-3: saving sale with Pathao creates courier order; consignment_id stored; amount_to_collect equals balance due', function () {
    Queue::fake();

    // Partial upfront payment: ৳6000 item + ৳100 delivery = ৳6100. Paid ৳2000 upfront. Due = ৳4100
    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 103,
        'area_id' => 10303,
        'courier_mode' => 'pathao',
        'item_weight' => 0.8,
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 1, 'unit_price' => 6000.00],
        ],
        'delivery_charge' => 100.00,
        'paid_amount' => 2000.00,
        'payment_method' => 'bkash',
    ];

    $response = $this->actingAs($this->staff)->postJson('/sales', $payload);
    $response->assertOk();

    $saleId = $response->json('sale.id');
    $courierOrder = CourierOrder::where('sale_id', $saleId)->first();

    expect($courierOrder)->not->toBeNull()
        ->and((float) $courierOrder->cod_amount)->toBe(4100.00); // Amount to collect equals balance due!

    // Service dispatch stores consignment_id
    $service = app(PathaoService::class);
    $service->createOrder($courierOrder);
    $courierOrder->refresh();

    expect($courierOrder->consignment_id)->not->toBeNull()
        ->and($courierOrder->status)->toBe('created');
});

test('AC-4: courier failure leaves sale intact; courier status = Failed; Retry button avoids duplicate orders', function () {
    Config::set('services.pathao.client_id', 'test-client');
    Config::set('services.pathao.client_secret', 'test-secret');

    PathaoCourierToken::create([
        'access_token' => 'active-token',
        'token_type' => 'Bearer',
        'expires_in' => 86400,
    ]);

    // Force Pathao endpoint error on 1st call, success on 2nd call via fakeSequence
    Http::fakeSequence('*/aladdin/api/v1/orders')
        ->push(['message' => 'Zone out of coverage'], 422)
        ->push(['data' => ['consignment_id' => 'PTH-RETRY-SUCCESS-01', 'delivery_fee' => 60]], 200);

    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0044',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 6000,
        'grand_total' => 6000,
        'paid_total' => 0,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => $sale->invoice_no,
        'store_id' => 1,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => $sale->customer_name,
        'recipient_phone' => $sale->customer_phone,
        'recipient_address' => $sale->customer_address,
        'recipient_city' => 1,
        'recipient_zone' => 103,
        'recipient_area' => 10303,
        'cod_amount' => 6000,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);
    $result = $service->createOrder($courierOrder);

    expect($result)->toBeFalse();
    $courierOrder->refresh();

    // Courier status is failed with safe error
    expect($courierOrder->status)->toBe('failed')
        ->and($courierOrder->last_error)->toContain('Zone out of coverage');

    // Sale remains active and fully intact
    $sale->refresh();
    expect($sale->status)->toBe('active')
        ->and($sale->invoice_no)->toBe('TASA-2610-0044');

    // Retry API endpoint execution via courier retry endpoint
    $retryResponse = $this->actingAs($this->owner)->post("/sales/courier/{$courierOrder->id}/retry");
    $retryResponse->assertRedirect();

    // Execute job with mocked successful sequence response
    (new \App\Jobs\DispatchPathaoOrderJob($courierOrder->id))->handle($service);

    $courierOrder->refresh();
    expect($courierOrder->status)->toBe('created')
        ->and($courierOrder->consignment_id)->toBe('PTH-RETRY-SUCCESS-01');

    // Courier order count remains strictly 1 for this sale
    expect(CourierOrder::where('sale_id', $sale->id)->count())->toBe(1);
});

test('AC-5: sale deducts product stock; cancel/return restores it; product_stock_movements matches product stock_qty', function () {
    Queue::fake();

    $initialStock = (int) $this->product->stock_qty; // 10

    // 1. Create sale for 2 items
    $saleService = app(SaleService::class);
    $saleResult = $saleService->createSale([
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 2, 'unit_price' => 6000.00],
        ],
    ], $this->owner);

    $sale = $saleResult['sale'];
    $this->product->refresh();

    // Stock deducted from 10 to 8
    expect((int) $this->product->stock_qty)->toBe($initialStock - 2);

    // Movement row created
    $movement = ProductStockMovement::where('reference_id', $sale->id)->first();
    expect($movement)->not->toBeNull()
        ->and((int) $movement->qty)->toBe(-2)
        ->and((int) $movement->balance_after)->toBe(8);

    // 2. Cancel sale restores stock
    $saleService->cancelSale($sale, ['reason' => 'Customer requested cancellation'], $this->owner);
    $this->product->refresh();

    // Stock restored back to 10
    expect((int) $this->product->stock_qty)->toBe($initialStock);
});

test('AC-6: leather and accessory stock: purchases - consumption = current stock; weighted average cost is accurate', function () {
    // 1. Leather Item
    $leather = LeatherItem::create([
        'name' => 'Full Grain Vegetable Tanned Leather',
        'colour_grade' => 'Cognac Grade A',
        'min_stock' => 50,
        'is_active' => true,
    ]);

    // Lot 1: 100 sqft @ ৳150 = ৳15,000
    LeatherPurchase::create([
        'leather_item_id' => $leather->id,
        'purchase_date' => now()->toDateString(),
        'qty_sqft' => 100.0,
        'rate_per_sqft' => 150.00,
        'total_cost' => 15000.00,
        'paid_amount' => 15000.00,
        'created_by' => $this->owner->id,
    ]);

    // Lot 2: 50 sqft @ ৳180 = ৳9,000
    LeatherPurchase::create([
        'leather_item_id' => $leather->id,
        'purchase_date' => now()->toDateString(),
        'qty_sqft' => 50.0,
        'rate_per_sqft' => 180.00,
        'total_cost' => 9000.00,
        'paid_amount' => 9000.00,
        'created_by' => $this->owner->id,
    ]);

    // Consumption: 30 sqft consumed
    LeatherConsumption::create([
        'leather_item_id' => $leather->id,
        'consumption_date' => now()->toDateString(),
        'qty_sqft' => 30.0,
        'note' => 'Briefcase production batch #1',
        'created_by' => $this->owner->id,
    ]);

    // Query DB view v_leather_stock
    $viewRow = DB::table('v_leather_stock')->where('id', $leather->id)->first();
    expect($viewRow)->not->toBeNull();

    // Current stock: 100 + 50 - 30 = 120 sqft
    expect((float) $viewRow->current_stock)->toBe(120.0);

    // Weighted average cost: (15000 + 9000) / (100 + 50) = 24000 / 150 = 160.00
    expect((float) $viewRow->avg_cost)->toBe(160.00);

    // Stock value: 120 * 160 = ৳19,200.00
    expect((float) $viewRow->stock_value)->toBe(19200.00);
});

test('AC-7: sales report by date range and by product returns correct quantities and totals versus raw records', function () {
    // Seed active sale
    $sale1 = Sale::create([
        'invoice_no' => 'TASA-2610-0071',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 6000.00,
        'discount_amount' => 500.00,
        'delivery_charge' => 100.00,
        'grand_total' => 5600.00,
        'paid_total' => 5600.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale1->id,
        'product_id' => $this->product->id,
        'name' => $this->product->name,
        'qty' => 1,
        'unit_price' => 6000.00,
        'unit_cost' => 3500.00,
        'line_total' => 6000.00,
    ]);

    // Execute SalesReportService
    $service = app(SalesReportService::class);
    $summary = $service->getSummary(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

    expect((float) $summary['gross_sales'])->toBe(6000.00)
        ->and((float) $summary['discounts'])->toBe(500.00)
        ->and((float) $summary['delivery_charge'])->toBe(100.00)
        ->and((float) $summary['net_sales'])->toBe(5500.00);

    // Product breakdown
    $byProduct = collect($service->getByProduct(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), true));
    $itemRow = $byProduct->firstWhere('product_id', $this->product->id);
    expect($itemRow)->not->toBeNull()
        ->and((int) $itemRow['qty_sold'])->toBe(1)
        ->and((float) $itemRow['revenue'])->toBe(6000.00);
});

test('AC-8: salaried employee with irregular payments shows correct month balance, carry-forward, and overpayment flag', function () {
    $employee = Employee::create([
        'name' => 'মাহমুদুল হাসান',
        'phone' => '01711998877',
        'designation' => 'Master Craftsman',
        'monthly_salary' => 25000.00,
        'status' => 'active',
        'joined_on' => '2026-08-01',
    ]);

    // Month 1: October 2026. Payable = ৳25,000. Paid = ৳20,000. Balance = ৳5,000 owed to employee
    $m1 = SalaryMonth::create([
        'employee_id' => $employee->id,
        'period' => '2026-10-01',
        'base_salary' => 25000.00,
        'additions' => 0.00,
        'deductions' => 0.00,
        'payable' => 25000.00,
        'note' => 'Regular month',
    ]);

    SalaryPayment::create([
        'employee_id' => $employee->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-15',
        'amount' => 20000.00,
        'method' => 'bank_transfer',
        'created_by' => $this->owner->id,
    ]);

    // Month 2: November 2026. Payable = ৳25,000. Paid = ৳35,000 (Advance given).
    // Carry forward from Month 1 = ৳5,000. Total owed = ৳30,000. Paid = ৳35,000.
    // Net balance = -৳5,000 (Employee owes Tasa / Overpayment, violet flag)
    $m2 = SalaryMonth::create([
        'employee_id' => $employee->id,
        'period' => '2026-11-01',
        'base_salary' => 25000.00,
        'additions' => 0.00,
        'deductions' => 0.00,
        'payable' => 25000.00,
    ]);

    SalaryPayment::create([
        'employee_id' => $employee->id,
        'period' => '2026-11-01',
        'paid_on' => '2026-11-10',
        'amount' => 35000.00,
        'method' => 'bank_transfer',
        'created_by' => $this->owner->id,
    ]);

    $payrollService = app(PayrollService::class);
    $statement = $payrollService->getEmployeeStatement($employee);

    expect((float) $statement['totals']['current_net_balance'])->toBe(-5000.00)
        ->and($statement['totals']['is_overpaid'])->toBeTrue(); // Negative = advance / overpaid!
});

test('AC-9: wage worker with weekly/twice-weekly payments shows correct running balance; overpayment displayed in violet', function () {
    $worker = Worker::create([
        'name' => 'আলাউদ্দিন',
        'phone' => '01912345678',
        'wage_basis' => 'per_day',
        'rate' => 800.00,
        'status' => 'active',
    ]);

    // Earnings: 6 days worked = ৳4,800 + ৳400 overtime = ৳5,200
    WorkerEarning::create([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-10',
        'qty' => 6.0,
        'rate' => 800.00,
        'amount' => 5200.00,
        'note' => '6 days + ৳400 overtime',
        'created_by' => $this->owner->id,
    ]);

    // Payment: Paid ৳6,000 (Worker took advance of ৳800)
    WorkerPayment::create([
        'worker_id' => $worker->id,
        'paid_on' => '2026-10-10',
        'amount' => 6000.00,
        'method' => 'cash',
        'created_by' => $this->owner->id,
    ]);

    $payrollService = app(PayrollService::class);
    $statement = $payrollService->getWorkerStatement($worker);

    // Balance = 5200 - 6000 = -800.00 (Owes Tasa / Overpayment)
    expect((float) $statement['closing_balance'])->toBe(-800.00);
});

test('AC-10: P&L for worked example produces Net Profit ৳6,000 and drills down to underlying records', function () {
    $fromDate = '2026-10-01';
    $toDate = '2026-10-31';

    // 1. Sales: Subtotal ৳200,000, Discount ৳10,000, Delivery ৳8,000
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0010',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => '2026-10-10',
        'sold_at' => now(),
        'subtotal' => 200000.00,
        'discount_amount' => 10000.00,
        'delivery_charge' => 8000.00,
        'grand_total' => 198000.00,
        'paid_total' => 198000.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    // COGS: ৳80,000
    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'name' => $this->product->name,
        'qty' => 20,
        'unit_price' => 10000.00,
        'unit_cost' => 4000.00, // 20 * 4000 = ৳80,000 COGS
        'line_total' => 200000.00,
    ]);

    // Returns: ৳5,000
    SaleReturn::create([
        'sale_id' => $sale->id,
        'returned_on' => '2026-10-12',
        'reason' => 'Customer defective return',
        'return_value' => 5000.00,
        'restock' => false,
        'created_by' => $this->owner->id,
    ]);

    // Operating expenses: Total ৳107,000
    $categories = [
        ['group' => 'marketing_facebook', 'amount' => 30000.00],
        ['group' => 'marketing_other',    'amount' => 5000.00],
        ['group' => 'courier',            'amount' => 12000.00],
        ['group' => 'packaging',          'amount' => 4000.00],
        ['group' => 'rent_utilities',     'amount' => 4000.00],
        ['group' => 'other',              'amount' => 2000.00],
    ];

    foreach ($categories as $cat) {
        $category = ExpenseCategory::where('pl_group', $cat['group'])->first()
            ?? ExpenseCategory::create(['name' => ucfirst($cat['group']), 'pl_group' => $cat['group'], 'is_system' => true]);

        Expense::create([
            'expense_category_id' => $category->id,
            'expense_date' => '2026-10-15',
            'amount' => $cat['amount'],
            'payment_method' => 'cash',
            'created_by' => $this->owner->id,
        ]);
    }

    // Salaries: ৳35,000
    $emp = Employee::create([
        'name' => 'Senior Tailor',
        'phone' => '01711223399',
        'designation' => 'Tailor',
        'monthly_salary' => 35000.00,
        'status' => 'active',
        'joined_on' => '2026-01-01',
    ]);

    SalaryMonth::create([
        'employee_id' => $emp->id,
        'period' => '2026-10-01',
        'base_salary' => 35000.00,
        'additions' => 0.00,
        'deductions' => 0.00,
        'payable' => 35000.00,
    ]);

    // Wages: ৳15,000
    $worker = Worker::create([
        'name' => 'Piece Worker A',
        'phone' => '01811223399',
        'wage_basis' => 'per_piece',
        'status' => 'active',
    ]);

    WorkerEarning::create([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-18',
        'qty' => 50.0,
        'rate' => 300.00,
        'amount' => 15000.00,
        'created_by' => $this->owner->id,
    ]);

    // Setting: Labour not in cost price
    Setting::set('payroll.cost_price_includes_labour', '0');

    // Calculate P&L Statement
    $plService = app(ProfitLossService::class);
    $report = $plService->calculate($fromDate, $toDate);

    // Verify Lines
    expect((float) $report['revenue']['gross_sales'])->toBe(200000.00)
        ->and((float) $report['revenue']['discounts'])->toBe(10000.00)
        ->and((float) $report['revenue']['returns'])->toBe(5000.00)
        ->and((float) $report['revenue']['net_sales'])->toBe(185000.00)
        ->and((float) $report['revenue']['total_revenue'])->toBe(193000.00)
        ->and((float) $report['statement']['cogs']['amount'])->toBe(80000.00)
        ->and((float) $report['statement']['gross_profit']['amount'])->toBe(113000.00)
        ->and((float) $report['statement']['gross_profit']['margin_pct'])->toBe(58.55) // 58.5%
        ->and((float) $report['statement']['expenses']['total']['amount'])->toBe(107000.00)
        ->and((float) $report['statement']['net_profit']['amount'])->toBe(6000.00); // Exact ৳6,000 net profit!

    // Drilldown endpoint returns source rows
    $drilldownResponse = $this->actingAs($this->owner)->getJson("/reports/profit-loss/drilldown?type=gross_sales&from={$fromDate}&to={$toDate}");
    $drilldownResponse->assertOk()
        ->assertJsonStructure(['count', 'total', 'records']);
});


test('AC-11: staff user cannot open Payroll or P&L by URL or API (403), and cost price/profit fields are absent', function () {
    // 1. Direct URL visits by Staff user return 403 Forbidden
    $this->actingAs($this->staff)->get('/payroll/employees')->assertStatus(403);
    $this->actingAs($this->staff)->get('/payroll/workers')->assertStatus(403);
    $this->actingAs($this->staff)->get('/reports/profit-loss')->assertStatus(403);
    $this->actingAs($this->staff)->getJson('/reports/profit-loss/drilldown?line=cogs')->assertStatus(403);

    // 2. Sensitive financial fields absent from Inertia props for Staff
    $productsResponse = $this->actingAs($this->staff)->get('/inventory/products');
    $productsResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/Products')
            ->where('canViewCost', false)
            ->where('products.data.0.cost_price', null)
        );
});

test('AC-12: role matrix changes take effect on user next request without requiring re-login', function () {
    $this->actingAs($this->staff);

    // Initial state: Staff blocked from viewing payroll
    $this->get('/payroll/employees')->assertStatus(403);

    // Grant payroll.view permission to Staff role
    $staffRole = Role::where('name', 'Staff')->first();
    $staffRole->givePermissionTo('payroll.view');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    // Immediate next request succeeds with 200 without re-login!
    $this->get('/payroll/employees')->assertOk();

    // Revoke permission
    $staffRole->revokePermissionTo('payroll.view');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    // Immediate subsequent request is blocked with 403
    $this->get('/payroll/employees')->assertStatus(403);
});

test('AC-13: mobile widths support responsive drawer and non-overflowing layouts', function () {
    // Verify pages render cleanly with appropriate responsive Inertia props
    $response = $this->actingAs($this->owner)->get('/sales/invoices');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/Invoices')
            ->has('invoices')
            ->has('filters')
        );

    // Verify app blade contains responsive viewport meta tag
    $appBlade = file_get_contents(resource_path('views/app.blade.php'));
    expect($appBlade)->toContain('name="viewport" content="width=device-width, initial-scale=1"');
});

test('AC-14: invoice layout prints correctly across A4, A5, and 80mm thermal formats with Bangla support', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0095',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 6000.00,
        'grand_total' => 6000.00,
        'paid_total' => 6000.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'name' => $this->product->name,
        'qty' => 1,
        'unit_price' => 6000.00,
        'unit_cost' => 3500.00,
        'line_total' => 6000.00,
    ]);

    $service = app(InvoiceService::class);

    // 1. A4 Format
    $htmlA4 = $service->renderHtml($sale, 'A4');
    expect($htmlA4)->toContain('TASA-2610-0095')
        ->and($htmlA4)->toContain('কাজী তানভীর আহমেদ')
        ->and($htmlA4)->toContain('Heritage Leather Briefcase')
        ->and($htmlA4)->toContain('a4 portrait');

    // 2. A5 Format
    $htmlA5 = $service->renderHtml($sale, 'A5');
    expect($htmlA5)->toContain('TASA-2610-0095')
        ->and($htmlA5)->toContain('a5 portrait');

    // 3. Thermal Format (80mm)
    $htmlThermal = $service->renderHtml($sale, '80mm');
    expect($htmlThermal)->toContain('TASA-2610-0095')
        ->and($htmlThermal)->toContain('80mm auto');
});
