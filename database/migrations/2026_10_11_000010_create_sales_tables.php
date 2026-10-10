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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique();
            $table->char('idempotency_token', 36)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('customer_name', 120);
            $table->char('customer_phone', 11);
            $table->string('customer_address', 500);
            $table->unsignedInteger('city_id')->nullable();
            $table->unsignedInteger('zone_id')->nullable();
            $table->unsignedInteger('area_id')->nullable();
            $table->string('city_name', 100)->nullable();
            $table->string('zone_name', 100)->nullable();
            $table->string('area_name', 100)->nullable();
            $table->date('sale_date');
            $table->dateTime('sold_at');
            $table->decimal('subtotal', 12, 2);
            $table->string('discount_type', 10)->default('flat'); // flat, percent
            $table->decimal('discount_value', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('delivery_charge', 12, 2)->default(0.00);
            $table->decimal('grand_total', 12, 2);
            $table->decimal('paid_total', 12, 2)->default(0.00);
            $table->decimal('due_amount', 12, 2)->storedAs('grand_total - paid_total');
            $table->string('payment_status', 10); // unpaid, partial, paid
            $table->string('status', 20)->default('active'); // active, cancelled, partially_returned, returned
            $table->string('courier_mode', 10)->default('pathao'); // pathao, none
            $table->string('notes', 500)->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('sale_date');
            $table->index('customer_id');
            $table->index(['status', 'sale_date']);
            $table->index('payment_status');
            $table->index('customer_phone');
        });

        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_totals_check CHECK (grand_total >= 0 AND paid_total >= 0);');

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('is_custom')->default(false);
            $table->string('name', 190);
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0.00);
            $table->decimal('line_total', 12, 2);
            $table->unsignedInteger('returned_qty')->default(0);

            $table->index('sale_id');
            $table->index('product_id');
        });

        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_qty_check CHECK (qty > 0 AND returned_qty <= qty);');

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('method', 20); // cash, cod, bkash, bank_transfer
            $table->string('reference', 100)->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('courier_order_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('sale_id');
            $table->index('paid_on');
        });

        DB::statement('ALTER TABLE sale_payments ADD CONSTRAINT sale_payments_amount_check CHECK (amount > 0);');

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->date('returned_on');
            $table->string('reason', 255);
            $table->decimal('return_value', 12, 2);
            $table->decimal('refunded_amount', 12, 2)->default(0.00);
            $table->boolean('restock')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('sale_id');
            $table->index('returned_on');
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
