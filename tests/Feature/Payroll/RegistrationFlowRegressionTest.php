<?php

namespace Tests\Feature\Payroll;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\SalaryMonth;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerEarning;
use App\Models\WorkerPayment;
use App\Services\Payroll\PayrollService;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
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

test('authorized owner can register salaried employee and it persists to database', function () {
    $payload = [
        'name' => 'Rashidul Islam',
        'phone' => '01712345678',
        'designation' => 'Pattern Master',
        'monthly_salary' => 32000.00,
        'joined_at' => '2026-02-01',
        'is_active' => true,
    ];

    $response = $this->actingAs($this->owner)->post('/payroll/employees', $payload);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $employee = Employee::where('name', 'Rashidul Islam')->first();
    expect($employee)->not->toBeNull()
        ->and((float) $employee->monthly_salary)->toBe(32000.00)
        ->and($employee->phone)->toBe('01712345678')
        ->and($employee->designation)->toBe('Pattern Master')
        ->and($employee->is_active)->toBeTrue();

    // Verify audit log exists
    expect(AuditLog::where('event', 'employee.created')->where('auditable_id', $employee->id)->exists())->toBeTrue();

    // Verify employee appears in employee directory listing
    $indexResponse = $this->actingAs($this->owner)->get('/payroll/employees?tab=employees');
    $indexResponse->assertOk();
    $indexResponse->assertInertia(fn ($page) => $page
        ->component('payroll/Employees')
        ->where('filters.tab', 'employees')
        ->has('employees')
    );
});

