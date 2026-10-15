<?php

namespace Tests\Feature\Payroll;

use App\Models\AuditLog;
use App\Models\Setting;
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

test('staff user receives 403 on all worker routes and cannot access wage data', function () {
    $worker = Worker::create([
        'name' => 'Test Worker',
        'wage_basis' => 'per_day',
        'rate' => 800.00,
        'is_active' => true,
    ]);

    // 1. Staff accessing wage worker routes -> 403
    $this->actingAs($this->staff)->get('/payroll/workers')->assertStatus(403);
    $this->actingAs($this->staff)->get("/payroll/workers/{$worker->id}")->assertStatus(403);
    $this->actingAs($this->staff)->get("/payroll/workers/{$worker->id}/statement")->assertStatus(403);

    // 2. Staff creating worker -> 403
    $this->actingAs($this->staff)->post('/payroll/workers', [
        'name' => 'Intruder Worker',
        'wage_basis' => 'per_piece',
        'rate' => 150.00,
    ])->assertStatus(403);

    // 3. Staff logging earning -> 403
    $this->actingAs($this->staff)->post('/payroll/workers/earnings', [
        'worker_id' => $worker->id,
        'work_date' => '2026-10-12',
        'qty' => 5,
        'rate' => 800.00,
    ])->assertStatus(403);

    // 4. Staff recording payment -> 403
    $this->actingAs($this->staff)->post('/payroll/workers/payments', [
        'worker_id' => $worker->id,
        'paid_on' => '2026-10-12',
        'amount' => 4000.00,
    ])->assertStatus(403);

    // 5. Staff attempting batch payout -> 403
    $this->actingAs($this->staff)->post('/payroll/workers/batch-payout', [
        'paid_on' => '2026-10-12',
        'payouts' => [['worker_id' => $worker->id, 'amount' => 4000.00]],
    ])->assertStatus(403);
});

test('owner can create, update, and soft-delete worker profile with audit trail', function () {
    // 1. Create worker profile
    $response = $this->actingAs($this->owner)->post('/payroll/workers', [
        'name' => 'Kalam Mia',
        'phone' => '01712345678',
        'role' => 'Master Cutter',
        'wage_basis' => 'per_piece',
        'rate' => 125.00,
        'is_active' => true,
    ]);
    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $worker = Worker::where('name', 'Kalam Mia')->first();
    expect($worker)->not->toBeNull()
        ->and($worker->role)->toBe('Master Cutter')
        ->and($worker->wage_basis)->toBe('per_piece')
        ->and((float) $worker->rate)->toBe(125.00)
        ->and($worker->is_active)->toBeTrue();

    expect(AuditLog::where('event', 'worker.created')->where('auditable_id', $worker->id)->exists())->toBeTrue();

    // 2. Update worker profile
    $updateResponse = $this->actingAs($this->owner)->put("/payroll/workers/{$worker->id}", [
        'name' => 'Kalam Mia',
        'phone' => '01712345678',
        'role' => 'Senior Master Cutter',
        'wage_basis' => 'per_piece',
        'rate' => 140.00,
        'is_active' => true,
    ]);
    $updateResponse->assertSessionHasNoErrors();

    $worker->refresh();
    expect($worker->role)->toBe('Senior Master Cutter')
        ->and((float) $worker->rate)->toBe(140.00);

    expect(AuditLog::where('event', 'worker.updated')->where('auditable_id', $worker->id)->exists())->toBeTrue();

    // 3. Soft-delete (archive) worker
    $deleteResponse = $this->actingAs($this->owner)->delete("/payroll/workers/{$worker->id}");
    $deleteResponse->assertRedirect();

    $worker->refresh();
    expect($worker->trashed())->toBeTrue()
        ->and($worker->is_active)->toBeFalse();

    expect(AuditLog::where('event', 'worker.archived')->where('auditable_id', $worker->id)->exists())->toBeTrue();

    // 4. Restore worker
    $restoreResponse = $this->actingAs($this->owner)->post("/payroll/workers/{$worker->id}/restore");
    $restoreResponse->assertRedirect();

    $worker->refresh();
    expect($worker->trashed())->toBeFalse()
        ->and($worker->is_active)->toBeTrue();

    expect(AuditLog::where('event', 'worker.restored')->where('auditable_id', $worker->id)->exists())->toBeTrue();
});

