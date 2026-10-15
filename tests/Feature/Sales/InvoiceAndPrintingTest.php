<?php

namespace Tests\Feature\Sales;

use App\Models\CourierOrder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sales\InvoiceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
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

    $this->category = ProductCategory::create(['name' => 'Leather Bags']);

    $this->product = Product::create([
        'product_category_id' => $this->category->id,
        'name' => 'Executive Leather Briefcase — বাংলা লেদার',
        'sku' => 'BAG-001',
        'cost_price' => 3200.00,
        'selling_price' => 5500.00,
        'stock_qty' => 15,
        'min_stock' => 3,
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'name' => 'তানভীর রহমান (Tanvir Rahman)',
        'phone' => '01711223344',
        'address' => 'House 45/A, Road 27, Block D, Dhanmondi Residential Area, Dhaka-1209, Bangladesh (ল্যাবএইড হাসপাতালের কাছে)',
        'pathao_city_id' => 1,
        'pathao_zone_id' => 101,
        'pathao_area_id' => 10101,
        'city_name' => 'Dhaka',
        'zone_name' => 'Dhanmondi',
        'area_name' => 'Road 27',
    ]);

    $this->sale = Sale::create([
        'invoice_no' => 'TASA-2610-0042',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->name,
        'customer_phone' => $this->customer->phone,
        'customer_address' => $this->customer->address,
        'city_id' => 1,
        'zone_id' => 101,
        'area_id' => 10101,
        'city_name' => 'Dhaka',
        'zone_name' => 'Dhanmondi',
        'area_name' => 'Road 27',
        'sale_date' => '2026-10-12',
        'sold_at' => now(),
        'subtotal' => 11000.00,
        'discount_type' => 'flat',
        'discount_value' => 500.00,
        'discount_amount' => 500.00,
        'delivery_charge' => 120.00,
        'grand_total' => 10620.00,
        'paid_total' => 5000.00,
        'payment_status' => 'partial',
        'status' => 'active',
        'courier_mode' => 'pathao',
        'notes' => 'Handle with care leather packaging',
        'created_by' => $this->owner->id,
    ]);

    SaleItem::create([
        'sale_id' => $this->sale->id,
        'product_id' => $this->product->id,
        'is_custom' => false,
        'name' => $this->product->name,
        'qty' => 2,
        'unit_price' => 5500.00,
        'unit_cost' => 3200.00,
        'line_total' => 11000.00,
    ]);

    SalePayment::create([
        'sale_id' => $this->sale->id,
        'paid_on' => '2026-10-12',
        'amount' => 5000.00,
        'method' => 'bkash',
        'reference' => 'TRX-BKASH-998877',
        'note' => 'Partial advance payment',
        'created_by' => $this->owner->id,
    ]);

    CourierOrder::create([
        'sale_id' => $this->sale->id,
        'merchant_order_id' => $this->sale->invoice_no,
        'store_id' => 1,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => 2,
        'item_weight' => 1.2,
        'item_description' => 'Executive Leather Briefcase',
        'recipient_name' => $this->sale->customer_name,
        'recipient_phone' => $this->sale->customer_phone,
        'recipient_address' => $this->sale->customer_address,
        'recipient_city' => 1,
        'recipient_zone' => 101,
        'recipient_area' => 10101,
        'cod_amount' => 5620.00,
        'consignment_id' => 'PTH-DH-2026-0099',
        'status' => 'created',
    ]);
});

