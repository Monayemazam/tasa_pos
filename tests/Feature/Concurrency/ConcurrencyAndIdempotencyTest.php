<?php

namespace Tests\Feature\Concurrency;

use App\Models\CourierOrder;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InvoiceSequence;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\RecurringExpense;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Courier\PathaoService;
use App\Services\Expenses\ExpenseService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(ExpenseCategorySeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->owner = User::factory()->create(['email' => 'owner@tasa.com']);
    $this->owner->assignSingleRole('Owner');

    $this->staff = User::factory()->create(['email' => 'staff@tasa.com']);
    $this->staff->assignSingleRole('Staff');

    $this->category = ProductCategory::create(['name' => 'Bags']);

    $this->product = Product::create([
        'product_category_id' => $this->category->id,
        'name' => 'Leather Messenger Bag',
        'sku' => 'BAG-MESS-01',
        'cost_price' => 2500.00,
        'selling_price' => 4500.00,
        'stock_qty' => 5, // Exactly 5 units in stock
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'name' => 'আরিফুল ইসলাম',
        'phone' => '01711223344',
        'address' => 'House 5, Road 2, Banani, Dhaka',
        'pathao_city_id' => 1,
        'pathao_zone_id' => 101,
        'pathao_area_id' => 10101,
    ]);
});

test('1. idempotency: double-submit on sale creation returns original sale without duplicate records', function () {
    Queue::fake();

    $idempotencyToken = (string) Str::uuid();
    $payload = [
        'idempotency_token' => $idempotencyToken,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'courier_mode' => 'pathao',
        'item_weight' => 0.5,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 2, 'unit_price' => 4500.00],
        ],
        'delivery_charge' => 100.00,
        'paid_amount' => 1000.00,
        'payment_method' => 'bkash',
    ];

    // First submission
    $response1 = $this->actingAs($this->staff)->postJson('/sales', $payload);
    $response1->assertOk();
    $saleId1 = $response1->json('sale.id');
    $invoiceNo1 = $response1->json('sale.invoice_no');

    // Immediate second submission with identical token (simulating double-click or network retry)
    $response2 = $this->actingAs($this->staff)->postJson('/sales', $payload);
    $response2->assertOk();
    $saleId2 = $response2->json('sale.id');
    $invoiceNo2 = $response2->json('sale.invoice_no');

    // Assert both responses point to the exact same sale
    expect($saleId1)->toBe($saleId2)
        ->and($invoiceNo1)->toBe($invoiceNo2);

    // Assert strictly 1 row in sales table
    expect(Sale::count())->toBe(1);

    // Assert strictly 1 row in sale_items table
    expect(SaleItem::count())->toBe(1);

    // Assert strictly 1 stock movement row (qty = -2)
    expect(ProductStockMovement::where('product_id', $this->product->id)->count())->toBe(1);

    // Assert product stock was decremented exactly once: 5 - 2 = 3
    $this->product->refresh();
    expect((int) $this->product->stock_qty)->toBe(3);

    // Assert strictly 1 courier order
    expect(CourierOrder::count())->toBe(1);

    // Assert strictly 1 sale payment
    expect(SalePayment::count())->toBe(1);
});

test('2. concurrency: simultaneous invoice number allocation produces strictly unique consecutive sequences', function () {
    $period = now();
    $prefix = 'TASA';

    // Generate 5 consecutive sequence numbers
    $invoiceNumbers = [];
    for ($i = 0; $i < 5; $i++) {
        $invoiceNumbers[] = InvoiceSequence::nextInvoiceNo($period, $prefix);
    }

    // All must be unique
    expect(count(array_unique($invoiceNumbers)))->toBe(5);

    $expectedMonth = now()->format('ym');
    expect($invoiceNumbers[0])->toBe("TASA-{$expectedMonth}-0001")
        ->and($invoiceNumbers[1])->toBe("TASA-{$expectedMonth}-0002")
        ->and($invoiceNumbers[2])->toBe("TASA-{$expectedMonth}-0003")
        ->and($invoiceNumbers[3])->toBe("TASA-{$expectedMonth}-0004")
        ->and($invoiceNumbers[4])->toBe("TASA-{$expectedMonth}-0005");
});

