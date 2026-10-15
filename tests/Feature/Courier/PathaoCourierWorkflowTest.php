<?php

namespace Tests\Feature\Courier;

use App\Jobs\DispatchPathaoOrderJob;
use App\Models\CourierOrder;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\PathaoCourierToken;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Courier\PathaoService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
        'name' => 'Premium Leather Laptop Bag',
        'sku' => 'BAG-LAPTOP-01',
        'cost_price' => 3000.00,
        'selling_price' => 5000.00,
        'stock_qty' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'name' => 'তাহমিদ হক (Tahmid Haque)',
        'phone' => '01712345678',
        'address' => 'House 14, Road 5, Dhanmondi, Dhaka',
        'pathao_city_id' => 1,
        'pathao_zone_id' => 101,
        'pathao_area_id' => 10101,
        'city_name' => 'Dhaka',
        'zone_name' => 'Dhanmondi',
        'area_name' => 'Dhanmondi Road 27',
    ]);

    Cache::flush();
});

test('1. connection status masks client id and warns when token days left is under 7 days', function () {
    Config::set('services.pathao.client_id', 'PTH-LIVE-CLIENT-999');
    Config::set('services.pathao.client_secret', 'SECRET-TOP-CONFIDENTIAL');

    $service = app(PathaoService::class);

    // No token yet -> token_needed
    $status = $service->getConnectionStatus();
    expect($status['is_configured'])->toBeTrue()
        ->and($status['status'])->toBe('token_needed')
        ->and($status['masked_client_id'])->not->toContain('SECRET')
        ->and($status['masked_client_id'])->toBe('PTH••••999');

    // Create token expiring in 4 days -> warn_expiry = true
    PathaoCourierToken::create([
        'access_token' => 'mock-jwt-token',
        'token_type' => 'Bearer',
        'expires_in' => 4 * 86400,
    ]);

    $statusWithToken = $service->getConnectionStatus();
    expect($statusWithToken['status'])->toBe('expiring_soon')
        ->and($statusWithToken['warn_expiry'])->toBeTrue()
        ->and($statusWithToken['token_days_left'])->toBeLessThanOrEqual(4);

    // Create fresh token expiring in 25 days -> warn_expiry = false
    PathaoCourierToken::create([
        'access_token' => 'mock-fresh-token',
        'token_type' => 'Bearer',
        'expires_in' => 25 * 86400,
    ]);

    $statusFresh = $service->getConnectionStatus();
    expect($statusFresh['status'])->toBe('connected')
        ->and($statusFresh['warn_expiry'])->toBeFalse()
        ->and($statusFresh['token_days_left'])->toBeGreaterThanOrEqual(24);
});

test('2. cascading locations cascade and are cached for 24 hours', function () {
    $service = app(PathaoService::class);

    $cities = $service->getCities();
    expect($cities)->toBeArray()->and(count($cities))->toBeGreaterThan(0)
        ->and(Cache::has('pathao_cities'))->toBeTrue();

    $zones = $service->getZones(1);
    expect($zones)->toBeArray()->and(count($zones))->toBeGreaterThan(0)
        ->and(Cache::has('pathao_zones_1'))->toBeTrue();

    $areas = $service->getAreas(101);
    expect($areas)->toBeArray()->and(count($areas))->toBeGreaterThan(0)
        ->and(Cache::has('pathao_areas_101'))->toBeTrue();
});

