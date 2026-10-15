<?php

namespace App\Services\Inventory;

use App\Models\LeatherConsumption;
use App\Models\LeatherItem;
use App\Models\LeatherPurchase;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeatherService
{
    /**
     * Create a new leather item.
     */
    public function createItem(array $data, ?User $user = null): LeatherItem
    {
        $name = trim($data['name']);
        $colourGrade = ! empty($data['colour_grade']) ? trim($data['colour_grade']) : null;

        $exists = LeatherItem::withTrashed()
            ->where('name', $name)
            ->where('colour_grade', $colourGrade)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'A leather item with this name and colour/grade already exists.',
            ]);
        }

        $minStock = isset($data['min_stock']) ? (float) $data['min_stock'] : 0.000;
        if ($minStock < 0) {
            throw ValidationException::withMessages([
                'min_stock' => 'Minimum stock cannot be negative.',
            ]);
        }

        $item = LeatherItem::create([
            'name' => $name,
            'colour_grade' => $colourGrade,
            'min_stock' => $minStock,
            'is_active' => $data['is_active'] ?? true,
        ]);

        AuditService::log('created', $item, null, $item->toArray(), $user?->id);

        return $item;
    }

    /**
     * Update an existing leather item.
     */
    public function updateItem(LeatherItem $item, array $data, ?User $user = null): LeatherItem
    {
        $name = trim($data['name'] ?? $item->name);
        $colourGrade = array_key_exists('colour_grade', $data)
            ? (! empty($data['colour_grade']) ? trim($data['colour_grade']) : null)
            : $item->colour_grade;

        $exists = LeatherItem::withTrashed()
            ->where('name', $name)
            ->where('colour_grade', $colourGrade)
            ->where('id', '<>', $item->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Another leather item with this name and colour/grade already exists.',
            ]);
        }

        $minStock = isset($data['min_stock']) ? (float) $data['min_stock'] : (float) $item->min_stock;
        if ($minStock < 0) {
            throw ValidationException::withMessages([
                'min_stock' => 'Minimum stock cannot be negative.',
            ]);
        }

        $old = $item->toArray();
        $item->update([
            'name' => $name,
            'colour_grade' => $colourGrade,
            'min_stock' => $minStock,
            'is_active' => $data['is_active'] ?? $item->is_active,
        ]);

        AuditService::log('updated', $item, $old, $item->toArray(), $user?->id);

        return $item;
    }

    /**
     * Soft-delete a leather item.
     */
    public function deleteItem(LeatherItem $item, ?User $user = null): bool
    {
        $old = $item->toArray();
        $deleted = $item->delete();

        AuditService::log('deleted', $item, $old, null, $user?->id);

        return (bool) $deleted;
    }

    /**
     * Record a leather purchase entry.
     * Note: Does NOT create an expense row (PRD BR-5).
     */
    public function recordPurchase(array $data, ?User $user = null): LeatherPurchase
    {
        return DB::transaction(function () use ($data, $user) {
            $item = LeatherItem::where('id', $data['leather_item_id'])->lockForUpdate()->firstOrFail();

            $qty = (float) $data['qty_sqft'];
            $rate = (float) $data['rate_per_sqft'];
            $paid = isset($data['paid_amount']) ? (float) $data['paid_amount'] : 0.00;

            if ($qty <= 0) {
                throw ValidationException::withMessages(['qty_sqft' => 'Quantity must be greater than zero.']);
            }
            if ($rate < 0) {
                throw ValidationException::withMessages(['rate_per_sqft' => 'Rate per sq ft cannot be negative.']);
            }
            if ($paid < 0) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot be negative.']);
            }

            $totalCost = round($qty * $rate, 2);

            $purchase = LeatherPurchase::create([
                'leather_item_id' => $item->id,
                'purchase_date' => $data['purchase_date'],
                'supplier' => ! empty($data['supplier']) ? trim($data['supplier']) : null,
                'qty_sqft' => $qty,
                'rate_per_sqft' => $rate,
                'total_cost' => $totalCost,
                'paid_amount' => $paid,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'note' => ! empty($data['note']) ? trim($data['note']) : null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            AuditService::log('leather_purchase', $item, null, $purchase->toArray(), $user?->id);

            return $purchase;
        });
    }

    /**
     * Record a leather consumption entry.
     * Note: Manual entry only, does NOT create an expense row (PRD BR-7).
     */
    public function recordConsumption(array $data, ?User $user = null): LeatherConsumption
    {
        return DB::transaction(function () use ($data, $user) {
            $item = LeatherItem::where('id', $data['leather_item_id'])->lockForUpdate()->firstOrFail();

            $qty = (float) $data['qty_sqft'];
            if ($qty <= 0) {
                throw ValidationException::withMessages(['qty_sqft' => 'Quantity must be greater than zero.']);
            }

            $currentStock = $this->getCurrentStock($item->id);
            $newStock = $currentStock - $qty;

            if ($newStock < 0) {
                $allowNegative = (string) Setting::get('inventory.allow_negative_stock', '0');
                if ($allowNegative !== '1') {
                    throw ValidationException::withMessages([
                        'qty_sqft' => sprintf(
                            'Consumption of %.3f sq ft exceeds available stock (%.3f sq ft). Negative stock is prohibited.',
                            $qty,
                            $currentStock
                        ),
                    ]);
                }
            }

            $consumption = LeatherConsumption::create([
                'leather_item_id' => $item->id,
                'consumption_date' => $data['consumption_date'],
                'qty_sqft' => $qty,
                'note' => ! empty($data['note']) ? trim($data['note']) : null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            AuditService::log('leather_consumption', $item, [
                'current_stock' => $currentStock,
            ], [
                'new_stock' => $newStock,
                'consumed_qty' => $qty,
                'consumption_id' => $consumption->id,
            ], $user?->id);

            return $consumption;
        });
    }

    /**
     * Stock adjustment for leather.
     */
    public function adjustStock(
        LeatherItem $item,
        float $signedQty,
        string $date,
        string $reason,
        ?string $note = null,
        ?User $user = null
    ): StockAdjustment {
        if ($signedQty == 0) {
            throw ValidationException::withMessages(['qty' => 'Adjustment quantity cannot be zero.']);
        }

        $validReasons = ['damaged', 'lost', 'count_correction', 'other'];
        if (! in_array($reason, $validReasons, true)) {
            throw ValidationException::withMessages(['reason' => 'Invalid reason.']);
        }

        if ($reason === 'other' && empty(trim((string) $note))) {
            throw ValidationException::withMessages(['note' => 'A note is required when reason is Other.']);
        }

        return DB::transaction(function () use ($item, $signedQty, $date, $reason, $note, $user) {
            $lockedItem = LeatherItem::where('id', $item->id)->lockForUpdate()->firstOrFail();

            $currentStock = $this->getCurrentStock($lockedItem->id);
            $newStock = $currentStock + $signedQty;

            if ($newStock < 0) {
                $allowNegative = (string) Setting::get('inventory.allow_negative_stock', '0');
                if ($allowNegative !== '1') {
                    throw ValidationException::withMessages([
                        'qty' => sprintf(
                            'Adjustment of %+.3f sq ft results in negative stock (%.3f sq ft), which is prohibited.',
                            $signedQty,
                            $newStock
                        ),
                    ]);
                }
            }

            $adjustment = StockAdjustment::create([
                'item_type' => 'leather',
                'item_id' => $lockedItem->id,
                'qty' => $signedQty,
                'reason' => $reason,
                'note' => $note,
                'adjusted_on' => $date,
                'created_by' => $user?->id,
            ]);

            AuditService::log('leather_adjustment', $lockedItem, [
                'current_stock' => $currentStock,
            ], [
                'new_stock' => $newStock,
                'adjustment_qty' => $signedQty,
                'reason' => $reason,
                'adjustment_id' => $adjustment->id,
            ], $user?->id);

            return $adjustment;
        });
    }

    /**
     * Calculate current all-time stock of a leather item.
     * Formula: SUM(purchases) - SUM(consumptions) + SUM(adjustments)
     */
    public function getCurrentStock(int $itemId): float
    {
        $purchased = (float) LeatherPurchase::where('leather_item_id', $itemId)->whereNull('deleted_at')->sum('qty_sqft');
        $consumed = (float) LeatherConsumption::where('leather_item_id', $itemId)->whereNull('deleted_at')->sum('qty_sqft');
        $adjusted = (float) StockAdjustment::where('item_type', 'leather')->where('item_id', $itemId)->sum('qty');

        return round($purchased - $consumed + $adjusted, 3);
    }

    /**
     * Get stock report with weighted average cost and stock value.
     */
    public function getStockReport(
        ?string $from = null,
        ?string $to = null,
        ?string $search = null,
        bool $lowStockOnly = false
    ): Collection {
        $query = LeatherItem::query()->whereNull('deleted_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('colour_grade', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('name')->get();

        return $items->map(function (LeatherItem $item) use ($from, $to) {
            // All-time totals for current stock and weighted average
            $purchasedQty = (float) LeatherPurchase::where('leather_item_id', $item->id)->whereNull('deleted_at')->sum('qty_sqft');
            $purchasedCost = (float) LeatherPurchase::where('leather_item_id', $item->id)->whereNull('deleted_at')->sum('total_cost');
            $consumedQty = (float) LeatherConsumption::where('leather_item_id', $item->id)->whereNull('deleted_at')->sum('qty_sqft');
            $adjustedQty = (float) StockAdjustment::where('item_type', 'leather')->where('item_id', $item->id)->sum('qty');

            $currentStock = round($purchasedQty - $consumedQty + $adjustedQty, 3);
            $avgCost = $purchasedQty > 0 ? round($purchasedCost / $purchasedQty, 2) : 0.00;
            $stockValue = round($currentStock * $avgCost, 2);

            // Period-specific activity if date range is specified
            $periodPurchasesQuery = LeatherPurchase::where('leather_item_id', $item->id)->whereNull('deleted_at');
            $periodConsumptionsQuery = LeatherConsumption::where('leather_item_id', $item->id)->whereNull('deleted_at');

            if ($from && $to) {
                $periodPurchasesQuery->whereBetween('purchase_date', [$from, $to]);
                $periodConsumptionsQuery->whereBetween('consumption_date', [$from, $to]);
            }

            $periodPurchasedQty = (float) $periodPurchasesQuery->sum('qty_sqft');
            $periodPurchasedCost = (float) $periodPurchasesQuery->sum('total_cost');
            $periodConsumedQty = (float) $periodConsumptionsQuery->sum('qty_sqft');

            $isLowStock = (bool) ($item->is_active && $currentStock <= (float) $item->min_stock);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'colour_grade' => $item->colour_grade,
                'min_stock' => (float) $item->min_stock,
                'is_active' => (bool) $item->is_active,
                'total_purchased_sqft' => $purchasedQty,
                'total_consumed_sqft' => $consumedQty,
                'total_adjusted_sqft' => $adjustedQty,
                'current_stock_sqft' => $currentStock,
                'avg_cost' => $avgCost,
                'stock_value' => $stockValue,
                'is_low_stock' => $isLowStock,
                'period_purchased_sqft' => $periodPurchasedQty,
                'period_purchased_cost' => $periodPurchasedCost,
                'period_consumed_sqft' => $periodConsumedQty,
            ];
        })->filter(function ($item) use ($lowStockOnly) {
            return ! $lowStockOnly || $item['is_low_stock'];
        })->values();
    }

    /**
     * Get paginated purchases.
     */
    public function getPurchases(array $filters = []): LengthAwarePaginator
    {
        $query = LeatherPurchase::with(['item:id,name,colour_grade', 'creator:id,name'])
            ->whereNull('deleted_at')
            ->orderBy('purchase_date', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filters['leather_item_id'])) {
            $query->where('leather_item_id', $filters['leather_item_id']);
        }

        if (! empty($filters['supplier'])) {
            $query->where('supplier', 'like', "%{$filters['supplier']}%");
        }

        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $query->whereBetween('purchase_date', [$filters['from'], $filters['to']]);
        }

        return $query->paginate(25)->withQueryString();
    }

    /**
     * Get paginated consumptions.
     */
    public function getConsumptions(array $filters = []): LengthAwarePaginator
    {
        $query = LeatherConsumption::with(['item:id,name,colour_grade', 'creator:id,name'])
            ->whereNull('deleted_at')
            ->orderBy('consumption_date', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filters['leather_item_id'])) {
            $query->where('leather_item_id', $filters['leather_item_id']);
        }

        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $query->whereBetween('consumption_date', [$filters['from'], $filters['to']]);
        }

        return $query->paginate(25)->withQueryString();
    }

    /**
     * Get chronological history of movements (purchases, consumptions, adjustments) for an item.
     */
    public function getMovements(LeatherItem $item): array
    {
        $purchases = LeatherPurchase::where('leather_item_id', $item->id)
            ->whereNull('deleted_at')
            ->with('creator:id,name')
            ->get()
            ->map(fn ($p) => [
                'id' => "p-{$p->id}",
                'date' => $p->purchase_date->format('Y-m-d'),
                'display_date' => $p->purchase_date->format('d M Y'),
                'type' => 'purchase',
                'signed_qty' => (float) $p->qty_sqft,
                'note' => $p->supplier ? "Supplier: {$p->supplier}".($p->note ? " · {$p->note}" : '') : $p->note,
                'creator' => $p->creator?->name ?? 'System',
                'created_at' => $p->created_at?->format('d M Y, h:i A'),
            ]);

        $consumptions = LeatherConsumption::where('leather_item_id', $item->id)
            ->whereNull('deleted_at')
            ->with('creator:id,name')
            ->get()
            ->map(fn ($c) => [
                'id' => "c-{$c->id}",
                'date' => $c->consumption_date->format('Y-m-d'),
                'display_date' => $c->consumption_date->format('d M Y'),
                'type' => 'consumption',
                'signed_qty' => -((float) $c->qty_sqft),
                'note' => $c->note,
                'creator' => $c->creator?->name ?? 'System',
                'created_at' => $c->created_at?->format('d M Y, h:i A'),
            ]);

        $adjustments = StockAdjustment::where('item_type', 'leather')
            ->where('item_id', $item->id)
            ->with('creator:id,name')
            ->get()
            ->map(fn ($a) => [
                'id' => "a-{$a->id}",
                'date' => $a->adjusted_on->format('Y-m-d'),
                'display_date' => $a->adjusted_on->format('d M Y'),
                'type' => 'adjustment',
                'signed_qty' => (float) $a->qty,
                'note' => 'Reason: '.ucfirst(str_replace('_', ' ', $a->reason)).($a->note ? " · {$a->note}" : ''),
                'creator' => $a->creator?->name ?? 'System',
                'created_at' => $a->created_at?->format('d M Y, h:i A'),
            ]);

        // Merge and sort chronologically asc to compute running balance, then reverse for display
        $all = $purchases->concat($consumptions)->concat($adjustments)
            ->sortBy([
                ['date', 'asc'],
                ['created_at', 'asc'],
            ]);

        $runningBalance = 0.000;
        $ledger = [];
        foreach ($all as $movement) {
            $runningBalance = round($runningBalance + $movement['signed_qty'], 3);
            $movement['balance_after'] = $runningBalance;
            $ledger[] = $movement;
        }

        return array_reverse($ledger);
    }
}