test('3. oversell protection: cannot oversell stock when negative stock is disallowed', function () {
    Queue::fake();

    // Default: allow_negative_stock = 0
    Setting::set('inventory.allow_negative_stock', '0');

    // Attempt to sell 6 units when only 5 exist
    $responseOversell = $this->actingAs($this->staff)->postJson('/sales', [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 6, 'unit_price' => 4500.00],
        ],
    ]);

    $responseOversell->assertStatus(422)
        ->assertJsonValidationErrors(['items']);

    // Now test sequential exhaustion: Sale A buys 3 units
    $responseSaleA = $this->actingAs($this->staff)->postJson('/sales', [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 3, 'unit_price' => 4500.00],
        ],
    ]);
    $responseSaleA->assertOk();

    $this->product->refresh();
    expect((int) $this->product->stock_qty)->toBe(2);

    // Sale B attempts to buy 3 units (only 2 remain) -> BLOCKED
    $responseSaleB = $this->actingAs($this->staff)->postJson('/sales', [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 3, 'unit_price' => 4500.00],
        ],
    ]);
    $responseSaleB->assertStatus(422)
        ->assertJsonValidationErrors(['items']);

    // Stock remains 2
    $this->product->refresh();
    expect((int) $this->product->stock_qty)->toBe(2);
});

test('4. idempotency: adding payment cannot exceed balance due or double-credit payment', function () {
    // Create sale with ৳4500 total, ৳1500 paid, ৳3000 due
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0099',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'subtotal' => 4500.00,
        'grand_total' => 4500.00,
        'paid_total' => 1500.00,
        'payment_status' => 'partial',
        'status' => 'active',
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    // Initial payment of 1500
    SalePayment::create([
        'sale_id' => $sale->id,
        'paid_on' => now()->toDateString(),
        'amount' => 1500.00,
        'method' => 'cash',
        'created_by' => $this->owner->id,
    ]);

    // Attempt payment of ৳3500 (exceeds remaining balance due of ৳3000) -> BLOCKED
    $excessPayment = $this->actingAs($this->owner)->postJson("/sales/{$sale->id}/payments", [
        'amount' => 3500.00,
        'method' => 'bkash',
        'paid_on' => now()->toDateString(),
    ]);

    $excessPayment->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);

    // Valid payment of exact balance due ৳3000
    $validPayment = $this->actingAs($this->owner)->postJson("/sales/{$sale->id}/payments", [
        'amount' => 3000.00,
        'method' => 'bkash',
        'paid_on' => now()->toDateString(),
    ]);
    $validPayment->assertOk();

    $sale->refresh();
    expect((float) $sale->paid_total)->toBe(4500.00)
        ->and($sale->payment_status)->toBe('paid');

    // Attempting another payment on now fully-paid sale -> BLOCKED
    $afterPaidPayment = $this->actingAs($this->owner)->postJson("/sales/{$sale->id}/payments", [
        'amount' => 500.00,
        'method' => 'cash',
        'paid_on' => now()->toDateString(),
    ]);
    $afterPaidPayment->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('5. idempotency: duplicate courier dispatch does not duplicate consignment or courier expense', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0088',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'subtotal' => 4500.00,
        'grand_total' => 4500.00,
        'paid_total' => 0.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
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
        'cod_amount' => 4500.00,
        'status' => 'queued',
    ]);

    $pathaoService = app(PathaoService::class);

    // Call 1: creates order and 1 expense
    $success1 = $pathaoService->createOrder($courierOrder);
    expect($success1)->toBeTrue();
    $courierOrder->refresh();
    $consignmentId = $courierOrder->consignment_id;
    expect($consignmentId)->not->toBeNull();

    // Call 2: duplicate call should recognize already created
    $success2 = $pathaoService->createOrder($courierOrder);
    $courierOrder->refresh();
    expect($courierOrder->consignment_id)->toBe($consignmentId);

    // Expense count must be strictly 1
    $expensesCount = Expense::where('courier_order_id', $courierOrder->id)
        ->where('source', 'pathao_charge')
        ->count();
    expect($expensesCount)->toBe(1);
});

test('6. idempotency: recurring monthly expense confirmation prevents duplicate creation in same month', function () {
    $category = \App\Models\ExpenseCategory::where('pl_group', 'rent_utilities')->first();

    $template = RecurringExpense::create([
        'expense_category_id' => $category->id,
        'name' => 'Monthly Workshop Rent',
        'amount' => 30000.00,
        'payment_method' => 'bank_transfer',
        'vendor' => 'Landlord',
        'is_active' => true,
    ]);

    $expenseService = app(ExpenseService::class);

    // First confirmation creates expense
    $expense1 = $expenseService->confirmRecurringExpense($template, $this->owner);
    expect($expense1)->not->toBeNull();

    // Second confirmation in same month throws ValidationException
    expect(fn () => $expenseService->confirmRecurringExpense($template, $this->owner))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // Exactly 1 expense created for this recurring template
    expect(Expense::where('recurring_expense_id', $template->id)->count())->toBe(1);
});
