<?php

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\LeatherConsumption;
use App\Models\LeatherItem;
use App\Models\LeatherPurchase;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Inventory\LeatherService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
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

test('owner can view leather items with full stock metrics and weighted average cost', function () {
    $item = LeatherItem::create([
        'name' => 'Full Grain Buffalo',
        'colour_grade' => 'Dark Brown Grade-A',
        'min_stock' => 50.000,
        'is_active' => true,
    ]);

    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'supplier' => 'Dhaka Tannery Ltd',
        'qty_sqft' => 100.000,
        'rate_per_sqft' => 150.00,
        'total_cost' => 15000.00,
        'paid_amount' => 15000.00,
        'payment_method' => 'cash',
    ]);

    $response = $this->actingAs($this->owner)->get('/inventory/leather');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Leather')
        ->has('stockItems', 1)
        ->where('stockItems.0.name', 'Full Grain Buffalo')
        ->where('stockItems.0.colour_grade', 'Dark Brown Grade-A')
        ->where('stockItems.0.current_stock_sqft', 100)
        ->where('stockItems.0.avg_cost', 150)
        ->where('stockItems.0.stock_value', 15000)
        ->where('canViewCost', true)
        ->where('canManage', true)
        ->where('canAdjust', true)
    );
});

test('staff CANNOT see purchase rates, total costs, avg cost, or stock value in leather props or API', function () {
    $item = LeatherItem::create([
        'name' => 'Chrome Tan Cowhide',
        'colour_grade' => 'Black',
        'min_stock' => 20.000,
        'is_active' => true,
    ]);

    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'supplier' => 'Savar Leathers',
        'qty_sqft' => 200.000,
        'rate_per_sqft' => 180.00,
        'total_cost' => 36000.00,
        'paid_amount' => 36000.00,
        'payment_method' => 'bank_transfer',
    ]);

    $response = $this->actingAs($this->staff)->get('/inventory/leather');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Leather')
        ->has('stockItems', 1)
        ->where('canViewCost', false)
        ->where('stockItems.0.avg_cost', null)
        ->where('stockItems.0.stock_value', null)
    );

    $props = $response->viewData('page')['props'];
    expect($props['canViewCost'])->toBeFalse();
    expect($props['stockItems'][0]['avg_cost'])->toBeNull();
    expect($props['stockItems'][0]['stock_value'])->toBeNull();

    // Check purchases tab props for staff
    $purchasesResponse = $this->actingAs($this->staff)->get('/inventory/leather?tab=purchases');
    $purchasesResponse->assertOk();
    $purchasesProps = $purchasesResponse->viewData('page')['props'];
    expect($purchasesProps['canViewCost'])->toBeFalse();
    if (! empty($purchasesProps['purchases']['data'])) {
        expect($purchasesProps['purchases']['data'][0]['rate_per_sqft'])->toBeNull();
        expect($purchasesProps['purchases']['data'][0]['total_cost'])->toBeNull();
    }

    // Check movements endpoint for staff
    $movementsResponse = $this->actingAs($this->staff)->getJson("/inventory/leather/{$item->id}/movements");
    $movementsResponse->assertOk();
    $movements = $movementsResponse->json('movements');
    expect($movements)->not->toBeEmpty();
    // Rates and costs are never exposed in movement ledger
    expect(isset($movements[0]['rate_per_sqft']))->toBeFalse();
    expect(isset($movements[0]['total_cost']))->toBeFalse();
});

test('staff WITH special permission inventory.cost.view CAN see rates and costs', function () {
    $this->staff->givePermissionTo('inventory.cost.view');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $item = LeatherItem::create([
        'name' => 'Vegetable Tanned Leather',
        'colour_grade' => 'Natural',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-02',
        'supplier' => 'Hazaribagh Exports',
        'qty_sqft' => 50.000,
        'rate_per_sqft' => 220.00,
        'total_cost' => 11000.00,
        'paid_amount' => 11000.00,
        'payment_method' => 'bkash',
    ]);

    $response = $this->actingAs($this->staff)->get('/inventory/leather');
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Leather')
        ->where('canViewCost', true)
        ->where('stockItems.0.avg_cost', 220)
        ->where('stockItems.0.stock_value', 11000)
    );
});