test('3. pos sale creates courier order with correct cod collection amount for full cod and partial payments', function () {
    Queue::fake();

    // A: Full Cash on Delivery (0 upfront payment) -> cod_amount = 5000 + 100 delivery = 5100
    $responseCod = $this->actingAs($this->staff)->postJson('/sales', [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'courier_mode' => 'pathao',
        'item_weight' => 0.5,
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 1, 'unit_price' => 5000],
        ],
        'delivery_charge' => 100,
        'paid_amount' => 0,
        'payment_method' => 'cod',
    ]);

    $responseCod->assertOk();
    $saleId = $responseCod->json('sale.id');
    $courierOrder = CourierOrder::where('sale_id', $saleId)->first();

    expect($courierOrder)->not->toBeNull()
        ->and((float) $courierOrder->cod_amount)->toBe(5100.0)
        ->and($courierOrder->status)->toBe('queued')
        ->and($courierOrder->item_type)->toBe(2); // Parcel

    Queue::assertPushed(DispatchPathaoOrderJob::class);

    // B: Partial payment upfront (1000 paid) -> cod_amount = 4100
    $responsePartial = $this->actingAs($this->staff)->postJson('/sales', [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'courier_mode' => 'pathao',
        'item_weight' => 0.5,
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 1, 'unit_price' => 5000],
        ],
        'delivery_charge' => 100,
        'paid_amount' => 1000,
        'payment_method' => 'bkash',
    ]);

    $responsePartial->assertOk();
    $salePartialId = $responsePartial->json('sale.id');
    $courierOrderPartial = CourierOrder::where('sale_id', $salePartialId)->first();

    expect((float) $courierOrderPartial->cod_amount)->toBe(4100.0);

    // C: Fully paid upfront (5100 paid) -> cod_amount = 0
    $responsePaid = $this->actingAs($this->staff)->postJson('/sales', [
        'idempotency_token' => (string) Str::uuid(),
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'courier_mode' => 'pathao',
        'item_weight' => 0.5,
        'items' => [
            ['product_id' => $this->product->id, 'name' => $this->product->name, 'qty' => 1, 'unit_price' => 5000],
        ],
        'delivery_charge' => 100,
        'paid_amount' => 5100,
        'payment_method' => 'bkash',
    ]);

    $responsePaid->assertOk();
    $salePaidId = $responsePaid->json('sale.id');
    $courierOrderPaid = CourierOrder::where('sale_id', $salePaidId)->first();

    expect((float) $courierOrderPaid->cod_amount)->toBe(0.0);
});

test('4. provider order dispatch saves consignment and creates exactly one linked courier expense', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0010',
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
        'subtotal' => 5000,
        'grand_total' => 5100,
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
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 5100,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);
    $success = $service->createOrder($courierOrder);

    expect($success)->toBeTrue();
    $courierOrder->refresh();

    expect($courierOrder->status)->toBe('created')
        ->and($courierOrder->consignment_id)->not->toBeNull()
        ->and((float) $courierOrder->courier_charge)->toBe(60.0);

    // Verify exactly one linked expense
    $expenses = Expense::where('courier_order_id', $courierOrder->id)
        ->where('source', 'pathao_charge')
        ->get();

    expect($expenses)->toHaveCount(1)
        ->and((float) $expenses->first()->amount)->toBe(60.0)
        ->and($expenses->first()->vendor)->toBe('Pathao');
});

test('5. retry does not duplicate order or create second courier expense', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0011',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 5000,
        'grand_total' => 5000,
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
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 5000,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);

    // First creation
    $service->createOrder($courierOrder);
    $courierOrder->refresh();
    $firstConsignment = $courierOrder->consignment_id;

    // Retry again
    $service->createOrder($courierOrder);
    $courierOrder->refresh();

    expect($courierOrder->consignment_id)->toBe($firstConsignment);

    // Check expense count remains strictly 1
    $expensesCount = Expense::where('courier_order_id', $courierOrder->id)
        ->where('source', 'pathao_charge')
        ->count();

    expect($expensesCount)->toBe(1);
});

test('6. failure preserves sale and invoice, records safe error and exposes retry', function () {
    // Configure client to trigger API error
    Config::set('services.pathao.client_id', 'test-client');
    Config::set('services.pathao.client_secret', 'test-secret');

    // Create a token to force calling the mock endpoint
    PathaoCourierToken::create([
        'access_token' => 'active-token',
        'token_type' => 'Bearer',
        'expires_in' => 86400,
    ]);

    Http::fake([
        '*/aladdin/api/v1/orders' => Http::response(['message' => 'Delivery address not serviceable'], 422),
    ]);

    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0012',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 5000,
        'grand_total' => 5000,
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
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 5000,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);
    $result = $service->createOrder($courierOrder);

    expect($result)->toBeFalse();
    $courierOrder->refresh();

    // Courier order is marked failed with safe error
    expect($courierOrder->status)->toBe('failed')
        ->and($courierOrder->last_error)->toContain('Delivery address not serviceable')
        ->and($courierOrder->consignment_id)->toBeNull();

    // Sale & invoice remain completely intact!
    $sale->refresh();
    expect($sale->status)->toBe('active')
        ->and($sale->invoice_no)->toBe('TASA-2610-0012');
});

test('7. status mapper maps provider states cleanly into canonical states', function () {
    expect(PathaoService::mapStatus('Delivered'))->toBe('delivered')
        ->and(PathaoService::mapStatus('Parcel Delivered to Customer'))->toBe('delivered')
        ->and(PathaoService::mapStatus('In Transit'))->toBe('in_transit')
        ->and(PathaoService::mapStatus('Out for delivery'))->toBe('in_transit')
        ->and(PathaoService::mapStatus('Picked up from merchant'))->toBe('picked_up')
        ->and(PathaoService::mapStatus('Assigned to rider'))->toBe('picked_up')
        ->and(PathaoService::mapStatus('Returned to merchant'))->toBe('returned')
        ->and(PathaoService::mapStatus('Failed delivery attempt'))->toBe('failed')
        ->and(PathaoService::mapStatus('Cancelled by merchant'))->toBe('cancelled');
});