test('salaried employee registration fails with validation errors when inputs are invalid', function () {
    // Missing required fields (name, monthly_salary) and negative salary
    $response = $this->actingAs($this->owner)->post('/payroll/employees', [
        'name' => '',
        'monthly_salary' => -500,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['name', 'monthly_salary']);
    expect(Employee::count())->toBe(0);
});

test('unauthorized users cannot create employees', function () {
    // Unauthenticated
    $this->post('/payroll/employees', [
        'name' => 'Ghost Employee',
        'monthly_salary' => 20000,
    ])->assertRedirect('/login');

    // Staff without payroll.create permission receives 403
    $this->actingAs($this->staff)->post('/payroll/employees', [
        'name' => 'Forbidden Employee',
        'monthly_salary' => 20000,
    ])->assertStatus(403);

    expect(Employee::where('name', 'Ghost Employee')->exists())->toBeFalse()
        ->and(Employee::where('name', 'Forbidden Employee')->exists())->toBeFalse();
});

test('authorized owner can register wage worker with piece-rate wage basis', function () {
    $payload = [
        'name' => 'Salam Mia',
        'phone' => '01899887766',
        'role' => 'Leather Cutter',
        'wage_basis' => 'per_piece',
        'rate' => 250.00,
        'is_active' => true,
    ];

    $response = $this->actingAs($this->owner)->post('/payroll/workers', $payload);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $worker = Worker::where('name', 'Salam Mia')->first();
    expect($worker)->not->toBeNull()
        ->and($worker->wage_basis)->toBe('per_piece')
        ->and((float) $worker->rate)->toBe(250.00)
        ->and($worker->role)->toBe('Leather Cutter')
        ->and($worker->is_active)->toBeTrue();

    // Verify audit log exists
    expect(AuditLog::where('event', 'worker.created')->where('auditable_id', $worker->id)->exists())->toBeTrue();

    // Verify worker appears in worker listing
    $indexResponse = $this->actingAs($this->owner)->get('/payroll/workers?tab=workers');
    $indexResponse->assertOk();
    $indexResponse->assertInertia(fn ($page) => $page
        ->component('payroll/Workers')
        ->where('filters.tab', 'workers')
        ->has('workers')
    );
});

test('wage worker registration validates wage basis options and rates', function () {
    // Invalid wage_basis
    $response = $this->actingAs($this->owner)->post('/payroll/workers', [
        'name' => 'Bad Worker',
        'wage_basis' => 'hourly_invalid',
        'rate' => 100,
    ]);
    $response->assertSessionHasErrors(['wage_basis']);

    // Negative rate
    $response2 = $this->actingAs($this->owner)->post('/payroll/workers', [
        'name' => 'Negative Rate Worker',
        'wage_basis' => 'per_day',
        'rate' => -50,
    ]);
    $response2->assertSessionHasErrors(['rate']);

    expect(Worker::count())->toBe(0);
});

test('wage worker earning and payment update running ledger correctly', function () {
    $worker = Worker::create([
        'name' => 'Jashim Uddin',
        'wage_basis' => 'per_day',
        'rate' => 600.00,
        'is_active' => true,
    ]);

    // 1. Log Earning: 3 days @ 600 = 1800
    $earningResponse = $this->actingAs($this->owner)->post('/payroll/workers/earnings', [
        'worker_id' => $worker->id,
        'work_date' => '2026-10-10',
        'qty' => 3,
        'rate' => 600.00,
        'amount' => 1800.00,
        'note' => 'Worked Mon-Wed',
    ]);
    $earningResponse->assertSessionHasNoErrors();
    expect(WorkerEarning::where('worker_id', $worker->id)->count())->toBe(1);

    // Summary should show balance 1800
    $summary1 = $this->payrollService->getWorkersSummary();
    $row1 = collect($summary1['rows'])->firstWhere('id', $worker->id);
    expect($row1['current_balance'])->toBe(1800.0)
        ->and($row1['total_earned'])->toBe(1800.0)
        ->and($row1['total_paid'])->toBe(0.0);

    // 2. Record Payment: 1000 cash
    $paymentResponse = $this->actingAs($this->owner)->post('/payroll/workers/payments', [
        'worker_id' => $worker->id,
        'paid_on' => '2026-10-11',
        'amount' => 1000.00,
        'method' => 'cash',
        'note' => 'Advance cash',
    ]);
    $paymentResponse->assertSessionHasNoErrors();
    expect(WorkerPayment::where('worker_id', $worker->id)->count())->toBe(1);

    // Summary should now show balance 800 (1800 - 1000)
    $summary2 = $this->payrollService->getWorkersSummary();
    $row2 = collect($summary2['rows'])->firstWhere('id', $worker->id);
    expect($row2['current_balance'])->toBe(800.0)
        ->and($row2['total_earned'])->toBe(1800.0)
        ->and($row2['total_paid'])->toBe(1000.0);
});

test('generating monthly salary sheet is idempotent and does not duplicate employee rows or payments', function () {
    $emp = Employee::create([
        'name' => 'Mahbub Alam',
        'monthly_salary' => 26000.00,
        'is_active' => true,
    ]);

    $currentPeriod = CarbonImmutable::parse('2026-10-01');

    // First generation
    $this->payrollService->generateMonthlySheets($currentPeriod, $this->owner->id);
    expect(SalaryMonth::where('employee_id', $emp->id)->where('period', '2026-10-01')->count())->toBe(1);

    // Record partial salary payment
    SalaryPayment::create([
        'employee_id' => $emp->id,
        'period' => '2026-10-01',
        'paid_on' => '2026-10-15',
        'amount' => 10000.00,
        'type' => 'salary',
        'method' => 'cash',
        'created_by' => $this->owner->id,
    ]);

    // Second generation: MUST NOT duplicate
    $response = $this->actingAs($this->owner)->post('/payroll/months/generate', [
        'period' => '2026-10',
    ]);
    $response->assertSessionHasNoErrors();

    expect(SalaryMonth::where('employee_id', $emp->id)->where('period', '2026-10-01')->count())->toBe(1);
    expect(SalaryPayment::where('employee_id', $emp->id)->count())->toBe(1);

    // Verify sheet balances
    $sheet = $this->payrollService->getMonthlySheet('2026-10');
    $row = collect($sheet['rows'])->firstWhere('employee_id', $emp->id);
    expect($row['payable'])->toBe(26000.0)
        ->and($row['paid_in_month'])->toBe(10000.0)
        ->and($row['month_balance'])->toBe(16000.0)
        ->and($row['net_balance'])->toBe(16000.0);
});
