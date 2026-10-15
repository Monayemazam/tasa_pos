<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Services\Courier\PathaoService;
use App\Services\Sales\CustomerService;
use App\Services\Sales\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function __construct(
        protected SaleService $saleService,
        protected CustomerService $customerService,
        protected PathaoService $pathaoService
    ) {
    }

    /**
     * Show POS New Sale terminal.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', \App\Models\Sale::class);

        $user = $request->user();
        $canEditPrice = $user->hasRole('Owner') || $user->can('sales.edit_price');
        $canViewCosts = $user->hasRole('Owner') || $user->can('inventory.view_costs') || $user->can('inventory.cost.view');

        // Fetch active products with categories and variants
        $products = Product::where('is_active', true)
            ->with([
                'category:id,name',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
            ])
            ->orderBy('name')
            ->get(['id', 'product_category_id', 'name', 'has_variants', 'variation_type', 'sku', 'cost_price', 'selling_price', 'stock_qty', 'min_stock'])
            ->map(function (Product $p) use ($canViewCosts) {
                $variants = $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'product_id' => $v->product_id,
                    'sku' => $v->sku,
                    'color_name' => $v->color_name,
                    'attribute_name' => $v->attribute_name,
                    'attribute_value' => $v->attribute_value,
                    'selling_price' => (float) $v->selling_price,
                    'cost_price' => $canViewCosts ? (float) $v->cost_price : null,
                    'stock_qty' => (int) $v->stock_qty,
                    'min_stock' => (int) $v->min_stock,
                    'is_low_stock' => $v->stock_qty <= $v->min_stock,
                    'is_active' => (bool) $v->is_active,
                ])->values()->all();

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'has_variants' => (bool) $p->has_variants,
                    'variation_type' => $p->variation_type ?: 'Color',
                    'sku' => $p->sku,
                    'category_id' => $p->product_category_id,
                    'category_name' => $p->category?->name,
                    'selling_price' => (float) $p->selling_price,
                    'cost_price' => $canViewCosts ? (float) $p->cost_price : null,
                    'stock_qty' => (int) $p->stock_qty,
                    'min_stock' => (int) $p->min_stock,
                    'is_low_stock' => $p->stock_qty <= $p->min_stock,
                    'variants' => $variants,
                ];
            });

        $categories = ProductCategory::orderBy('name')->get(['id', 'name']);

        $cities = $this->pathaoService->getCities();
        $defaultWeight = (float) Setting::get('pathao.default_weight', 0.5);
        $defaultDeliveryType = (int) Setting::get('pathao.default_delivery_type', 48);
        $allowNegativeStock = in_array(Setting::get('inventory.allow_negative_stock', '0'), ['1', 1, true], true);
        $showSuccessRate = in_array(Setting::get('pathao.show_success_rate', '1'), ['1', 1, true], true);

        return Inertia::render('sales/Pos', [
            'products' => $products,
            'categories' => $categories,
            'cities' => $cities,
            'canEditPrice' => $canEditPrice,
            'allowNegativeStock' => $allowNegativeStock,
            'defaultWeight' => $defaultWeight,
            'defaultDeliveryType' => $defaultDeliveryType,
            'showSuccessRate' => $showSuccessRate,
        ]);
    }

    /**
     * Store new POS Sale.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $result = $this->saleService->createSale($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'sale' => $result['sale'],
            'is_duplicate' => $result['is_duplicate'],
            'message' => $result['is_duplicate']
                ? 'Duplicate submission detected: returned existing invoice.'
                : 'Sale successfully saved and invoice created.',
        ]);
    }

    /**
     * Fast live phone lookup for customer auto-fill.
     */
    public function customerLookup(Request $request): JsonResponse
    {
        $phone = $request->query('phone', '');
        $customer = $this->customerService->lookup($phone);

        if (! $customer) {
            return response()->json([
                'found' => false,
                'customer' => null,
                'success_rate' => null,
            ]);
        }

        $successRate = $this->pathaoService->getCustomerSuccessRate($phone);

        return response()->json([
            'found' => true,
            'customer' => $customer,
            'success_rate' => $successRate,
        ]);
    }

    /**
     * Cascading Pathao zones for a city.
     */
    public function zones(Request $request): JsonResponse
    {
        $cityId = (int) $request->query('city_id', 0);
        if ($cityId <= 0) {
            return response()->json([]);
        }

        return response()->json($this->pathaoService->getZones($cityId));
    }

    /**
     * Cascading Pathao areas for a zone.
     */
    public function areas(Request $request): JsonResponse
    {
        $zoneId = (int) $request->query('zone_id', 0);
        if ($zoneId <= 0) {
            return response()->json([]);
        }

        return response()->json($this->pathaoService->getAreas($zoneId));
    }

    /**
     * Pathao customer delivery success-rate check.
     */
    public function successRate(Request $request): JsonResponse
    {
        $phone = $request->query('phone', '');

        return response()->json($this->pathaoService->getCustomerSuccessRate($phone));
    }
}