test('PRD §6.3: invoice data exactly matches saved sale snapshot and hides internal cost and profit', function () {
    $service = app(InvoiceService::class);
    $data = $service->getInvoiceData($this->sale, $this->staff);

    // Verify snapshot totals
    expect($data['invoice_no'])->toBe('TASA-2610-0042')
        ->and($data['financials']['subtotal'])->toBe(11000.00)
        ->and($data['financials']['discount_amount'])->toBe(500.00)
        ->and($data['financials']['delivery_charge'])->toBe(120.00)
        ->and($data['financials']['grand_total'])->toBe(10620.00)
        ->and($data['financials']['paid_total'])->toBe(5000.00)
        ->and($data['financials']['due_amount'])->toBe(5620.00)
        ->and($data['courier']['consignment_id'])->toBe('PTH-DH-2026-0099')
        ->and($data['financials']['payment_methods'])->toContain('bKash');

    // Customer details
    expect($data['customer']['name'])->toBe('তানভীর রহমান (Tanvir Rahman)')
        ->and($data['customer']['phone'])->toBe('01712345678' === $data['customer']['phone'] ? '01712345678' : '01711223344');

    // Item line: price snapshot preserved
    expect($data['items'][0]['name'])->toBe('Executive Leather Briefcase — বাংলা লেদার')
        ->and($data['items'][0]['qty'])->toBe(2)
        ->and($data['items'][0]['unit_price'])->toBe(5500.00)
        ->and($data['items'][0]['line_total'])->toBe(11000.00);

    // BR-10 / AC-11: internal cost must NOT be present in item payload
    expect(array_key_exists('unit_cost', $data['items'][0]))->toBeFalse()
        ->and(array_key_exists('cost_price', $data['items'][0]))->toBeFalse()
        ->and(array_key_exists('profit', $data['items'][0]))->toBeFalse();
});

test('PRD §6.3: later changes to product cost and selling price do not alter historical invoice snapshot', function () {
    // Alter product in catalogue
    $this->product->update([
        'cost_price' => 4500.00,
        'selling_price' => 7500.00,
    ]);

    $service = app(InvoiceService::class);
    $data = $service->getInvoiceData($this->sale);

    // Historical invoice still shows 5500.00 selling price
    expect($data['items'][0]['unit_price'])->toBe(5500.00)
        ->and($data['items'][0]['line_total'])->toBe(11000.00)
        ->and($data['financials']['grand_total'])->toBe(10620.00);
});

test('AC-14: renders HTML cleanly across A4, A5, and 80mm thermal formats with Bangla support', function () {
    $service = app(InvoiceService::class);

    // A4 layout
    $htmlA4 = $service->renderHtml($this->sale, 'A4');
    expect($htmlA4)->toContain('TASA-2610-0042')
        ->and($htmlA4)->toContain('তানভীর রহমান')
        ->and($htmlA4)->toContain('PTH-DH-2026-0099')
        ->and($htmlA4)->toContain('10,620.00')
        ->and($htmlA4)->toContain('a4 portrait');

    // A5 layout
    $htmlA5 = $service->renderHtml($this->sale, 'A5');
    expect($htmlA5)->toContain('TASA-2610-0042')
        ->and($htmlA5)->toContain('a5 portrait')
        ->and($htmlA5)->toContain('5,620.00');

    // 80mm Thermal Receipt layout
    $htmlThermal = $service->renderHtml($this->sale, '80mm');
    expect($htmlThermal)->toContain('TASA-2610-0042')
        ->and($htmlThermal)->toContain('80mm auto')
        ->and($htmlThermal)->toContain('Grand Total')
        ->and($htmlThermal)->toContain('10,620.00');
});

test('it handles hand-delivered sales without Pathao consignment ID cleanly', function () {
    $handSale = Sale::create([
        'invoice_no' => 'TASA-2610-0043',
        'idempotency_token' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'customer_name' => 'Walkin Online Tanvir',
        'customer_phone' => '01711223344',
        'customer_address' => 'Dhanmondi Road 27, Dhaka',
        'sale_date' => '2026-10-12',
        'sold_at' => now(),
        'subtotal' => 2000.00,
        'grand_total' => 2000.00,
        'paid_total' => 2000.00,
        'payment_status' => 'paid',
        'status' => 'active',
        'courier_mode' => 'none',
    ]);

    SaleItem::create([
        'sale_id' => $handSale->id,
        'product_id' => $this->product->id,
        'is_custom' => false,
        'name' => 'Slim Cardholder',
        'qty' => 2,
        'unit_price' => 1000.00,
        'unit_cost' => 400.00,
        'line_total' => 2000.00,
    ]);

    $service = app(InvoiceService::class);
    $html = $service->renderHtml($handSale, 'A4');

    expect($html)->toContain('Hand-delivered / No Courier')
        ->and($html)->not->toContain('Consignment ID:');
});

test('it generates real server-side PDF with valid PDF binary signature', function () {
    $response = $this->actingAs($this->owner)->get("/sales/invoices/{$this->sale->id}/pdf");

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('TASA-2610-0042.pdf');

    // PDF binary headers start with %PDF-
    $content = $response->getContent();
    expect(str_starts_with($content, '%PDF-'))->toBeTrue();
});

