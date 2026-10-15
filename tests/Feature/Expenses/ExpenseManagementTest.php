<?php

namespace Tests\Feature\Expenses;

use App\Models\CourierOrder;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(ExpenseCategorySeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    Storage::fake('local');

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

it('seeds default categories with correct P&L mappings and protects system categories', function () {
    // 1. Verify all 12 default categories exist
    $expected = [
        'Facebook Ads' => 'marketing_facebook',
        'Other Marketing' => 'marketing_other',
        'Packaging' => 'packaging',
        'Courier Charges' => 'courier',
        'Rent' => 'rent_utilities',
        'Electricity' => 'rent_utilities',
        'Internet' => 'rent_utilities',
        'Gas/Water' => 'rent_utilities',
        'Transport' => 'other',
        'Repair & Maintenance' => 'other',
        'Tools & Equipment' => 'other',
        'Miscellaneous' => 'other',
    ];

    foreach ($expected as $name => $plGroup) {
        $category = ExpenseCategory::where('name', $name)->first();
        expect($category)->not->toBeNull();
        expect($category->pl_group)->toBe($plGroup);
    }

    // Facebook Ads and Courier Charges must be system categories
    $fbCat = ExpenseCategory::where('name', 'Facebook Ads')->first();
    $courierCat = ExpenseCategory::where('name', 'Courier Charges')->first();
    expect($fbCat->is_system)->toBeTrue();
    expect($courierCat->is_system)->toBeTrue();

    // System categories cannot be deleted
    $response = $this->actingAs($this->owner)
        ->delete(route('expenses.categories.destroy', $fbCat->id));

    $response->assertForbidden();
    expect(ExpenseCategory::find($fbCat->id))->not->toBeNull();
});

it('allows staff to view and record expenses with positive amount', function () {
    $cat = ExpenseCategory::where('name', 'Electricity')->first();

    // Staff can view expenses
    $this->actingAs($this->staff)
        ->get(route('expenses.index'))
        ->assertOk();

    // Staff can create expense
    $response = $this->actingAs($this->staff)
        ->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 4500.50,
            'payment_method' => 'bkash',
            'vendor' => 'DESCO',
            'note' => 'Monthly electricity bill for workshop',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('expenses', [
        'expense_category_id' => $cat->id,
        'amount' => 4500.50,
        'payment_method' => 'bkash',
        'vendor' => 'DESCO',
        'created_by' => $this->staff->id,
    ]);

    // Amount must be positive (> 0)
    $failResponse = $this->actingAs($this->staff)
        ->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 0,
            'payment_method' => 'cash',
        ]);

    $failResponse->assertSessionHasErrors(['amount']);
});

