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
        Schema::create('leather_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('colour_grade', 100)->nullable();
            $table->decimal('min_stock', 12, 3)->default(0.000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'colour_grade']);
        });

        Schema::create('leather_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leather_item_id')->constrained('leather_items')->restrictOnDelete();
            $table->date('purchase_date');
            $table->string('supplier', 150)->nullable();
            $table->decimal('qty_sqft', 12, 3);
            $table->decimal('rate_per_sqft', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->string('payment_method', 20)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['leather_item_id', 'purchase_date']);
            $table->index('purchase_date');
        });

        DB::statement('ALTER TABLE leather_purchases ADD CONSTRAINT leather_purchases_values_check CHECK (qty_sqft > 0 AND rate_per_sqft >= 0 AND paid_amount >= 0);');

        Schema::create('leather_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leather_item_id')->constrained('leather_items')->restrictOnDelete();
            $table->date('consumption_date');
            $table->decimal('qty_sqft', 12, 3);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['leather_item_id', 'consumption_date']);
        });

        DB::statement('ALTER TABLE leather_consumptions ADD CONSTRAINT leather_consumptions_qty_check CHECK (qty_sqft > 0);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leather_consumptions');
        Schema::dropIfExists('leather_purchases');
        Schema::dropIfExists('leather_items');
    }
};
