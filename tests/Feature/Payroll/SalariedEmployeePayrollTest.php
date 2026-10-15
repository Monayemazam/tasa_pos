<?php

namespace Tests\Feature\Payroll;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\SalaryMonth;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\QueryException;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(ExpenseCategorySeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->owner = User::factory()->create([
        'name' => 'Tasa Owner',
        'email' => 'owner@tasa.com',
    ]);
    $this->owner->assignSingleRole('Owner');

    $this->staff = User::factory()->create([
        'name' => 'Tasa Staff',
        'email' => 'staff@tasa.com',
    ]);
    $this->staff->assignSingleRole('Staff');

    $this->payrollService = app(PayrollService::class);
});

test('staff user receives 403 on all payroll routes and sensitive data cannot be retrieved', function () {
    $employee = Employee::create([
        'name' => 'Test Employee',
        'phone' => '01711111111',
        'designation' => 'Artisan',
        'monthly_salary' => 25000.00,
        'is_active' => true,
    ]);

    // 1. Staff accessing payroll routes -> 403
    $this->actingAs($this->staff)->get('/payroll/employees')->assertStatus(403);
    $this->actingAs($this->staff)->get("/payroll/employees/{$employee->id}")->assertStatus(403);
    $this->actingAs($this->staff)->get("/payroll/employees/{$employee->id}/statement")->assertStatus(403);

    // 2. Staff attempting to create an employee -> 403
    $this->actingAs($this->staff)->post('/payroll/employees', [
        'name' => 'Intruder Employee',
        'monthly_salary' => 30000,
        'is_active' => true,
    ])->assertStatus(403);

    // 3. Staff attempting to generate sheets -> 403
    $this->actingAs($this->staff)->post('/payroll/months/generate', [
        'period' => '2026-10',
    ])->assertStatus(403);

    // 4. Staff attempting to record payment -> 403
    $this->actingAs($this->staff)->post('/payroll/payments', [
        'employee_id' => $employee->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-15',
        'amount' => 5000,
        'type' => 'salary',
    ])->assertStatus(403);
});

test('owner can create, update, and soft-delete employees with audit trail', function () {
    // 1. Create employee
    $response = $this->actingAs($this->owner)->post('/payroll/employees', [
        'name' => 'Kabir Hossain',
        'phone' => '01812345678',
        'designation' => 'Pattern Cutter',
        'monthly_salary' => 28000.00,
        'joined_at' => '2026-01-15',
        'is_active' => true,
    ]);
    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $employee = Employee::where('name', 'Kabir Hossain')->first();
    expect($employee)->not->toBeNull()
        ->and((float) $employee->monthly_salary)->toBe(28000.00)
        ->and($employee->is_active)->toBeTrue();

    // Verify audit log for creation
    expect(AuditLog::where('event', 'employee.created')->where('auditable_id', $employee->id)->exists())->toBeTrue();

    // 2. Update employee
    $updateResponse = $this->actingAs($this->owner)->put("/payroll/employees/{$employee->id}", [
        'name' => 'Kabir Hossain',
        'phone' => '01812345678',
        'designation' => 'Senior Pattern Cutter',
        'monthly_salary' => 32000.00,
        'joined_at' => '2026-01-15',
        'is_active' => true,
    ]);
    $updateResponse->assertSessionHasNoErrors();

    $employee->refresh();
    expect($employee->designation)->toBe('Senior Pattern Cutter')
        ->and((float) $employee->monthly_salary)->toBe(32000.00);

    // Verify audit log for update
    expect(AuditLog::where('event', 'employee.updated')->where('auditable_id', $employee->id)->exists())->toBeTrue();

    // 3. Soft-delete (archive) employee
    $deleteResponse = $this->actingAs($this->owner)->delete("/payroll/employees/{$employee->id}");
    $deleteResponse->assertRedirect();

    $employee->refresh();
    expect($employee->trashed())->toBeTrue()
        ->and($employee->is_active)->toBeFalse();

    // Verify audit log for archive
    expect(AuditLog::where('event', 'employee.archived')->where('auditable_id', $employee->id)->exists())->toBeTrue();

    // 4. Restore employee
    $restoreResponse = $this->actingAs($this->owner)->post("/payroll/employees/{$employee->id}/restore");
    $restoreResponse->assertRedirect();

    $employee->refresh();
    expect($employee->trashed())->toBeFalse()
        ->and($employee->is_active)->toBeTrue();

    expect(AuditLog::where('event', 'employee.restored')->where('auditable_id', $employee->id)->exists())->toBeTrue();
});

