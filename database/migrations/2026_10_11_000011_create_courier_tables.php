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
        Schema::create('courier_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->restrictOnDelete();
            $table->string('merchant_order_id', 30);
            $table->string('consignment_id', 50)->nullable()->unique();
            $table->unsignedInteger('store_id');
            $table->unsignedTinyInteger('delivery_type')->default(48); // 48 Normal, 12 On-demand
            $table->unsignedTinyInteger('item_type')->default(2); // 2 Parcel
            $table->unsignedInteger('item_quantity');
            $table->decimal('item_weight', 6, 2); // kg
            $table->string('item_description', 255)->nullable();
            $table->string('special_instruction', 255)->nullable();
            $table->string('recipient_name', 120);
            $table->char('recipient_phone', 11);
            $table->string('recipient_address', 500);
            $table->unsignedInteger('recipient_city');
            $table->unsignedInteger('recipient_zone');
            $table->unsignedInteger('recipient_area');
            $table->decimal('cod_amount', 12, 2)->default(0.00);
            $table->decimal('courier_charge', 12, 2)->nullable();
            $table->string('status', 20)->default('queued'); // queued, failed, created, picked_up, in_transit, delivered, returned, on_hold, cancelled
            $table->string('raw_status', 60)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('cod_settled_at')->nullable();
            $table->decimal('cod_settled_amount', 12, 2)->nullable();
            $table->foreignId('cod_settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'last_synced_at']);
            $table->index(['cod_amount', 'delivered_at', 'cod_settled_at']);
        });

        Schema::create('courier_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_order_id')->constrained('courier_orders')->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('raw_status', 60)->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('logged_at');

            $table->index(['courier_order_id', 'logged_at']);
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->foreign('courier_order_id')
                ->references('id')
                ->on('courier_orders')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropForeign(['courier_order_id']);
        });

        Schema::dropIfExists('courier_status_logs');
        Schema::dropIfExists('courier_orders');
    }
};