test('8. owner can settle cod on delivered orders which updates sale without inflating gross revenue', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0013',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 5000,
        'grand_total' => 5000,
        'paid_total' => 0,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => $sale->invoice_no,
        'consignment_id' => 'PTH-DELIV-001',
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
        'cod_amount' => 5000,
        'status' => 'delivered', // Delivered!
        'delivered_at' => now(),
    ]);

    $service = app(PathaoService::class);
    $settled = $service->settleCod($courierOrder, $this->owner);

    expect($settled)->toBeTrue();
    $courierOrder->refresh();
    $sale->refresh();

    // Check settlement state
    expect($courierOrder->cod_settled_at)->not->toBeNull()
        ->and((float) $courierOrder->cod_settled_amount)->toBe(5000.0)
        ->and($courierOrder->cod_settled_by)->toBe($this->owner->id);

    // Check sale revenue was not modified, but paid_total and payment_status were updated
    expect((float) $sale->grand_total)->toBe(5000.0) // Gross sales revenue preserved!
        ->and((float) $sale->paid_total)->toBe(5000.0)
        ->and($sale->payment_status)->toBe('paid');

    // Check exactly one COD sale_payment row created
    $payments = SalePayment::where('sale_id', $sale->id)->get();
    expect($payments)->toHaveCount(1)
        ->and($payments->first()->method)->toBe('cod')
        ->and((float) $payments->first()->amount)->toBe(5000.0)
        ->and($payments->first()->courier_order_id)->toBe($courierOrder->id);

    // Attempting to settle again throws exception
    expect(fn () => $service->settleCod($courierOrder, $this->owner))
        ->toThrow(\Exception::class, 'COD collection for this order has already been settled.');
});

test('9. requirement 12: editing recipient, address or amount on order with active consignment is blocked with PRD message', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0014',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 5000,
        'grand_total' => 5000,
        'paid_total' => 0,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => $sale->invoice_no,
        'consignment_id' => 'PTH-EXISTING-99',
        'store_id' => 1,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => 'Original Customer',
        'recipient_phone' => '01711223344',
        'recipient_address' => 'House 1, Dhanmondi',
        'recipient_city' => 1,
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 5000,
        'status' => 'in_transit',
    ]);

    // Attempt to update recipient address on active consignment via PUT endpoint
    $response = $this->actingAs($this->owner)->put("/sales/courier/{$courierOrder->id}", [
        'recipient_address' => 'New Different Address, Gulshan',
    ]);

    $response->assertSessionHas('error');
    $errorMessage = session('error');

    expect($errorMessage)->toContain('Consignment already generated in Pathao')
        ->and($errorMessage)->toContain('Please update them directly in the Pathao Merchant Panel');

    // Unchanged in DB
    $courierOrder->refresh();
    expect($courierOrder->recipient_address)->toBe('House 1, Dhanmondi');
});

test('10. owner can access courier orders screen, test connection and view pathao settings', function () {
    $response = $this->actingAs($this->owner)->get('/sales/courier');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/CourierOrders')
            ->has('orders')
            ->has('connection')
            ->has('filters')
        );

    $settingsResponse = $this->actingAs($this->owner)->get('/settings/pathao');
    $settingsResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/PathaoSettings')
            ->has('settings')
            ->has('connection')
            ->has('stores')
        );

    $testConnResponse = $this->actingAs($this->owner)->getJson('/settings/pathao/test-connection');
    $testConnResponse->assertOk()
        ->assertJsonStructure(['success', 'message']);
});

test('11. provider timeout marks courier order as failed with safe error and leaves sale intact', function () {
    Config::set('services.pathao.client_id', 'test-client');
    Config::set('services.pathao.client_secret', 'test-secret');

    PathaoCourierToken::create([
        'access_token' => 'active-token',
        'token_type' => 'Bearer',
        'expires_in' => 86400,
    ]);

    Http::fake([
        '*/aladdin/api/v1/orders' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 12000 milliseconds'),
    ]);

    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0080',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 5000,
        'grand_total' => 5000,
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
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 5000,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);
    $result = $service->createOrder($courierOrder);

    expect($result)->toBeFalse();
    $courierOrder->refresh();

    expect($courierOrder->status)->toBe('failed')
        ->and($courierOrder->last_error)->toContain('timed out')
        ->and($courierOrder->consignment_id)->toBeNull();

    // Sale remains active and unaffected
    $sale->refresh();
    expect($sale->status)->toBe('active')
        ->and($sale->invoice_no)->toBe('TASA-2610-0080');
});