test('generates salary_months rows with payable formula and enforces unique constraint', function () {
    $emp = Employee::create([
        'name' => 'Tarek Rahman',
        'phone' => '01712345678',
        'designation' => 'Finisher',
        'monthly_salary' => 24000.00,
        'joined_at' => '2026-01-01',
        'is_active' => true,
    ]);

    // Generate monthly sheet for 2026-10
    $count = $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);
    expect($count)->toBe(1);

    $salaryMonth = SalaryMonth::where('employee_id', $emp->id)
        ->where('period', '2026-10-01')
        ->first();

    expect($salaryMonth)->not->toBeNull()
        ->and((float) $salaryMonth->base_salary)->toBe(24000.00)
        ->and((float) $salaryMonth->bonus)->toBe(0.00)
        ->and((float) $salaryMonth->deduction)->toBe(0.00)
        ->and((float) $salaryMonth->payable)->toBe(24000.00);

    // Re-running generation does not duplicate rows (idempotent)
    $secondCount = $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);
    expect($secondCount)->toBe(0);

    // Enforce DB unique constraint
    expect(function () use ($emp) {
        SalaryMonth::create([
            'employee_id' => $emp->id,
            'period' => '2026-10-01',
            'base_salary' => 24000.00,
        ]);
    })->toThrow(QueryException::class);
});

test('editable base salary for a specific month changes payable without altering profile salary', function () {
    $emp = Employee::create([
        'name' => 'Salma Begum',
        'designation' => 'Stitcher',
        'monthly_salary' => 20000.00,
        'is_active' => true,
    ]);

    $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);

    $sm = SalaryMonth::where('employee_id', $emp->id)->where('period', '2026-10-01')->first();

    // Owner edits base salary for October to 22,000, bonus = 3,000, deduction = 1,000
    $response = $this->actingAs($this->owner)->put("/payroll/months/{$sm->id}", [
        'base_salary' => 22000.00,
        'bonus' => 3000.00,
        'deduction' => 1000.00,
        'note' => 'Temporary festival increase and overtime',
    ]);
    $response->assertSessionHasNoErrors();

    $sm->refresh();
    // Payable = 22,000 + 3,000 - 1,000 = 24,000
    expect((float) $sm->base_salary)->toBe(22000.00)
        ->and((float) $sm->bonus)->toBe(3000.00)
        ->and((float) $sm->deduction)->toBe(1000.00)
        ->and((float) $sm->payable)->toBe(24000.00);

    // Employee profile monthly salary must remain 20,000
    $emp->refresh();
    expect((float) $emp->monthly_salary)->toBe(20000.00);

    // Audit log written
    expect(AuditLog::where('event', 'salary_month.updated')->where('auditable_id', $sm->id)->exists())->toBeTrue();
});

