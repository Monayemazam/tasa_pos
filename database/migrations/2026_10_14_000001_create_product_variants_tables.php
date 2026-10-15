<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add variation support flags to products table
        if (! Schema::hasColumn('products', 'has_variants')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('has_variants')->default(false)->after('name');
                $table->string('variation_type', 50)->nullable()->default('Color')->after('has_variants');
                $table->index('has_variants');
            });
        }

        // 2. Create product_variants table
        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('sku', 50)->unique();
                $table->string('color_name', 100)->nullable();
                $table->string('attribute_name', 50)->default('Color');
                $table->string('attribute_value', 100);
                $table->decimal('cost_price', 12, 2)->default(0.00);
                $table->decimal('selling_price', 12, 2)->default(0.00);
                $table->integer('stock_qty')->default(0);
                $table->unsignedInteger('min_stock')->default(0);
                $table->string('barcode', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['product_id', 'is_active']);
                $table->index('sku');
                $table->index('color_name');
                $table->index('attribute_value');
                $table->unique(['product_id', 'attribute_value', 'deleted_at'], 'product_attribute_unique');
            });

            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_prices_check CHECK (cost_price >= 0 AND selling_price >= 0);');
        }

        // 3. Add variant tracking to product_stock_movements
        if (! Schema::hasColumn('product_stock_movements', 'product_variant_id')) {
            Schema::table('product_stock_movements', function (Blueprint $table) {
                $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
                $table->index(['product_variant_id', 'movement_date']);
            });
        }

        // 4. Add variant tracking to sale_items
        if (! Schema::hasColumn('sale_items', 'product_variant_id')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
                $table->string('variant_name', 100)->nullable()->after('name');
                $table->string('variant_sku', 50)->nullable()->after('variant_name');
                $table->index('product_variant_id');
            });
        }

        // 5. Add variant tracking to stock_adjustments
        if (! Schema::hasColumn('stock_adjustments', 'product_variant_id')) {
            Schema::table('stock_adjustments', function (Blueprint $table) {
                $table->foreignId('product_variant_id')->nullable()->after('item_id')->constrained('product_variants')->nullOnDelete();
                $table->index('product_variant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('stock_adjustments', 'product_variant_id')) {
            Schema::table('stock_adjustments', function (Blueprint $table) {
                $table->dropForeign(['product_variant_id']);
                $table->dropIndex(['product_variant_id']);
                $table->dropColumn('product_variant_id');
            });
        }

        if (Schema::hasColumn('sale_items', 'product_variant_id')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropForeign(['product_variant_id']);
                $table->dropIndex(['product_variant_id']);
                $table->dropColumn(['product_variant_id', 'variant_name', 'variant_sku']);
            });
        }

        if (Schema::hasColumn('product_stock_movements', 'product_variant_id')) {
            Schema::table('product_stock_movements', function (Blueprint $table) {
                $table->dropForeign(['product_variant_id']);
                $table->dropIndex(['product_variant_id', 'movement_date']);
                $table->dropColumn('product_variant_id');
            });
        }

        Schema::dropIfExists('product_variants');

        if (Schema::hasColumn('products', 'has_variants')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['has_variants']);
                $table->dropColumn(['has_variants', 'variation_type']);
            });
        }
    }
};