test('per-day worker: logs earnings, computes amount, records payment and tracks running balance', function () {
    $worker = $this->payrollService->createWorker([
        'name' => 'Daily Artisan',
        'role' => 'Stitcher',
        'wage_basis' => 'per_day',
        'rate' => 800.00,
        'is_active' => true,
    ], $this->owner->id);

    // Day 1: 5 days worked @ 800 = 4,000
    $earning1 = $this->payrollService->recordWorkerEarning([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-05',
        'qty' => 5.0,
        'rate' => 800.00,
        'note' => 'Week 1 stitching shift',
    ], $this->owner->id);

    expect((float) $earning1->amount)->toBe(4000.00);

    // Day 2: 3.5 days worked @ 800 = 2,800
    $earning2 = $this->payrollService->recordWorkerEarning([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-12',
        'qty' => 3.5,
        'rate' => 800.00,
        'note' => 'Part-time week 2',
    ], $this->owner->id);

    expect((float) $earning2->amount)->toBe(2800.00);

    // Total earned = 6,800. Balance before payment = +6,800
    $stmt1 = $this->payrollService->getWorkerStatement($worker);
    expect($stmt1['total_earned_all'])->toBe(6800.0)
        ->and($stmt1['total_paid_all'])->toBe(0.0)
        ->and($stmt1['current_balance'])->toBe(6800.0)
        ->and($stmt1['status_tone'])->toBe('warning');

    // Partial cash payment of 4,000
    $this->payrollService->recordWorkerPayment([
        'worker_id' => $worker->id,
        'paid_on' => '2026-10-15',
        'amount' => 4000.00,
        'method' => 'cash',
        'note' => 'Mid-month settlement',
    ], $this->owner->id);

    $stmt2 = $this->payrollService->getWorkerStatement($worker);
    expect($stmt2['total_earned_all'])->toBe(6800.0)
        ->and($stmt2['total_paid_all'])->toBe(4000.0)
        ->and($stmt2['current_balance'])->toBe(2800.0);
});

test('per-piece worker: supports piece rates and manual amount overrides', function () {
    $worker = $this->payrollService->createWorker([
        'name' => 'Piece Artisan',
        'role' => 'Finisher',
        'wage_basis' => 'per_piece',
        'rate' => 120.00,
        'is_active' => true,
    ], $this->owner->id);

    // 50 pieces @ 120 = 6,000
    $e1 = $this->payrollService->recordWorkerEarning([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-06',
        'qty' => 50,
        'rate' => 120.00,
        'note' => 'Standard wallets finished',
    ], $this->owner->id);
    expect((float) $e1->amount)->toBe(6000.00);

    // Manual amount override: 20 pieces with bonus override to 2,800 (instead of 2,400)
    $e2 = $this->payrollService->recordWorkerEarning([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-10',
        'qty' => 20,
        'rate' => 120.00,
        'amount' => 2800.00,
        'note' => 'Rush holiday piece delivery with 400 bonus',
    ], $this->owner->id);
    expect((float) $e2->amount)->toBe(2800.00);

    // Full payment of 8,800 settles balance to exactly 0.0
    $this->payrollService->recordWorkerPayment([
        'worker_id' => $worker->id,
        'paid_on' => '2026-10-11',
        'amount' => 8800.00,
        'method' => 'bkash',
    ], $this->owner->id);

    $stmt = $this->payrollService->getWorkerStatement($worker);
    expect($stmt['total_earned_all'])->toBe(8800.0)
        ->and($stmt['total_paid_all'])->toBe(8800.0)
        ->and($stmt['current_balance'])->toBe(0.0)
        ->and($stmt['status_label'])->toBe('Fully Settled')
        ->and($stmt['status_tone'])->toBe('success');
});