test('can create leather item with Bangla and Unicode characters', function () {
    $banglaName = 'প্রিমিয়াম গরুর চামড়া (ট্যান)';
    $banglaGrade = 'গ্রেড-এ ফিনিশড';

    $response = $this->actingAs($this->owner)->post('/inventory/leather/items', [
        'name' => $banglaName,
        'colour_grade' => $banglaGrade,
        'min_stock' => 45.500,
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('leather_items', [
        'name' => $banglaName,
        'colour_grade' => $banglaGrade,
        'min_stock' => 45.500,
        'is_active' => 1,
    ]);
});

test('validates unique name and colour_grade combination', function () {
    LeatherItem::create([
        'name' => 'Nappa Leather',
        'colour_grade' => 'Black',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    // Same name, same grade -> should fail
    $response = $this->actingAs($this->owner)->post('/inventory/leather/items', [
        'name' => 'Nappa Leather',
        'colour_grade' => 'Black',
        'min_stock' => 15.000,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['name']);

    // Same name, different grade -> should succeed
    $responseOk = $this->actingAs($this->owner)->post('/inventory/leather/items', [
        'name' => 'Nappa Leather',
        'colour_grade' => 'Cognac',
        'min_stock' => 15.000,
        'is_active' => true,
    ]);

    $responseOk->assertRedirect();
    $this->assertDatabaseHas('leather_items', [
        'name' => 'Nappa Leather',
        'colour_grade' => 'Cognac',
    ]);
});

test('multiple purchase rates prove weighted average cost calculation accurately', function () {
    $item = LeatherItem::create([
        'name' => 'Split Suede',
        'colour_grade' => 'Navy',
        'min_stock' => 50.000,
        'is_active' => true,
    ]);

    // Purchase 1: 100.000 sq ft @ ৳150.00 = ৳15,000.00
    $p1 = $this->actingAs($this->owner)->post('/inventory/leather/purchases', [
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'supplier' => 'Supplier Alpha',
        'qty_sqft' => 100.000,
        'rate_per_sqft' => 150.00,
        'paid_amount' => 15000.00,
        'payment_method' => 'cash',
        'note' => 'First batch',
    ]);
    $p1->assertRedirect();

    // Purchase 2: 200.000 sq ft @ ৳180.00 = ৳36,000.00
    $p2 = $this->actingAs($this->owner)->post('/inventory/leather/purchases', [
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-02',
        'supplier' => 'Supplier Beta',
        'qty_sqft' => 200.000,
        'rate_per_sqft' => 180.00,
        'paid_amount' => 20000.00,
        'payment_method' => 'bank_transfer',
        'note' => 'Second batch',
    ]);
    $p2->assertRedirect();

    // Expected:
    // Total purchased qty = 300.000 sq ft
    // Total purchase cost = 15,000 + 36,000 = 51,000.00
    // Weighted avg cost = 51,000 / 300 = 170.0000
    // Stock value = 300 * 170 = 51,000.00
    $leatherService = app(LeatherService::class);
    $stockReport = $leatherService->getStockReport();
    $itemStock = $stockReport->firstWhere('id', $item->id);

    expect((float) $itemStock['current_stock_sqft'])->toBe(300.0);
    expect((float) $itemStock['avg_cost'])->toBe(170.0);
    expect((float) $itemStock['stock_value'])->toBe(51000.0);

    // Verify against direct MySQL view query
    $viewRow = DB::table('v_leather_stock')->where('id', $item->id)->first();
    expect($viewRow)->not->toBeNull();
    expect((float) $viewRow->current_stock)->toBe(300.0);
    expect((float) $viewRow->avg_cost)->toBe(170.0);
    expect((float) $viewRow->stock_value)->toBe(51000.0);
});

test('purchase does NOT create any row in expenses table (BR-5)', function () {
    $item = LeatherItem::create([
        'name' => 'Crazy Horse Leather',
        'colour_grade' => 'Brown',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    expect(Expense::count())->toBe(0);

    $this->actingAs($this->owner)->post('/inventory/leather/purchases', [
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-03',
        'supplier' => 'Raw Materials Ltd',
        'qty_sqft' => 75.500,
        'rate_per_sqft' => 210.00,
        'paid_amount' => 10000.00,
        'payment_method' => 'bkash',
        'note' => 'Raw hide stock',
    ]);

    // BR-5: Purchase cash flow is shown only in P&L memo/cash summary; NEVER recorded as an expense
    expect(Expense::count())->toBe(0);
});

test('manual consumption decrements stock without creating expense (BR-7)', function () {
    $item = LeatherItem::create([
        'name' => 'Pull Up Leather',
        'colour_grade' => 'Cherry',
        'min_stock' => 20.000,
        'is_active' => true,
    ]);

    // Purchase 100 sq ft @ ৳150
    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'qty_sqft' => 100.000,
        'rate_per_sqft' => 150.00,
        'total_cost' => 15000.00,
        'paid_amount' => 15000.00,
        'payment_method' => 'cash',
    ]);

    expect(Expense::count())->toBe(0);

    // Consume 40 sq ft
    $response = $this->actingAs($this->owner)->post('/inventory/leather/consumptions', [
        'leather_item_id' => $item->id,
        'consumption_date' => '2026-10-04',
        'qty_sqft' => 40.000,
        'note' => 'Used for 20 messenger bags',
    ]);
    $response->assertRedirect();

    $leatherService = app(LeatherService::class);
    $currentStock = $leatherService->getCurrentStock($item->id);
    expect($currentStock)->toBe(60.0);

    // Weighted average cost is preserved, stock value is updated
    $stockReport = $leatherService->getStockReport();
    $reportItem = $stockReport->firstWhere('id', $item->id);
    expect((float) $reportItem['avg_cost'])->toBe(150.0);
    expect((float) $reportItem['stock_value'])->toBe(9000.0); // 60 * 150

    // BR-7: Consumption does not create a P&L expense
    expect(Expense::count())->toBe(0);
});

test('negative stock is blocked by default when inventory.allow_negative_stock is 0', function () {
    Setting::set('inventory.allow_negative_stock', '0');

    $item = LeatherItem::create([
        'name' => 'Oil Tanned Cow',
        'colour_grade' => 'Dark Tan',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'qty_sqft' => 30.000,
        'rate_per_sqft' => 120.00,
        'total_cost' => 3600.00,
        'paid_amount' => 3600.00,
    ]);

    // Try consuming 35 sq ft (exceeds 30)
    $response = $this->actingAs($this->owner)->post('/inventory/leather/consumptions', [
        'leather_item_id' => $item->id,
        'consumption_date' => '2026-10-05',
        'qty_sqft' => 35.000,
        'note' => 'Excess consumption attempt',
    ]);

    $response->assertSessionHasErrors(['qty_sqft']);
    expect(LeatherConsumption::count())->toBe(0);
});

test('negative stock is permitted when inventory.allow_negative_stock is 1', function () {
    Setting::set('inventory.allow_negative_stock', '1');

    $item = LeatherItem::create([
        'name' => 'Nubuck Leather',
        'colour_grade' => 'Olive',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'qty_sqft' => 20.000,
        'rate_per_sqft' => 140.00,
        'total_cost' => 2800.00,
        'paid_amount' => 2800.00,
    ]);

    // Consume 25 sq ft -> creates -5 sq ft stock
    $response = $this->actingAs($this->owner)->post('/inventory/leather/consumptions', [
        'leather_item_id' => $item->id,
        'consumption_date' => '2026-10-05',
        'qty_sqft' => 25.000,
        'note' => 'Allowed negative consumption',
    ]);

    $response->assertRedirect();
    expect(LeatherConsumption::count())->toBe(1);

    $leatherService = app(LeatherService::class);
    expect($leatherService->getCurrentStock($item->id))->toBe(-5.0);
});

test('stock adjustments affect current stock with signed quantities, reason, and audit log', function () {
    $item = LeatherItem::create([
        'name' => 'Bridle Leather',
        'colour_grade' => 'Havana',
        'min_stock' => 15.000,
        'is_active' => true,
    ]);

    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'qty_sqft' => 100.000,
        'rate_per_sqft' => 200.00,
        'total_cost' => 20000.00,
        'paid_amount' => 20000.00,
    ]);

    // Negative adjustment (damaged leather)
    $response1 = $this->actingAs($this->owner)->post('/inventory/leather/adjust-stock', [
        'leather_item_id' => $item->id,
        'qty' => -15.500,
        'adjusted_on' => '2026-10-05',
        'reason' => 'damaged',
        'note' => 'Water damage during transport',
    ]);
    $response1->assertRedirect();

    $adjustment1 = StockAdjustment::where('item_type', 'leather')
        ->where('item_id', $item->id)
        ->first();
    expect($adjustment1)->not->toBeNull();
    expect((float) $adjustment1->qty)->toBe(-15.500);
    expect($adjustment1->reason)->toBe('damaged');
    expect($adjustment1->created_by)->toBe($this->owner->id);

    $leatherService = app(LeatherService::class);
    expect($leatherService->getCurrentStock($item->id))->toBe(84.5);

    // Audit log verification
    expect(AuditLog::where('event', 'leather_adjustment')->exists())->toBeTrue();

    // Positive adjustment (found extra stock on physical count)
    $response2 = $this->actingAs($this->owner)->post('/inventory/leather/adjust-stock', [
        'leather_item_id' => $item->id,
        'qty' => 5.500,
        'adjusted_on' => '2026-10-06',
        'reason' => 'count_correction',
        'note' => 'Recount after stock audit',
    ]);
    $response2->assertRedirect();

    expect($leatherService->getCurrentStock($item->id))->toBe(90.0);
});

test('low-stock alert boundary and low-stock filtering works', function () {
    $item = LeatherItem::create([
        'name' => 'Patent Leather',
        'colour_grade' => 'Gloss Black',
        'min_stock' => 50.000,
        'is_active' => true,
    ]);

    // Initial stock: 60 sq ft (above min_stock 50)
    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'qty_sqft' => 60.000,
        'rate_per_sqft' => 160.00,
        'total_cost' => 9600.00,
        'paid_amount' => 9600.00,
    ]);

    $response = $this->actingAs($this->owner)->get('/inventory/leather?low_stock=1');
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Leather')
        ->has('stockItems', 0) // Not in low stock yet
    );

    // Consume 15 sq ft -> stock becomes 45 (below min_stock 50)
    LeatherConsumption::create([
        'leather_item_id' => $item->id,
        'consumption_date' => '2026-10-06',
        'qty_sqft' => 15.000,
    ]);

    $responseLow = $this->actingAs($this->owner)->get('/inventory/leather?low_stock=1');
    $responseLow->assertOk();
    $responseLow->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Leather')
        ->has('stockItems', 1)
        ->where('stockItems.0.id', $item->id)
        ->where('stockItems.0.is_low_stock', true)
        ->where('stockItems.0.current_stock_sqft', 45)
    );
});