test('12. expired token with refresh token automatically refreshes token via oauth refresh grant', function () {
    Config::set('services.pathao.client_id', 'test-client');
    Config::set('services.pathao.client_secret', 'test-secret');

    // Create expired token record
    $token = PathaoCourierToken::create([
        'access_token' => 'expired-old-token',
        'refresh_token' => 'valid-refresh-token',
        'token_type' => 'Bearer',
        'expires_in' => 1,
    ]);
    // Force created_at to 2 days ago so isExpired() is true
    $token->created_at = now()->subDays(2);
    $token->save();

    expect($token->isExpired())->toBeTrue();

    Http::fake([
        '*/aladdin/api/v1/issue-token' => Http::response([
            'access_token' => 'brand-new-refreshed-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 2592000,
            'token_type' => 'Bearer',
        ], 200),
    ]);

    $service = app(PathaoService::class);
    $freshAccessToken = $service->getToken();

    expect($freshAccessToken)->toBe('brand-new-refreshed-token');

    $latestToken = PathaoCourierToken::latest('id')->first();
    expect($latestToken->access_token)->toBe('brand-new-refreshed-token')
        ->and($latestToken->isExpired())->toBeFalse();
});

test('13. retry backoff halts after maximum 3 attempts and protects provider from flooding', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0081',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2500,
        'grand_total' => 2500,
        'paid_total' => 0,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => 'TASA-2610-0081',
        'store_id' => 1,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => 'Retry Customer',
        'recipient_phone' => '01711223344',
        'recipient_address' => 'House 5, Road 2, Dhanmondi',
        'recipient_city' => 1,
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2500,
        'attempts' => 3, // Already reached maximum attempts!
        'status' => 'failed',
    ]);

    $service = app(PathaoService::class);
    $result = $service->createOrder($courierOrder);

    expect($result)->toBeFalse();
    $courierOrder->refresh();

    expect($courierOrder->attempts)->toBe(3)
        ->and($courierOrder->status)->toBe('failed')
        ->and($courierOrder->last_error)->toContain('Exceeded maximum retry attempts');
});

test('14. field validation error extraction formats nested Pathao error dictionary safely without leaking credentials', function () {
    Config::set('services.pathao.client_id', 'test-client');
    Config::set('services.pathao.client_secret', 'test-secret');

    PathaoCourierToken::create([
        'access_token' => 'active-token',
        'token_type' => 'Bearer',
        'expires_in' => 86400,
    ]);

    Http::fake([
        '*/aladdin/api/v1/orders' => Http::response([
            'message' => 'Please fix the given errors',
            'errors' => [
                'recipient_zone' => ['This zone does not belong to this city'],
            ],
        ], 422),
    ]);

    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0082',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 3000,
        'grand_total' => 3000,
        'paid_total' => 0,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => 'TASA-2610-0082',
        'store_id' => 67938,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => 'Ctg Customer',
        'recipient_phone' => '01812345678',
        'recipient_address' => 'Halishahar Housing Estate, Chittagong',
        'recipient_city' => 2,
        'recipient_zone' => 202, // Mismatched zone
        'recipient_area' => 20202,
        'cod_amount' => 3000,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);
    $result = $service->createOrder($courierOrder);

    expect($result)->toBeFalse();
    $courierOrder->refresh();

    // Verify extracted field error format and that credentials are not leaked
    expect($courierOrder->status)->toBe('failed')
        ->and($courierOrder->last_error)->toContain('recipient_zone: This zone does not belong to this city')
        ->and($courierOrder->last_error)->not->toContain('test-secret')
        ->and($courierOrder->last_error)->not->toContain('active-token');
});

