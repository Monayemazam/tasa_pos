<?php

namespace Tests\Feature\Sales;

use App\Jobs\DispatchPathaoOrderJob;
use App\Models\CourierOrder;
use App\Models\Customer;
use App\Models\InvoiceSequence;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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

    $this->category = ProductCategory::create(['name' => 'Wallets']);

    $this->product = Product::create([
        'product_category_id' => $this->category->id,
        'name' => 'Classic Bifold Wallet',
        'sku' => 'P-000101',
        'cost_price' => 850.00,
        'selling_price' => 1500.00,
        'stock_qty' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);
});

test('AC-1: rejects sale submission if customer name, valid BD phone, or 10-char address is missing', function () {
    // Missing customer name
    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => '',
        'customer_phone' => '01712345678',
        'customer_address' => 'House 12, Road 5, Dhanmondi, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'qty' => 1, 'unit_price' => 1500.00, 'name' => $this->product->name],
        ],
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(422)->assertJsonValidationErrors(['customer_name']);

    // Invalid Bangladeshi phone format (e.g. 10 digits or non-BD prefix)
    $payload['customer_name'] = 'Rahim Ahmed';
    $payload['customer_phone'] = '01212345678'; // 012 is not valid BD mobile prefix
    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(422)->assertJsonValidationErrors(['customer_phone']);

    $payload['customer_phone'] = '017123456'; // too short
    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(422)->assertJsonValidationErrors(['customer_phone']);

    // Address shorter than 10 characters
    $payload['customer_phone'] = '01712345678';
    $payload['customer_address'] = 'Dhaka'; // 5 chars
    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(422)->assertJsonValidationErrors(['customer_address']);

    // Empty cart items
    $payload['customer_address'] = 'House 12, Road 5, Dhanmondi, Dhaka';
    $payload['items'] = [];
    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(422)->assertJsonValidationErrors(['items']);

    expect(Sale::count())->toBe(0);
});

test('AC-2: saving a sale creates exactly one invoice with sequential monthly number and reserves sequence atomically', function () {
    Queue::fake();

    $token = (string) Str::uuid();
    $payload = [
        'idempotency_token' => $token,
        'customer_name' => 'Tanvir Hasan',
        'customer_phone' => '01812345678',
        'customer_address' => 'Road 27, House 45, Dhanmondi, Dhaka',
        'courier_mode' => 'pathao',
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'city_name' => 'Dhaka',
        'zone_name' => 'Dhanmondi',
        'area_name' => 'Road 27',
        'item_weight' => 0.5,
        'items' => [
            [
                'product_id' => $this->product->id,
                'name' => $this->product->name,
                'qty' => 2,
                'unit_price' => 1500.00,
            ],
        ],
        'discount_type' => 'flat',
        'discount_value' => 200.00,
        'delivery_charge' => 80.00,
        'paid_amount' => 1000.00,
        'payment_method' => 'bkash',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(200);

    $sale = Sale::first();
    expect($sale)->not->toBeNull();

    // Check sequential format TASA-YYMM-0001
    $expectedPeriod = now()->format('ym');
    expect($sale->invoice_no)->toBe("TASA-{$expectedPeriod}-0001")
        ->and((float) $sale->subtotal)->toBe(3000.00)
        ->and((float) $sale->discount_amount)->toBe(200.00)
        ->and((float) $sale->delivery_charge)->toBe(80.00)
        ->and((float) $sale->grand_total)->toBe(2880.00)
        ->and((float) $sale->paid_total)->toBe(1000.00)
        ->and((float) $sale->due_amount)->toBe(1880.00)
        ->and($sale->payment_status)->toBe('partial');

    // Customer created on the fly
    $customer = Customer::where('phone', '01812345678')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Tanvir Hasan')
        ->and($customer->pathao_city_id)->toBe(1);

    // Initial payment logged in sale_payments
    $payment = SalePayment::where('sale_id', $sale->id)->first();
    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(1000.00)
        ->and($payment->method)->toBe('bkash');

    // Courier order created and queued job dispatched
    $courierOrder = CourierOrder::where('sale_id', $sale->id)->first();
    expect($courierOrder)->not->toBeNull()
        ->and($courierOrder->status)->toBe('queued')
        ->and((float) $courierOrder->cod_amount)->toBe(1880.00); // Pathao will collect remaining due

    Queue::assertPushed(DispatchPathaoOrderJob::class);
});

test('AC-2 (Idempotency): duplicate save with same token returns existing sale without duplicate records', function () {
    Queue::fake();

    $token = (string) Str::uuid();
    $payload = [
        'idempotency_token' => $token,
        'customer_name' => 'Fahim Rahman',
        'customer_phone' => '01799887766',
        'customer_address' => 'Mirpur DOHS, Road 3, House 15, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $this->product->id,
                'name' => $this->product->name,
                'qty' => 1,
                'unit_price' => 1500.00,
            ],
        ],
        'paid_amount' => 1500.00,
        'payment_method' => 'cash',
    ];

    // First request
    $response1 = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response1->assertStatus(200)->assertJson(['is_duplicate' => false]);
    $saleId1 = $response1->json('sale.id');

    // Duplicate request with exact same token (simulating double-click or network retry)
    $response2 = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response2->assertStatus(200)->assertJson(['is_duplicate' => true]);
    $saleId2 = $response2->json('sale.id');

    expect($saleId1)->toBe($saleId2)
        ->and(Sale::count())->toBe(1)
        ->and(SaleItem::count())->toBe(1)
        ->and(SalePayment::count())->toBe(1)
        ->and($this->product->fresh()->stock_qty)->toBe(19); // Deducted only once!
});