test('fixed-weekly worker: supports weekly period and repeated payouts within same week', function () {
    $worker = $this->payrollService->createWorker([
        'name' => 'Weekly Artisan',
        'role' => 'Workshop Lead',
        'wage_basis' => 'fixed_weekly',
        'rate' => 6000.00,
        'is_active' => true,
    ], $this->owner->id);

    // Log weekly earning with period start and work date
    $this->payrollService->recordWorkerEarning([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-11',
        'period_start' => '2026-10-05',
        'qty' => 1,
        'rate' => 6000.00,
        'note' => 'Week 41 fixed wage',
    ], $this->owner->id);

    // Repeated payments: Payment 1 (Wednesday mid-week cash 2,500)
    $this->payrollService->recordWorkerPayment([
        'worker_id' => $worker->id,
        'paid_on' => '2026-10-08',
        'amount' => 2500.00,
        'method' => 'cash',
        'note' => 'Mid-week advance',
    ], $this->owner->id);

    // Payment 2 (Sunday final cash 3,500)
    $this->payrollService->recordWorkerPayment([
        'worker_id' => $worker->id,
        'paid_on' => '2026-10-11',
        'amount' => 3500.00,
        'method' => 'cash',
        'note' => 'Weekend settlement',
    ], $this->owner->id);

    $stmt = $this->payrollService->getWorkerStatement($worker);
    expect($stmt['total_earned_all'])->toBe(6000.0)
        ->and($stmt['total_paid_all'])->toBe(6000.0)
        ->and($stmt['current_balance'])->toBe(0.0)
        ->and($stmt['ledger'])->toHaveCount(3);
});

test('overpayment displays negative balance distinctly in violet', function () {
    $worker = $this->payrollService->createWorker([
        'name' => 'Overpaid Worker',
        'wage_basis' => 'per_day',
        'rate' => 1000.00,
        'is_active' => true,
    ], $this->owner->id);

    // Earned 3 days = 3,000
    $this->payrollService->recordWorkerEarning([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-05',
        'qty' => 3,
        'rate' => 1000.00,
    ], $this->owner->id);

    // Employer disburses 5,000 (3,000 wage + 2,000 advance)
    $this->payrollService->recordWorkerPayment([
        'worker_id' => $worker->id,
        'paid_on' => '2026-10-06',
        'amount' => 5000.00,
        'method' => 'cash',
    ], $this->owner->id);

    $stmt = $this->payrollService->getWorkerStatement($worker);
    // Hand calc: 3000 - 5000 = -2000
    expect($stmt['total_earned_all'])->toBe(3000.0)
        ->and($stmt['total_paid_all'])->toBe(5000.0)
        ->and($stmt['current_balance'])->toBe(-2000.0)
        ->and($stmt['is_overpaid'])->toBeTrue()
        ->and($stmt['status_label'])->toBe('Owes Tasa (Over-paid)')
        ->and($stmt['status_tone'])->toBe('advance'); // Violet tone

    // Summary table also returns advance violet tone
    $summary = $this->payrollService->getWorkersSummary();
    $workerRow = collect($summary['rows'])->firstWhere('id', $worker->id);
    expect($workerRow['current_balance'])->toBe(-2000.0)
        ->and($workerRow['status_tone'])->toBe('advance')
        ->and($summary['totals']['total_overpaid_by_worker'])->toBe(2000.0);
});

test('running ledger calculates opening balance, chronological entries, and closing balance with date filters', function () {
    $worker = $this->payrollService->createWorker([
        'name' => 'Ledger Filter Worker',
        'wage_basis' => 'per_day',
        'rate' => 1000.00,
        'is_active' => true,
    ], $this->owner->id);

    // September activity:
    // Earned 4,000 on Sept 10
    $this->payrollService->recordWorkerEarning([
        'worker_id' => $worker->id,
        'work_date' => '2026-09-10',
        'qty' => 4,
        'rate' => 1000.00,
    ], $this->owner->id);
    // Paid 3,000 on Sept 15
    $this->payrollService->recordWorkerPayment([
        'worker_id' => $worker->id,
        'paid_on' => '2026-09-15',
        'amount' => 3000.00,
    ], $this->owner->id);
    // Sept closing balance = +1,000

    // October activity:
    // Earned 5,000 on Oct 05
    $this->payrollService->recordWorkerEarning([
        'worker_id' => $worker->id,
        'work_date' => '2026-10-05',
        'qty' => 5,
        'rate' => 1000.00,
    ], $this->owner->id);
    // Paid 4,000 on Oct 10
    $this->payrollService->recordWorkerPayment([
        'worker_id' => $worker->id,
        'paid_on' => '2026-10-10',
        'amount' => 4000.00,
    ], $this->owner->id);

    // Filter by October only (2026-10-01 to 2026-10-31)
    $filtered = $this->payrollService->getWorkerStatement($worker, '2026-10-01', '2026-10-31');

    // Opening balance from September MUST BE +1,000.0
    expect($filtered['opening_balance'])->toBe(1000.0)
        ->and($filtered['total_earned_range'])->toBe(5000.0)
        ->and($filtered['total_paid_range'])->toBe(4000.0)
        ->and($filtered['closing_balance'])->toBe(2000.0) // 1000 opening + 5000 - 4000 = 2000
        ->and($filtered['current_balance'])->toBe(2000.0)
        ->and($filtered['ledger'])->toHaveCount(2);

    // Ledger entry 1: Oct 05 Earning 5000 -> Running balance = 1000 + 5000 = 6000
    expect($filtered['ledger'][0]['date'])->toBe('2026-10-05')
        ->and($filtered['ledger'][0]['running_balance'])->toBe(6000.0);

    // Ledger entry 2: Oct 10 Payment 4000 -> Running balance = 6000 - 4000 = 2000
    expect($filtered['ledger'][1]['date'])->toBe('2026-10-10')
        ->and($filtered['ledger'][1]['running_balance'])->toBe(2000.0);
});

