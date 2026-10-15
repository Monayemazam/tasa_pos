<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustLeatherStockRequest;
use App\Http\Requests\Inventory\StoreLeatherConsumptionRequest;
use App\Http\Requests\Inventory\StoreLeatherItemRequest;
use App\Http\Requests\Inventory\StoreLeatherPurchaseRequest;
use App\Http\Requests\Inventory\UpdateLeatherItemRequest;
use App\Models\LeatherItem;
use App\Models\Setting;
use App\Services\Inventory\LeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeatherController extends Controller
{
    public function __construct(
        protected LeatherService $leatherService
    ) {}

    /**
     * Display the leather inventory screen with Stock, Purchases, and Consumption tabs.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeatherItem::class);

        $user = $request->user();
        $canViewCost = $user->hasRole('Owner')
            || $user->can('inventory.view_costs')
            || $user->can('inventory.cost.view');

        $activeTab = $request->input('tab', 'stock');
        if (! in_array($activeTab, ['stock', 'purchases', 'consumption'], true)) {
            $activeTab = 'stock';
        }

        $from = $request->input('from') ?? $request->input('start_date');
        $to = $request->input('to') ?? $request->input('end_date');
        $search = trim((string) $request->input('search', ''));
        $lowStockOnly = $request->boolean('low_stock');
        $selectedItemId = $request->input('leather_item_id');

        // 1. Stock Tab Data
        $stockReport = $this->leatherService->getStockReport($from, $to, $search, $lowStockOnly);

        // Strip sensitive cost fields if unauthorized
        $stockItems = $stockReport->map(function ($row) use ($canViewCost) {
            if (! $canViewCost) {
                $row['avg_cost'] = null;
                $row['stock_value'] = null;
                $row['period_purchased_cost'] = null;
            }

            return $row;
        });

        // 2. Purchases Tab Data
        $purchases = null;
        if ($activeTab === 'purchases') {
            $purchases = $this->leatherService->getPurchases([
                'leather_item_id' => $selectedItemId,
                'supplier' => $request->input('supplier'),
                'from' => $from,
                'to' => $to,
            ]);

            $purchases->through(function ($p) use ($canViewCost) {
                return [
                    'id' => $p->id,
                    'purchase_date' => $p->purchase_date?->format('d M Y'),
                    'raw_date' => $p->purchase_date?->format('Y-m-d'),
                    'item_id' => $p->leather_item_id,
                    'item_name' => $p->item?->name,
                    'colour_grade' => $p->item?->colour_grade,
                    'supplier' => $p->supplier,
                    'qty_sqft' => (float) $p->qty_sqft,
                    'rate_per_sqft' => $canViewCost ? (float) $p->rate_per_sqft : null,
                    'total_cost' => $canViewCost ? (float) $p->total_cost : null,
                    'paid_amount' => (float) $p->paid_amount,
                    'payment_method' => $p->payment_method,
                    'note' => $p->note,
                    'creator' => $p->creator?->name ?? 'System',
                ];
            });
        }

        // 3. Consumption Tab Data
        $consumptions = null;
        if ($activeTab === 'consumption') {
            $consumptions = $this->leatherService->getConsumptions([
                'leather_item_id' => $selectedItemId,
                'from' => $from,
                'to' => $to,
            ]);

            $consumptions->through(function ($c) {
                return [
                    'id' => $c->id,
                    'consumption_date' => $c->consumption_date?->format('d M Y'),
                    'raw_date' => $c->consumption_date?->format('Y-m-d'),
                    'item_id' => $c->leather_item_id,
                    'item_name' => $c->item?->name,
                    'colour_grade' => $c->item?->colour_grade,
                    'qty_sqft' => (float) $c->qty_sqft,
                    'note' => $c->note,
                    'creator' => $c->creator?->name ?? 'System',
                ];
            });
        }

        // Dropdown options
        $allItems = LeatherItem::orderBy('name')->get(['id', 'name', 'colour_grade', 'is_active', 'min_stock']);

        // KPI Summary stats
        $stats = [
            'total_items' => LeatherItem::count(),
            'active_items' => LeatherItem::where('is_active', true)->count(),
            'low_stock_count' => $stockReport->filter(fn ($i) => $i['is_low_stock'])->count(),
            'total_stock_sqft' => round($stockReport->sum('current_stock_sqft'), 3),
            'total_stock_value' => $canViewCost ? round($stockReport->sum('stock_value'), 2) : null,
            'period_purchased_sqft' => round($stockReport->sum('period_purchased_sqft'), 3),
            'period_consumed_sqft' => round($stockReport->sum('period_consumed_sqft'), 3),
            'period_purchased_cost' => $canViewCost ? round($stockReport->sum('period_purchased_cost'), 2) : null,
        ];

        return Inertia::render('inventory/Leather', [
            'activeTab' => $activeTab,
            'stockItems' => $stockItems,
            'purchases' => $purchases,
            'consumptions' => $consumptions,
            'allItems' => $allItems,
            'filters' => [
                'tab' => $activeTab,
                'from' => $from,
                'to' => $to,
                'search' => $search,
                'low_stock' => $lowStockOnly,
                'leather_item_id' => $selectedItemId,
                'supplier' => $request->input('supplier', ''),
            ],
            'canViewCost' => $canViewCost,
            'canManage' => $user->can('leather.create') || $user->can('leather.update'),
            'canDelete' => $user->can('leather.delete'),
            'canAdjust' => $user->can('adjustments.create'),
            'stats' => $stats,
            'allowNegativeStock' => (string) Setting::get('inventory.allow_negative_stock', '0') === '1',
        ]);
    }

    /**
     * Create a new leather catalogue item.
     */
    public function storeItem(StoreLeatherItemRequest $request): RedirectResponse
    {
        $item = $this->leatherService->createItem($request->validated(), $request->user());

        return back()->with('success', "Leather item '{$item->name}' created.");
    }

    /**
     * Update an existing leather item.
     */
    public function updateItem(UpdateLeatherItemRequest $request, LeatherItem $item): RedirectResponse
    {
        $this->leatherService->updateItem($item, $request->validated(), $request->user());

        return back()->with('success', "Leather item '{$item->name}' updated.");
    }

    /**
     * Soft-delete a leather item.
     */
    public function destroyItem(LeatherItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $name = $item->name;
        $this->leatherService->deleteItem($item, request()->user());

        return back()->with('success', "Leather item '{$name}' archived.");
    }

    /**
     * Record a leather purchase entry.
     */
    public function storePurchase(StoreLeatherPurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->leatherService->recordPurchase($request->validated(), $request->user());

        return back()->with('success', sprintf(
            'Purchased %.3f sq ft of %s recorded.',
            $purchase->qty_sqft,
            $purchase->item?->name ?? 'leather'
        ));
    }

    /**
     * Record a leather consumption entry.
     */
    public function storeConsumption(StoreLeatherConsumptionRequest $request): RedirectResponse
    {
        $consumption = $this->leatherService->recordConsumption($request->validated(), $request->user());

        return back()->with('success', sprintf(
            'Consumed %.3f sq ft of %s logged.',
            $consumption->qty_sqft,
            $consumption->item?->name ?? 'leather'
        ));
    }

    /**
     * Stock adjustment for leather.
     */
    public function adjustStock(AdjustLeatherStockRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $item = LeatherItem::findOrFail($validated['leather_item_id']);

        $adjustedOn = $validated['adjusted_on'] ?? $validated['date'];

        $this->leatherService->adjustStock(
            $item,
            (float) $validated['qty'],
            $adjustedOn,
            $validated['reason'],
            $validated['note'] ?? null,
            $request->user()
        );

        $sign = $validated['qty'] > 0 ? "+{$validated['qty']}" : "{$validated['qty']}";

        return back()->with('success', "Leather stock adjustment ({$sign} sq ft) recorded for {$item->name}.");
    }

    /**
     * Get running stock movements ledger for a specific leather item.
     */
    public function movements(LeatherItem $item): JsonResponse
    {
        $this->authorize('view', $item);

        $movements = $this->leatherService->getMovements($item);

        return response()->json([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'colour_grade' => $item->colour_grade,
                'min_stock' => (float) $item->min_stock,
                'current_stock' => $this->leatherService->getCurrentStock($item->id),
            ],
            'movements' => $movements,
        ]);
    }
}