test('date-range filter correctly scopes period purchases and consumptions', function () {
    $item = LeatherItem::create([
        'name' => 'Calfskin Leather',
        'colour_grade' => 'Burgundy',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    // Purchase in September
    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-09-15',
        'qty_sqft' => 100.000,
        'rate_per_sqft' => 200.00,
        'total_cost' => 20000.00,
        'paid_amount' => 20000.00,
    ]);

    // Purchase in October
    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-10',
        'qty_sqft' => 50.000,
        'rate_per_sqft' => 220.00,
        'total_cost' => 11000.00,
        'paid_amount' => 11000.00,
    ]);

    // Consumption in October
    LeatherConsumption::create([
        'leather_item_id' => $item->id,
        'consumption_date' => '2026-10-11',
        'qty_sqft' => 30.000,
    ]);

    // Query for October only
    $response = $this->actingAs($this->owner)->get('/inventory/leather?from=2026-10-01&to=2026-10-31');
    $response->assertOk();

    $stats = $response->viewData('page')['props']['stats'];
    // In October period:
    // period_purchased_sqft should be 50.000
    // period_purchased_cost should be 11000.00
    // period_consumed_sqft should be 30.000
    expect((float) $stats['period_purchased_sqft'])->toBe(50.0);
    expect((float) $stats['period_purchased_cost'])->toBe(11000.0);
    expect((float) $stats['period_consumed_sqft'])->toBe(30.0);
    // All-time current stock should still be 100 + 50 - 30 = 120.000
    expect((float) $stats['total_stock_sqft'])->toBe(120.0);
});