it('enforces owner-only permission for editing and deleting expenses', function () {
    $cat = ExpenseCategory::where('name', 'Transport')->first();

    $expense = Expense::create([
        'expense_category_id' => $cat->id,
        'expense_date' => now()->toDateString(),
        'amount' => 500.00,
        'payment_method' => 'cash',
        'vendor' => 'Local CNG',
        'created_by' => $this->staff->id,
    ]);

    // Staff cannot update
    $this->actingAs($this->staff)
        ->post(route('expenses.update', $expense->id), [
            'amount' => 600.00,
            'expense_category_id' => $cat->id,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ])
        ->assertForbidden();

    // Staff cannot delete
    $this->actingAs($this->staff)
        ->delete(route('expenses.destroy', $expense->id))
        ->assertForbidden();

    // Owner can update
    $this->actingAs($this->owner)
        ->post(route('expenses.update', $expense->id), [
            'amount' => 600.00,
            'expense_category_id' => $cat->id,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'vendor' => 'Local Ride',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($expense->fresh()->amount)->toBe('600.00');

    // Owner can delete
    $this->actingAs($this->owner)
        ->delete(route('expenses.destroy', $expense->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($expense->fresh()->trashed())->toBeTrue();
});

it('stores receipt attachment on private disk and authorizes access through controller', function () {
    $cat = ExpenseCategory::where('name', 'Repair & Maintenance')->first();
    $file = UploadedFile::fake()->create('ac-service-receipt.pdf', 1024, 'application/pdf');

    $response = $this->actingAs($this->owner)
        ->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 2500.00,
            'payment_method' => 'cash',
            'vendor' => 'Cooling Service BD',
            'attachment' => $file,
        ]);

    $response->assertRedirect();
    $expense = Expense::latest('id')->first();
    expect($expense->attachment_path)->not->toBeNull();

    // File stored on private local disk
    Storage::disk('local')->assertExists($expense->attachment_path);

    // Authorized download through controller
    $downloadResponse = $this->actingAs($this->owner)
        ->get(route('expenses.attachment', $expense->id));

    $downloadResponse->assertOk();

    // Unauthenticated user cannot download
    auth()->logout();
    $this->get(route('expenses.attachment', $expense->id))
        ->assertRedirect(route('login'));
});

it('blocks payroll and raw material purchases from being logged as operating expenses (PRD BR-5 & BR-9)', function () {
    $cat = ExpenseCategory::where('name', 'Miscellaneous')->first();

    // Worker wage in note
    $responseWage = $this->actingAs($this->staff)
        ->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 3000.00,
            'payment_method' => 'cash',
            'note' => 'Payment for worker wage weekly payroll',
        ]);

    $responseWage->assertSessionHasErrors(['name']);

    // Leather purchase in note
    $responseLeather = $this->actingAs($this->staff)
        ->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 15000.00,
            'payment_method' => 'bank_transfer',
            'note' => 'Raw material leather purchase from Hazaribagh',
        ]);

    $responseLeather->assertSessionHasErrors(['name']);

    // Category creation with forbidden raw material pattern
    $responseCat = $this->actingAs($this->owner)
        ->post(route('expenses.categories.store'), [
            'name' => 'Accessory Purchase Expense',
            'pl_group' => 'other',
        ]);

    $responseCat->assertSessionHasErrors(['name']);
});

