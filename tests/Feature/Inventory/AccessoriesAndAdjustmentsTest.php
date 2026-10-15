<?php

use App\Models\AccessoryConsumption;
use App\Models\AccessoryItem;
use App\Models\AccessoryPurchase;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\LeatherItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Inventory\AccessoryService;
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

test('owner can view accessory items with full stock metrics, units, and weighted average cost', function () {
    $item = AccessoryItem::create([
        'name' => 'Antique Brass Zipper #5',
        'unit' => 'pcs',
        'min_stock' => 100.000,
        'is_active' => true,
    ]);

    AccessoryPurchase::create([
        'accessory_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'supplier' => 'YKK Bangladesh Ltd',
        'qty' => 200.000,
        'rate' => 35.00,
        'total_cost' => 7000.00,
        'paid_amount' => 7000.00,
        'payment_method' => 'cash',
    ]);

    $response = $this->actingAs($this->owner)->get('/inventory/accessories');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Accessories')
        ->has('stockItems', 1)
        ->where('stockItems.0.name', 'Antique Brass Zipper #5')
        ->where('stockItems.0.unit', 'pcs')
        ->where('stockItems.0.current_stock_qty', 200)
        ->where('stockItems.0.avg_cost', 35)
        ->where('stockItems.0.stock_value', 7000)
        ->where('canViewCost', true)
        ->where('canManage', true)
        ->where('canAdjust', true)
    );
});

test('staff CANNOT see accessory purchase rates, total costs, avg cost, or stock value', function () {
    $item = AccessoryItem::create([
        'name' => 'Nylon Bonded Thread Tex 90',
        'unit' => 'spool',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    AccessoryPurchase::create([
        'accessory_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'supplier' => 'Coats Bangladesh',
        'qty' => 50.000,
        'rate' => 120.00,
        'total_cost' => 6000.00,
        'paid_amount' => 6000.00,
        'payment_method' => 'bkash',
    ]);

    $response = $this->actingAs($this->staff)->get('/inventory/accessories');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Accessories')
        ->where('canViewCost', false)
        ->where('stockItems.0.avg_cost', null)
        ->where('stockItems.0.stock_value', null)
    );

    $props = $response->viewData('page')['props'];
    expect($props['canViewCost'])->toBeFalse();
    expect($props['stockItems'][0]['avg_cost'])->toBeNull();
    expect($props['stockItems'][0]['stock_value'])->toBeNull();

    // Check purchases tab props for staff
    $purchasesResponse = $this->actingAs($this->staff)->get('/inventory/accessories?tab=purchases');
    $purchasesResponse->assertOk();
    $purchasesProps = $purchasesResponse->viewData('page')['props'];
    expect($purchasesProps['canViewCost'])->toBeFalse();
    if (! empty($purchasesProps['purchases']['data'])) {
        expect($purchasesProps['purchases']['data'][0]['rate'])->toBeNull();
        expect($purchasesProps['purchases']['data'][0]['total_cost'])->toBeNull();
    }
});

test('staff WITH special permission inventory.cost.view CAN see accessory costs and rates', function () {
    $this->staff->givePermissionTo('inventory.cost.view');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $item = AccessoryItem::create([
        'name' => 'Solid Brass Roller Buckle 35mm',
        'unit' => 'pcs',
        'min_stock' => 50.000,
        'is_active' => true,
    ]);

    AccessoryPurchase::create([
        'accessory_item_id' => $item->id,
        'purchase_date' => '2026-10-02',
        'supplier' => 'Hardware Hub',
        'qty' => 100.000,
        'rate' => 85.00,
        'total_cost' => 8500.00,
        'paid_amount' => 8500.00,
        'payment_method' => 'bank_transfer',
    ]);

    $response = $this->actingAs($this->staff)->get('/inventory/accessories');
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Accessories')
        ->where('canViewCost', true)
        ->where('stockItems.0.avg_cost', 85)
        ->where('stockItems.0.stock_value', 8500)
    );
});

