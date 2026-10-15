<?php

namespace App\Services\Inventory;

use App\Models\AccessoryConsumption;
use App\Models\AccessoryItem;
use App\Models\AccessoryPurchase;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessoryService
{
    /**
     * Create a new accessory item.
     */
    public function createItem(array $data, ?User $user = null): AccessoryItem
    {
        $name = trim($data['name']);

        $exists = AccessoryItem::withTrashed()
            ->where('name', $name)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'An accessory item with this name already exists.',
            ]);
        }

        $minStock = isset($data['min_stock']) ? (float) $data['min_stock'] : 0.000;
        if ($minStock < 0) {
            throw ValidationException::withMessages([
                'min_stock' => 'Minimum stock cannot be negative.',
            ]);
        }

        $unit = trim($data['unit'] ?? 'pcs');

        $item = AccessoryItem::create([
            'name' => $name,
            'unit' => $unit,
            'min_stock' => $minStock,
            'is_active' => $data['is_active'] ?? true,
        ]);

        AuditService::log('created', $item, null, $item->toArray(), $user?->id);

        return $item;
    }

    /**
     * Update an existing accessory item.
     */
    public function updateItem(AccessoryItem $item, array $data, ?User $user = null): AccessoryItem
    {
        $name = trim($data['name'] ?? $item->name);

        $exists = AccessoryItem::withTrashed()
            ->where('name', $name)
            ->where('id', '<>', $item->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Another accessory item with this name already exists.',
            ]);
        }

        $minStock = isset($data['min_stock']) ? (float) $data['min_stock'] : (float) $item->min_stock;
        if ($minStock < 0) {
            throw ValidationException::withMessages([
                'min_stock' => 'Minimum stock cannot be negative.',
            ]);
        }

        $unit = isset($data['unit']) ? trim($data['unit']) : $item->unit;

        $old = $item->toArray();
        $item->update([
            'name' => $name,
            'unit' => $unit,
            'min_stock' => $minStock,
            'is_active' => $data['is_active'] ?? $item->is_active,
        ]);

        AuditService::log('updated', $item, $old, $item->toArray(), $user?->id);

        return $item;
    }

    /**
     * Soft-delete an accessory item.
     */
    public function deleteItem(AccessoryItem $item, ?User $user = null): bool
    {
        $old = $item->toArray();
        $deleted = $item->delete();

        AuditService::log('deleted', $item, $old, null, $user?->id);

        return (bool) $deleted;
    }

    /**
     * Record an accessory purchase entry.
     * Note: Does NOT create an expense row (PRD BR-5).
     */
    public function recordPurchase(array $data, ?User $user = null): AccessoryPurchase
    {
        return DB::transaction(function () use ($data, $user) {
            $item = AccessoryItem::where('id', $data['accessory_item_id'])->lockForUpdate()->firstOrFail();

            $qty = (float) $data['qty'];
            $rate = (float) $data['rate'];
            $paid = isset($data['paid_amount']) ? (float) $data['paid_amount'] : 0.00;

            if ($qty <= 0) {
                throw ValidationException::withMessages(['qty' => 'Quantity must be greater than zero.']);
            }
            if ($rate < 0) {
                throw ValidationException::withMessages(['rate' => 'Rate cannot be negative.']);
            }
            if ($paid < 0) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot be negative.']);
            }

            $totalCost = round($qty * $rate, 2);

            $purchase = AccessoryPurchase::create([
                'accessory_item_id' => $item->id,
                'purchase_date' => $data['purchase_date'],
                'supplier' => ! empty($data['supplier']) ? trim($data['supplier']) : null,
                'qty' => $qty,
                'rate' => $rate,
                'total_cost' => $totalCost,
                'paid_amount' => $paid,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'note' => ! empty($data['note']) ? trim($data['note']) : null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            AuditService::log('accessory_purchase', $item, null, $purchase->toArray(), $user?->id);

            return $purchase;
        });
    }

    /**
     * Record an accessory consumption entry.
     * Note: Manual entry only, does NOT create an expense row (PRD BR-7).
     */
    public function recordConsumption(array $data, ?User $user = null): AccessoryConsumption
    {
        return DB::transaction(function () use ($data, $user) {
            $item = AccessoryItem::where('id', $data['accessory_item_id'])->lockForUpdate()->firstOrFail();

            $qty = (float) $data['qty'];
            if ($qty <= 0) {
                throw ValidationException::withMessages(['qty' => 'Quantity must be greater than zero.']);
            }

            $currentStock = $this->getCurrentStock($item->id);
            $newStock = $currentStock - $qty;

            if ($newStock < 0) {
                $allowNegative = (string) Setting::get('inventory.allow_negative_stock', '0');
                if ($allowNegative !== '1') {
                    throw ValidationException::withMessages([
                        'qty' => sprintf(
                            'Consumption of %.3f %s exceeds available stock (%.3f %s). Negative stock is prohibited.',
                            $qty,
                            $item->unit,
                            $currentStock,
                            $item->unit
                        ),
                    ]);
                }
            }

            $consumption = AccessoryConsumption::create([
                'accessory_item_id' => $item->id,
                'consumption_date' => $data['consumption_date'],
                'qty' => $qty,
                'note' => ! empty($data['note']) ? trim($data['note']) : null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            AuditService::log('accessory_consumption', $item, [
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
     * Stock adjustment for accessory.
     */
    public function adjustStock(
        AccessoryItem $item,
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
            throw ValidationException::withMessages(['reason' => 'Invalid adjustment reason.']);
        }

        if ($reason === 'other' && empty(trim((string) $note))) {
            throw ValidationException::withMessages(['note' => 'A note is required when reason is "Other".']);
        }

        return DB::transaction(function () use ($item, $signedQty, $date, $reason, $note, $user) {
            $lockedItem = AccessoryItem::where('id', $item->id)->lockForUpdate()->firstOrFail();

            $currentStock = $this->getCurrentStock($lockedItem->id);
            $newStock = $currentStock + $signedQty;

            if ($newStock < 0) {
                $allowNegative = (string) Setting::get('inventory.allow_negative_stock', '0');
                if ($allowNegative !== '1') {
                    throw ValidationException::withMessages([
                        'qty' => sprintf(
                            'Adjustment of %+.3f %s results in negative stock (%.3f %s), which is prohibited by system settings.',
                            $signedQty,
                            $lockedItem->unit,
                            $newStock,
                            $lockedItem->unit
                        ),
                    ]);
                }
            }

            $adjustment = StockAdjustment::create([
                'item_type' => 'accessory',
                'item_id' => $lockedItem->id,
                'qty' => $signedQty,
                'reason' => $reason,
                'note' => $note,
                'adjusted_on' => $date,
                'created_by' => $user?->id,
            ]);

            AuditService::log('accessory_adjustment', $lockedItem, [
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
     * Calculate current all-time stock of an accessory item.
     * Formula: SUM(purchases) - SUM(consumptions) + SUM(adjustments)
     */
    public function getCurrentStock(int $itemId): float
    {
        $purchased = (float) AccessoryPurchase::where('accessory_item_id', $itemId)->whereNull('deleted_at')->sum('qty');
        $consumed = (float) AccessoryConsumption::where('accessory_item_id', $itemId)->whereNull('deleted_at')->sum('qty');
        $adjusted = (float) StockAdjustment::where('item_type', 'accessory')->where('item_id', $itemId)->sum('qty');

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
        $query = AccessoryItem::query()->whereNull('deleted_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('unit', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('name')->get();

        return $items->map(function (AccessoryItem $item) use ($from, $to) {
            // All-time totals for current stock and weighted average
            $purchasedQty = (float) AccessoryPurchase::where('accessory_item_id', $item->id)->whereNull('deleted_at')->sum('qty');
            $purchasedCost = (float) AccessoryPurchase::where('accessory_item_id', $item->id)->whereNull('deleted_at')->sum('total_cost');
            $consumedQty = (float) AccessoryConsumption::where('accessory_item_id', $item->id)->whereNull('deleted_at')->sum('qty');
            $adjustedQty = (float) StockAdjustment::where('item_type', 'accessory')->where('item_id', $item->id)->sum('qty');

            $currentStock = round($purchasedQty - $consumedQty + $adjustedQty, 3);
            $avgCost = $purchasedQty > 0 ? round($purchasedCost / $purchasedQty, 2) : 0.00;
            $stockValue = round($currentStock * $avgCost, 2);

            // Period-specific activity if date range is specified
            $periodPurchasesQuery = AccessoryPurchase::where('accessory_item_id', $item->id)->whereNull('deleted_at');
            $periodConsumptionsQuery = AccessoryConsumption::where('accessory_item_id', $item->id)->whereNull('deleted_at');

            if ($from && $to) {
                $periodPurchasesQuery->whereBetween('purchase_date', [$from, $to]);
                $periodConsumptionsQuery->whereBetween('consumption_date', [$from, $to]);
            }

            $periodPurchasedQty = (float) $periodPurchasesQuery->sum('qty');
            $periodPurchasedCost = (float) $periodPurchasesQuery->sum('total_cost');
            $periodConsumedQty = (float) $periodConsumptionsQuery->sum('qty');

            $isLowStock = (bool) ($item->is_active && $currentStock <= (float) $item->min_stock);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'min_stock' => (float) $item->min_stock,
                'is_active' => (bool) $item->is_active,
                'total_purchased_qty' => $purchasedQty,
                'total_consumed_qty' => $consumedQty,
                'total_adjusted_qty' => $adjustedQty,
                'current_stock_qty' => $currentStock,
                'avg_cost' => $avgCost,
                'stock_value' => $stockValue,
                'is_low_stock' => $isLowStock,
                'period_purchased_qty' => $periodPurchasedQty,
                'period_purchased_cost' => $periodPurchasedCost,
                'period_consumed_qty' => $periodConsumedQty,
            ];
        })->filter(function ($item) use ($lowStockOnly) {
            if ($lowStockOnly) {
                return $item['is_low_stock'];
            }

            return true;
        })->values();
    }

    /**
     * Get paginated purchases for accessories.
     */
    public function getPurchases(array $filters = []): LengthAwarePaginator
    {
        $query = AccessoryPurchase::query()
            ->with(['item:id,name,unit', 'creator:id,name'])
            ->whereNull('deleted_at')
            ->orderBy('purchase_date', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filters['accessory_item_id'])) {
            $query->where('accessory_item_id', $filters['accessory_item_id']);
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
     * Get paginated consumptions for accessories.
     */
    public function getConsumptions(array $filters = []): LengthAwarePaginator
    {
        $query = AccessoryConsumption::query()
            ->with(['item:id,name,unit', 'creator:id,name'])
            ->whereNull('deleted_at')
            ->orderBy('consumption_date', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filters['accessory_item_id'])) {
            $query->where('accessory_item_id', $filters['accessory_item_id']);
        }

        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $query->whereBetween('consumption_date', [$filters['from'], $filters['to']]);
        }

        return $query->paginate(25)->withQueryString();
    }

    /**
     * Get chronological history of movements for an accessory item.
     */
    public function getMovements(AccessoryItem $item): array
    {
        $purchases = AccessoryPurchase::where('accessory_item_id', $item->id)
            ->whereNull('deleted_at')
            ->with('creator:id,name')
            ->get()
            ->map(fn ($p) => [
                'id' => "p-{$p->id}",
                'date' => $p->purchase_date->format('Y-m-d'),
                'display_date' => $p->purchase_date->format('d M Y'),
                'type' => 'purchase',
                'signed_qty' => (float) $p->qty,
                'note' => $p->supplier ? "Supplier: {$p->supplier}".($p->note ? " · {$p->note}" : '') : $p->note,
                'creator' => $p->creator?->name ?? 'System',
                'created_at' => $p->created_at?->format('d M Y, h:i A'),
            ]);

        $consumptions = AccessoryConsumption::where('accessory_item_id', $item->id)
            ->whereNull('deleted_at')
            ->with('creator:id,name')
            ->get()
            ->map(fn ($c) => [
                'id' => "c-{$c->id}",
                'date' => $c->consumption_date->format('Y-m-d'),
                'display_date' => $c->consumption_date->format('d M Y'),
                'type' => 'consumption',
                'signed_qty' => -((float) $c->qty),
                'note' => $c->note,
                'creator' => $c->creator?->name ?? 'System',
                'created_at' => $c->created_at?->format('d M Y, h:i A'),
            ]);

        $adjustments = StockAdjustment::where('item_type', 'accessory')
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