test('15. 401 unauthorized response triggers token refresh and transparently succeeds on second attempt', function () {
    Config::set('services.pathao.client_id', 'test-client');
    Config::set('services.pathao.client_secret', 'test-secret');

    // Token exists but is stale on server side
    PathaoCourierToken::create([
        'access_token' => 'stale-token',
        'refresh_token' => 'valid-refresh-token',
        'token_type' => 'Bearer',
        'expires_in' => 86400,
    ]);

    $callCount = 0;
    Http::fake([
        '*/aladdin/api/v1/issue-token' => Http::response([
            'access_token' => 'new-fresh-token',
            'refresh_token' => 'renewed-refresh-token',
            'expires_in' => 86400,
            'token_type' => 'Bearer',
        ], 200),
        '*/aladdin/api/v1/orders' => function ($request) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // First call: 401 Unauthorized
                return Http::response(['message' => 'Unauthenticated.'], 401);
            }
            // Second call with new token: 200 Success
            return Http::response([
                'type' => 'success',
                'code' => 200,
                'message' => 'Order created successfully',
                'data' => [
                    'consignment_id' => 'PTH-REFRESHED-OK',
                    'merchant_order_id' => 'TASA-2610-0083',
                    'order_status' => 'Pending',
                    'delivery_fee' => 70,
                ],
            ], 200);
        },
    ]);

    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0083',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 2000,
        'grand_total' => 2000,
        'paid_total' => 0,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => 'TASA-2610-0083',
        'store_id' => 67938,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => 'Refresh Customer',
        'recipient_phone' => '01711223344',
        'recipient_address' => 'House 12, Road 4, Dhanmondi',
        'recipient_city' => 1,
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 2000,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);
    $result = $service->createOrder($courierOrder);

    expect($result)->toBeTrue();
    $courierOrder->refresh();

    expect($courierOrder->status)->toBe('created')
        ->and($courierOrder->consignment_id)->toBe('PTH-REFRESHED-OK')
        ->and((float) $courierOrder->courier_charge)->toBe(70.0);
});

test('16. pre-dispatch validation rejects invalid store or location before making external HTTP call', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0084',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 1500,
        'grand_total' => 1500,
        'paid_total' => 0,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => 'TASA-2610-0084',
        'store_id' => 0, // Missing store
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => 'Bad Data Customer',
        'recipient_phone' => '12345', // Invalid phone
        'recipient_address' => 'Too short', // Under 10 chars
        'recipient_city' => 0,
        'recipient_zone' => 0,
        'recipient_area' => 0,
        'cod_amount' => 1500,
        'status' => 'queued',
    ]);

    $service = app(PathaoService::class);
    $result = $service->createOrder($courierOrder);

    expect($result)->toBeFalse();
    $courierOrder->refresh();

    expect($courierOrder->status)->toBe('failed')
        ->and($courierOrder->last_error)->toContain('Pre-dispatch validation failed');
});

test('17. manual retry resets attempts to 0, clears last error, and successfully creates consignment after operator corrects data', function () {
    Config::set('services.pathao.client_id', 'test-client');
    Config::set('services.pathao.client_secret', 'test-secret');

    Queue::fake([DispatchPathaoOrderJob::class]);

    PathaoCourierToken::create([
        'access_token' => 'active-token',
        'token_type' => 'Bearer',
        'expires_in' => 86400,
    ]);

    Http::fake([
        '*/aladdin/api/v1/orders' => Http::response([
            'type' => 'success',
            'code' => 200,
            'message' => 'Order created successfully',
            'data' => [
                'consignment_id' => 'PTH-RETRY-SUCCESS-99',
                'merchant_order_id' => 'TASA-2610-0085',
                'order_status' => 'Pending',
                'delivery_fee' => 60,
            ],
        ], 200),
    ]);

    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0085',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 1200,
        'grand_total' => 1200,
        'paid_total' => 0,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    // Order previously failed with exhausted attempts
    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => 'TASA-2610-0085',
        'store_id' => 1,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_name' => 'Operator Edited Customer',
        'recipient_phone' => '01812345678',
        'recipient_address' => 'Halishahar Block A, Road 2, Chittagong',
        'recipient_city' => 2,
        'recipient_zone' => 75,
        'recipient_area' => 5240,
        'cod_amount' => 1200,
        'attempts' => 3, // Exhausted
        'status' => 'failed',
        'last_error' => 'Exceeded maximum retry attempts (3). Please check address details.',
    ]);

    // Operator hits the Retry endpoint
    $response = $this->actingAs($this->owner)->post("/sales/courier/{$courierOrder->id}/retry");
    $response->assertRedirect();

    $courierOrder->refresh();
    // Verify attempts were reset to 0, status reset to queued, and error cleared
    expect($courierOrder->attempts)->toBe(0)
        ->and($courierOrder->status)->toBe('queued')
        ->and($courierOrder->last_error)->toBeNull();

    Queue::assertPushed(DispatchPathaoOrderJob::class);

    // Now dispatch runs (e.g. queue worker or service)
    $service = app(PathaoService::class);
    $result = $service->createOrder($courierOrder);

    expect($result)->toBeTrue();
    $courierOrder->refresh();

    expect($courierOrder->status)->toBe('created')
        ->and($courierOrder->consignment_id)->toBe('PTH-RETRY-SUCCESS-99')
        ->and((float) $courierOrder->courier_charge)->toBe(60.0);
});

