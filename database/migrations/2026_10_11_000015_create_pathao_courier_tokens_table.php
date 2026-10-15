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
        $tableName = env('PATHAO_DB_TABLE_NAME', 'pathao_courier_tokens');

        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->unsignedInteger('expires_in')->nullable();
                $table->string('token_type', 50)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = env('PATHAO_DB_TABLE_NAME', 'pathao_courier_tokens');
        Schema::dropIfExists($tableName);
    }
};
