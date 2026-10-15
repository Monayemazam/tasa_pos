<?php

namespace Tests\Feature\Courier;

use App\Models\CourierOrder;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\User;
use App\Services\Courier\PathaoService;
use App\Services\Reports\ProfitLossService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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

    $this->category = ProductCategory::create(['name' => 'Leather Bags']);

    $this->product = Product::create([
        'product_category_id' => $this->category->id,
        'name' => 'Classic Leather Messenger',
        'sku' => 'BAG-MSG-001',
        'cost_price' => 1500.00,
        'selling_price' => 2500.00,
        'stock_qty' => 30,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'name' => 'মীর জাফরুল (Mir Jafrul)',
        'phone' => '01799887766',
        'address' => 'House 10, Road 4, Banani, Dhaka',
        'pathao_city_id' => 1,
        'pathao_zone_id' => 101,
        'pathao_area_id' => 10101,
        'city_name' => 'Dhaka',
        'zone_name' => 'Banani',
        'area_name' => 'Banani Block B',
    ]);

    Cache::flush();
});

test('Scenario A: customer-funded delivery charge is included in invoice total and creates 0 courier expenses', function () {
    // 1. Customer pays ৳100 delivery charge for a ৳2500 bag. Subtotal 2500, delivery charge 100, grand total 2600.
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-A001',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2500.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 100.00,
        'grand_total' => 2600.00,
        'paid_total' => 0.00,
        'due_amount' => 2600.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    expect((float) $sale->delivery_charge)->toBe(100.0)
        ->and((float) $sale->grand_total)->toBe(2600.0);

    // 2. Courier order created and dispatched to Pathao (mocked fee = ৳60)
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
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2600.00,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);
    $success = $service->createOrder($courierOrder);

    expect($success)->toBeTrue();
    $courierOrder->refresh();
    expect($courierOrder->status)->toBe('created')
        ->and((float) $courierOrder->courier_charge)->toBe(60.0);

    // 3. Since customer paid ৳100 (which covers the ৳60 fee), business-borne cost is 0.
    // Assert NO courier expense is created
    $expenses = Expense::where('courier_order_id', $courierOrder->id)
        ->where('source', 'pathao_charge')
        ->get();

    expect($expenses)->toHaveCount(0);

    // 4. Verify Business Expenses page has 0 courier expenses for this order
    $response = $this->actingAs($this->owner)->get('/expenses?source=pathao_charge');
    $response->assertOk();
    $pageProps = $response->original->getData()['page']['props'];
    expect($pageProps['expenses']['total'])->toBe(0);

    // 5. Verify Profit & Loss: Delivery Income = ৳100 (Line E), Courier OPEX = ৳0 (Line J)
    $plService = app(ProfitLossService::class);
    $report = $plService->calculate(now()->startOfMonth(), now()->endOfMonth());

    expect((float) $report['revenue']['delivery_income'])->toBe(100.0)
        ->and((float) $report['expenses']['courier'])->toBe(0.0);
});

test('Scenario B: business-borne courier fee (free shipping) creates exactly one courier expense', function () {
    // 1. Business offers free delivery (delivery_charge = 0). Grand total = 2500.
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-B001',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2500.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00,
        'grand_total' => 2500.00,
        'paid_total' => 0.00,
        'due_amount' => 2500.00,
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
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2500.00,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);
    $success = $service->createOrder($courierOrder);

    expect($success)->toBeTrue();
    $courierOrder->refresh();

    // 2. Business bears the full ৳60 fee. Exactly ONE expense is created.
    $expenses = Expense::where('courier_order_id', $courierOrder->id)
        ->where('source', 'pathao_charge')
        ->get();

    expect($expenses)->toHaveCount(1)
        ->and((float) $expenses->first()->amount)->toBe(60.0)
        ->and($expenses->first()->vendor)->toBe('Pathao');

    // 3. Profit & Loss reflects ৳0 Delivery Income and ৳60 Courier Expense
    $plService = app(ProfitLossService::class);
    $report = $plService->calculate(now()->startOfMonth(), now()->endOfMonth());

    expect((float) $report['revenue']['delivery_income'])->toBe(0.0)
        ->and((float) $report['expenses']['courier'])->toBe(60.0);
});

