<?php

namespace Tests\Feature\Sales;

use App\Models\AuditLog;
use App\Models\CourierOrder;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Str;
use RuntimeException;
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
        'name' => 'Heritage Duffel Bag',
        'sku' => 'BAG-DUFFEL-01',
        'cost_price' => 3000.00,
        'selling_price' => 5000.00,
        'stock_qty' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'name' => 'আহমেদ জুবায়ের (Ahmed Zubair)',
        'phone' => '01811223344',
        'address' => 'Road 12, Sector 4, Uttara, Dhaka-1230',
        'pathao_city_id' => 1,
        'pathao_zone_id' => 105,
        'pathao_area_id' => 10501,
        'city_name' => 'Dhaka',
        'zone_name' => 'Uttara',
        'area_name' => 'Sector 4',
    ]);
});

test('it records additional payment and recalculates payment status and balance due', function () {
    // Create an active sale with 5000 grand total, 2000 initial paid, 3000 due
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0101',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => '2026-10-12',
        'sold_at' => now(),
        'subtotal' => 5000.00,
        'grand_total' => 5000.00,
        'paid_total' => 2000.00,
        'payment_status' => 'partial',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->staff->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'is_custom' => false,
        'name' => $this->product->name,
        'qty' => 1,
        'unit_price' => 5000.00,
        'unit_cost' => 3000.00,
        'line_total' => 5000.00,
    ]);

    SalePayment::create([
        'sale_id' => $sale->id,
        'paid_on' => '2026-10-12',
        'amount' => 2000.00,
        'method' => 'cash',
        'created_by' => $this->staff->id,
    ]);

    expect((float) $sale->net_due_amount)->toBe(3000.00);

    // 1. Staff adds partial payment of 1000
    $response = $this->actingAs($this->staff)->post("/sales/invoices/{$sale->id}/payments", [
        'amount' => 1000.00,
        'paid_on' => '2026-10-12',
        'method' => 'bkash',
        'reference' => 'BK-PAY-776655',
        'note' => 'Second installment via bKash',
    ]);

    $response->assertSessionHasNoErrors();
    $sale->refresh();

    expect((float) $sale->paid_total)->toBe(3000.00)
        ->and((float) $sale->due_amount)->toBe(2000.00)
        ->and((float) $sale->net_due_amount)->toBe(2000.00)
        ->and($sale->payment_status)->toBe('partial')
        ->and($sale->payments)->toHaveCount(2);

    // 2. Reject payment exceeding remaining balance due
    $excessResponse = $this->actingAs($this->staff)->post("/sales/invoices/{$sale->id}/payments", [
        'amount' => 2500.00, // exceeds remaining 2000
        'method' => 'cash',
    ]);
    $excessResponse->assertSessionHasErrors(['amount']);

    // 3. Clear remaining balance
    $finalResponse = $this->actingAs($this->staff)->post("/sales/invoices/{$sale->id}/payments", [
        'amount' => 2000.00,
        'method' => 'cash',
    ]);
    $finalResponse->assertSessionHasNoErrors();
    $sale->refresh();

    expect((float) $sale->paid_total)->toBe(5000.00)
        ->and((float) $sale->due_amount)->toBe(0.00)
        ->and($sale->payment_status)->toBe('paid');
});

test('SalePayment is append-only and throws RuntimeException on update or delete', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0102',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => '2026-10-12',
        'sold_at' => now(),
        'subtotal' => 2000.00,
        'grand_total' => 2000.00,
        'paid_total' => 2000.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
    ]);

    $payment = SalePayment::create([
        'sale_id' => $sale->id,
        'paid_on' => '2026-10-12',
        'amount' => 2000.00,
        'method' => 'cash',
    ]);

    // Updating must throw
    expect(fn () => $payment->update(['amount' => 1500.00]))
        ->toThrow(RuntimeException::class, 'Sale payments are append-only and cannot be updated.');

    // Deleting must throw
    expect(fn () => $payment->delete())
        ->toThrow(RuntimeException::class, 'Sale payments are append-only and cannot be deleted.');
});

