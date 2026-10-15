<?php

namespace App\Services\Inventory;

use App\Models\AccessoryItem;
use App\Models\LeatherItem;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(
        protected ProductService $productService,
        protected LeatherService $leatherService,
        protected AccessoryService $accessoryService
    ) {}

    /**
     * Create a universal stock adjustment.
     */
    public function createAdjustment(array $data, ?User $user = null): StockAdjustment
    {
        $itemType = trim((string) ($data['item_type'] ?? ''));
        $itemId = (int) ($data['item_id'] ?? 0);
        $qty = (float) ($data['qty'] ?? 0);
        $adjustedOn = $data['adjusted_on'] ?? $data['date'] ?? now()->toDateString();
        $reason = trim((string) ($data['reason'] ?? ''));
        $note = ! empty($data['note']) ? trim((string) $data['note']) : null;

        // 1. Validate Item Type
        if (! in_array($itemType, ['product', 'leather', 'accessory'], true)) {
            throw ValidationException::withMessages([
                'item_type' => 'Item type must be product, leather, or accessory.',
            ]);
        }

        // 2. Validate Signed Quantity
        if ($qty == 0) {
            throw ValidationException::withMessages([
                'qty' => 'Adjustment quantity cannot be zero.',
            ]);
        }

        // 3. Validate Reason & Note
        $validReasons = ['damaged', 'lost', 'count_correction', 'other'];
        if (! in_array($reason, $validReasons, true)) {
            throw ValidationException::withMessages([
                'reason' => 'Reason must be damaged, lost, count_correction, or other.',
            ]);
        }

        if ($reason === 'other' && empty($note)) {
            throw ValidationException::withMessages([
                'note' => 'A note is required when reason is "Other".',
            ]);
        }

        $variantId = ! empty($data['variant_id'])
            ? (int) $data['variant_id']
            : (! empty($data['product_variant_id']) ? (int) $data['product_variant_id'] : null);

        // 4. Safely validate and adjust based on polymorphic item type
        return match ($itemType) {
            'product' => $this->adjustProduct($itemId, (int) round($qty), $adjustedOn, $reason, $note, $user, $variantId),
            'leather' => $this->adjustLeather($itemId, $qty, $adjustedOn, $reason, $note, $user),
            'accessory' => $this->adjustAccessory($itemId, $qty, $adjustedOn, $reason, $note, $user),
        };
    }

    /**
     * Adjust finished product stock.
     */
    protected function adjustProduct(
        int $productId,
        int $signedQty,
        string $adjustedOn,
        string $reason,
        ?string $note,
        ?User $user,
        ?int $variantId = null
    ): StockAdjustment {
        $product = Product::where('id', $productId)->whereNull('deleted_at')->first();
        if (! $product) {
            throw ValidationException::withMessages([
                'item_id' => 'The selected product does not exist or has been archived.',
            ]);
        }

        return $this->productService->adjustStock(
            $product,
            $signedQty,
            $adjustedOn,
            $reason,
            $note,
            $user,
            $variantId
        );
    }

    /**
     * Adjust leather raw material stock.
     */
    protected function adjustLeather(
        int $leatherId,
        float $signedQty,
        string $adjustedOn,
        string $reason,
        ?string $note,
        ?User $user
    ): StockAdjustment {
        $item = LeatherItem::where('id', $leatherId)->whereNull('deleted_at')->first();
        if (! $item) {
            throw ValidationException::withMessages([
                'item_id' => 'The selected leather item does not exist or has been archived.',
            ]);
        }

        return $this->leatherService->adjustStock(
            $item,
            $signedQty,
            $adjustedOn,
            $reason,
            $note,
            $user
        );
    }

    /**
     * Adjust accessory stock.
     */
    protected function adjustAccessory(
        int $accessoryId,
        float $signedQty,
        string $adjustedOn,
        string $reason,
        ?string $note,
        ?User $user
    ): StockAdjustment {
        $item = AccessoryItem::where('id', $accessoryId)->whereNull('deleted_at')->first();
        if (! $item) {
            throw ValidationException::withMessages([
                'item_id' => 'The selected accessory item does not exist or has been archived.',
            ]);
        }

        return $this->accessoryService->adjustStock(
            $item,
            $signedQty,
            $adjustedOn,
            $reason,
            $note,
            $user
        );
    }

    /**
     * Fetch active items for dropdown selection by type.
     */
    public function getItemsByType(string $type): array
    {
        if ($type === 'product') {
            $products = Product::where('is_active', true)
                ->with(['variants' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('name')
                ->get();

            $list = [];
            foreach ($products as $p) {
                if ($p->has_variants && $p->variants->isNotEmpty()) {
                    foreach ($p->variants as $v) {
                        $list[] = [
                            'id' => $p->id,
                            'variant_id' => $v->id,
                            'name' => "{$p->name} — {$v->color_name} ({$v->sku})",
                            'unit' => 'pcs',
                            'current_stock' => (float) $v->stock_qty,
                            'min_stock' => (float) $v->min_stock,
                        ];
                    }
                } else {
                    $list[] = [
                        'id' => $p->id,
                        'variant_id' => null,
                        'name' => "{$p->name} ({$p->sku})",
                        'unit' => 'pcs',
                        'current_stock' => (float) $p->stock_qty,
                        'min_stock' => (float) $p->min_stock,
                    ];
                }
            }

            return $list;
        }

        return match ($type) {
            'leather' => LeatherItem::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'colour_grade', 'min_stock'])
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'name' => $l->colour_grade ? "{$l->name} — {$l->colour_grade}" : $l->name,
                    'unit' => 'sq ft',
                    'current_stock' => $this->leatherService->getCurrentStock($l->id),
                    'min_stock' => (float) $l->min_stock,
                ])
                ->all(),
            'accessory' => AccessoryItem::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'unit', 'min_stock'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => "{$a->name} ({$a->unit})",
                    'unit' => $a->unit,
                    'current_stock' => $this->accessoryService->getCurrentStock($a->id),
                    'min_stock' => (float) $a->min_stock,
                ])
                ->all(),
            default => [],
        };
    }

    /**
     * Get paginated adjustments log with polymorphic item details.
     */
    public function getAdjustments(array $filters = []): LengthAwarePaginator
    {
        $query = StockAdjustment::query()
            ->with(['creator:id,name', 'variant:id,color_name,sku'])
            ->orderBy('adjusted_on', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filters['item_type'])) {
            $query->where('item_type', $filters['item_type']);
        }

        if (! empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $query->whereBetween('adjusted_on', [$filters['from'], $filters['to']]);
        }

        $paginated = $query->paginate(25)->withQueryString();

        // Hydrate polymorphic item names and units
        $productIds = [];
        $leatherIds = [];
        $accessoryIds = [];

        foreach ($paginated->items() as $adj) {
            if ($adj->item_type === 'product') {
                $productIds[] = $adj->item_id;
            } elseif ($adj->item_type === 'leather') {
                $leatherIds[] = $adj->item_id;
            } elseif ($adj->item_type === 'accessory') {
                $accessoryIds[] = $adj->item_id;
            }
        }

        $products = Product::withTrashed()->whereIn('id', array_unique($productIds))->get()->keyBy('id');
        $leathers = LeatherItem::withTrashed()->whereIn('id', array_unique($leatherIds))->get()->keyBy('id');
        $accessories = AccessoryItem::withTrashed()->whereIn('id', array_unique($accessoryIds))->get()->keyBy('id');

        $paginated->through(function (StockAdjustment $adj) use ($products, $leathers, $accessories) {
            $itemName = 'Unknown Item';
            $unit = 'unit';

            if ($adj->item_type === 'product') {
                $prod = $products->get($adj->item_id);
                if ($adj->variant) {
                    $prodName = $prod ? $prod->name : "Product #{$adj->item_id}";
                    $itemName = "{$prodName} — {$adj->variant->color_name} ({$adj->variant->sku})";
                } else {
                    $itemName = $prod ? "{$prod->name} ({$prod->sku})" : "Product #{$adj->item_id}";
                }
                $unit = 'pcs';
            } elseif ($adj->item_type === 'leather') {
                $lea = $leathers->get($adj->item_id);
                $itemName = $lea ? ($lea->colour_grade ? "{$lea->name} — {$lea->colour_grade}" : $lea->name) : "Leather #{$adj->item_id}";
                $unit = 'sq ft';
            } elseif ($adj->item_type === 'accessory') {
                $acc = $accessories->get($adj->item_id);
                $itemName = $acc ? $acc->name : "Accessory #{$adj->item_id}";
                $unit = $acc?->unit ?? 'pcs';
            }

            return [
                'id' => $adj->id,
                'item_type' => $adj->item_type,
                'item_id' => $adj->item_id,
                'product_variant_id' => $adj->product_variant_id,
                'variant_name' => $adj->variant?->color_name,
                'variant_sku' => $adj->variant?->sku,
                'item_name' => $itemName,
                'unit' => $unit,
                'qty' => (float) $adj->qty,
                'reason' => $adj->reason,
                'note' => $adj->note,
                'adjusted_on' => $adj->adjusted_on?->format('d M Y'),
                'raw_date' => $adj->adjusted_on?->format('Y-m-d'),
                'creator' => $adj->creator?->name ?? 'System',
                'created_at' => $adj->created_at?->format('d M Y, h:i A'),
            ];
        });

        return $paginated;
    }
}