test('verifies hand calculations for no payment, partial payment, irregular payments, advance, overpayment and carry-forward', function () {
    // -------------------------------------------------------------
    // Scenario 1: Employee with NO payment
    // -------------------------------------------------------------
    $emp1 = Employee::create([
        'name' => 'Worker Unpaid',
        'monthly_salary' => 20000.00,
        'is_active' => true,
    ]);
    $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);

    $sheet = $this->payrollService->getMonthlySheet('2026-10-01');
    $row1 = collect($sheet['rows'])->firstWhere('employee_id', $emp1->id);

    expect($row1['payable'])->toBe(20000.0)
        ->and($row1['paid_in_month'])->toBe(0.0)
        ->and($row1['month_balance'])->toBe(20000.0)
        ->and($row1['carry_forward'])->toBe(0.0)
        ->and($row1['net_balance'])->toBe(20000.0)
        ->and($row1['status'])->toBe('unpaid');

    // -------------------------------------------------------------
    // Scenario 2: Employee with PARTIAL payment
    // -------------------------------------------------------------
    $emp2 = Employee::create([
        'name' => 'Worker Partial',
        'monthly_salary' => 30000.00,
        'is_active' => true,
    ]);
    $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);

    // Two partial payments
    $this->payrollService->recordPayment([
        'employee_id' => $emp2->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-05',
        'amount' => 10000.00,
        'type' => 'salary',
        'method' => 'cash',
    ], $this->owner->id);

    $this->payrollService->recordPayment([
        'employee_id' => $emp2->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-20',
        'amount' => 8000.00,
        'type' => 'salary',
        'method' => 'bkash',
    ], $this->owner->id);

    $sheet = $this->payrollService->getMonthlySheet('2026-10-01');
    $row2 = collect($sheet['rows'])->firstWhere('employee_id', $emp2->id);

    // Hand calc: Payable = 30000, Paid = 18000, Month balance = 12000, Net = 12000
    expect($row2['payable'])->toBe(30000.0)
        ->and($row2['paid_in_month'])->toBe(18000.0)
        ->and($row2['month_balance'])->toBe(12000.0)
        ->and($row2['carry_forward'])->toBe(0.0)
        ->and($row2['net_balance'])->toBe(12000.0)
        ->and($row2['status'])->toBe('partially_paid');

    // -------------------------------------------------------------
    // Scenario 3: Multiple irregular payments totaling exact payable
    // -------------------------------------------------------------
    $emp3 = Employee::create([
        'name' => 'Worker Fully Paid',
        'monthly_salary' => 25000.00,
        'is_active' => true,
    ]);
    $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);
    $sm3 = SalaryMonth::where('employee_id', $emp3->id)->where('period', '2026-10-01')->first();
    $sm3->update(['bonus' => 2000.00, 'deduction' => 1000.00]); // Payable = 26000

    $this->payrollService->recordPayment([
        'employee_id' => $emp3->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-02',
        'amount' => 6000.00,
        'type' => 'salary',
    ], $this->owner->id);

    $this->payrollService->recordPayment([
        'employee_id' => $emp3->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-15',
        'amount' => 10000.00,
        'type' => 'salary',
    ], $this->owner->id);

    $this->payrollService->recordPayment([
        'employee_id' => $emp3->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-28',
        'amount' => 10000.00,
        'type' => 'salary',
    ], $this->owner->id);

    $sheet = $this->payrollService->getMonthlySheet('2026-10-01');
    $row3 = collect($sheet['rows'])->firstWhere('employee_id', $emp3->id);

    // Hand calc: Payable = 26000, Paid = 26000, Month balance = 0, Net = 0
    expect($row3['payable'])->toBe(26000.0)
        ->and($row3['paid_in_month'])->toBe(26000.0)
        ->and($row3['month_balance'])->toBe(0.0)
        ->and($row3['net_balance'])->toBe(0.0)
        ->and($row3['status'])->toBe('fully_paid');

    // -------------------------------------------------------------
    // Scenario 4: Advance / Overpayment and Next-Month Carry-Forward
    // -------------------------------------------------------------
    $emp4 = Employee::create([
        'name' => 'Worker Advance',
        'monthly_salary' => 25000.00,
        'is_active' => true,
    ]);

    // September 2026:
    $this->payrollService->generateMonthlySheets('2026-09-01', $this->owner->id);

    // Pay full salary 25,000 + Advance 10,000 = 35,000
    $this->payrollService->recordPayment([
        'employee_id' => $emp4->id,
        'period' => '2026-09-01',
        'paid_on' => '2026-09-01',
        'amount' => 25000.00,
        'type' => 'salary',
    ], $this->owner->id);

    $this->payrollService->recordPayment([
        'employee_id' => $emp4->id,
        'period' => '2026-09-01',
        'paid_on' => '2026-09-25',
        'amount' => 10000.00,
        'type' => 'advance',
    ], $this->owner->id);

    // Check September Sheet:
    $sepSheet = $this->payrollService->getMonthlySheet('2026-09-01');
    $rowSep = collect($sepSheet['rows'])->firstWhere('employee_id', $emp4->id);

    // Hand calc: Payable = 25000, Paid = 35000, Month bal = -10000, Net bal = -10000
    expect($rowSep['payable'])->toBe(25000.0)
        ->and($rowSep['paid_in_month'])->toBe(35000.0)
        ->and($rowSep['month_balance'])->toBe(-10000.0)
        ->and($rowSep['carry_forward'])->toBe(0.0)
        ->and($rowSep['net_balance'])->toBe(-10000.0)
        ->and($rowSep['status'])->toBe('over_paid')
        ->and($rowSep['status_tone'])->toBe('advance'); // Violet tone

    // October 2026: Next-Month Carry-Forward
    $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);

    // Before any payment in October:
    $octSheetBefore = $this->payrollService->getMonthlySheet('2026-10-01');
    $rowOctBefore = collect($octSheetBefore['rows'])->firstWhere('employee_id', $emp4->id);

    // Carry-forward MUST BE -10000.0 (from September)!
    expect($rowOctBefore['carry_forward'])->toBe(-10000.0)
        ->and($rowOctBefore['payable'])->toBe(25000.0)
        ->and($rowOctBefore['paid_in_month'])->toBe(0.0)
        ->and($rowOctBefore['month_balance'])->toBe(25000.0)
        ->and($rowOctBefore['net_balance'])->toBe(15000.0); // -10000 + 25000 = +15000

    // Now in October, employer disburses 15,000 cash to settle
    $this->payrollService->recordPayment([
        'employee_id' => $emp4->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-15',
        'amount' => 15000.00,
        'type' => 'salary',
    ], $this->owner->id);

    $octSheetAfter = $this->payrollService->getMonthlySheet('2026-10-01');
    $rowOctAfter = collect($octSheetAfter['rows'])->firstWhere('employee_id', $emp4->id);

    // Month balance = 25000 - 15000 = 10000
    // Net balance = Carry-forward (-10000) + Month balance (+10000) = 0.0 (Fully paid)!
    expect($rowOctAfter['carry_forward'])->toBe(-10000.0)
        ->and($rowOctAfter['paid_in_month'])->toBe(15000.0)
        ->and($rowOctAfter['month_balance'])->toBe(10000.0)
        ->and($rowOctAfter['net_balance'])->toBe(0.0)
        ->and($rowOctAfter['status'])->toBe('fully_paid');

    // Boundary check: A payment attributed to September is NEVER counted in October paid
    expect($rowOctAfter['paid_in_month'])->toBe(15000.0);
});