test('weekly payout helper disburses batch payments for selected workers and ignores blank/zero rows', function () {
    $w1 = $this->payrollService->createWorker(['name' => 'Worker 1', 'wage_basis' => 'per_day', 'rate' => 800, 'is_active' => true], $this->owner->id);
    $w2 = $this->payrollService->createWorker(['name' => 'Worker 2', 'wage_basis' => 'per_day', 'rate' => 800, 'is_active' => true], $this->owner->id);
    $w3 = $this->payrollService->createWorker(['name' => 'Worker 3', 'wage_basis' => 'per_day', 'rate' => 800, 'is_active' => true], $this->owner->id);

    // W1 earned 4,000; W2 earned 2,400; W3 earned 1,600
    $this->payrollService->recordWorkerEarning(['worker_id' => $w1->id, 'work_date' => '2026-10-10', 'qty' => 5, 'rate' => 800], $this->owner->id);
    $this->payrollService->recordWorkerEarning(['worker_id' => $w2->id, 'work_date' => '2026-10-10', 'qty' => 3, 'rate' => 800], $this->owner->id);
    $this->payrollService->recordWorkerEarning(['worker_id' => $w3->id, 'work_date' => '2026-10-10', 'qty' => 2, 'rate' => 800], $this->owner->id);

    // Process batch payout:
    // W1: pay 4,000
    // W2: pay 1,500
    // W3: blank / 0 (must NOT create a payment record)
    $response = $this->actingAs($this->owner)->post('/payroll/workers/batch-payout', [
        'paid_on' => '2026-10-11',
        'method' => 'cash',
        'note' => 'Weekend batch settlement',
        'payouts' => [
            ['worker_id' => $w1->id, 'amount' => 4000.00],
            ['worker_id' => $w2->id, 'amount' => 1500.00],
            ['worker_id' => $w3->id, 'amount' => 0.00], // Blank / zero
        ],
    ]);
    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // Verify DB payments: W1 and W2 have payments, W3 has NO payment row
    expect(WorkerPayment::where('worker_id', $w1->id)->count())->toBe(1)
        ->and(WorkerPayment::where('worker_id', $w2->id)->count())->toBe(1)
        ->and(WorkerPayment::where('worker_id', $w3->id)->count())->toBe(0);

    // Verify updated balances:
    // W1: 4000 - 4000 = 0
    // W2: 2400 - 1500 = 900
    // W3: 1600 - 0 = 1600
    $s1 = $this->payrollService->getWorkerStatement($w1);
    $s2 = $this->payrollService->getWorkerStatement($w2);
    $s3 = $this->payrollService->getWorkerStatement($w3);

    expect($s1['current_balance'])->toBe(0.0)
        ->and($s2['current_balance'])->toBe(900.0)
        ->and($s3['current_balance'])->toBe(1600.0);
});

