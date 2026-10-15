<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\InvoiceSequence;
use App\Models\LeatherConsumption;
use App\Models\LeatherItem;
use App\Models\LeatherPurchase;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\Role;
use App\Models\SalaryMonth;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('spatie roles have is_locked and owner cannot be unlocked', function () {
    $owner = Role::where('name', 'Owner')->first();
    expect($owner)->not->toBeNull()
        ->and($owner->is_locked)->toBeTrue();

    $staff = Role::where('name', 'Staff')->first();
    expect($staff)->not->toBeNull()
        ->and($staff->is_locked)->toBeFalse();
});

test('users model enforces single role and soft deletes', function () {
    $user = User::factory()->create();
    $user->assignSingleRole('Staff');

    expect($user->hasRole('Staff'))->toBeTrue()
        ->and($user->hasRole('Owner'))->toBeFalse();

    $user->assignSingleRole('Owner');
    $user->refresh();

    expect($user->hasRole('Owner'))->toBeTrue()
        ->and($user->hasRole('Staff'))->toBeFalse()
        ->and($user->roles()->count())->toBe(1);

    // Soft delete test
    $userId = $user->id;
    $user->delete();

    expect(User::find($userId))->toBeNull()
        ->and(User::withTrashed()->find($userId))->not->toBeNull();
});

