<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\Courier\PathaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    /**
     * Display general shop and operational settings.
     */
    public function general(): Response
    {
        $this->authorize('viewAny', Setting::class);

        $settings = [
            'shop_name' => Setting::get('general.shop_name', 'Tasa Leather Goods'),
            'address' => Setting::get('general.address', ''),
            'phone' => Setting::get('general.phone', ''),
            'logo' => Setting::get('general.logo', ''),
            'currency_symbol' => Setting::get('general.currency_symbol', '৳'),
            'allow_negative_stock' => (bool) Setting::get('inventory.allow_negative_stock', '0'),
            'cost_price_includes_labour' => (bool) Setting::get('payroll.cost_price_includes_labour', '0'),
            'allow_price_edit' => (bool) Setting::get('sales.allow_price_edit', '1'),
            'show_success_rate' => (bool) Setting::get('pathao.show_success_rate', '1'),
        ];

        $categories = ProductCategory::withCount('products')->get();
        $expenseCategories = ExpenseCategory::all();

        return Inertia::render('settings/GeneralSettings', [
            'settings' => $settings,
            'categories' => $categories,
            'expenseCategories' => $expenseCategories,
        ]);
    }

    /**
     * Update general settings.
     */
    public function updateGeneral(Request $request): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'logo_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'allow_negative_stock' => ['required', 'boolean'],
            'cost_price_includes_labour' => ['required', 'boolean'],
            'allow_price_edit' => ['required', 'boolean'],
            'show_success_rate' => ['required', 'boolean'],
        ]);

        $keys = [
            'general.shop_name' => $validated['shop_name'],
            'general.address' => $validated['address'] ?? '',
            'general.phone' => $validated['phone'] ?? '',
            'general.currency_symbol' => $validated['currency_symbol'],
            'inventory.allow_negative_stock' => $validated['allow_negative_stock'] ? '1' : '0',
            'payroll.cost_price_includes_labour' => $validated['cost_price_includes_labour'] ? '1' : '0',
            'sales.allow_price_edit' => $validated['allow_price_edit'] ? '1' : '0',
            'pathao.show_success_rate' => $validated['show_success_rate'] ? '1' : '0',
        ];

        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('branding', 'public');
            $keys['general.logo'] = "/storage/{$path}";
        }

        $oldValues = [];
        $newValues = [];

        foreach ($keys as $compositeKey => $value) {
            $oldValues[$compositeKey] = Setting::get($compositeKey);
            Setting::set($compositeKey, $value);
            $newValues[$compositeKey] = $value;
        }

        AuditService::log('general_settings_updated', Setting::class, $oldValues, $newValues);

        return back()->with('success', 'General settings updated successfully.');
    }

    /**
     * Display invoice branding and template settings.
     */
    public function invoice(): Response
    {
        $this->authorize('viewAny', Setting::class);

        $defaultSelectable = [
            'show_logo' => true,
            'show_shop_address' => true,
            'show_shop_phone' => true,
            'show_discount' => true,
            'show_delivery_charge' => true,
            'show_consignment_id' => true,
            'show_payment_methods' => true,
            'show_footer_note' => true,
            'show_terms' => true,
        ];

        $settings = [
            'shop_name' => Setting::get('general.shop_name', 'Tasa Leather Goods'),
            'shop_address' => Setting::get('general.address', 'Dhaka, Bangladesh'),
            'shop_phone' => Setting::get('general.phone', '+880 1700-000000'),
            'logo' => Setting::get('general.logo', ''),
            'prefix' => Setting::get('invoice.prefix', 'TASA'),
            'paper_size' => Setting::get('invoice.paper_size', 'A4'),
            'footer_note' => Setting::get('invoice.footer_note', 'Thank you for shopping with Tasa Leather Goods!'),
            'terms' => Setting::get('invoice.terms', 'Exchange within 7 days with invoice. No cash refunds.'),
            'selectable_fields' => Setting::get('invoice.selectable_fields', $defaultSelectable),
            'custom_template' => Setting::get('invoice.custom_template', null),
        ];

        return Inertia::render('settings/InvoiceSettings', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update invoice branding and template settings.
     */
    public function updateInvoice(Request $request): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $validated = $request->validate([
            'shop_name' => ['nullable', 'string', 'max:120'],
            'shop_address' => ['nullable', 'string', 'max:500'],
            'shop_phone' => ['nullable', 'string', 'max:50'],
            'logo_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'],
            'paper_size' => ['required', 'in:A4,A5,80mm'],
            'footer_note' => ['nullable', 'string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'selectable_fields' => ['nullable', 'array'],
            'custom_template' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (empty($value)) {
                    return;
                }

                // STRICT: No PHP, no script execution, no Blade expressions
                $forbiddenPatterns = [
                    '/<\?php/i',
                    '/<\?=/i',
                    '/<\?/i',
                    '/<script\b[^>]*>/i',
                    '/<iframe\b[^>]*>/i',
                    '/<embed\b[^>]*>/i',
                    '/<object\b[^>]*>/i',
                    '/\{\{--/i',
                    '/@php/i',
                    '/@include/i',
                    '/javascript:/i',
                    '/onload=/i',
                    '/onerror=/i',
                ];

                foreach ($forbiddenPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $fail('Custom template contains forbidden executable code or scripts. Only safe HTML/CSS placeholders allowed.');

                        return;
                    }
                }
            }],
        ]);

        $keys = [
            'invoice.prefix' => $validated['prefix'],
            'invoice.paper_size' => $validated['paper_size'],
            'invoice.footer_note' => $validated['footer_note'] ?? '',
            'invoice.terms' => $validated['terms'] ?? '',
            'invoice.custom_template' => $validated['custom_template'],
        ];

        if (array_key_exists('shop_name', $validated)) {
            $keys['general.shop_name'] = $validated['shop_name'];
        }
        if (array_key_exists('shop_address', $validated)) {
            $keys['general.address'] = $validated['shop_address'];
        }
        if (array_key_exists('shop_phone', $validated)) {
            $keys['general.phone'] = $validated['shop_phone'];
        }
        if (! empty($validated['selectable_fields'])) {
            $keys['invoice.selectable_fields'] = $validated['selectable_fields'];
        }

        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('branding', 'public');
            $keys['general.logo'] = "/storage/{$path}";
        }

        $oldValues = [];
        $newValues = [];

        foreach ($keys as $compositeKey => $value) {
            $oldValues[$compositeKey] = Setting::get($compositeKey);
            Setting::set($compositeKey, $value);
            $newValues[$compositeKey] = $value;
        }

        AuditService::log('invoice_settings_updated', Setting::class, $oldValues, $newValues);

        return back()->with('success', 'Invoice branding settings updated successfully.');
    }

    /**
     * Display Pathao Courier integration settings.
     */
    public function pathao(PathaoService $pathaoService): Response
    {
        $this->authorize('viewAny', Setting::class);

        $settings = [
            'store_id' => Setting::get('pathao.store_id', ''),
            'sender_name' => Setting::get('pathao.sender_name', ''),
            'sender_phone' => Setting::get('pathao.sender_phone', ''),
            'default_delivery_type' => Setting::get('pathao.default_delivery_type', '48'),
            'default_item_type' => Setting::get('pathao.default_item_type', '2'),
            'default_weight' => Setting::get('pathao.default_weight', '0.5'),
            'show_success_rate' => (bool) Setting::get('pathao.show_success_rate', '1'),
            'is_configured' => $pathaoService->isConfigured(),
        ];

        $connection = $pathaoService->getConnectionStatus();
        $stores = $pathaoService->getStores();
        $cities = $pathaoService->getCities();

        return Inertia::render('settings/PathaoSettings', [
            'settings' => $settings,
            'connection' => $connection,
            'stores' => $stores,
            'cities' => $cities,
        ]);
    }

    /**
     * Test connection to Pathao Merchant API.
     */
    public function testPathaoConnection(PathaoService $pathaoService): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return response()->json($pathaoService->testConnection());
    }

    /**
     * Create a new store on Pathao.
     */
    public function createPathaoStore(Request $request, PathaoService $pathaoService): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'city_id' => ['required', 'integer'],
            'zone_id' => ['required', 'integer'],
            'area_id' => ['required', 'integer'],
        ]);

        try {
            $store = $pathaoService->createStore($validated);
            AuditService::log('pathao_store_created', Setting::class, null, $store);

            return back()->with('success', "Store '{$validated['name']}' created on Pathao successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Update Pathao Courier settings.
     */
    public function updatePathao(Request $request): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $validated = $request->validate([
            'store_id' => ['nullable', 'string', 'max:50'],
            'sender_name' => ['nullable', 'string', 'max:100'],
            'sender_phone' => ['nullable', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'default_delivery_type' => ['required', 'in:48,12'],
            'default_item_type' => ['required', 'in:1,2'],
            'default_weight' => ['required', 'numeric', 'min:0.1', 'max:20'],
            'show_success_rate' => ['required', 'boolean'],
        ]);

        $keys = [
            'pathao.store_id' => $validated['store_id'] ?? '',
            'pathao.sender_name' => $validated['sender_name'] ?? '',
            'pathao.sender_phone' => $validated['sender_phone'] ?? '',
            'pathao.default_delivery_type' => $validated['default_delivery_type'],
            'pathao.default_item_type' => $validated['default_item_type'],
            'pathao.default_weight' => (string) $validated['default_weight'],
            'pathao.show_success_rate' => $validated['show_success_rate'] ? '1' : '0',
        ];

        $oldValues = [];
        $newValues = [];

        foreach ($keys as $compositeKey => $value) {
            $oldValues[$compositeKey] = Setting::get($compositeKey);
            Setting::set($compositeKey, $value);
            $newValues[$compositeKey] = $value;
        }

        AuditService::log('pathao_settings_updated', Setting::class, $oldValues, $newValues);

        return back()->with('success', 'Pathao courier settings updated successfully.');
    }

    /**
     * Store a product category.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:product_categories,name'],
        ]);

        $category = ProductCategory::create($validated);

        AuditService::log('product_category_created', $category, null, ['name' => $category->name]);

        return back()->with('success', "Category '{$category->name}' created successfully.");
    }

    /**
     * Delete a product category.
     */
    public function destroyCategory(ProductCategory $category): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        if ($category->products()->count() > 0) {
            return back()->withErrors([
                'error' => "Cannot delete category '{$category->name}' because products are assigned to it.",
            ]);
        }

        $catName = $category->name;
        $category->delete();

        AuditService::log('product_category_deleted', ProductCategory::class, ['name' => $catName], null);

        return back()->with('success', "Category '{$catName}' deleted successfully.");
    }
}
