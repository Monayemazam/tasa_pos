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
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('item_type', 20); // product, leather, accessory
            $table->unsignedBigInteger('item_id');
            $table->decimal('qty', 12, 3); // signed (+/-)
            $table->string('reason', 30); // damaged, lost, count_correction, other
            $table->string('note', 255)->nullable();
            $table->date('adjusted_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_type', 'item_id', 'adjusted_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