test('Scenario C: partial customer delivery charge records only the unrecovered business-borne amount', function () {
    // 1. Customer pays partial delivery charge of ৳40. Pathao charges ৳100 fee.
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-C001',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2500.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 40.00,
        'grand_total' => 2540.00,
        'paid_total' => 0.00,
        'due_amount' => 2540.00,
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
        'item_weight' => 1.5,
        'recipient_name' => $sale->customer_name,
        'recipient_phone' => $sale->customer_phone,
        'recipient_address' => $sale->customer_address,
        'recipient_city' => 1,
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2540.00,
        'courier_charge' => 100.00, // Explicit ৳100 courier fee
        'status' => 'created',
    ]);

    $service = app(PathaoService::class);
    $expense = $service->reconcileCourierExpense($courierOrder);

    // Unrecovered business-borne amount: ৳100 - ৳40 = ৳60
    expect($expense)->not->toBeNull()
        ->and((float) $expense->amount)->toBe(60.0);

    $expenses = Expense::where('courier_order_id', $courierOrder->id)
        ->where('source', 'pathao_charge')
        ->get();

    expect($expenses)->toHaveCount(1)
        ->and((float) $expenses->first()->amount)->toBe(60.0);

    // Profit & Loss reconciliation:
    // Delivery Income = ৳40 (Line E)
    // Courier Expense = ৳60 (Line J)
    // Net effect on P&L = +40 - 60 = -20 (the exact real net courier deficit)
    $plService = app(ProfitLossService::class);
    $report = $plService->calculate(now()->startOfMonth(), now()->endOfMonth());

    expect((float) $report['revenue']['delivery_income'])->toBe(40.0)
        ->and((float) $report['expenses']['courier'])->toBe(60.0);
});

test('idempotency: retrying dispatch and repeated status polling do not duplicate expenses', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-IDEM01',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2500.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00, // Free delivery -> 1 expense
        'grand_total' => 2500.00,
        'paid_total' => 0.00,
        'due_amount' => 2500.00,
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
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2500.00,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);

    // Initial dispatch
    $service->createOrder($courierOrder);

    // Attempt repeated dispatch retry
    $service->createOrder($courierOrder);

    // Attempt repeated status polling (5 times)
    for ($i = 0; $i < 5; $i++) {
        $service->syncOrder($courierOrder);
    }

    // Exactly ONE expense exists
    $expenses = Expense::where('courier_order_id', $courierOrder->id)
        ->where('source', 'pathao_charge')
        ->get();

    expect($expenses)->toHaveCount(1)
        ->and((float) $expenses->first()->amount)->toBe(60.0);
});

test('cancellation: cancelled courier order or cancelled sale soft-deletes linked courier expense', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-CANC01',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2500.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00,
        'grand_total' => 2500.00,
        'paid_total' => 0.00,
        'due_amount' => 2500.00,
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
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2500.00,
        'courier_charge' => 60.00,
        'status' => 'created',
    ]);

    $service = app(PathaoService::class);
    $service->reconcileCourierExpense($courierOrder);

    // Active expense exists
    expect(Expense::where('courier_order_id', $courierOrder->id)->where('source', 'pathao_charge')->count())->toBe(1);

    // Simulate Pathao status update to cancelled (e.g. Pickup Cancel)
    $courierOrder->update(['status' => 'cancelled', 'raw_status' => 'Pickup Cancel']);
    $service->reconcileCourierExpense($courierOrder);

    // Active expense is gone (soft-deleted for audit trail)
    expect(Expense::where('courier_order_id', $courierOrder->id)->where('source', 'pathao_charge')->count())->toBe(0);
    expect(Expense::withTrashed()->where('courier_order_id', $courierOrder->id)->where('source', 'pathao_charge')->count())->toBe(1);

    // OPEX in P&L must be 0
    $plService = app(ProfitLossService::class);
    $report = $plService->calculate(now()->startOfMonth(), now()->endOfMonth());
    expect((float) $report['expenses']['courier'])->toBe(0.0);
});