test('Owner can cancel sale, restoring stock exactly once, auditing changes, and blocking second cancel', function () {
    // Initial product stock = 20
    $initialStock = $this->product->stock_qty;

    // Create sale with 3 bags sold (stock decreases to 17)
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0103',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => '2026-10-12',
        'sold_at' => now(),
        'subtotal' => 15000.00,
        'grand_total' => 15000.00,
        'paid_total' => 5000.00,
        'payment_status' => 'partial',
        'status' => 'active',
        'courier_mode' => 'none',
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'is_custom' => false,
        'name' => $this->product->name,
        'qty' => 3,
        'unit_price' => 5000.00,
        'unit_cost' => 3000.00,
        'line_total' => 15000.00,
    ]);

    // Decrement stock in catalog to simulate sale
    $this->product->update(['stock_qty' => 17]);
    ProductStockMovement::create([
        'product_id' => $this->product->id,
        'type' => 'sale',
        'qty' => -3,
        'balance_after' => 17,
        'reference_type' => 'sales',
        'reference_id' => $sale->id,
        'movement_date' => '2026-10-12',
    ]);

    // Customer due before cancel
    expect($this->customer->outstanding_due)->toBe(10000.00);

    // Staff lacks permission to cancel
    $staffCancel = $this->actingAs($this->staff)->post("/sales/invoices/{$sale->id}/cancel", [
        'reason' => 'Staff tried cancelling sale',
    ]);
    $staffCancel->assertStatus(403);

    // Owner cancels sale with valid reason
    $ownerCancel = $this->actingAs($this->owner)->post("/sales/invoices/{$sale->id}/cancel", [
        'reason' => 'Customer requested cancellation due to change of mind',
    ]);
    $ownerCancel->assertSessionHasNoErrors();

    $sale->refresh();
    $this->product->refresh();

    // Verify sale status and cancel attributes
    expect($sale->status)->toBe('cancelled')
        ->and($sale->cancel_reason)->toBe('Customer requested cancellation due to change of mind')
        ->and($sale->cancelled_by)->toBe($this->owner->id)
        ->and($sale->cancelled_at)->not->toBeNull();

    // Verify stock restored back to 20 exactly once
    expect($this->product->stock_qty)->toBe(20);

    // Verify product_stock_movements has sale_cancel entry
    $movement = ProductStockMovement::where('reference_type', 'sales')
        ->where('reference_id', $sale->id)
        ->where('type', 'sale_cancel')
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->qty)->toBe(3)
        ->and($movement->balance_after)->toBe(20);

    // Verify customer due after cancel becomes 0
    expect($this->customer->outstanding_due)->toBe(0.00);

    // Second cancel attempt must be rejected
    $duplicateCancel = $this->actingAs($this->owner)->post("/sales/invoices/{$sale->id}/cancel", [
        'reason' => 'Attempting duplicate cancellation',
    ]);
    $duplicateCancel->assertSessionHasErrors(['reason']);

    // Stock must still be 20 (not incremented again)
    $this->product->refresh();
    expect($this->product->stock_qty)->toBe(20);

    // Verify audit log
    $audit = AuditLog::where('auditable_type', Sale::class)
        ->where('event', 'sale_cancelled')
        ->latest('id')
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->new_values['status'])->toBe('cancelled');
});

test('cancellation warns if parcel is already dispatched with Pathao', function () {
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0104',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => '2026-10-12',
        'sold_at' => now(),
        'subtotal' => 5000.00,
        'grand_total' => 5000.00,
        'paid_total' => 0.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'is_custom' => false,
        'name' => $this->product->name,
        'qty' => 1,
        'unit_price' => 5000.00,
        'unit_cost' => 3000.00,
        'line_total' => 5000.00,
    ]);

    CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => $sale->invoice_no,
        'store_id' => 1,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'item_description' => 'Bag',
        'recipient_name' => $sale->customer_name,
        'recipient_phone' => $sale->customer_phone,
        'recipient_address' => $sale->customer_address,
        'recipient_city' => 1,
        'recipient_zone' => 105,
        'recipient_area' => 10501,
        'cod_amount' => 5000.00,
        'consignment_id' => 'PTH-DISPATCHED-0099',
        'status' => 'picked_up',
    ]);

    $response = $this->actingAs($this->owner)->post("/sales/invoices/{$sale->id}/cancel", [
        'reason' => 'Customer refused parcel at delivery',
    ]);

    // Session has warning about dispatched parcel
    $response->assertSessionHas('warning');
    $warning = session('warning');
    expect($warning)->toContain('PTH-DISPATCHED-0099')
        ->and($warning)->toContain('Pathao Merchant Panel');

    $sale->refresh();
    expect($sale->status)->toBe('cancelled');
});