test('AC-5: sale deducts product stock and creates product_stock_movements with running balance', function () {
    expect($this->product->stock_qty)->toBe(20);

    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Zakir Hossain',
        'customer_phone' => '01912345678',
        'customer_address' => 'Uttara Sector 7, Road 12, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $this->product->id,
                'name' => $this->product->name,
                'qty' => 5,
                'unit_price' => 1500.00,
            ],
        ],
        'paid_amount' => 7500.00,
        'payment_method' => 'cash',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(200);

    $this->product->refresh();
    expect($this->product->stock_qty)->toBe(15);

    $movement = ProductStockMovement::where('product_id', $this->product->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('sale')
        ->and($movement->qty)->toBe(-5)
        ->and($movement->balance_after)->toBe(15)
        ->and($movement->reference_type)->toBe('sales');
});

test('it blocks selling below zero stock when allow_negative_stock is 0', function () {
    Setting::set('inventory.allow_negative_stock', '0');

    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Customer A',
        'customer_phone' => '01711112233',
        'customer_address' => 'Gulshan 2, Road 103, House 4, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $this->product->id,
                'name' => $this->product->name,
                'qty' => 25, // Available is only 20
                'unit_price' => 1500.00,
            ],
        ],
        'paid_amount' => 0,
        'payment_method' => 'cod',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(422)->assertJsonValidationErrors(['items']);

    expect($this->product->fresh()->stock_qty)->toBe(20)
        ->and(Sale::count())->toBe(0);
});

test('it allows selling below zero stock when allow_negative_stock is 1', function () {
    Setting::set('inventory.allow_negative_stock', '1');

    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Customer B',
        'customer_phone' => '01711112244',
        'customer_address' => 'Gulshan 2, Road 103, House 4, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $this->product->id,
                'name' => $this->product->name,
                'qty' => 25, // Available is 20, becomes -5
                'unit_price' => 1500.00,
            ],
        ],
        'paid_amount' => 0,
        'payment_method' => 'cod',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(200);

    expect($this->product->fresh()->stock_qty)->toBe(-5);
});

test('it handles custom-made items without deducting catalogue product stock and preserves manual cost snapshot', function () {
    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Custom Buyer',
        'customer_phone' => '01811223344',
        'customer_address' => 'Banani Block C, Road 11, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'is_custom' => true,
                'name' => 'Bespoke Crocodile Cardholder with Initials',
                'qty' => 1,
                'unit_price' => 3500.00,
                'unit_cost' => 1200.00,
            ],
        ],
        'paid_amount' => 3500.00,
        'payment_method' => 'bkash',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(200);

    $saleItem = SaleItem::first();
    expect($saleItem->is_custom)->toBeTrue()
        ->and($saleItem->product_id)->toBeNull()
        ->and($saleItem->name)->toBe('Bespoke Crocodile Cardholder with Initials')
        ->and((float) $saleItem->unit_price)->toBe(3500.00)
        ->and((float) $saleItem->unit_cost)->toBe(1200.00);

    // Catalogue stock untouched and no movement row written
    expect($this->product->fresh()->stock_qty)->toBe(20)
        ->and(ProductStockMovement::count())->toBe(0);
});