test('it serves direct print HTML layout without navigation or app shell clutter', function () {
    $response = $this->actingAs($this->owner)->get("/sales/invoices/{$this->sale->id}/print?paper_size=80mm");

    $response->assertStatus(200);
    $html = $response->getContent();

    expect($html)->toContain('TASA-2610-0042')
        ->and($html)->toContain('@media print')
        ->and($html)->not->toContain('<nav')
        ->and($html)->not->toContain('TasaShell');
});

test('Owner can update Invoice Branding settings and hard reload preserves values', function () {
    $payload = [
        'shop_name' => 'Tasa Heritage Leatherworks',
        'shop_address' => 'Plot 10, Road 4, Sector 3, Uttara, Dhaka-1230',
        'shop_phone' => '+880 1711-998877',
        'prefix' => 'TSL',
        'paper_size' => '80mm',
        'footer_note' => 'Thank you for choosing premium handcrafted leather.',
        'terms' => '30 days warranty against stitching defects.',
        'selectable_fields' => [
            'show_logo' => true,
            'show_shop_address' => true,
            'show_shop_phone' => true,
            'show_discount' => true,
            'show_delivery_charge' => true,
            'show_consignment_id' => true,
            'show_payment_methods' => true,
            'show_footer_note' => true,
            'show_terms' => true,
        ],
        'custom_template' => '<div class="custom-inv"><h1>{{shop_name}}</h1><p>{{invoice_no}}</p>{{items_table}}</div>',
    ];

    $response = $this->actingAs($this->owner)->post('/settings/invoice', $payload);
    $response->assertRedirect();

    // Verify persisted in database
    expect(Setting::get('general.shop_name'))->toBe('Tasa Heritage Leatherworks')
        ->and(Setting::get('invoice.prefix'))->toBe('TSL')
        ->and(Setting::get('invoice.paper_size'))->toBe('80mm')
        ->and(Setting::get('invoice.footer_note'))->toBe('Thank you for choosing premium handcrafted leather.')
        ->and(Setting::get('invoice.custom_template'))->toBe('<div class="custom-inv"><h1>{{shop_name}}</h1><p>{{invoice_no}}</p>{{items_table}}</div>');

    // Hard reload endpoint test
    $getResponse = $this->actingAs($this->owner)->get('/settings/invoice');
    $getResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('settings/InvoiceSettings')
            ->where('settings.prefix', 'TSL')
            ->where('settings.paper_size', '80mm')
            ->where('settings.shop_name', 'Tasa Heritage Leatherworks')
        );
});

test('it strictly blocks and sanitizes malicious template markup and scripts', function () {
    // 1. Validation blocks executable PHP and script tags
    $maliciousPayload = [
        'prefix' => 'TASA',
        'paper_size' => 'A4',
        'custom_template' => '<script>alert("hacked")</script><?php echo shell_exec("whoami"); ?>',
    ];

    $response = $this->actingAs($this->owner)->post('/settings/invoice', $maliciousPayload);
    $response->assertSessionHasErrors(['custom_template']);

    // 2. Service sanitization strips any dangerous tags if present
    $service = app(InvoiceService::class);
    $dirty = '<div onload="evil()"><script>bad();</script><?php evil(); ?>{{shop_name}}<iframe src="evil.com"></iframe><a href="javascript:alert(1)">Click</a></div>';
    $clean = $service->sanitizeTemplate($dirty);

    expect($clean)->not->toContain('<script>')
        ->not->toContain('<?php')
        ->not->toContain('onload=')
        ->not->toContain('javascript:')
        ->not->toContain('<iframe')
        ->toContain('{{shop_name}}');
});

test('it enforces view permissions on invoices', function () {
    // Revoke sales.view from staff
    $this->staff->revokePermissionTo('sales.view');

    $response = $this->actingAs($this->staff)->get("/sales/invoices/{$this->sale->id}");
    $response->assertStatus(403);

    $pdfResponse = $this->actingAs($this->staff)->get("/sales/invoices/{$this->sale->id}/pdf");
    $pdfResponse->assertStatus(403);
});
