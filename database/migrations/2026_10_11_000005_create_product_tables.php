<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name', 190);
            $table->string('sku', 50)->unique();
            $table->decimal('cost_price', 12, 2)->default(0.00);
            $table->decimal('selling_price', 12, 2)->default(0.00);
            $table->integer('stock_qty')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('product_category_id');
            $table->index('is_active');
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_prices_check CHECK (cost_price >= 0 AND selling_price >= 0);');

        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('type', 20); // opening, production, sale, sale_cancel, return, adjustment
            $table->integer('qty'); // signed (+/-)
            $table->integer('balance_after');
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('movement_date');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
        });

        DB::statement('ALTER TABLE product_stock_movements ADD CONSTRAINT product_stock_movements_qty_check CHECK (qty <> 0);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