test('BR-6: snapshots product cost_price at sale time and changing cost_price later does not alter snapshot', function () {
    expect((float) $this->product->cost_price)->toBe(850.00);

    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Snapshot Test',
        'customer_phone' => '01755667788',
        'customer_address' => 'Mohakhali DOHS, Road 1, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $this->product->id,
                'name' => $this->product->name,
                'qty' => 1,
                'unit_price' => 1500.00,
            ],
        ],
        'paid_amount' => 1500.00,
        'payment_method' => 'cash',
    ];

    $this->actingAs($this->owner)->postJson('/sales', $payload)->assertStatus(200);

    $saleItem = SaleItem::first();
    expect((float) $saleItem->unit_cost)->toBe(850.00);

    // Later, product cost price changes to 950.00
    $this->product->update(['cost_price' => 950.00]);

    // Historical sale item cost snapshot remains 850.00
    $saleItem->refresh();
    expect((float) $saleItem->unit_cost)->toBe(850.00);
});

test('it blocks staff from modifying selling price if sales.edit_price permission is revoked', function () {
    // Revoke edit price permission from staff
    $this->staff->revokePermissionTo('sales.edit_price');

    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Price Edit Test',
        'customer_phone' => '01744332211',
        'customer_address' => 'Lalmatia Block D, Road 4, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'product_id' => $this->product->id,
                'name' => $this->product->name,
                'qty' => 1,
                'unit_price' => 1200.00, // Catalogue price is 1500.00
            ],
        ],
        'paid_amount' => 1200.00,
        'payment_method' => 'cash',
    ];

    $response = $this->actingAs($this->staff)->postJson('/sales', $payload);
    $response->assertStatus(422)->assertJsonValidationErrors(['items.0.unit_price']);

    // When staff keeps catalogue price, it succeeds
    $payload['items'][0]['unit_price'] = 1500.00;
    $payload['paid_amount'] = 1500.00;
    $this->actingAs($this->staff)->postJson('/sales', $payload)->assertStatus(200);
});

test('it allows adding later payments to sales with dues and recalculates status', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0099',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => Customer::create([
            'name' => 'Due Customer',
            'phone' => '01700112233',
            'address' => 'Baridhara J Block, Road 2, Dhaka',
        ])->id,
        'customer_name' => 'Due Customer',
        'customer_phone' => '01700112233',
        'customer_address' => 'Baridhara J Block, Road 2, Dhaka',
        'sale_date' => '2026-10-10',
        'sold_at' => now(),
        'subtotal' => 2000.00,
        'grand_total' => 2000.00,
        'paid_total' => 500.00,
        'payment_status' => 'partial',
        'status' => 'active',
        'courier_mode' => 'none',
    ]);

    expect((float) $sale->due_amount)->toBe(1500.00);

    // Add payment of 500
    $response = $this->actingAs($this->owner)->postJson("/sales/{$sale->id}/payments", [
        'amount' => 500.00,
        'method' => 'bkash',
        'reference' => 'BKASH-TRX-1234',
        'note' => 'Second installment',
    ]);

    $response->assertStatus(200);

    $sale->refresh();
    expect((float) $sale->paid_total)->toBe(1000.00)
        ->and((float) $sale->due_amount)->toBe(1000.00)
        ->and($sale->payment_status)->toBe('partial');

    // Add remaining 1000
    $this->actingAs($this->owner)->postJson("/sales/{$sale->id}/payments", [
        'amount' => 1000.00,
        'method' => 'cash',
    ])->assertStatus(200);

    $sale->refresh();
    expect((float) $sale->paid_total)->toBe(2000.00)
        ->and((float) $sale->due_amount)->toBe(0.00)
        ->and($sale->payment_status)->toBe('paid');
});

