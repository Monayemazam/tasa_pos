<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Models\AccessoryItem;
use App\Models\LeatherItem;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Inventory\AccessoryService;
use App\Services\Inventory\LeatherService;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    public function __construct(
        protected StockAdjustmentService $adjustmentService,
        protected LeatherService $leatherService,
        protected AccessoryService $accessoryService
    ) {}

    /**
     * Display universal stock adjustments ledger.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user->can('adjustments.view')) {
            abort(403, 'Unauthorized.');
        }

        $filters = [
            'item_type' => $request->input('item_type', ''),
            'reason' => $request->input('reason', ''),
            'from' => $request->input('from') ?? $request->input('start_date'),
            'to' => $request->input('to') ?? $request->input('end_date'),
            'search' => trim((string) $request->input('search', '')),
        ];

        $adjustments = $this->adjustmentService->getAdjustments($filters);

        // Preload item options for quick modal adjustment
        $initialType = $filters['item_type'] ?: 'product';
        $items = $this->adjustmentService->getItemsByType($initialType);

        return Inertia::render('inventory/Adjustments', [
            'adjustments' => $adjustments,
            'filters' => $filters,
            'initialItems' => $items,
            'canCreate' => $user->can('adjustments.create'),
            'allowNegativeStock' => (string) Setting::get('inventory.allow_negative_stock', '0') === '1',
        ]);
    }

    /**
     * Store a universal stock adjustment entry.
     */
    public function store(AdjustStockRequest $request): RedirectResponse
    {
        $adjustment = $this->adjustmentService->createAdjustment($request->validated(), $request->user());

        $sign = $adjustment->qty > 0 ? "+{$adjustment->qty}" : "{$adjustment->qty}";

        return back()->with('success', "Stock adjustment ({$sign}) for {$adjustment->item_type} recorded successfully.");
    }

    /**
     * Fetch active items for a given item type for adjustment dropdowns.
     */
    public function getItemsByType(Request $request): JsonResponse
    {
        $type = $request->input('type', 'product');
        $items = $this->adjustmentService->getItemsByType($type);

        return response()->json([
            'type' => $type,
            'items' => $items,
        ]);
    }

    /**
     * Global inventory search across finished goods, leather, and accessories.
     */
    public function globalSearch(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        if (mb_strlen($query) < 1) {
            return response()->json(['results' => []]);
        }

        $user = $request->user();
        $canViewCost = $user->hasRole('Owner')
            || $user->can('inventory.view_costs')
            || $user->can('inventory.cost.view');

        $results = [];

        // 1. Products
        if ($user->can('products.view')) {
            $products = Product::where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get();

            foreach ($products as $p) {
                $isLowStock = $p->stock_qty <= $p->min_stock;
                $results[] = [
                    'id' => "product-{$p->id}",
                    'item_id' => $p->id,
                    'type' => 'product',
                    'type_label' => 'Finished Good',
                    'name' => $p->name,
                    'sub_title' => "SKU: {$p->sku}",
                    'unit' => 'pcs',
                    'current_stock' => (float) $p->stock_qty,
                    'min_stock' => (float) $p->min_stock,
                    'is_low_stock' => $isLowStock,
                    'selling_price' => (float) $p->selling_price,
                    'cost_price' => $canViewCost ? (float) $p->cost_price : null,
                    'url' => '/inventory/products?search='.urlencode($p->sku),
                ];
            }
        }

        // 2. Leather
        if ($user->can('leather.view')) {
            $leathers = LeatherItem::where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('colour_grade', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get();

            foreach ($leathers as $l) {
                $stock = $this->leatherService->getCurrentStock($l->id);
                $isLowStock = $stock <= (float) $l->min_stock;
                $results[] = [
                    'id' => "leather-{$l->id}",
                    'item_id' => $l->id,
                    'type' => 'leather',
                    'type_label' => 'Raw Leather',
                    'name' => $l->name,
                    'sub_title' => $l->colour_grade ? "Grade: {$l->colour_grade}" : 'Raw Material',
                    'unit' => 'sq ft',
                    'current_stock' => $stock,
                    'min_stock' => (float) $l->min_stock,
                    'is_low_stock' => $isLowStock,
                    'selling_price' => null,
                    'cost_price' => null,
                    'url' => '/inventory/leather?search='.urlencode($l->name),
                ];
            }
        }

        // 3. Accessories
        if ($user->can('accessories.view')) {
            $accessories = AccessoryItem::where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('unit', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get();

            foreach ($accessories as $a) {
                $stock = $this->accessoryService->getCurrentStock($a->id);
                $isLowStock = $stock <= (float) $a->min_stock;
                $results[] = [
                    'id' => "accessory-{$a->id}",
                    'item_id' => $a->id,
                    'type' => 'accessory',
                    'type_label' => 'Accessory',
                    'name' => $a->name,
                    'sub_title' => "Unit: {$a->unit}",
                    'unit' => $a->unit,
                    'current_stock' => $stock,
                    'min_stock' => (float) $a->min_stock,
                    'is_low_stock' => $isLowStock,
                    'selling_price' => null,
                    'cost_price' => null,
                    'url' => '/inventory/accessories?search='.urlencode($a->name),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }
}