test('boundary behaviour: payment is never counted twice in earlier and later months', function () {
    $emp = Employee::create([
        'name' => 'Boundary Worker',
        'monthly_salary' => 20000.00,
        'is_active' => true,
    ]);

    $this->payrollService->generateMonthlySheets('2026-09-01', $this->owner->id);
    $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);

    // Payment on Sept 30 attributed to October (period = 2026-10-01)
    $payment = $this->payrollService->recordPayment([
        'employee_id' => $emp->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-09-30',
        'amount' => 5000.00,
        'type' => 'advance',
    ], $this->owner->id);

    $sepSheet = $this->payrollService->getMonthlySheet('2026-09-01');
    $octSheet = $this->payrollService->getMonthlySheet('2026-10-01');

    $sepRow = collect($sepSheet['rows'])->firstWhere('employee_id', $emp->id);
    $octRow = collect($octSheet['rows'])->firstWhere('employee_id', $emp->id);

    // Not counted in September paid
    expect($sepRow['paid_in_month'])->toBe(0.0);
    // Counted in October paid
    expect($octRow['paid_in_month'])->toBe(5000.0);
});

test('p&l salary expense is accrued payable, not cash paid, and advances do not count twice', function () {
    $emp = Employee::create([
        'name' => 'Expense Accrual Worker',
        'monthly_salary' => 25000.00,
        'is_active' => true,
    ]);

    // Month 1: Sept
    $this->payrollService->generateMonthlySheets('2026-09-01', $this->owner->id);
    // Disbursed 35,000 cash (including 10k advance)
    $this->payrollService->recordPayment([
        'employee_id' => $emp->id,
        'period' => '2026-09-01',
        'paid_on' => '2026-09-15',
        'amount' => 35000.00,
        'type' => 'salary',
    ], $this->owner->id);

    // Month 2: Oct
    $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);
    // Disbursed 15,000 cash
    $this->payrollService->recordPayment([
        'employee_id' => $emp->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-15',
        'amount' => 15000.00,
        'type' => 'salary',
    ], $this->owner->id);

    // 1. Sept P&L expense must be 25,000 (accrued payable), NOT 35,000 (cash paid)
    $septPnlExpense = $this->payrollService->getEarnedSalaryExpense('2026-09-01', '2026-09-30');
    $septCashPaid = $this->payrollService->getCashSalaryPaid('2026-09-01', '2026-09-30');
    expect($septPnlExpense)->toBe(25000.0)
        ->and($septCashPaid)->toBe(35000.0);

    // 2. Oct P&L expense must be 25,000 (accrued payable), NOT 15,000 (cash paid)
    $octPnlExpense = $this->payrollService->getEarnedSalaryExpense('2026-10-01', '2026-10-31');
    $octCashPaid = $this->payrollService->getCashSalaryPaid('2026-10-01', '2026-10-31');
    expect($octPnlExpense)->toBe(25000.0)
        ->and($octCashPaid)->toBe(15000.0);

    // 3. Combined period P&L expense is 50,000, matching total earned
    $totalPnlExpense = $this->payrollService->getEarnedSalaryExpense('2026-09-01', '2026-10-31');
    $totalCashPaid = $this->payrollService->getCashSalaryPaid('2026-09-01', '2026-10-31');
    expect($totalPnlExpense)->toBe(50000.0)
        ->and($totalCashPaid)->toBe(50000.0);
});

