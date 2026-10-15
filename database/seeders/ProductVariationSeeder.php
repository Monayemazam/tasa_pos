<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductVariationSeeder extends Seeder
{
    /**
     * Seed example parent product with color variations.
     * Product: Eyewear Case Series 1
     * Variant 1: Brown (12 pcs, ৳850)
     * Variant 2: Black (8 pcs, ৳850)
     */
    public function run(): void
    {
        $owner = User::first();

        $category = ProductCategory::firstOrCreate(
            ['name' => 'Eyewear Cases']
        );

        $product = Product::where('name', 'Eyewear Case Series 1')->first();

        if (! $product) {
            $product = Product::create([
                'name' => 'Eyewear Case Series 1',
                'sku' => 'EWC-S1',
                'product_category_id' => $category->id,
                'cost_price' => 450.00,
                'selling_price' => 850.00,
                'stock_qty' => 20, // 12 + 8
                'min_stock' => 5,
                'is_active' => true,
                'has_variants' => true,
                'variation_type' => 'Color',
                'created_by' => $owner?->id,
                'updated_by' => $owner?->id,
            ]);
        } else {
            $product->update([
                'has_variants' => true,
                'variation_type' => 'Color',
            ]);
        }

        // Variant 1: Brown
        $brown = ProductVariant::firstOrCreate(
            [
                'product_id' => $product->id,
                'attribute_value' => 'Brown',
            ],
            [
                'sku' => 'EWC-S1-BRN',
                'color_name' => 'Brown',
                'attribute_name' => 'Color',
                'cost_price' => 450.00,
                'selling_price' => 850.00,
                'stock_qty' => 12,
                'min_stock' => 3,
                'is_active' => true,
                'created_by' => $owner?->id,
                'updated_by' => $owner?->id,
            ]
        );

        if ($brown->wasRecentlyCreated) {
            ProductStockMovement::create([
                'product_id' => $product->id,
                'product_variant_id' => $brown->id,
                'movement_date' => now()->toDateString(),
                'type' => 'opening',
                'qty' => 12,
                'balance_after' => 12,
                'note' => 'Opening stock for Brown variant',
                'created_by' => $owner?->id,
            ]);
        }

        // Variant 2: Black
        $black = ProductVariant::firstOrCreate(
            [
                'product_id' => $product->id,
                'attribute_value' => 'Black',
            ],
            [
                'sku' => 'EWC-S1-BLK',
                'color_name' => 'Black',
                'attribute_name' => 'Color',
                'cost_price' => 450.00,
                'selling_price' => 850.00,
                'stock_qty' => 8,
                'min_stock' => 3,
                'is_active' => true,
                'created_by' => $owner?->id,
                'updated_by' => $owner?->id,
            ]
        );

        if ($black->wasRecentlyCreated) {
            ProductStockMovement::create([
                'product_id' => $product->id,
                'product_variant_id' => $black->id,
                'movement_date' => now()->toDateString(),
                'type' => 'opening',
                'qty' => 8,
                'balance_after' => 8,
                'note' => 'Opening stock for Black variant',
                'created_by' => $owner?->id,
            ]);
        }

        // Sync total parent stock
        $product->stock_qty = (int) $product->variants()->sum('stock_qty');
        $product->save();
    }
}