test('it looks up customer by phone with computed totals and Pathao prefill without mutating customer table', function () {
    $customer = Customer::create([
        'name' => 'Existing Shopper',
        'phone' => '01766554433',
        'address' => 'Pallabi, Section 12, Mirpur, Dhaka',
        'pathao_city_id' => 1,
        'pathao_zone_id' => 104,
        'pathao_area_id' => 10404,
        'city_name' => 'Dhaka',
        'zone_name' => 'Mirpur',
        'area_name' => 'Mirpur 12',
    ]);

    // Create 2 sales for this customer
    Sale::create([
        'invoice_no' => 'TASA-2610-0010',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => $customer->address,
        'sale_date' => '2026-10-05',
        'sold_at' => now(),
        'subtotal' => 1500.00,
        'grand_total' => 1500.00,
        'paid_total' => 1500.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
    ]);

    Sale::create([
        'invoice_no' => 'TASA-2610-0011',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => $customer->address,
        'sale_date' => '2026-10-08',
        'sold_at' => now(),
        'subtotal' => 3000.00,
        'grand_total' => 3000.00,
        'paid_total' => 1000.00,
        'payment_status' => 'partial',
        'status' => 'active',
        'courier_mode' => 'none',
    ]);

    $response = $this->actingAs($this->owner)->getJson('/sales/customers/lookup?phone=01766554433');
    $response->assertStatus(200);

    expect($response->json('found'))->toBeTrue()
        ->and($response->json('customer.name'))->toBe('Existing Shopper')
        ->and($response->json('customer.pathao_city_id'))->toBe(1)
        ->and($response->json('customer.total_orders'))->toBe(2)
        ->and((float) $response->json('customer.total_spent'))->toBe(4500.00)
        ->and((float) $response->json('customer.outstanding_due'))->toBe(2000.00);
});

test('it rejects bad discount when discount exceeds subtotal or percentage exceeds 100', function () {
    // Flat discount > subtotal
    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Bad Discount Buyer',
        'customer_phone' => '01712345678',
        'customer_address' => 'Mirpur Road 10, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'qty' => 1, 'unit_price' => 1500.00, 'name' => $this->product->name],
        ],
        'discount_type' => 'flat',
        'discount_value' => 2000.00, // exceeds subtotal of 1500
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(422)->assertJsonValidationErrors(['discount_value']);

    // Percent discount > 100%
    $payload['discount_type'] = 'percent';
    $payload['discount_value'] = 110.00;
    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(422)->assertJsonValidationErrors(['discount_value']);
});

test('hand-delivered sale (courier_mode none) succeeds without Pathao IDs or courier orders', function () {
    Queue::fake();

    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Hand Delivery Buyer',
        'customer_phone' => '01899887766',
        'customer_address' => 'Green Road, Farmgate, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            ['product_id' => $this->product->id, 'qty' => 1, 'unit_price' => 1500.00, 'name' => $this->product->name],
        ],
        'paid_amount' => 1500.00,
        'payment_method' => 'cash',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(200);

    $sale = Sale::where('customer_phone', '01899887766')->first();
    expect($sale)->not->toBeNull()
        ->and($sale->courier_mode)->toBe('none')
        ->and($sale->courierOrder)->toBeNull();

    Queue::assertNothingPushed();
});

test('custom item with omitted cost defaults to safe agreed 0.00 snapshot', function () {
    $payload = [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => 'Zero Cost Buyer',
        'customer_phone' => '01733221100',
        'customer_address' => 'Shahbagh, Katabon, Dhaka',
        'courier_mode' => 'none',
        'items' => [
            [
                'is_custom' => true,
                'name' => 'Custom Leather Keyring',
                'qty' => 2,
                'unit_price' => 250.00,
                // unit_cost intentionally omitted
            ],
        ],
        'paid_amount' => 500.00,
        'payment_method' => 'cash',
    ];

    $response = $this->actingAs($this->owner)->postJson('/sales', $payload);
    $response->assertStatus(200);

    $saleItem = SaleItem::where('name', 'Custom Leather Keyring')->first();
    expect($saleItem)->not->toBeNull()
        ->and((float) $saleItem->unit_cost)->toBe(0.00)
        ->and((float) $saleItem->line_total)->toBe(500.00);
});

test('invoice sequence generates consecutive monthly numbers and never reuses numbers', function () {
    $period = now()->format('ym');

    $no1 = InvoiceSequence::nextInvoiceNo();
    $no2 = InvoiceSequence::nextInvoiceNo();
    $no3 = InvoiceSequence::nextInvoiceNo();

    expect($no1)->toBe("TASA-{$period}-0001")
        ->and($no2)->toBe("TASA-{$period}-0002")
        ->and($no3)->toBe("TASA-{$period}-0003");
});

