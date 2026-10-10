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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('designation', 100)->nullable();
            $table->decimal('monthly_salary', 12, 2);
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('salary_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('period'); // first day of month: YYYY-MM-01
            $table->decimal('base_salary', 12, 2);
            $table->decimal('bonus', 12, 2)->default(0.00);
            $table->decimal('deduction', 12, 2)->default(0.00);
            $table->decimal('payable', 12, 2)->storedAs('base_salary + bonus - deduction');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'period']);
            $table->index('period');
        });

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('period');
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('type', 10)->default('salary'); // salary, advance
            $table->string('method', 20)->nullable(); // cash, bkash, bank_transfer
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'period']);
            $table->index('paid_on');
        });

        DB::statement('ALTER TABLE salary_payments ADD CONSTRAINT salary_payments_amount_check CHECK (amount > 0);');

        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('role', 100)->nullable(); // cutter, stitcher
            $table->string('wage_basis', 15); // per_day, per_piece, fixed_weekly
            $table->decimal('rate', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('worker_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->restrictOnDelete();
            $table->date('work_date');
            $table->date('period_start')->nullable();
            $table->decimal('qty', 8, 2)->default(1.00);
            $table->decimal('rate', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['worker_id', 'work_date']);
            $table->index('work_date');
        });

        DB::statement('ALTER TABLE worker_earnings ADD CONSTRAINT worker_earnings_values_check CHECK (qty > 0 AND amount >= 0);');

        Schema::create('worker_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->restrictOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('method', 20)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['worker_id', 'paid_on']);
        });

        DB::statement('ALTER TABLE worker_payments ADD CONSTRAINT worker_payments_amount_check CHECK (amount > 0);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worker_payments');
        Schema::dropIfExists('worker_earnings');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('salary_months');
        Schema::dropIfExists('employees');
    }
};
