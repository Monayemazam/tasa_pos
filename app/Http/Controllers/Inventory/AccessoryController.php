<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustAccessoryStockRequest;
use App\Http\Requests\Inventory\StoreAccessoryConsumptionRequest;
use App\Http\Requests\Inventory\StoreAccessoryItemRequest;
use App\Http\Requests\Inventory\StoreAccessoryPurchaseRequest;
use App\Http\Requests\Inventory\UpdateAccessoryItemRequest;
use App\Models\AccessoryItem;
use App\Models\Setting;
use App\Services\Inventory\AccessoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessoryController extends Controller
{
    public function __construct(
        protected AccessoryService $accessoryService
    ) {}

    /**
     * Display the accessories inventory screen with Stock, Purchases, and Consumption tabs.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AccessoryItem::class);

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
        $selectedItemId = $request->input('accessory_item_id');

        // 1. Stock Tab Data
        $stockReport = $this->accessoryService->getStockReport($from, $to, $search, $lowStockOnly);

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
            $purchases = $this->accessoryService->getPurchases([
                'accessory_item_id' => $selectedItemId,
                'supplier' => $request->input('supplier'),
                'from' => $from,
                'to' => $to,
            ]);

            $purchases->through(function ($p) use ($canViewCost) {
                return [
                    'id' => $p->id,
                    'purchase_date' => $p->purchase_date?->format('d M Y'),
                    'raw_date' => $p->purchase_date?->format('Y-m-d'),
                    'item_id' => $p->accessory_item_id,
                    'item_name' => $p->item?->name,
                    'unit' => $p->item?->unit ?? 'pcs',
                    'supplier' => $p->supplier,
                    'qty' => (float) $p->qty,
                    'rate' => $canViewCost ? (float) $p->rate : null,
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
            $consumptions = $this->accessoryService->getConsumptions([
                'accessory_item_id' => $selectedItemId,
                'from' => $from,
                'to' => $to,
            ]);

            $consumptions->through(function ($c) {
                return [
                    'id' => $c->id,
                    'consumption_date' => $c->consumption_date?->format('d M Y'),
                    'raw_date' => $c->consumption_date?->format('Y-m-d'),
                    'item_id' => $c->accessory_item_id,
                    'item_name' => $c->item?->name,
                    'unit' => $c->item?->unit ?? 'pcs',
                    'qty' => (float) $c->qty,
                    'note' => $c->note,
                    'creator' => $c->creator?->name ?? 'System',
                ];
            });
        }

        // Dropdown options
        $allItems = AccessoryItem::orderBy('name')->get(['id', 'name', 'unit', 'is_active', 'min_stock']);

        // KPI Summary stats
        $stats = [
            'total_items' => AccessoryItem::count(),
            'active_items' => AccessoryItem::where('is_active', true)->count(),
            'low_stock_count' => $stockReport->filter(fn ($i) => $i['is_low_stock'])->count(),
            'total_stock_qty' => round($stockReport->sum('current_stock_qty'), 3),
            'total_stock_value' => $canViewCost ? round($stockReport->sum('stock_value'), 2) : null,
            'period_purchased_qty' => round($stockReport->sum('period_purchased_qty'), 3),
            'period_consumed_qty' => round($stockReport->sum('period_consumed_qty'), 3),
            'period_purchased_cost' => $canViewCost ? round($stockReport->sum('period_purchased_cost'), 2) : null,
        ];

        return Inertia::render('inventory/Accessories', [
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
                'accessory_item_id' => $selectedItemId,
                'supplier' => $request->input('supplier', ''),
            ],
            'canViewCost' => $canViewCost,
            'canManage' => $user->can('accessories.create') || $user->can('accessories.update'),
            'canDelete' => $user->can('accessories.delete'),
            'canAdjust' => $user->can('adjustments.create'),
            'stats' => $stats,
            'allowNegativeStock' => (string) Setting::get('inventory.allow_negative_stock', '0') === '1',
        ]);
    }

    /**
     * Create a new accessory catalogue item.
     */
    public function storeItem(StoreAccessoryItemRequest $request): RedirectResponse
    {
        $item = $this->accessoryService->createItem($request->validated(), $request->user());

        return back()->with('success', "Accessory item '{$item->name}' created.");
    }

    /**
     * Update an existing accessory item.
     */
    public function updateItem(UpdateAccessoryItemRequest $request, AccessoryItem $item): RedirectResponse
    {
        $this->accessoryService->updateItem($item, $request->validated(), $request->user());

        return back()->with('success', "Accessory item '{$item->name}' updated.");
    }

    /**
     * Soft-delete an accessory item.
     */
    public function destroyItem(AccessoryItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $name = $item->name;
        $this->accessoryService->deleteItem($item, request()->user());

        return back()->with('success', "Accessory item '{$name}' archived.");
    }

    /**
     * Record an accessory purchase entry.
     */
    public function storePurchase(StoreAccessoryPurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->accessoryService->recordPurchase($request->validated(), $request->user());

        return back()->with('success', sprintf(
            'Purchased %.3f %s of %s recorded.',
            $purchase->qty,
            $purchase->item?->unit ?? 'units',
            $purchase->item?->name ?? 'accessory'
        ));
    }

    /**
     * Record an accessory consumption entry.
     */
    public function storeConsumption(StoreAccessoryConsumptionRequest $request): RedirectResponse
    {
        $consumption = $this->accessoryService->recordConsumption($request->validated(), $request->user());

        return back()->with('success', sprintf(
            'Consumed %.3f %s of %s logged.',
            $consumption->qty,
            $consumption->item?->unit ?? 'units',
            $consumption->item?->name ?? 'accessory'
        ));
    }

    /**
     * Stock adjustment for accessory.
     */
    public function adjustStock(AdjustAccessoryStockRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $item = AccessoryItem::findOrFail($validated['accessory_item_id']);
        $adjustedOn = $validated['adjusted_on'] ?? $validated['date'];

        $this->accessoryService->adjustStock(
            $item,
            (float) $validated['qty'],
            $adjustedOn,
            $validated['reason'],
            $validated['note'] ?? null,
            $request->user()
        );

        $sign = $validated['qty'] > 0 ? "+{$validated['qty']}" : "{$validated['qty']}";

        return back()->with('success', "Accessory stock adjustment ({$sign} {$item->unit}) recorded for {$item->name}.");
    }

    /**
     * Get running stock movements ledger for a specific accessory item.
     */
    public function movements(AccessoryItem $item): JsonResponse
    {
        $this->authorize('view', $item);

        $movements = $this->accessoryService->getMovements($item);

        return response()->json([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'min_stock' => (float) $item->min_stock,
                'current_stock' => $this->accessoryService->getCurrentStock($item->id),
            ],
            'movements' => $movements,
        ]);
    }
}
