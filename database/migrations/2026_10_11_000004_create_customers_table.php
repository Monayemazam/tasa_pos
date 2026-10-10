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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->char('phone', 11)->unique();
            $table->char('alt_phone', 11)->nullable();
            $table->string('address', 500);
            $table->unsignedInteger('pathao_city_id')->nullable();
            $table->unsignedInteger('pathao_zone_id')->nullable();
            $table->unsignedInteger('pathao_area_id')->nullable();
            $table->string('city_name', 100)->nullable();
            $table->string('zone_name', 100)->nullable();
            $table->string('area_name', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
