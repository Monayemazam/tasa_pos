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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20)->nullable(); // cash, bkash, bank_transfer, other
            $table->string('vendor', 150)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('source', 20)->default('manual'); // manual, pathao_charge, return_courier, recurring
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('courier_order_id')->nullable()->constrained('courier_orders')->nullOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained('sale_returns')->nullOnDelete();
            $table->foreignId('recurring_expense_id')->nullable()->constrained('recurring_expenses')->nullOnDelete();
            $table->boolean('fee_deducted_from_cod')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('expense_date');
            $table->index(['expense_category_id', 'expense_date']);
            $table->unique(['courier_order_id', 'source']);
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amount_check CHECK (amount > 0);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