test('statement endpoint returns chronological ledger and respects date filters', function () {
    $emp = Employee::create([
        'name' => 'Ledger Worker',
        'monthly_salary' => 20000.00,
        'joined_at' => '2026-08-01',
        'is_active' => true,
    ]);

    $this->payrollService->generateMonthlySheets('2026-08-01', $this->owner->id);
    $this->payrollService->generateMonthlySheets('2026-09-01', $this->owner->id);
    $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);

    $this->payrollService->recordPayment([
        'employee_id' => $emp->id,
        'period' => '2026-08-01',
        'paid_on' => '2026-08-15',
        'amount' => 20000.00,
        'type' => 'salary',
    ], $this->owner->id);

    $this->payrollService->recordPayment([
        'employee_id' => $emp->id,
        'period' => '2026-09-01',
        'paid_on' => '2026-09-15',
        'amount' => 15000.00,
        'type' => 'salary',
    ], $this->owner->id);

    // Owner accesses statement
    $response = $this->actingAs($this->owner)->get("/payroll/employees/{$emp->id}/statement");
    $response->assertOk();

    $statement = $this->payrollService->getEmployeeStatement($emp);
    expect($statement['months'])->toHaveCount(3)
        ->and($statement['totals']['total_earned'])->toBe(60000.0)
        ->and($statement['totals']['total_paid'])->toBe(35000.0)
        ->and($statement['totals']['current_net_balance'])->toBe(25000.0); // 5000 from Sep + 20000 from Oct

    // Filter by Sept to Oct
    $filtered = $this->payrollService->getEmployeeStatement($emp, '2026-09', '2026-10');
    expect($filtered['months'])->toHaveCount(2);
    // Month 1 in filtered is September, its carry_forward is 0.0 (August was fully paid)
    expect($filtered['months'][0]['period'])->toBe('2026-09-01')
        ->and($filtered['months'][0]['carry_forward'])->toBe(0.0)
        ->and($filtered['months'][0]['net_balance'])->toBe(5000.0)
        ->and($filtered['months'][1]['period'])->toBe('2026-10-01')
        ->and($filtered['months'][1]['carry_forward'])->toBe(5000.0)
        ->and($filtered['months'][1]['net_balance'])->toBe(25000.0);
});