test('COD settlement does not double count revenue or create duplicate expenses', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-COD01',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2500.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 100.00,
        'grand_total' => 2600.00,
        'paid_total' => 0.00,
        'due_amount' => 2600.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => $sale->invoice_no,
        'consignment_id' => 'PATHAO-COD-TEST-001',
        'store_id' => 1,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => $sale->customer_name,
        'recipient_phone' => $sale->customer_phone,
        'recipient_address' => $sale->customer_address,
        'recipient_city' => 1,
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2600.00,
        'courier_charge' => 60.00,
        'status' => 'delivered',
    ]);

    $service = app(PathaoService::class);
    $service->settleCod($courierOrder, $this->owner);

    $courierOrder->refresh();
    $sale->refresh();

    expect($courierOrder->cod_settled_at)->not->toBeNull()
        ->and((float) $courierOrder->cod_settled_amount)->toBe(2600.0)
        ->and((float) $sale->paid_total)->toBe(2600.0)
        ->and($sale->payment_status)->toBe('paid');

    // Revenue in P&L is still strictly Net Sales (2500) + Delivery Income (100) = 2600.
    // The COD settlement payment simply settled the receivables, never double counting revenue.
    $plService = app(ProfitLossService::class);
    $report = $plService->calculate(now()->startOfMonth(), now()->endOfMonth());

    expect((float) $report['revenue']['gross_sales'])->toBe(2500.0)
        ->and((float) $report['revenue']['delivery_income'])->toBe(100.0)
        ->and((float) $report['revenue']['total_revenue'])->toBe(2600.0)
        ->and((float) $report['expenses']['courier'])->toBe(0.0);
});

test('sale cancellation: cancelling a sale soft-deletes the linked courier expense', function () {
    $saleService = app(\App\Services\Sales\SaleService::class);

    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-SALECANC01',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2500.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00, // Free delivery -> creates 1 business-borne expense
        'grand_total' => 2500.00,
        'paid_total' => 0.00,
        'due_amount' => 2500.00,
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
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2500.00,
        'courier_charge' => 60.00,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);
    $service->reconcileCourierExpense($courierOrder);

    expect(Expense::where('courier_order_id', $courierOrder->id)->where('source', 'pathao_charge')->count())->toBe(1);

    // Cancel the sale via SaleService
    $result = $saleService->cancelSale($sale, ['reason' => 'Customer changed mind'], $this->owner);

    expect($result['sale']->status)->toBe('cancelled');

    // Expense is soft-deleted
    expect(Expense::where('courier_order_id', $courierOrder->id)->where('source', 'pathao_charge')->count())->toBe(0);
    expect(Expense::withTrashed()->where('courier_order_id', $courierOrder->id)->where('source', 'pathao_charge')->count())->toBe(1);
});