it('ensures courier charge expenses generated by Pathao and returns are immutable and idempotent', function () {
    $courierCat = ExpenseCategory::where('pl_group', 'courier')->first();

    $customer = Customer::create([
        'name' => 'Customer Test',
        'phone' => '01711000000',
        'address' => 'Test Address 1234567890',
    ]);

    $sale = Sale::create([
        'invoice_no' => 'TASA-2610-0099',
        'idempotency_token' => (string) \Illuminate\Support\Str::uuid(),
        'customer_id' => $customer->id,
        'customer_name' => 'Customer Test',
        'customer_phone' => '01711000000',
        'customer_address' => 'Test Address 1234567890',
        'sale_date' => now()->toDateString(),
        'sold_at' => now(),
        'subtotal' => 1200.00,
        'discount_amount' => 0.00,
        'delivery_charge' => 120.00,
        'grand_total' => 1320.00,
        'paid_total' => 0.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'created_by' => $this->staff->id,
    ]);

    $courierOrder = CourierOrder::create([
        'sale_id' => $sale->id,
        'merchant_order_id' => 'ORD-TEST-001',
        'consignment_id' => 'PATHAO-CS-999',
        'store_id' => 1,
        'recipient_name' => 'Customer Test',
        'recipient_phone' => '01711000000',
        'recipient_address' => 'Test Address 1234567890',
        'recipient_city' => 1,
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'delivery_type' => 48,
        'item_type' => 1,
        'item_quantity' => 1,
        'item_weight' => 0.5,
        'cod_amount' => 1200.00,
        'courier_charge' => 120.00,
        'status' => 'Pending',
    ]);

    // Create system courier charge
    $courierExpense = Expense::create([
        'expense_category_id' => $courierCat->id,
        'expense_date' => now()->toDateString(),
        'amount' => 120.00,
        'payment_method' => 'other',
        'source' => 'pathao_charge',
        'courier_order_id' => $courierOrder->id,
        'fee_deducted_from_cod' => true,
        'vendor' => 'Pathao',
    ]);

    // System courier charges cannot be edited or deleted by users
    $this->actingAs($this->owner)
        ->post(route('expenses.update', $courierExpense->id), [
            'amount' => 150.00,
            'expense_category_id' => $courierCat->id,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'other',
        ])
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->delete(route('expenses.destroy', $courierExpense->id))
        ->assertForbidden();

    // Idempotency: Attempting duplicate courier charge for same order & source throws DB unique constraint
    expect(function () use ($courierCat, $courierOrder) {
        Expense::create([
            'expense_category_id' => $courierCat->id,
            'expense_date' => now()->toDateString(),
            'amount' => 120.00,
            'payment_method' => 'other',
            'source' => 'pathao_charge',
            'courier_order_id' => $courierOrder->id,
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

it('creates monthly recurring templates and enforces 1-click confirmation with no duplicates', function () {
    $rentCat = ExpenseCategory::where('name', 'Rent')->first();

    // 1. Owner creates recurring template
    $response = $this->actingAs($this->owner)
        ->post(route('expenses.recurring.store'), [
            'expense_category_id' => $rentCat->id,
            'amount' => 40000.00,
            'vendor' => 'Shop Landlord',
            'note' => 'Showroom Monthly Rent',
            'day_of_month' => 1,
            'payment_method' => 'bank_transfer',
            'is_active' => true,
        ]);

    $response->assertRedirect();
    $template = RecurringExpense::latest('id')->first();
    expect($template)->not->toBeNull();
    expect($template->amount)->toBe('40000.00');

    // 2. Confirm template for current period (1-Click)
    $confirmResponse = $this->actingAs($this->owner)
        ->post(route('expenses.recurring.confirm', $template->id));

    $confirmResponse->assertRedirect();
    $confirmResponse->assertSessionHas('success');

    $currentPeriod = now()->format('Y-m');
    expect($template->fresh()->last_confirmed_period)->toBe($currentPeriod);

    // Verify expense created
    $createdExpense = Expense::where('recurring_expense_id', $template->id)->first();
    expect($createdExpense)->not->toBeNull();
    expect($createdExpense->amount)->toBe('40000.00');
    expect($createdExpense->source)->toBe('recurring');

    // 3. Second confirmation in same month is blocked (no silent auto-post / no duplicate)
    $secondConfirm = $this->actingAs($this->owner)
        ->post(route('expenses.recurring.confirm', $template->id));

    $secondConfirm->assertSessionHasErrors(['recurring']);
    expect(Expense::where('recurring_expense_id', $template->id)->count())->toBe(1);
});

it('filters expenses by date, category, pl_group and computes Facebook Ads totals', function () {
    $fbCat = ExpenseCategory::where('name', 'Facebook Ads')->first();
    $packCat = ExpenseCategory::where('name', 'Packaging')->first();

    // Create Facebook Ads expense
    Expense::create([
        'expense_category_id' => $fbCat->id,
        'expense_date' => now()->toDateString(),
        'amount' => 12500.00,
        'payment_method' => 'bank_transfer',
        'vendor' => 'Meta Ads',
        'source' => 'manual',
    ]);

    // Create Packaging expense
    Expense::create([
        'expense_category_id' => $packCat->id,
        'expense_date' => now()->toDateString(),
        'amount' => 3000.00,
        'payment_method' => 'cash',
        'vendor' => 'Box Maker',
        'source' => 'manual',
    ]);

    // Test filtering by pl_group
    $response = $this->actingAs($this->owner)
        ->get(route('expenses.index', ['pl_group' => 'marketing_facebook']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('expenses/Expenses')
        ->has('expenses.data', 1)
        ->where('analytics.facebook_ads_total', fn ($val) => (float) $val == 12500.0)
        ->where('analytics.packaging_total', fn ($val) => (float) $val == 3000.0)
        ->where('analytics.total_amount', fn ($val) => (float) $val == 15500.0)
    );
});