test('it handles partial and full returns with restock, over-return prevention, and optional courier expense', function () {
    $this->product->update(['stock_qty' => 10]);

    // Sale with 4 duffel bags sold
    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0105',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => '2026-10-12',
        'sold_at' => now(),
        'subtotal' => 20000.00,
        'grand_total' => 20000.00,
        'paid_total' => 20000.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'pathao',
    ]);

    $saleItem = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->product->id,
        'is_custom' => false,
        'name' => $this->product->name,
        'qty' => 4,
        'unit_price' => 5000.00,
        'unit_cost' => 3000.00,
        'line_total' => 20000.00,
        'returned_qty' => 0,
    ]);

    // 1. Partial Return: 1 item returned with restock = true
    $partialResponse = $this->actingAs($this->owner)->post("/sales/invoices/{$sale->id}/return", [
        'return_date' => '2026-10-15',
        'reason' => 'Customer ordered too many, returning one bag',
        'items' => [
            ['sale_item_id' => $saleItem->id, 'qty' => 1],
        ],
        'refunded_amount' => 5000.00,
        'restock' => true,
        'return_courier_cost' => 120.00,
        'return_courier_payment_method' => 'cash',
    ]);

    $partialResponse->assertSessionHasNoErrors();
    $sale->refresh();
    $saleItem->refresh();
    $this->product->refresh();

    expect($sale->status)->toBe('partially_returned')
        ->and($saleItem->returned_qty)->toBe(1)
        ->and($this->product->stock_qty)->toBe(11); // Restocked 1 unit

    // Verify linked return courier expense was created
    $expense = Expense::where('source', 'return_courier')
        ->where('sale_id', $sale->id)
        ->first();
    expect($expense)->not->toBeNull()
        ->and((float) $expense->amount)->toBe(120.00)
        ->and($expense->expense_date->format('Y-m-d'))->toBe('2026-10-15');

    // 2. Over-return prevention: remaining returnable is 3. Trying to return 4 must fail!
    $overReturn = $this->actingAs($this->owner)->post("/sales/invoices/{$sale->id}/return", [
        'return_date' => '2026-10-15',
        'reason' => 'Over-return attempt',
        'items' => [
            ['sale_item_id' => $saleItem->id, 'qty' => 4],
        ],
    ]);
    $overReturn->assertSessionHasErrors(['items.0.qty']);

    // 3. Return remaining 3 with restock = false (damaged goods)
    $remainingReturn = $this->actingAs($this->owner)->post("/sales/invoices/{$sale->id}/return", [
        'return_date' => '2026-10-16',
        'reason' => 'Remaining bags arrived damaged',
        'items' => [
            ['sale_item_id' => $saleItem->id, 'qty' => 3],
        ],
        'refunded_amount' => 15000.00,
        'restock' => false, // Damaged, do not restore to catalogue
    ]);

    $remainingReturn->assertSessionHasNoErrors();
    $sale->refresh();
    $saleItem->refresh();
    $this->product->refresh();

    // All items returned -> status becomes returned
    expect($sale->status)->toBe('returned')
        ->and($saleItem->returned_qty)->toBe(4)
        ->and($this->product->stock_qty)->toBe(11); // Stock stays 11 because restock = false

    // Cannot return on cancelled sale
    $cancelledSale = Sale::create([
        'invoice_no' => 'TASA-2610-0106',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'sale_date' => '2026-10-12',
        'sold_at' => now(),
        'subtotal' => 5000.00,
        'grand_total' => 5000.00,
        'paid_total' => 0.00,
        'payment_status' => 'unpaid',
        'status' => 'cancelled',
        'courier_mode' => 'none',
    ]);
    $itemOnCancelled = SaleItem::create([
        'sale_id' => $cancelledSale->id,
        'name' => 'Cardholder',
        'qty' => 1,
        'unit_price' => 5000.00,
        'line_total' => 5000.00,
    ]);

    $failReturn = $this->actingAs($this->owner)->post("/sales/invoices/{$cancelledSale->id}/return", [
        'reason' => 'Try return on cancelled sale',
        'items' => [['sale_item_id' => $itemOnCancelled->id, 'qty' => 1]],
    ]);
    $failReturn->assertSessionHasErrors(['reason']);
});

test('Invoices index filters by date presets, payment status, and search query', function () {
    // Sale 1: Today, Paid
    Sale::create([
        'invoice_no' => 'TASA-2610-0201',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => 'John Doe Today',
        'customer_phone' => '01811223344',
        'customer_address' => 'Uttara, Dhaka',
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 4000.00,
        'grand_total' => 4000.00,
        'paid_total' => 4000.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
    ]);

    // Sale 2: Yesterday, Partial
    Sale::create([
        'invoice_no' => 'TASA-2610-0202',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => 'Jane Yesterday',
        'customer_phone' => '01711223399',
        'customer_address' => 'Gulshan, Dhaka',
        'sale_date' => now()->subDay()->toDateString(),
        'sold_at' => now()->subDay(),
        'subtotal' => 6000.00,
        'grand_total' => 6000.00,
        'paid_total' => 2000.00,
        'payment_status' => 'partial',
        'status' => 'active',
        'courier_mode' => 'pathao',
    ]);

    // 1. Search filter
    $searchResponse = $this->actingAs($this->owner)->get('/sales/invoices?search=TASA-2610-0201');
    $searchResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('sales/Invoices')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.invoice_no', 'TASA-2610-0201')
        );

    // 2. Date preset: today
    $todayResponse = $this->actingAs($this->owner)->get('/sales/invoices?date_preset=today');
    $todayResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('filters.date_preset', 'today')
        );

    // 3. Payment status filter: partial
    $partialResponse = $this->actingAs($this->owner)->get('/sales/invoices?payment_status=partial');
    $partialResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('invoices.data.0.invoice_no', 'TASA-2610-0202')
        );
});