test('status sync updates business-borne expense amount idempotently if courier fee changes', function () {
    // 1. Partial customer contribution: customer paid ৳40
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-FEECHG01',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2500.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 40.00,
        'grand_total' => 2540.00,
        'paid_total' => 0.00,
        'due_amount' => 2540.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    // Initial Pathao order with estimated fee ৳60 -> business bears ৳20
    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => $sale->invoice_no,
        'consignment_id' => 'CS-FEE-CHG-999',
        'store_id' => 1,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => $sale->customer_name,
        'recipient_phone' => $sale->customer_phone,
        'recipient_address' => $sale->customer_address,
        'recipient_city' => 1,
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2540.00,
        'courier_charge' => 60.00,
        'status' => 'created',
    ]);

    $service = app(PathaoService::class);
    $service->reconcileCourierExpense($courierOrder);

    $expense = Expense::where('courier_order_id', $courierOrder->id)->where('source', 'pathao_charge')->first();
    expect($expense)->not->toBeNull()
        ->and((float) $expense->amount)->toBe(20.0);

    // Mock Pathao API info endpoint returning updated delivery_fee = 90
    Config::set('services.pathao.client_id', 'test-client');
    Config::set('services.pathao.client_secret', 'test-secret');

    \App\Models\PathaoCourierToken::create([
        'access_token' => 'sync-test-token',
        'token_type' => 'Bearer',
        'expires_in' => 86400,
    ]);

    Http::fake([
        '*/aladdin/api/v1/orders/CS-FEE-CHG-999/info' => Http::response([
            'type' => 'success',
            'code' => 200,
            'message' => 'Order info',
            'data' => [
                'consignment_id' => 'CS-FEE-CHG-999',
                'order_status' => 'In Transit',
                'delivery_fee' => 90.0,
            ],
        ], 200),
    ]);

    // Perform sync
    $service->syncOrder($courierOrder);

    $courierOrder->refresh();
    expect((float) $courierOrder->courier_charge)->toBe(90.0);

    // Expense is updated to: ৳90 fee - ৳40 customer paid = ৳50 business-borne
    $allExpenses = Expense::where('courier_order_id', $courierOrder->id)->where('source', 'pathao_charge')->get();
    expect($allExpenses)->toHaveCount(1)
        ->and((float) $allExpenses->first()->amount)->toBe(50.0);
});

test('business expenses index reconciles with genuine expense records and excludes customer delivery charges', function () {
    // 1. Regular business expense: Packaging ৳500
    $pkgCat = ExpenseCategory::where('pl_group', 'packaging')->first();
    Expense::create([
        'expense_category_id' => $pkgCat->id,
        'expense_date' => now()->toDateString(),
        'amount' => 500.00,
        'payment_method' => 'cash',
        'vendor' => 'Packaging Supplier',
        'created_by' => $this->owner->id,
    ]);

    // 2. Scenario A Sale (customer pays ৳100 delivery charge) -> 0 expenses
    $saleA = Sale::create([
        'invoice_no' => 'TASA-2610-PAGE-A',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2000.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 100.00,
        'grand_total' => 2100.00,
        'paid_total' => 0.00,
        'due_amount' => 2100.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);
    $orderA = CourierOrder::create([
        'sale_id' => $saleA->id,
        'merchant_order_id' => $saleA->invoice_no,
        'store_id' => 1,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => $saleA->customer_name,
        'recipient_phone' => $saleA->customer_phone,
        'recipient_address' => $saleA->customer_address,
        'recipient_city' => 1,
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2100.00,
        'status' => 'queued',
    ]);
    app(PathaoService::class)->createOrder($orderA);

    // 3. Scenario B Sale (free delivery, courier fee ৳60) -> 1 business expense of ৳60
    $saleB = Sale::create([
        'invoice_no' => 'TASA-2610-PAGE-B',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2000.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00,
        'grand_total' => 2000.00,
        'paid_total' => 0.00,
        'due_amount' => 2000.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);
    $orderB = CourierOrder::create([
        'sale_id' => $saleB->id,
        'merchant_order_id' => $saleB->invoice_no,
        'store_id' => 1,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => $saleB->customer_name,
        'recipient_phone' => $saleB->customer_phone,
        'recipient_address' => $saleB->customer_address,
        'recipient_city' => 1,
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2000.00,
        'status' => 'queued',
    ]);
    app(PathaoService::class)->createOrder($orderB);

    // Fetch Business Expenses page
    $response = $this->actingAs($this->owner)->get('/expenses');
    $response->assertOk();

    $pageProps = $response->original->getData()['page']['props'];
    $analytics = $pageProps['analytics'];

    // Total expenses must be Packaging (500) + Courier (60) = 560
    // Customer-funded delivery charge of ৳100 is NOT in operating expenses!
    expect((float) $analytics['total_amount'])->toBe(560.0);
    expect((float) $analytics['by_pl_group']['courier'])->toBe(60.0);
    expect((float) $analytics['by_pl_group']['packaging'])->toBe(500.0);
});

