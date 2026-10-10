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
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('pl_group', 30); // marketing_facebook, marketing_other, courier, packaging, rent_utilities, other
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('vendor', 150)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->boolean('is_active')->default(true);
            $table->char('last_confirmed_period', 7)->nullable(); // YYYY-MM
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('expense_categories');
    }
};