test('p&l wage expense accrues earnings in period and respects cost_price_includes_labour setting', function () {
    $worker = $this->payrollService->createWorker(['name' => 'P&L Worker', 'wage_basis' => 'per_day', 'rate' => 1000, 'is_active' => true], $this->owner->id);

    // Earned in Oct: 10,000
    $this->payrollService->recordWorkerEarning(['worker_id' => $worker->id, 'work_date' => '2026-10-10', 'qty' => 10, 'rate' => 1000], $this->owner->id);

    // Disbursed in Oct: 15,000 cash (including 5,000 advance)
    $this->payrollService->recordWorkerPayment(['worker_id' => $worker->id, 'paid_on' => '2026-10-15', 'amount' => 15000.00], $this->owner->id);

    // 1. By default, setting is '0' (Cost price does NOT include labour per PRD O-1)
    Setting::set('payroll.cost_price_includes_labour', '0');

    $earnedWage = $this->payrollService->getEarnedWageExpense('2026-10-01', '2026-10-31');
    $cashPaid = $this->payrollService->getCashWagePaid('2026-10-01', '2026-10-31');

    // P&L wage expense must be 10,000 (earned accrual), NOT 15,000 (cash paid)
    expect($earnedWage)->toBe(10000.00)
        ->and($cashPaid)->toBe(15000.00);

    // 2. Toggle setting to '1' (Cost price DOES include labour)
    Setting::set('payroll.cost_price_includes_labour', '1');

    // Wage expense MUST BE 0.00 to avoid double counting with COGS
    $earnedWageWithLabour = $this->payrollService->getEarnedWageExpense('2026-10-01', '2026-10-31');
    expect($earnedWageWithLabour)->toBe(0.00);

    // 3. Toggle back to '0'
    Setting::set('payroll.cost_price_includes_labour', '0');
    expect($this->payrollService->getEarnedWageExpense('2026-10-01', '2026-10-31'))->toBe(10000.00);
});

test('hard delete of worker earnings and payments is strictly blocked by policy', function () {
    $worker = $this->payrollService->createWorker(['name' => 'Permanent Ledger Worker', 'wage_basis' => 'per_day', 'rate' => 800, 'is_active' => true], $this->owner->id);

    $earning = $this->payrollService->recordWorkerEarning(['worker_id' => $worker->id, 'work_date' => '2026-10-10', 'qty' => 2, 'rate' => 800], $this->owner->id);
    $payment = $this->payrollService->recordWorkerPayment(['worker_id' => $worker->id, 'paid_on' => '2026-10-11', 'amount' => 1600.00], $this->owner->id);

    // Policies return false for delete even for Owner
    expect($this->owner->can('delete', $earning))->toBeFalse()
        ->and($this->owner->can('delete', $payment))->toBeFalse();
});

test('controlled edits on worker earnings and payments record audit trail', function () {
    $worker = $this->payrollService->createWorker(['name' => 'Correction Worker', 'wage_basis' => 'per_piece', 'rate' => 100, 'is_active' => true], $this->owner->id);

    $earning = $this->payrollService->recordWorkerEarning(['worker_id' => $worker->id, 'work_date' => '2026-10-10', 'qty' => 10, 'rate' => 100], $this->owner->id);

    // Update earning: qty corrected to 15 (amount recomputes to 1,500)
    $responseEarning = $this->actingAs($this->owner)->put("/payroll/workers/earnings/{$earning->id}", [
        'work_date' => '2026-10-10',
        'qty' => 15,
        'rate' => 100,
        'note' => 'Corrected piece count after workshop recount',
    ]);
    $responseEarning->assertSessionHasNoErrors();

    $earning->refresh();
    expect((float) $earning->qty)->toBe(15.0)
        ->and((float) $earning->amount)->toBe(1500.00);

    expect(AuditLog::where('event', 'worker_earning.updated')->where('auditable_id', $earning->id)->exists())->toBeTrue();

    // Payment update
    $payment = $this->payrollService->recordWorkerPayment(['worker_id' => $worker->id, 'paid_on' => '2026-10-11', 'amount' => 1000.00], $this->owner->id);

    $responsePayment = $this->actingAs($this->owner)->put("/payroll/workers/payments/{$payment->id}", [
        'paid_on' => '2026-10-11',
        'amount' => 1500.00,
        'method' => 'bkash',
        'note' => 'Full payout adjustment via bKash',
    ]);
    $responsePayment->assertSessionHasNoErrors();

    $payment->refresh();
    expect((float) $payment->amount)->toBe(1500.00)
        ->and($payment->method)->toBe('bkash');

    expect(AuditLog::where('event', 'worker_payment.updated')->where('auditable_id', $payment->id)->exists())->toBeTrue();
});