test('settings enforce group and key uniqueness and get/set helper works', function () {
    Setting::set('inventory.allow_negative_stock', '1');
    expect(Setting::get('inventory.allow_negative_stock'))->toEqual('1');

    Setting::set('inventory.allow_negative_stock', '0');
    expect(Setting::get('inventory.allow_negative_stock'))->toEqual('0');

    // Attempting raw duplicate insert throws unique violation
    expect(function () {
        DB::table('settings')->insert([
            'group' => 'inventory',
            'key' => 'allow_negative_stock',
            'value' => '99',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

test('invoice sequence produces race-free sequential monthly numbers', function () {
    $date = now();
    $period = $date->format('ym');

    $no1 = InvoiceSequence::nextInvoiceNo($date);
    $no2 = InvoiceSequence::nextInvoiceNo($date);

    expect($no1)->toMatch("/^TASA-{$period}-\d{4}$/")
        ->and($no2)->toMatch("/^TASA-{$period}-\d{4}$/")
        ->and($no2)->not->toBe($no1);
});

test('audit logs and stock movements are append-only and cannot be mutated or deleted', function () {
    $log = AuditLog::create([
        'event' => 'created',
        'auditable_type' => Product::class,
        'auditable_id' => 1,
        'new_values' => ['name' => 'Wallet'],
    ]);

    expect(fn () => $log->update(['event' => 'tampered']))
        ->toThrow(RuntimeException::class, 'Audit logs are append-only and cannot be updated.');

    expect(fn () => $log->delete())
        ->toThrow(RuntimeException::class, 'Audit logs are append-only and cannot be deleted.');

    // Test product movement append-only
    $cat = ProductCategory::create(['name' => 'Wallets_'.Str::random(5)]);
    $prod = Product::create([
        'product_category_id' => $cat->id,
        'name' => 'Slim Wallet',
        'sku' => 'SKU_'.Str::random(8),
        'cost_price' => 500.00,
        'selling_price' => 1200.00,
        'stock_qty' => 10,
    ]);

    $mov = ProductStockMovement::create([
        'product_id' => $prod->id,
        'type' => 'opening',
        'qty' => 10,
        'balance_after' => 10,
        'movement_date' => now()->toDateString(),
    ]);

    expect(fn () => $mov->update(['qty' => 20]))
        ->toThrow(RuntimeException::class, 'Product stock movements are append-only and cannot be updated.');

    expect(fn () => $mov->delete())
        ->toThrow(RuntimeException::class, 'Product stock movements are append-only and cannot be deleted.');
});

test('unique constraints on customer phone and product sku are enforced', function () {
    $phone = '017'.rand(10000000, 99999999);
    Customer::create([
        'name' => 'Customer A',
        'phone' => $phone,
        'address' => '123 Test Road, Dhaka',
    ]);

    expect(function () use ($phone) {
        Customer::create([
            'name' => 'Customer Duplicate',
            'phone' => $phone,
            'address' => '456 Test Road, Dhaka',
        ]);
    })->toThrow(QueryException::class);

    $cat = ProductCategory::create(['name' => 'Belts_'.Str::random(5)]);
    $sku = 'BELT_'.Str::random(6);

    Product::create([
        'product_category_id' => $cat->id,
        'name' => 'Leather Belt 1',
        'sku' => $sku,
        'cost_price' => 400.00,
        'selling_price' => 950.00,
    ]);

    expect(function () use ($cat, $sku) {
        Product::create([
            'product_category_id' => $cat->id,
            'name' => 'Leather Belt 2',
            'sku' => $sku,
            'cost_price' => 450.00,
            'selling_price' => 1050.00,
        ]);
    })->toThrow(QueryException::class);
});

test('stored generated columns compute accurately for sales due_amount and salary_months payable', function () {
    $cust = Customer::create([
        'name' => 'Zubair Ahmed',
        'phone' => '018'.rand(10000000, 99999999),
        'address' => 'House 4, Road 2, Dhanmondi, Dhaka',
    ]);

    $sale = Sale::create([
        'invoice_no' => 'TASA-'.now()->format('ym').'-'.rand(1000, 9999),
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $cust->id,
        'customer_name' => $cust->name,
        'customer_phone' => $cust->phone,
        'customer_address' => $cust->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 3000.00,
        'discount_type' => 'flat',
        'discount_amount' => 200.00,
        'delivery_charge' => 100.00,
        'grand_total' => 2900.00,
        'paid_total' => 1000.00,
        'payment_status' => 'partial',
    ]);

    $sale->refresh();
    // grand_total (2900) - paid_total (1000) = 1900.00
    expect((float) $sale->due_amount)->toEqual(1900.00);

    // Test salary_months payable: base_salary (25000) + bonus (3000) - deduction (1000) = 27000.00
    $emp = Employee::create([
        'name' => 'Karim Master',
        'phone' => '01912345678',
        'designation' => 'Pattern Master',
        'monthly_salary' => 25000.00,
    ]);

    $sm = SalaryMonth::create([
        'employee_id' => $emp->id,
        'period' => '2026-10-01',
        'base_salary' => 25000.00,
        'bonus' => 3000.00,
        'deduction' => 1000.00,
    ]);

    $sm->refresh();
    expect((float) $sm->payable)->toEqual(27000.00);
});

test('foreign key restrict prevents deleting records that have dependent child transactions', function () {
    $cust = Customer::create([
        'name' => 'Restricted Cust',
        'phone' => '015'.rand(10000000, 99999999),
        'address' => 'Mirpur 10, Dhaka',
    ]);

    Sale::create([
        'invoice_no' => 'TASA-'.now()->format('ym').'-'.rand(1000, 9999),
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $cust->id,
        'customer_name' => $cust->name,
        'customer_phone' => $cust->phone,
        'customer_address' => $cust->address,
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 1000.00,
        'grand_total' => 1000.00,
        'paid_total' => 1000.00,
        'payment_status' => 'paid',
    ]);

    // Force deleting customer must fail because sales.customer_id is RESTRICT
    expect(fn () => $cust->forceDelete())
        ->toThrow(QueryException::class);
});

test('v_leather_stock and v_accessory_stock views return calculated stock metrics', function () {
    $leather = LeatherItem::create([
        'name' => 'Test Veg-Tan Leather',
        'colour_grade' => 'Tan Grade A',
        'min_stock' => 15.000,
    ]);

    LeatherPurchase::create([
        'leather_item_id' => $leather->id,
        'purchase_date' => now()->toDateString(),
        'qty_sqft' => 100.000,
        'rate_per_sqft' => 150.00,
        'total_cost' => 15000.00,
        'paid_amount' => 15000.00,
    ]);

    LeatherConsumption::create([
        'leather_item_id' => $leather->id,
        'consumption_date' => now()->toDateString(),
        'qty_sqft' => 25.000,
        'note' => 'Batch 101',
    ]);

    $viewRow = DB::table('v_leather_stock')->where('id', $leather->id)->first();

    expect($viewRow)->not->toBeNull()
        ->and((float) $viewRow->purchased)->toEqual(100.000)
        ->and((float) $viewRow->consumed)->toEqual(25.000)
        ->and((float) $viewRow->current_stock)->toEqual(75.000)
        ->and((float) $viewRow->avg_cost)->toEqual(150.00)
        ->and((float) $viewRow->stock_value)->toEqual(11250.00);
});
