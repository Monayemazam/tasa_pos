<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    /**
     * Auto-generate sequential SKU in format P-000123.
     */
    public function generateSku(): string
    {
        $lastSku = Product::withTrashed()
            ->where('sku', 'LIKE', 'P-%')
            ->orderByRaw('LENGTH(sku) DESC, sku DESC')
            ->pluck('sku')
            ->filter(fn ($sku) => preg_match('/^P-(\d+)$/', $sku))
            ->first();

        if ($lastSku && preg_match('/^P-(\d+)$/', $lastSku, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        do {
            $sku = sprintf('P-%06d', $nextNumber);
            $existsInProducts = Product::withTrashed()->where('sku', $sku)->exists();
            $existsInVariants = ProductVariant::withTrashed()->where('sku', $sku)->exists();
            $exists = $existsInProducts || $existsInVariants;
            $nextNumber++;
        } while ($exists);

        return $sku;
    }

    /**
     * Auto-generate variant SKU based on parent SKU and color/attribute name.
     */
    public function generateVariantSku(string $parentSku, string $colorName): string
    {
        $cleanColor = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $colorName));
        $abbr = strlen($cleanColor) > 4 ? substr($cleanColor, 0, 3) : $cleanColor;
        if (empty($abbr)) {
            $abbr = 'VAR';
        }

        $base = "{$parentSku}-{$abbr}";
        $sku = $base;
        $counter = 1;

        while (
            Product::withTrashed()->where('sku', $sku)->exists() ||
            ProductVariant::withTrashed()->where('sku', $sku)->exists()
        ) {
            $sku = "{$base}-{$counter}";
            $counter++;
        }

        return $sku;
    }

    /**
     * Create a new product (with or without variants) and optional opening stock movement.
     */
    public function createProduct(array $data, ?User $user = null): Product
    {
        return DB::transaction(function () use ($data, $user) {
            $hasVariants = ! empty($data['has_variants']) || ! empty($data['variants']);
            $variationType = trim((string) ($data['variation_type'] ?? 'Color')) ?: 'Color';

            $sku = trim((string) ($data['sku'] ?? ''));
            if ($sku === '') {
                $sku = $this->generateSku();
            } else {
                if (Product::withTrashed()->where('sku', $sku)->exists() || ProductVariant::withTrashed()->where('sku', $sku)->exists()) {
                    throw ValidationException::withMessages([
                        'sku' => "SKU '{$sku}' already exists in database.",
                    ]);
                }
            }

            $costPrice = isset($data['cost_price']) && $data['cost_price'] !== '' ? (float) $data['cost_price'] : 0.00;
            $sellingPrice = isset($data['selling_price']) && $data['selling_price'] !== '' ? (float) $data['selling_price'] : 0.00;
            $minStock = isset($data['min_stock']) ? (int) $data['min_stock'] : 0;
            $openingStock = isset($data['opening_stock']) ? (int) $data['opening_stock'] : 0;

            if ($costPrice < 0 || $sellingPrice < 0) {
                throw ValidationException::withMessages([
                    'selling_price' => 'Prices cannot be negative.',
                ]);
            }

            if ($minStock < 0) {
                throw ValidationException::withMessages([
                    'min_stock' => 'Minimum stock cannot be negative.',
                ]);
            }

            if ($openingStock < 0) {
                throw ValidationException::withMessages([
                    'opening_stock' => 'Opening stock cannot be negative.',
                ]);
            }

            // Category resolution
            $categoryId = $data['product_category_id'] ?? null;
            if (! $categoryId && ! empty($data['category_name'])) {
                $category = ProductCategory::firstOrCreate([
                    'name' => trim($data['category_name']),
                ]);
                $categoryId = $category->id;
            }

            // Process variants validation if hasVariants is true
            $variantsInput = $data['variants'] ?? [];
            if ($hasVariants && empty($variantsInput)) {
                throw ValidationException::withMessages([
                    'variants' => 'Please provide at least one product variant when variation is enabled.',
                ]);
            }

            if ($hasVariants) {
                $seenColors = [];
                $seenSkus = [];
                foreach ($variantsInput as $idx => $v) {
                    $colorName = trim((string) ($v['color_name'] ?? $v['attribute_value'] ?? ''));
                    if ($colorName === '') {
                        throw ValidationException::withMessages([
                            "variants.{$idx}.color_name" => "Variant #".($idx + 1)." requires a {$variationType} name.",
                        ]);
                    }

                    $lowerColor = strtolower($colorName);
                    if (in_array($lowerColor, $seenColors, true)) {
                        throw ValidationException::withMessages([
                            "variants.{$idx}.color_name" => "Duplicate {$variationType} '{$colorName}' is not allowed for the same product.",
                        ]);
                    }
                    $seenColors[] = $lowerColor;

                    $vSku = trim((string) ($v['sku'] ?? ''));
                    if ($vSku !== '') {
                        $lowerSku = strtolower($vSku);
                        if (in_array($lowerSku, $seenSkus, true) || $lowerSku === strtolower($sku)) {
                            throw ValidationException::withMessages([
                                "variants.{$idx}.sku" => "Duplicate variant SKU '{$vSku}' in product form.",
                            ]);
                        }
                        $seenSkus[] = $lowerSku;

                        if (Product::withTrashed()->where('sku', $vSku)->exists() || ProductVariant::withTrashed()->where('sku', $vSku)->exists()) {
                            throw ValidationException::withMessages([
                                "variants.{$idx}.sku" => "SKU '{$vSku}' already exists in database.",
                            ]);
                        }
                    }

                    $vCost = isset($v['cost_price']) && $v['cost_price'] !== '' ? (float) $v['cost_price'] : 0.00;
                    $vSell = isset($v['selling_price']) && $v['selling_price'] !== '' ? (float) $v['selling_price'] : 0.00;
                    if ($vCost < 0 || $vSell < 0) {
                        throw ValidationException::withMessages([
                            "variants.{$idx}.selling_price" => 'Variant prices cannot be negative.',
                        ]);
                    }
                }

                // If parent prices were not explicitly specified, default to first variant prices
                if ($sellingPrice === 0.00 && isset($variantsInput[0]['selling_price'])) {
                    $sellingPrice = (float) $variantsInput[0]['selling_price'];
                }
                if ($costPrice === 0.00 && isset($variantsInput[0]['cost_price'])) {
                    $costPrice = (float) $variantsInput[0]['cost_price'];
                }
            }

            $product = Product::create([
                'product_category_id' => $categoryId,
                'name' => trim($data['name']),
                'has_variants' => $hasVariants,
                'variation_type' => $hasVariants ? $variationType : null,
                'sku' => $sku,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'stock_qty' => $hasVariants ? 0 : $openingStock,
                'min_stock' => $minStock,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            if ($hasVariants) {
                $totalOpeningStock = 0;
                foreach ($variantsInput as $v) {
                    $colorName = trim((string) ($v['color_name'] ?? $v['attribute_value'] ?? ''));
                    $vSku = trim((string) ($v['sku'] ?? ''));
                    if ($vSku === '') {
                        $vSku = $this->generateVariantSku($product->sku, $colorName);
                    }

                    $vCost = isset($v['cost_price']) && $v['cost_price'] !== '' ? (float) $v['cost_price'] : $costPrice;
                    $vSell = isset($v['selling_price']) && $v['selling_price'] !== '' ? (float) $v['selling_price'] : $sellingPrice;
                    $vOpening = isset($v['opening_stock']) ? (int) $v['opening_stock'] : 0;
                    $vMin = isset($v['min_stock']) ? (int) $v['min_stock'] : $minStock;
                    $vBarcode = isset($v['barcode']) ? trim((string) $v['barcode']) : null;

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $vSku,
                        'color_name' => $colorName,
                        'attribute_name' => $variationType,
                        'attribute_value' => $colorName,
                        'cost_price' => $vCost,
                        'selling_price' => $vSell,
                        'stock_qty' => $vOpening,
                        'min_stock' => $vMin,
                        'barcode' => $vBarcode ?: null,
                        'is_active' => $v['is_active'] ?? true,
                        'created_by' => $user?->id,
                        'updated_by' => $user?->id,
                    ]);

                    if ($vOpening > 0) {
                        ProductStockMovement::create([
                            'product_id' => $product->id,
                            'product_variant_id' => $variant->id,
                            'type' => 'opening',
                            'qty' => $vOpening,
                            'balance_after' => $vOpening,
                            'reference_type' => null,
                            'reference_id' => null,
                            'movement_date' => $data['opening_date'] ?? now()->toDateString(),
                            'note' => $v['opening_note'] ?? $data['opening_note'] ?? "Opening stock ({$colorName})",
                            'created_by' => $user?->id,
                        ]);
                        $totalOpeningStock += $vOpening;
                    }
                }

                $product->stock_qty = $totalOpeningStock;
                $product->save();
            } else {
                if ($openingStock > 0) {
                    ProductStockMovement::create([
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'type' => 'opening',
                        'qty' => $openingStock,
                        'balance_after' => $openingStock,
                        'reference_type' => null,
                        'reference_id' => null,
                        'movement_date' => $data['opening_date'] ?? now()->toDateString(),
                        'note' => $data['opening_note'] ?? 'Opening stock',
                        'created_by' => $user?->id,
                    ]);
                }
            }

            AuditService::log('created', $product, null, $product->load('variants')->toArray(), $user?->id);

            return $product->load('variants');
        });
    }

    /**
     * Update an existing product and its variants.
     */
    public function updateProduct(Product $product, array $data, ?User $user = null): Product
    {
        return DB::transaction(function () use ($product, $data, $user) {
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $oldValues = $lockedProduct->only([
                'name',
                'sku',
                'product_category_id',
                'has_variants',
                'variation_type',
                'cost_price',
                'selling_price',
                'min_stock',
                'is_active',
            ]);

            $costPrice = isset($data['cost_price']) && $data['cost_price'] !== ''
                ? (float) $data['cost_price']
                : (float) $lockedProduct->cost_price;
            $sellingPrice = isset($data['selling_price']) && $data['selling_price'] !== ''
                ? (float) $data['selling_price']
                : (float) $lockedProduct->selling_price;
            $minStock = isset($data['min_stock']) ? (int) $data['min_stock'] : (int) $lockedProduct->min_stock;

            if ($costPrice < 0 || $sellingPrice < 0) {
                throw ValidationException::withMessages([
                    'selling_price' => 'Prices cannot be negative.',
                ]);
            }

            if ($minStock < 0) {
                throw ValidationException::withMessages([
                    'min_stock' => 'Minimum stock cannot be negative.',
                ]);
            }

            $sku = trim((string) ($data['sku'] ?? $lockedProduct->sku));
            if ($sku === '') {
                $sku = $lockedProduct->sku;
            } else {
                $exists = Product::withTrashed()->where('sku', $sku)->where('id', '<>', $lockedProduct->id)->exists()
                    || ProductVariant::withTrashed()->where('sku', $sku)->where('product_id', '<>', $lockedProduct->id)->exists();
                if ($exists) {
                    throw ValidationException::withMessages([
                        'sku' => "SKU '{$sku}' is already assigned to another product or variant.",
                    ]);
                }
            }

            $categoryId = array_key_exists('product_category_id', $data)
                ? $data['product_category_id']
                : $lockedProduct->product_category_id;

            if (! $categoryId && ! empty($data['category_name'])) {
                $category = ProductCategory::firstOrCreate([
                    'name' => trim($data['category_name']),
                ]);
                $categoryId = $category->id;
            }

            $hasVariants = array_key_exists('has_variants', $data)
                ? (bool) $data['has_variants']
                : (bool) $lockedProduct->has_variants;

            $variationType = trim((string) ($data['variation_type'] ?? $lockedProduct->variation_type ?? 'Color')) ?: 'Color';

            // Variant updates
            if ($hasVariants && isset($data['variants']) && is_array($data['variants'])) {
                $variantsInput = $data['variants'];
                $seenColors = [];
                $seenSkus = [];

                foreach ($variantsInput as $idx => $v) {
                    $colorName = trim((string) ($v['color_name'] ?? $v['attribute_value'] ?? ''));
                    if ($colorName === '') {
                        throw ValidationException::withMessages([
                            "variants.{$idx}.color_name" => "Variant requires a {$variationType} name.",
                        ]);
                    }

                    $lowerColor = strtolower($colorName);
                    if (in_array($lowerColor, $seenColors, true)) {
                        throw ValidationException::withMessages([
                            "variants.{$idx}.color_name" => "Duplicate {$variationType} '{$colorName}' is not allowed.",
                        ]);
                    }
                    $seenColors[] = $lowerColor;

                    $vId = $v['id'] ?? null;
                    $vSku = trim((string) ($v['sku'] ?? ''));
                    if ($vSku !== '') {
                        $lowerSku = strtolower($vSku);
                        if (in_array($lowerSku, $seenSkus, true) || $lowerSku === strtolower($sku)) {
                            throw ValidationException::withMessages([
                                "variants.{$idx}.sku" => "Duplicate variant SKU '{$vSku}' in product form.",
                            ]);
                        }
                        $seenSkus[] = $lowerSku;

                        $skuQuery = ProductVariant::withTrashed()->where('sku', $vSku);
                        if ($vId) {
                            $skuQuery->where('id', '<>', $vId);
                        }
                        if ($skuQuery->exists() || Product::withTrashed()->where('sku', $vSku)->where('id', '<>', $lockedProduct->id)->exists()) {
                            throw ValidationException::withMessages([
                                "variants.{$idx}.sku" => "SKU '{$vSku}' already exists in database.",
                            ]);
                        }
                    }
                }

                // Apply variant updates
                foreach ($variantsInput as $v) {
                    $vId = $v['id'] ?? null;
                    $colorName = trim((string) ($v['color_name'] ?? $v['attribute_value'] ?? ''));
                    $vCost = isset($v['cost_price']) && $v['cost_price'] !== '' ? (float) $v['cost_price'] : $costPrice;
                    $vSell = isset($v['selling_price']) && $v['selling_price'] !== '' ? (float) $v['selling_price'] : $sellingPrice;
                    $vMin = isset($v['min_stock']) ? (int) $v['min_stock'] : $minStock;
                    $vActive = isset($v['is_active']) ? (bool) $v['is_active'] : true;
                    $vBarcode = isset($v['barcode']) ? trim((string) $v['barcode']) : null;

                    if ($vId) {
                        $variant = ProductVariant::where('product_id', $lockedProduct->id)
                            ->where('id', $vId)
                            ->first();

                        if ($variant) {
                            $vSku = trim((string) ($v['sku'] ?? $variant->sku)) ?: $variant->sku;
                            // Note: We do NOT silently reset stock_qty when updating existing variant.
                            $variant->update([
                                'sku' => $vSku,
                                'color_name' => $colorName,
                                'attribute_name' => $variationType,
                                'attribute_value' => $colorName,
                                'cost_price' => $vCost,
                                'selling_price' => $vSell,
                                'min_stock' => $vMin,
                                'barcode' => $vBarcode ?: null,
                                'is_active' => $vActive,
                                'updated_by' => $user?->id,
                            ]);
                        }
                    } else {
                        // Brand new variant added during edit
                        $vSku = trim((string) ($v['sku'] ?? ''));
                        if ($vSku === '') {
                            $vSku = $this->generateVariantSku($lockedProduct->sku, $colorName);
                        }

                        $vOpening = isset($v['opening_stock']) ? (int) $v['opening_stock'] : 0;

                        $variant = ProductVariant::create([
                            'product_id' => $lockedProduct->id,
                            'sku' => $vSku,
                            'color_name' => $colorName,
                            'attribute_name' => $variationType,
                            'attribute_value' => $colorName,
                            'cost_price' => $vCost,
                            'selling_price' => $vSell,
                            'stock_qty' => $vOpening,
                            'min_stock' => $vMin,
                            'barcode' => $vBarcode ?: null,
                            'is_active' => $vActive,
                            'created_by' => $user?->id,
                            'updated_by' => $user?->id,
                        ]);

                        if ($vOpening > 0) {
                            ProductStockMovement::create([
                                'product_id' => $lockedProduct->id,
                                'product_variant_id' => $variant->id,
                                'type' => 'opening',
                                'qty' => $vOpening,
                                'balance_after' => $vOpening,
                                'reference_type' => null,
                                'reference_id' => null,
                                'movement_date' => now()->toDateString(),
                                'note' => "Opening stock ({$colorName})",
                                'created_by' => $user?->id,
                            ]);
                        }
                    }
                }
            }

            // Sync parent stock if variants exist
            $parentStock = $hasVariants
                ? (int) ProductVariant::where('product_id', $lockedProduct->id)->sum('stock_qty')
                : $lockedProduct->stock_qty;

            $lockedProduct->update([
                'name' => trim($data['name'] ?? $lockedProduct->name),
                'sku' => $sku,
                'has_variants' => $hasVariants,
                'variation_type' => $hasVariants ? $variationType : null,
                'product_category_id' => $categoryId,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'stock_qty' => $parentStock,
                'min_stock' => $minStock,
                'is_active' => $data['is_active'] ?? $lockedProduct->is_active,
                'updated_by' => $user?->id,
            ]);

            $newValues = $lockedProduct->load('variants')->toArray();
            AuditService::log('updated', $lockedProduct, $oldValues, $newValues, $user?->id);

            return $lockedProduct;
        });
    }

    /**
     * Inline update for minimum stock only.
     */
    public function updateMinStock(Product $product, int $minStock, ?User $user = null): Product
    {
        if ($minStock < 0) {
            throw ValidationException::withMessages([
                'min_stock' => 'Minimum stock cannot be negative.',
            ]);
        }

        return DB::transaction(function () use ($product, $minStock, $user) {
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();
            $old = ['min_stock' => $lockedProduct->min_stock];

            $lockedProduct->min_stock = $minStock;
            $lockedProduct->updated_by = $user?->id;
            $lockedProduct->save();

            AuditService::log('updated', $lockedProduct, $old, ['min_stock' => $minStock], $user?->id);

            return $lockedProduct;
        });
    }

    /**
     * Soft-delete/archive a product and its variants.
     */
    public function deleteProduct(Product $product, ?User $user = null): bool
    {
        return DB::transaction(function () use ($product, $user) {
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();
            $snapshot = $lockedProduct->load('variants')->toArray();

            // Soft delete variants as well
            ProductVariant::where('product_id', $lockedProduct->id)->delete();
            $deleted = $lockedProduct->delete();

            AuditService::log('deleted', $lockedProduct, $snapshot, null, $user?->id);

            return (bool) $deleted;
        });
    }

    /**
     * Add stock / production entry (supports parent product or specific variant).
     */
    public function addProductionEntry(
        Product $product,
        int $qty,
        string $date,
        ?string $note = null,
        ?User $user = null,
        ?int $variantId = null
    ): ProductStockMovement {
        if ($qty <= 0) {
            throw ValidationException::withMessages([
                'qty' => 'Quantity must be a positive whole number.',
            ]);
        }

        return DB::transaction(function () use ($product, $qty, $date, $note, $user, $variantId) {
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $variant = null;
            if ($variantId) {
                $variant = ProductVariant::where('product_id', $lockedProduct->id)
                    ->where('id', $variantId)
                    ->lockForUpdate()
                    ->firstOrFail();
            } elseif ($lockedProduct->has_variants) {
                // If product has variants, require a variant
                throw ValidationException::withMessages([
                    'variant_id' => "Please select which {$lockedProduct->variation_type} variant to add production stock to.",
                ]);
            }

            if ($variant) {
                $newVariantBalance = $variant->stock_qty + $qty;
                $variant->stock_qty = $newVariantBalance;
                $variant->updated_by = $user?->id;
                $variant->save();

                $newParentBalance = (int) ProductVariant::where('product_id', $lockedProduct->id)->sum('stock_qty');
                $lockedProduct->stock_qty = $newParentBalance;
                $lockedProduct->updated_by = $user?->id;
                $lockedProduct->save();

                $movement = ProductStockMovement::create([
                    'product_id' => $lockedProduct->id,
                    'product_variant_id' => $variant->id,
                    'type' => 'production',
                    'qty' => $qty,
                    'balance_after' => $newVariantBalance,
                    'reference_type' => null,
                    'reference_id' => null,
                    'movement_date' => $date,
                    'note' => $note ?: "Production entry ({$variant->color_name})",
                    'created_by' => $user?->id,
                ]);

                AuditService::log('production_entry', $lockedProduct, [
                    'variant_id' => $variant->id,
                    'variant_stock' => $variant->stock_qty - $qty,
                ], [
                    'variant_id' => $variant->id,
                    'variant_stock' => $newVariantBalance,
                    'added_qty' => $qty,
                    'movement_id' => $movement->id,
                ], $user?->id);

                return $movement;
            }

            // Standard non-variant product
            $newBalance = $lockedProduct->stock_qty + $qty;

            $movement = ProductStockMovement::create([
                'product_id' => $lockedProduct->id,
                'product_variant_id' => null,
                'type' => 'production',
                'qty' => $qty,
                'balance_after' => $newBalance,
                'reference_type' => null,
                'reference_id' => null,
                'movement_date' => $date,
                'note' => $note,
                'created_by' => $user?->id,
            ]);

            $oldStock = $lockedProduct->stock_qty;
            $lockedProduct->stock_qty = $newBalance;
            $lockedProduct->updated_by = $user?->id;
            $lockedProduct->save();

            AuditService::log('production_entry', $lockedProduct, [
                'stock_qty' => $oldStock,
            ], [
                'stock_qty' => $newBalance,
                'added_qty' => $qty,
                'movement_id' => $movement->id,
            ], $user?->id);

            return $movement;
        });
    }

    /**
     * Stock adjustment for finished goods (supports parent product or specific variant).
     */
    public function adjustStock(
        Product $product,
        int $signedQty,
        string $date,
        string $reason,
        ?string $note = null,
        ?User $user = null,
        ?int $variantId = null
    ): StockAdjustment {
        if ($signedQty === 0) {
            throw ValidationException::withMessages([
                'qty' => 'Adjustment quantity cannot be zero.',
            ]);
        }

        $validReasons = ['damaged', 'lost', 'count_correction', 'other'];
        if (! in_array($reason, $validReasons, true)) {
            throw ValidationException::withMessages([
                'reason' => 'Invalid adjustment reason.',
            ]);
        }

        if ($reason === 'other' && empty(trim((string) $note))) {
            throw ValidationException::withMessages([
                'note' => 'A note is required when reason is "Other".',
            ]);
        }

        return DB::transaction(function () use ($product, $signedQty, $date, $reason, $note, $user, $variantId) {
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $variant = null;
            if ($variantId) {
                $variant = ProductVariant::where('product_id', $lockedProduct->id)
                    ->where('id', $variantId)
                    ->lockForUpdate()
                    ->firstOrFail();
            } elseif ($lockedProduct->has_variants) {
                throw ValidationException::withMessages([
                    'variant_id' => "Please select which {$lockedProduct->variation_type} variant to adjust.",
                ]);
            }

            $allowNegative = in_array(Setting::get('inventory.allow_negative_stock', '0'), ['1', 1, true], true);

            if ($variant) {
                $newVariantBalance = $variant->stock_qty + $signedQty;
                if ($newVariantBalance < 0 && ! $allowNegative) {
                    throw ValidationException::withMessages([
                        'qty' => "Adjustment would result in negative stock for '{$variant->color_name}' ({$newVariantBalance}), which is prohibited by system settings.",
                    ]);
                }

                $adjustment = StockAdjustment::create([
                    'item_type' => 'product',
                    'item_id' => $lockedProduct->id,
                    'product_variant_id' => $variant->id,
                    'qty' => $signedQty,
                    'reason' => $reason,
                    'note' => $note,
                    'adjusted_on' => $date,
                    'created_by' => $user?->id,
                ]);

                $movement = ProductStockMovement::create([
                    'product_id' => $lockedProduct->id,
                    'product_variant_id' => $variant->id,
                    'type' => 'adjustment',
                    'qty' => $signedQty,
                    'balance_after' => $newVariantBalance,
                    'reference_type' => 'stock_adjustments',
                    'reference_id' => $adjustment->id,
                    'movement_date' => $date,
                    'note' => $note ?: ucfirst(str_replace('_', ' ', $reason)),
                    'created_by' => $user?->id,
                ]);

                $variant->stock_qty = $newVariantBalance;
                $variant->updated_by = $user?->id;
                $variant->save();

                $newParentBalance = (int) ProductVariant::where('product_id', $lockedProduct->id)->sum('stock_qty');
                $lockedProduct->stock_qty = $newParentBalance;
                $lockedProduct->updated_by = $user?->id;
                $lockedProduct->save();

                AuditService::log('stock_adjustment', $lockedProduct, [
                    'variant_id' => $variant->id,
                    'stock_qty' => $variant->stock_qty - $signedQty,
                ], [
                    'variant_id' => $variant->id,
                    'stock_qty' => $newVariantBalance,
                    'adjusted_qty' => $signedQty,
                    'reason' => $reason,
                    'adjustment_id' => $adjustment->id,
                    'movement_id' => $movement->id,
                ], $user?->id);

                return $adjustment;
            }

            // Standard non-variant adjustment
            $newBalance = $lockedProduct->stock_qty + $signedQty;
            if ($newBalance < 0 && ! $allowNegative) {
                throw ValidationException::withMessages([
                    'qty' => "Adjustment would result in negative stock ({$newBalance}), which is prohibited by system settings.",
                ]);
            }

            $adjustment = StockAdjustment::create([
                'item_type' => 'product',
                'item_id' => $lockedProduct->id,
                'product_variant_id' => null,
                'qty' => $signedQty,
                'reason' => $reason,
                'note' => $note,
                'adjusted_on' => $date,
                'created_by' => $user?->id,
            ]);

            $movement = ProductStockMovement::create([
                'product_id' => $lockedProduct->id,
                'product_variant_id' => null,
                'type' => 'adjustment',
                'qty' => $signedQty,
                'balance_after' => $newBalance,
                'reference_type' => 'stock_adjustments',
                'reference_id' => $adjustment->id,
                'movement_date' => $date,
                'note' => $note ?: ucfirst(str_replace('_', ' ', $reason)),
                'created_by' => $user?->id,
            ]);

            $oldStock = $lockedProduct->stock_qty;
            $lockedProduct->stock_qty = $newBalance;
            $lockedProduct->updated_by = $user?->id;
            $lockedProduct->save();

            AuditService::log('stock_adjustment', $lockedProduct, [
                'stock_qty' => $oldStock,
            ], [
                'stock_qty' => $newBalance,
                'adjusted_qty' => $signedQty,
                'reason' => $reason,
                'adjustment_id' => $adjustment->id,
                'movement_id' => $movement->id,
            ], $user?->id);

            return $adjustment;
        });
    }
}
