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
        Schema::create('accessory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('unit', 20); // pcs, set, meter, kg
            $table->decimal('min_stock', 12, 3)->default(0.000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accessory_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accessory_item_id')->constrained('accessory_items')->restrictOnDelete();
            $table->date('purchase_date');
            $table->string('supplier', 150)->nullable();
            $table->decimal('qty', 12, 3);
            $table->decimal('rate', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->string('payment_method', 20)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['accessory_item_id', 'purchase_date']);
        });

        DB::statement('ALTER TABLE accessory_purchases ADD CONSTRAINT accessory_purchases_values_check CHECK (qty > 0 AND rate >= 0 AND paid_amount >= 0);');

        Schema::create('accessory_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accessory_item_id')->constrained('accessory_items')->restrictOnDelete();
            $table->date('consumption_date');
            $table->decimal('qty', 12, 3);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['accessory_item_id', 'consumption_date']);
        });

        DB::statement('ALTER TABLE accessory_consumptions ADD CONSTRAINT accessory_consumptions_qty_check CHECK (qty > 0);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accessory_consumptions');
        Schema::dropIfExists('accessory_purchases');
        Schema::dropIfExists('accessory_items');
    }
};
