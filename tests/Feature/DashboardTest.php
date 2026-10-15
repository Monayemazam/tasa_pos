<?php

namespace Tests\Feature;

use App\Models\CourierOrder;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
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

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('staff user receives basic operational widgets only and financial props are strictly null', function () {
    $response = $this->actingAs($this->staff)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('basic')
        ->where('basic.today_sales', fn ($val) => isset($val['count']) && isset($val['amount']))
        ->where('basic.this_month_sales', fn ($val) => isset($val['count']) && isset($val['amount']))
        ->where('basic.courier_orders', fn ($val) => isset($val['pending']) && isset($val['failed']))
        ->where('basic.low_stock', fn ($val) => isset($val['total_count']))
        ->has('basic.latest_invoices')
        ->where('canViewFinancials', false)
        ->where('financials', null)
    );
});

test('owner user receives full financial metrics and 6-month trend widgets', function () {
    $response = $this->actingAs($this->owner)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('basic')
        ->where('canViewFinancials', true)
        ->has('financials')
        ->has('financials.net_profit_this_month')
        ->has('financials.gross_margin_pct')
        ->has('financials.total_revenue_this_month')
        ->has('financials.total_expenses_this_month')
        ->has('financials.cod_to_receive')
        ->has('financials.customer_dues')
        ->has('financials.payroll_balances')
        ->has('financials.facebook_ads')
        ->has('financials.six_month_trend')
    );
});

test('zero ad spend displays roas as null and dash rather than division by zero error', function () {
    // Ensure no Facebook Ads expenses exist
    $fbCat = ExpenseCategory::where('pl_group', 'marketing_facebook')->first();
    if ($fbCat) {
        Expense::where('expense_category_id', $fbCat->id)->delete();
    }

    $response = $this->actingAs($this->owner)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('financials.facebook_ads.ad_spend', 0)
        ->where('financials.facebook_ads.roas', null)
        ->where('financials.facebook_ads.roas_formatted', '—')
    );
});

test('facebook ads roas calculates correctly when ad spend is present', function () {
    $today = CarbonImmutable::now()->toDateString();
    $customer = Customer::create([
        'name' => 'ROAS Customer',
        'phone' => '01700000001',
        'address' => 'Dhaka',
    ]);

    // Create a sale of 50,000
    Sale::create([
        'invoice_no' => 'TASA-ROAS-001',
        'idempotency_token' => 'roas-token-1',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => $today,
        'sold_at' => now(),
        'subtotal' => 50000.00,
        'discount_type' => 'flat',
        'discount_value' => 0.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 0.00,
        'grand_total' => 50000.00,
        'paid_total' => 50000.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
        'created_by' => $this->owner->id,
    ]);

    // Create Facebook Ads expense of 10,000
    $fbCategory = ExpenseCategory::firstOrCreate(
        ['pl_group' => 'marketing_facebook'],
        ['name' => 'Facebook Ads', 'is_system' => true, 'is_active' => true]
    );

    Expense::create([
        'expense_category_id' => $fbCategory->id,
        'expense_date' => $today,
        'amount' => 10000.00,
        'payment_method' => 'bkash',
        'created_by' => $this->owner->id,
    ]);

    $response = $this->actingAs($this->owner)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('financials.facebook_ads.ad_spend', 10000)
        ->where('financials.facebook_ads.roas', 5) // 50,000 / 10,000 = 5.0
        ->where('financials.facebook_ads.roas_formatted', '5.00x')
    );
});

test('cod to receive matches delivered unsettled courier orders only', function () {
    $today = CarbonImmutable::now()->toDateString();
    $customer = Customer::create([
        'name' => 'COD Customer',
        'phone' => '01700000002',
        'address' => 'Dhaka',
    ]);

    $sale1 = Sale::create([
        'invoice_no' => 'TASA-COD-001',
        'idempotency_token' => 'cod-token-1',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => $today,
        'sold_at' => now(),
        'subtotal' => 3000.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 100.00,
        'grand_total' => 3100.00,
        'paid_total' => 0.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    // Delivered & unsettled COD: 3100 (must be included)
    CourierOrder::create([
        'sale_id' => $sale1->id,
        'consignment_id' => 'CS-COD-001',
        'merchant_order_id' => $sale1->invoice_no,
        'store_id' => 1,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_city' => 1,
        'recipient_zone' => 1,
        'recipient_area' => 1,
        'recipient_name' => $customer->name,
        'recipient_phone' => $customer->phone,
        'recipient_address' => 'Dhaka',
        'status' => 'delivered',
        'delivery_charge' => 100.00,
        'cod_amount' => 3100.00,
        'cod_settled_at' => null,
    ]);

    // Settled COD (must be excluded)
    $sale2 = Sale::create([
        'invoice_no' => 'TASA-COD-002',
        'idempotency_token' => 'cod-token-2',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'customer_address' => 'Dhaka',
        'sale_date' => $today,
        'sold_at' => now(),
        'subtotal' => 1500.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 100.00,
        'grand_total' => 1600.00,
        'paid_total' => 1600.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->owner->id,
    ]);

    CourierOrder::create([
        'sale_id' => $sale2->id,
        'consignment_id' => 'CS-COD-002',
        'merchant_order_id' => $sale2->invoice_no,
        'store_id' => 1,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'recipient_city' => 1,
        'recipient_zone' => 1,
        'recipient_area' => 1,
        'recipient_name' => $customer->name,
        'recipient_phone' => $customer->phone,
        'recipient_address' => 'Dhaka',
        'status' => 'delivered',
        'delivery_charge' => 100.00,
        'cod_amount' => 1600.00,
        'cod_settled_at' => now(),
        'cod_settled_amount' => 1600.00,
    ]);

    $response = $this->actingAs($this->owner)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('financials.cod_to_receive', 3100)
    );
});

test('low stock alerts count active products below min stock', function () {
    $cat = ProductCategory::firstOrCreate(['name' => 'Bags'], ['is_active' => true]);

    Product::create([
        'name' => 'Low Stock Bag',
        'sku' => 'P-LOW-001',
        'product_category_id' => $cat->id,
        'cost_price' => 500.00,
        'selling_price' => 1000.00,
        'stock_qty' => 2,
        'min_stock' => 5, // 2 <= 5 -> low stock alert!
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->staff)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('basic.low_stock.products_count', fn ($val) => $val >= 1)
        ->where('basic.low_stock.total_count', fn ($val) => $val >= 1)
    );
});