test('owner can perform controlled edits on payment with audit logging', function () {
    $emp = Employee::create([
        'name' => 'Correction Employee',
        'monthly_salary' => 25000.00,
        'is_active' => true,
    ]);

    $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);

    $payment = $this->payrollService->recordPayment([
        'employee_id' => $emp->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-10',
        'amount' => 12000.00,
        'type' => 'salary',
        'method' => 'cash',
        'note' => 'Original payment',
    ], $this->owner->id);

    // Controlled edit: owner updates amount from 12,000 to 15,000 and method to bKash
    $response = $this->actingAs($this->owner)->put("/payroll/payments/{$payment->id}", [
        'period' => '2026-10-01',
        'paid_on' => '2026-10-12',
        'amount' => 15000.00,
        'type' => 'salary',
        'method' => 'bkash',
        'note' => 'Corrected amount after reconciliation',
    ]);
    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $payment->refresh();
    expect((float) $payment->amount)->toBe(15000.00)
        ->and($payment->method)->toBe('bkash')
        ->and($payment->paid_on->toDateString())->toBe('2026-10-12')
        ->and($payment->note)->toBe('Corrected amount after reconciliation');

    // Audit log written for payment update
    $audit = AuditLog::where('event', 'salary_payment.updated')
        ->where('auditable_id', $payment->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and((float) $audit->old_values['amount'])->toBe(12000.00)
        ->and((float) $audit->new_values['amount'])->toBe(15000.00);

    // Monthly sheet reflects corrected payment
    $sheet = $this->payrollService->getMonthlySheet('2026-10-01');
    $row = collect($sheet['rows'])->firstWhere('employee_id', $emp->id);
    expect($row['paid_in_month'])->toBe(15000.0)
        ->and($row['month_balance'])->toBe(10000.0);
});

test('hard delete of payroll history (salary_months and salary_payments) is blocked by policy', function () {
    $emp = Employee::create([
        'name' => 'Permanent Payroll Employee',
        'monthly_salary' => 20000.00,
        'is_active' => true,
    ]);

    $this->payrollService->generateMonthlySheets('2026-10-01', $this->owner->id);
    $sm = SalaryMonth::where('employee_id', $emp->id)->where('period', '2026-10-01')->first();

    $payment = $this->payrollService->recordPayment([
        'employee_id' => $emp->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-15',
        'amount' => 10000.00,
        'type' => 'salary',
    ], $this->owner->id);

    // Policy blocks deletion of both models even for Owner
    expect($this->owner->can('delete', $sm))->toBeFalse()
        ->and($this->owner->can('delete', $payment))->toBeFalse();
});

test('archived employee retains history and statement is still accessible without 404', function () {
    $emp = Employee::create([
        'name' => 'Archived History Employee',
        'monthly_salary' => 30000.00,
        'is_active' => true,
    ]);

    $this->payrollService->generateMonthlySheets('2026-09-01', $this->owner->id);

    $this->payrollService->recordPayment([
        'employee_id' => $emp->id,
        'period' => '2026-09-01',
        'paid_on' => '2026-09-15',
        'amount' => 30000.00,
        'type' => 'salary',
    ], $this->owner->id);

    // Soft-delete (archive) employee
    $this->actingAs($this->owner)->delete("/payroll/employees/{$emp->id}");

    $emp->refresh();
    expect($emp->trashed())->toBeTrue();

    // Owner can still access show endpoint and statement endpoint with complete history
    $showResponse = $this->actingAs($this->owner)->get("/payroll/employees/{$emp->id}");
    $showResponse->assertOk();

    $statementResponse = $this->actingAs($this->owner)->get("/payroll/employees/{$emp->id}/statement");
    $statementResponse->assertOk();

    // Verify statement data
    $statement = $this->payrollService->getEmployeeStatement($emp);
    expect($statement['employee']['is_archived'])->toBeTrue()
        ->and($statement['totals']['total_paid'])->toBe(30000.0)
        ->and($statement['months'])->toHaveCount(1);
});

test('can create or update month sheet row on demand without pre-generation', function () {
    $emp = Employee::create([
        'name' => 'On-Demand Employee',
        'monthly_salary' => 18000.00,
        'is_active' => true,
    ]);

    // November 2026 has NOT been generated yet
    expect(SalaryMonth::where('employee_id', $emp->id)->where('period', '2026-11-01')->exists())->toBeFalse();

    // Owner updates row on-demand via PUT /payroll/months
    $response = $this->actingAs($this->owner)->put('/payroll/months', [
        'employee_id' => $emp->id,
        'period' => '2026-11',
        'base_salary' => 18000.00,
        'bonus' => 2000.00,
        'deduction' => 500.00,
        'note' => 'On-demand generation on edit',
    ]);
    $response->assertSessionHasNoErrors();

    $sm = SalaryMonth::where('employee_id', $emp->id)->where('period', '2026-11-01')->first();
    expect($sm)->not->toBeNull()
        ->and((float) $sm->payable)->toBe(19500.00); // 18000 + 2000 - 500

    expect(AuditLog::where('event', 'salary_month.updated')->where('auditable_id', $sm->id)->exists())->toBeTrue();
});