test('movement ledger endpoint returns chronological balance history', function () {
    $item = LeatherItem::create([
        'name' => 'Waxed Nubuck',
        'colour_grade' => 'Chestnut',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    LeatherPurchase::create([
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'supplier' => 'Apex Footwear Ltd',
        'qty_sqft' => 100.000,
        'rate_per_sqft' => 190.00,
        'total_cost' => 19000.00,
        'paid_amount' => 19000.00,
    ]);

    LeatherConsumption::create([
        'leather_item_id' => $item->id,
        'consumption_date' => '2026-10-03',
        'qty_sqft' => 25.000,
        'note' => 'Sample cutting',
    ]);

    StockAdjustment::create([
        'item_type' => 'leather',
        'item_id' => $item->id,
        'qty' => 5.000,
        'reason' => 'count_correction',
        'note' => 'Found 5 sqft remnant',
        'adjusted_on' => '2026-10-04',
        'created_by' => $this->owner->id,
    ]);

    $response = $this->actingAs($this->owner)->getJson("/inventory/leather/{$item->id}/movements");
    $response->assertOk();

    $movements = $response->json('movements');
    expect(count($movements))->toBe(3);

    // Movemens are returned newest first (reverse chronological)
    // Most recent is adjustment (+5) -> balance_after 80.0
    expect($movements[0]['type'])->toBe('adjustment');
    expect((float) $movements[0]['signed_qty'])->toBe(5.0);
    expect((float) $movements[0]['balance_after'])->toBe(80.0);

    // Second is consumption (-25) -> balance_after 75.0
    expect($movements[1]['type'])->toBe('consumption');
    expect((float) $movements[1]['signed_qty'])->toBe(-25.0);
    expect((float) $movements[1]['balance_after'])->toBe(75.0);

    // Earliest is purchase (+100) -> balance_after 100.0
    expect($movements[2]['type'])->toBe('purchase');
    expect((float) $movements[2]['signed_qty'])->toBe(100.0);
    expect((float) $movements[2]['balance_after'])->toBe(100.0);
});

test('purchase validation blocks invalid quantities, negative rates, or negative paid amounts', function () {
    $item = LeatherItem::create([
        'name' => 'Goat Leather',
        'colour_grade' => 'White',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    // Qty <= 0
    $res1 = $this->actingAs($this->owner)->post('/inventory/leather/purchases', [
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'qty_sqft' => 0,
        'rate_per_sqft' => 150.00,
        'paid_amount' => 0,
    ]);
    $res1->assertSessionHasErrors(['qty_sqft']);

    // Rate < 0
    $res2 = $this->actingAs($this->owner)->post('/inventory/leather/purchases', [
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'qty_sqft' => 10,
        'rate_per_sqft' => -50.00,
        'paid_amount' => 0,
    ]);
    $res2->assertSessionHasErrors(['rate_per_sqft']);

    // Paid < 0
    $res3 = $this->actingAs($this->owner)->post('/inventory/leather/purchases', [
        'leather_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'qty_sqft' => 10,
        'rate_per_sqft' => 150.00,
        'paid_amount' => -100.00,
    ]);
    $res3->assertSessionHasErrors(['paid_amount']);
});