test('can create accessory item with Bangla name and custom unit', function () {
    $banglaName = 'প্রিমিয়াম পিতলের বাকল ৩৫ মিমি';
    $unit = 'সেট';

    $response = $this->actingAs($this->owner)->post('/inventory/accessories/items', [
        'name' => $banglaName,
        'unit' => $unit,
        'min_stock' => 25.500,
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('accessory_items', [
        'name' => $banglaName,
        'unit' => $unit,
        'min_stock' => 25.500,
        'is_active' => 1,
    ]);
});

test('enforces unique name for accessory items', function () {
    AccessoryItem::create([
        'name' => 'Magnetic Snap Button 18mm',
        'unit' => 'pcs',
        'min_stock' => 100.000,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)->post('/inventory/accessories/items', [
        'name' => 'Magnetic Snap Button 18mm',
        'unit' => 'set',
        'min_stock' => 50.000,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('multiple purchase rates prove weighted average cost for accessories accurately', function () {
    $item = AccessoryItem::create([
        'name' => 'Reinforced Rivets 9mm',
        'unit' => 'pcs',
        'min_stock' => 500.000,
        'is_active' => true,
    ]);

    // Purchase 1: 1000 pcs @ ৳2.50 = ৳2,500.00
    $p1 = $this->actingAs($this->owner)->post('/inventory/accessories/purchases', [
        'accessory_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'supplier' => 'Fastener Corp',
        'qty' => 1000.000,
        'rate' => 2.50,
        'paid_amount' => 2500.00,
        'payment_method' => 'cash',
        'note' => 'First batch rivets',
    ]);
    $p1->assertRedirect();

    // Purchase 2: 2000 pcs @ ৳4.00 = ৳8,000.00
    $p2 = $this->actingAs($this->owner)->post('/inventory/accessories/purchases', [
        'accessory_item_id' => $item->id,
        'purchase_date' => '2026-10-02',
        'supplier' => 'Fastener Corp',
        'qty' => 2000.000,
        'rate' => 4.00,
        'paid_amount' => 8000.00,
        'payment_method' => 'bank_transfer',
        'note' => 'Second batch rivets',
    ]);
    $p2->assertRedirect();

    // Expected:
    // Total purchased qty = 3000 pcs
    // Total purchase cost = 2,500 + 8,000 = 10,500.00
    // Weighted avg cost = 10,500 / 3000 = 3.5000
    // Stock value = 3000 * 3.50 = 10,500.00
    $accessoryService = app(AccessoryService::class);
    $stockReport = $accessoryService->getStockReport();
    $itemStock = $stockReport->firstWhere('id', $item->id);

    expect((float) $itemStock['current_stock_qty'])->toBe(3000.0);
    expect((float) $itemStock['avg_cost'])->toBe(3.50);
    expect((float) $itemStock['stock_value'])->toBe(10500.0);

    // Verify against direct MySQL view query (v_accessory_stock)
    $viewRow = DB::table('v_accessory_stock')->where('id', $item->id)->first();
    expect($viewRow)->not->toBeNull();
    expect((float) $viewRow->current_stock)->toBe(3000.0);
    expect((float) $viewRow->avg_cost)->toBe(3.50);
    expect((float) $viewRow->stock_value)->toBe(10500.0);
});

test('accessory purchase does NOT create any row in expenses table (BR-5)', function () {
    $item = AccessoryItem::create([
        'name' => 'Edge Dye Black 1L',
        'unit' => 'bottle',
        'min_stock' => 5.000,
        'is_active' => true,
    ]);

    expect(Expense::count())->toBe(0);

    $this->actingAs($this->owner)->post('/inventory/accessories/purchases', [
        'accessory_item_id' => $item->id,
        'purchase_date' => '2026-10-03',
        'supplier' => 'Fenice Chemicals',
        'qty' => 10.000,
        'rate' => 1500.00,
        'paid_amount' => 15000.00,
        'payment_method' => 'bank_transfer',
    ]);

    // BR-5: Purchases are never recorded as operating expenses
    expect(Expense::count())->toBe(0);
});

test('accessory manual consumption decrements stock without creating expense (BR-7)', function () {
    $item = AccessoryItem::create([
        'name' => 'Keyring Swivel Hooks',
        'unit' => 'pcs',
        'min_stock' => 100.000,
        'is_active' => true,
    ]);

    AccessoryPurchase::create([
        'accessory_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'qty' => 500.000,
        'rate' => 15.00,
        'total_cost' => 7500.00,
        'paid_amount' => 7500.00,
        'payment_method' => 'cash',
    ]);

    expect(Expense::count())->toBe(0);

    // Consume 150 pcs
    $res = $this->actingAs($this->owner)->post('/inventory/accessories/consumptions', [
        'accessory_item_id' => $item->id,
        'consumption_date' => '2026-10-04',
        'qty' => 150.000,
        'note' => 'Used for 150 key fobs',
    ]);
    $res->assertRedirect();

    $accessoryService = app(AccessoryService::class);
    expect($accessoryService->getCurrentStock($item->id))->toBe(350.0);

    // Preserves average cost and updates valuation
    $report = $accessoryService->getStockReport();
    $reportItem = $report->firstWhere('id', $item->id);
    expect((float) $reportItem['avg_cost'])->toBe(15.0);
    expect((float) $reportItem['stock_value'])->toBe(5250.0); // 350 * 15

    // BR-7: Consumption does not create an expense row
    expect(Expense::count())->toBe(0);
});

test('accessory negative stock is blocked by default but permitted when setting is 1', function () {
    Setting::set('inventory.allow_negative_stock', '0');

    $item = AccessoryItem::create([
        'name' => 'Cotton Canvas Lining',
        'unit' => 'meter',
        'min_stock' => 20.000,
        'is_active' => true,
    ]);

    AccessoryPurchase::create([
        'accessory_item_id' => $item->id,
        'purchase_date' => '2026-10-01',
        'qty' => 25.000,
        'rate' => 80.00,
        'total_cost' => 2000.00,
        'paid_amount' => 2000.00,
    ]);

    // Attempting to consume 30m when 25m in stock -> blocked
    $resBlocked = $this->actingAs($this->owner)->post('/inventory/accessories/consumptions', [
        'accessory_item_id' => $item->id,
        'consumption_date' => '2026-10-05',
        'qty' => 30.000,
        'note' => 'Excess usage attempt',
    ]);
    $resBlocked->assertSessionHasErrors(['qty']);
    expect(AccessoryConsumption::count())->toBe(0);

    // Enable negative stock setting
    Setting::set('inventory.allow_negative_stock', '1');

    $resAllowed = $this->actingAs($this->owner)->post('/inventory/accessories/consumptions', [
        'accessory_item_id' => $item->id,
        'consumption_date' => '2026-10-05',
        'qty' => 30.000,
        'note' => 'Allowed negative usage',
    ]);
    $resAllowed->assertRedirect();
    expect(AccessoryConsumption::count())->toBe(1);

    $accessoryService = app(AccessoryService::class);
    expect($accessoryService->getCurrentStock($item->id))->toBe(-5.0);
});

test('shared stock adjustments system handles all three item types correctly', function () {
    // 1. Finished Product
    $category = ProductCategory::create(['name' => 'Cardholders']);
    $product = Product::create([
        'product_category_id' => $category->id,
        'name' => 'Minimalist Cardholder',
        'sku' => 'P-000101',
        'selling_price' => 850.00,
        'cost_price' => 400.00,
        'stock_qty' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    // 2. Raw Leather
    $leather = LeatherItem::create([
        'name' => 'Crazy Horse Tan',
        'colour_grade' => 'Grade-A',
        'min_stock' => 15.000,
        'is_active' => true,
    ]);

    // 3. Accessory
    $accessory = AccessoryItem::create([
        'name' => 'Heavy Duty D-Ring 25mm',
        'unit' => 'pcs',
        'min_stock' => 50.000,
        'is_active' => true,
    ]);

    // Adjust Product (-3 units damaged)
    $resProd = $this->actingAs($this->owner)->post('/inventory/adjustments', [
        'item_type' => 'product',
        'item_id' => $product->id,
        'qty' => -3,
        'reason' => 'damaged',
        'adjusted_on' => '2026-10-06',
        'note' => 'Damaged in transit',
    ]);
    $resProd->assertRedirect();

    $product->refresh();
    expect($product->stock_qty)->toBe(17);
    $prodMovement = ProductStockMovement::where('product_id', $product->id)->where('type', 'adjustment')->first();
    expect($prodMovement)->not->toBeNull();
    expect($prodMovement->qty)->toBe(-3);
    expect($prodMovement->balance_after)->toBe(17);

    // Adjust Leather (+12.500 sq ft count correction)
    $resLeather = $this->actingAs($this->owner)->post('/inventory/adjustments', [
        'item_type' => 'leather',
        'item_id' => $leather->id,
        'qty' => 12.500,
        'reason' => 'count_correction',
        'adjusted_on' => '2026-10-06',
        'note' => 'Stock recount audit',
    ]);
    $resLeather->assertRedirect();

    $leatherService = app(LeatherService::class);
    expect($leatherService->getCurrentStock($leather->id))->toBe(12.5);

    // Adjust Accessory (-15 pcs lost)
    Setting::set('inventory.allow_negative_stock', '1');
    $resAcc = $this->actingAs($this->owner)->post('/inventory/adjustments', [
        'item_type' => 'accessory',
        'item_id' => $accessory->id,
        'qty' => -15.000,
        'reason' => 'lost',
        'adjusted_on' => '2026-10-06',
        'note' => 'Missing from workshop bin',
    ]);
    $resAcc->assertRedirect();

    $accessoryService = app(AccessoryService::class);
    expect($accessoryService->getCurrentStock($accessory->id))->toBe(-15.0);

    // Verify all adjustments recorded in stock_adjustments table and audited
    expect(StockAdjustment::where('item_type', 'product')->count())->toBe(1);
    expect(StockAdjustment::where('item_type', 'leather')->count())->toBe(1);
    expect(StockAdjustment::where('item_type', 'accessory')->count())->toBe(1);
    expect(AuditLog::count())->toBeGreaterThanOrEqual(3);
});

test('shared stock adjustments safely rejects non-existent item IDs', function () {
    $resInvalidProd = $this->actingAs($this->owner)->post('/inventory/adjustments', [
        'item_type' => 'product',
        'item_id' => 999999,
        'qty' => 5,
        'reason' => 'count_correction',
        'adjusted_on' => '2026-10-06',
    ]);
    $resInvalidProd->assertSessionHasErrors(['item_id']);

    $resInvalidLeather = $this->actingAs($this->owner)->post('/inventory/adjustments', [
        'item_type' => 'leather',
        'item_id' => 888888,
        'qty' => 5,
        'reason' => 'count_correction',
        'adjusted_on' => '2026-10-06',
    ]);
    $resInvalidLeather->assertSessionHasErrors(['item_id']);

    $resInvalidAcc = $this->actingAs($this->owner)->post('/inventory/adjustments', [
        'item_type' => 'accessory',
        'item_id' => 777777,
        'qty' => 5,
        'reason' => 'count_correction',
        'adjusted_on' => '2026-10-06',
    ]);
    $resInvalidAcc->assertSessionHasErrors(['item_id']);
});

test('requires note when adjustment reason is other', function () {
    $item = AccessoryItem::create([
        'name' => 'Zipper Pull Tabs',
        'unit' => 'pcs',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    $res = $this->actingAs($this->owner)->post('/inventory/adjustments', [
        'item_type' => 'accessory',
        'item_id' => $item->id,
        'qty' => -2,
        'reason' => 'other',
        'adjusted_on' => '2026-10-06',
        'note' => '', // blank note for other
    ]);

    $res->assertSessionHasErrors(['note']);
});

test('global inventory search endpoint returns matching products, leather, and accessories', function () {
    Product::create([
        'name' => 'Executive Leather Briefcase',
        'sku' => 'P-000888',
        'selling_price' => 5500.00,
        'cost_price' => 3000.00,
        'stock_qty' => 5,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    LeatherItem::create([
        'name' => 'Executive Pull-Up Hide',
        'colour_grade' => 'Dark Cognac',
        'min_stock' => 50.000,
        'is_active' => true,
    ]);

    AccessoryItem::create([
        'name' => 'Executive Briefcase Lock Clasp',
        'unit' => 'set',
        'min_stock' => 10.000,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)->getJson('/inventory/search?q=Executive');
    $response->assertOk();

    $results = $response->json('results');
    expect(count($results))->toBe(3);

    $types = collect($results)->pluck('type')->all();
    expect($types)->toContain('product');
    expect($types)->toContain('leather');
    expect($types)->toContain('accessory');
});
