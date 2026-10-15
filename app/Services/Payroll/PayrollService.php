<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\SalaryMonth;
use App\Models\SalaryPayment;
use App\Models\Setting;
use App\Models\Worker;
use App\Models\WorkerEarning;
use App\Models\WorkerPayment;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Generate monthly salary sheet rows for all active salaried employees.
     * PRD §10.1 & Schema §4.11: Rows are created on the 1st of each month or on-demand from UI.
     */
    public function generateMonthlySheets(string|CarbonInterface $period, ?int $userId = null): int
    {
        $periodDate = CarbonImmutable::parse($period)->startOfMonth()->toDateString();
        $periodStart = CarbonImmutable::parse($periodDate);
        $periodEnd = $periodStart->endOfMonth();

        $activeEmployees = Employee::active()
            ->where(function ($query) use ($periodEnd) {
                $query->whereNull('joined_at')
                    ->orWhere('joined_at', '<=', $periodEnd);
            })
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('left_at')
                    ->orWhere('left_at', '>=', $periodStart);
            })
            ->get();

        $createdCount = 0;

        foreach ($activeEmployees as $employee) {
            $existing = SalaryMonth::where('employee_id', $employee->id)
                ->where('period', $periodDate)
                ->first();

            if (! $existing) {
                $salaryMonth = SalaryMonth::create([
                    'employee_id' => $employee->id,
                    'period' => $periodDate,
                    'base_salary' => $employee->monthly_salary,
                    'bonus' => 0.00,
                    'deduction' => 0.00,
                    'note' => null,
                    'created_by' => $userId,
                ]);

                AuditService::log(
                    'salary_month.created',
                    $salaryMonth,
                    null,
                    $salaryMonth->toArray(),
                    $userId
                );

                $createdCount++;
            }
        }

        return $createdCount;
    }

    /**
     * Get the monthly salary sheet for all relevant employees with accurate carry-forward and net balance.
     */
    public function getMonthlySheet(string|CarbonInterface $period, array $filters = []): array
    {
        $periodObj = CarbonImmutable::parse($period)->startOfMonth();
        $periodDate = $periodObj->toDateString();
        $periodLabel = $periodObj->format('F Y');

        // Query active employees, plus any employee having activity in this specific period
        $employees = Employee::withTrashed()
            ->where(function ($query) use ($periodDate) {
                $query->where('is_active', true)
                    ->orWhereHas('salaryMonths', fn ($q) => $q->where('period', $periodDate))
                    ->orWhereHas('salaryPayments', fn ($q) => $q->where('period', $periodDate));
            })
            ->orderBy('name')
            ->get();

        // Batch fetch existing salary months for this period
        $salaryMonths = SalaryMonth::where('period', $periodDate)
            ->get()
            ->keyBy('employee_id');

        // Batch fetch payments made for this period
        $payments = SalaryPayment::with('creator:id,name')
            ->where('period', $periodDate)
            ->orderBy('paid_on', 'asc')
            ->get()
            ->groupBy('employee_id');

        // Batch fetch cumulative payables for earlier periods (prior earned)
        $priorPayables = SalaryMonth::where('period', '<', $periodDate)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(payable) as total_payable')
            ->pluck('total_payable', 'employee_id');

        // Batch fetch cumulative payments for earlier periods (prior cash paid)
        $priorPayments = SalaryPayment::where('period', '<', $periodDate)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(amount) as total_paid')
            ->pluck('total_paid', 'employee_id');

        $rows = [];
        $totals = [
            'total_base' => 0.0,
            'total_bonus' => 0.0,
            'total_deduction' => 0.0,
            'total_payable' => 0.0,
            'total_paid' => 0.0,
            'total_month_balance' => 0.0,
            'total_carry_forward' => 0.0,
            'total_net_balance' => 0.0,
            'total_owed' => 0.0,
            'total_overpaid' => 0.0,
            'active_count' => 0,
        ];

        foreach ($employees as $employee) {
            /** @var SalaryMonth|null $sm */
            $sm = $salaryMonths->get($employee->id);
            /** @var Collection $empPayments */
            $empPayments = $payments->get($employee->id) ?? collect();

            $baseSalary = $sm ? (float) $sm->base_salary : (float) $employee->monthly_salary;
            $bonus = $sm ? (float) $sm->bonus : 0.0;
            $deduction = $sm ? (float) $sm->deduction : 0.0;
            $payable = $sm ? (float) $sm->payable : round($baseSalary + $bonus - $deduction, 2);

            $paidInMonth = round((float) $empPayments->sum('amount'), 2);
            $monthBalance = round($payable - $paidInMonth, 2);

            $priorEarned = (float) ($priorPayables->get($employee->id) ?? 0.0);
            $priorPaid = (float) ($priorPayments->get($employee->id) ?? 0.0);
            $carryForward = round($priorEarned - $priorPaid, 2);

            $netBalance = round($carryForward + $monthBalance, 2);

            // Determine status badge (PRD §10.1: Fully paid · Partially paid · Over-paid)
            if ($netBalance < 0) {
                $status = 'over_paid';
                $statusLabel = 'Over-paid';
                $statusTone = 'advance'; // Distinct violet badge and tone
            } elseif (abs($netBalance) < 0.005) {
                $status = 'fully_paid';
                $statusLabel = 'Fully paid';
                $statusTone = 'success';
            } elseif ($paidInMonth > 0) {
                $status = 'partially_paid';
                $statusLabel = 'Partially paid';
                $statusTone = 'warning';
            } else {
                $status = 'unpaid';
                $statusLabel = 'Unpaid';
                $statusTone = 'danger';
            }

            $row = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_phone' => $employee->phone,
                'employee_designation' => $employee->designation,
                'employee_monthly_salary' => (float) $employee->monthly_salary,
                'is_active' => (bool) $employee->is_active,
                'is_archived' => $employee->trashed(),
                'salary_month_id' => $sm?->id,
                'is_generated' => $sm !== null,
                'base_salary' => $baseSalary,
                'bonus' => $bonus,
                'deduction' => $deduction,
                'payable' => $payable,
                'paid_in_month' => $paidInMonth,
                'month_balance' => $monthBalance,
                'carry_forward' => $carryForward,
                'net_balance' => $netBalance,
                'status' => $status,
                'status_label' => $statusLabel,
                'status_tone' => $statusTone,
                'note' => $sm?->note,
                'payments' => $empPayments->map(fn ($p) => [
                    'id' => $p->id,
                    'paid_on' => $p->paid_on?->toDateString(),
                    'amount' => (float) $p->amount,
                    'type' => $p->type,
                    'method' => $p->method,
                    'note' => $p->note,
                    'created_by' => $p->creator?->name,
                ])->values(),
            ];

            // Apply filters if provided
            $matchesSearch = true;
            if (! empty($filters['search'])) {
                $term = strtolower(trim($filters['search']));
                $matchesSearch = str_contains(strtolower($employee->name), $term)
                    || str_contains((string) $employee->phone, $term)
                    || str_contains(strtolower((string) $employee->designation), $term);
            }

            $matchesStatus = true;
            if (! empty($filters['status']) && $filters['status'] !== 'all') {
                $matchesStatus = $status === $filters['status'];
            }

            if ($matchesSearch && $matchesStatus) {
                $rows[] = $row;
            }

            // Always accumulate totals for the entire month
            $totals['total_base'] += $baseSalary;
            $totals['total_bonus'] += $bonus;
            $totals['total_deduction'] += $deduction;
            $totals['total_payable'] += $payable;
            $totals['total_paid'] += $paidInMonth;
            $totals['total_month_balance'] += $monthBalance;
            $totals['total_carry_forward'] += $carryForward;
            $totals['total_net_balance'] += $netBalance;

            if ($netBalance > 0) {
                $totals['total_owed'] += $netBalance;
            } elseif ($netBalance < 0) {
                $totals['total_overpaid'] += abs($netBalance);
            }

            if ($employee->is_active) {
                $totals['active_count']++;
            }
        }

        // Round all totals to 2 decimals
        foreach ($totals as $key => $val) {
            if (is_float($val)) {
                $totals[$key] = round($val, 2);
            }
        }

        return [
            'period' => $periodDate,
            'period_label' => $periodLabel,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Get chronological employee ledger/statement with running carry-forward and net balance.
     * PRD §10.1 & Prompt 12 requirement 8.
     */
    public function getEmployeeStatement(
        Employee $employee,
        ?string $fromPeriod = null,
        ?string $toPeriod = null
    ): array {
        // Collect all periods where this employee has a salary_month, a salary_payment, or active history
        $monthPeriods = SalaryMonth::where('employee_id', $employee->id)->pluck('period')->map(fn ($d) => CarbonImmutable::parse($d)->startOfMonth()->toDateString());
        $paymentPeriods = SalaryPayment::where('employee_id', $employee->id)->pluck('period')->map(fn ($d) => CarbonImmutable::parse($d)->startOfMonth()->toDateString());

        $allPeriods = $monthPeriods->concat($paymentPeriods)->unique()->sort()->values();

        // If no periods recorded yet, at least provide current month or joining month
        if ($allPeriods->isEmpty()) {
            $defaultDate = $employee->joined_at
                ? CarbonImmutable::parse($employee->joined_at)->startOfMonth()->toDateString()
                : CarbonImmutable::now()->startOfMonth()->toDateString();
            $allPeriods->push($defaultDate);
        }

        // Fetch all salary months and payments for this employee
        $salaryMonths = SalaryMonth::where('employee_id', $employee->id)
            ->get()
            ->keyBy(fn ($sm) => CarbonImmutable::parse($sm->period)->startOfMonth()->toDateString());

        $payments = SalaryPayment::with('creator:id,name')
            ->where('employee_id', $employee->id)
            ->orderBy('paid_on', 'asc')
            ->get()
            ->groupBy(fn ($sp) => CarbonImmutable::parse($sp->period)->startOfMonth()->toDateString());

        $runningCarryForward = 0.0;
        $ledgerMonths = [];
        $totalEarned = 0.0;
        $totalPaid = 0.0;

        foreach ($allPeriods as $periodDate) {
            $periodObj = CarbonImmutable::parse($periodDate);
            /** @var SalaryMonth|null $sm */
            $sm = $salaryMonths->get($periodDate);
            /** @var Collection $monthPayments */
            $monthPayments = $payments->get($periodDate) ?? collect();

            $baseSalary = $sm ? (float) $sm->base_salary : (float) $employee->monthly_salary;
            $bonus = $sm ? (float) $sm->bonus : 0.0;
            $deduction = $sm ? (float) $sm->deduction : 0.0;
            $payable = $sm ? (float) $sm->payable : ($sm ? round($baseSalary + $bonus - $deduction, 2) : 0.0);

            $paidInMonth = round((float) $monthPayments->sum('amount'), 2);
            $monthBalance = round($payable - $paidInMonth, 2);
            $carryForward = round($runningCarryForward, 2);
            $netBalance = round($carryForward + $monthBalance, 2);

            // Carry forward for the next chronological month is this month's net balance
            $runningCarryForward = $netBalance;

            $totalEarned += $payable;
            $totalPaid += $paidInMonth;

            // Status badge
            if ($netBalance < 0) {
                $status = 'over_paid';
                $statusLabel = 'Over-paid';
                $statusTone = 'advance';
            } elseif (abs($netBalance) < 0.005) {
                $status = 'fully_paid';
                $statusLabel = 'Fully paid';
                $statusTone = 'success';
            } elseif ($paidInMonth > 0) {
                $status = 'partially_paid';
                $statusLabel = 'Partially paid';
                $statusTone = 'warning';
            } else {
                $status = 'unpaid';
                $statusLabel = 'Unpaid';
                $statusTone = 'danger';
            }

            // Check if within requested filter range
            $isInFilterRange = true;
            if ($fromPeriod && $periodDate < CarbonImmutable::parse($fromPeriod)->startOfMonth()->toDateString()) {
                $isInFilterRange = false;
            }
            if ($toPeriod && $periodDate > CarbonImmutable::parse($toPeriod)->startOfMonth()->toDateString()) {
                $isInFilterRange = false;
            }

            if ($isInFilterRange) {
                $ledgerMonths[] = [
                    'period' => $periodDate,
                    'period_label' => $periodObj->format('F Y'),
                    'base_salary' => $baseSalary,
                    'bonus' => $bonus,
                    'deduction' => $deduction,
                    'payable' => $payable,
                    'paid_in_month' => $paidInMonth,
                    'month_balance' => $monthBalance,
                    'carry_forward' => $carryForward,
                    'net_balance' => $netBalance,
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'status_tone' => $statusTone,
                    'note' => $sm?->note,
                    'payments' => $monthPayments->map(fn ($p) => [
                        'id' => $p->id,
                        'paid_on' => $p->paid_on?->toDateString(),
                        'amount' => (float) $p->amount,
                        'type' => $p->type,
                        'method' => $p->method,
                        'note' => $p->note,
                        'created_by' => $p->creator?->name,
                    ])->values(),
                ];
            }
        }

        // All individual payment records
        $allPaymentsQuery = SalaryPayment::with('creator:id,name')
            ->where('employee_id', $employee->id);

        if ($fromPeriod) {
            $allPaymentsQuery->where('paid_on', '>=', CarbonImmutable::parse($fromPeriod)->startOfMonth()->toDateString());
        }
        if ($toPeriod) {
            $allPaymentsQuery->where('paid_on', '<=', CarbonImmutable::parse($toPeriod)->endOfMonth()->toDateString());
        }

        $allPayments = $allPaymentsQuery->orderBy('paid_on', 'desc')->get()->map(fn ($p) => [
            'id' => $p->id,
            'period' => $p->period?->toDateString(),
            'period_label' => $p->period ? CarbonImmutable::parse($p->period)->format('M Y') : null,
            'paid_on' => $p->paid_on?->toDateString(),
            'amount' => (float) $p->amount,
            'type' => $p->type,
            'method' => $p->method,
            'note' => $p->note,
            'created_by' => $p->creator?->name,
        ]);

        $currentNetBalance = round($runningCarryForward, 2);

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'designation' => $employee->designation,
                'monthly_salary' => (float) $employee->monthly_salary,
                'joined_at' => $employee->joined_at?->toDateString(),
                'left_at' => $employee->left_at?->toDateString(),
                'is_active' => (bool) $employee->is_active,
                'is_archived' => $employee->trashed(),
            ],
            'months' => $ledgerMonths,
            'payments' => $allPayments,
            'totals' => [
                'total_earned' => round($totalEarned, 2),
                'total_paid' => round($totalPaid, 2),
                'current_net_balance' => $currentNetBalance,
                'is_owed_by_tasa' => $currentNetBalance > 0,
                'is_overpaid' => $currentNetBalance < 0,
                'status_label' => $currentNetBalance < 0
                    ? 'Over-paid (Owes Tasa)'
                    : ($currentNetBalance > 0 ? 'Tasa Owes Employee' : 'Fully Settled'),
                'status_tone' => $currentNetBalance < 0
                    ? 'advance'
                    : ($currentNetBalance > 0 ? 'warning' : 'success'),
            ],
        ];
    }

    /**
     * Record a new salary or advance payment.
     * PRD §10.1: Any number of payments on any date, amount, method, note, and type Salary or Advance.
     */
    public function recordPayment(array $data, ?int $userId = null): SalaryPayment
    {
        $periodDate = CarbonImmutable::parse($data['period'])->startOfMonth()->toDateString();

        $payment = SalaryPayment::create([
            'employee_id' => $data['employee_id'],
            'period' => $periodDate,
            'paid_on' => $data['paid_on'],
            'amount' => $data['amount'],
            'type' => $data['type'] ?? 'salary',
            'method' => $data['method'] ?? 'cash',
            'note' => $data['note'] ?? null,
            'created_by' => $userId,
        ]);

        AuditService::log(
            'salary_payment.created',
            $payment,
            null,
            $payment->toArray(),
            $userId
        );

        return $payment;
    }

    /**
     * Update an existing payment entry with audit trail.
     */
    public function updatePayment(SalaryPayment $payment, array $data, ?int $userId = null): SalaryPayment
    {
        $old = $payment->toArray();

        if (isset($data['period'])) {
            $data['period'] = CarbonImmutable::parse($data['period'])->startOfMonth()->toDateString();
        }

        $payment->update($data);

        AuditService::log(
            'salary_payment.updated',
            $payment,
            $old,
            $payment->fresh()->toArray(),
            $userId
        );

        return $payment->fresh();
    }

    /**
     * Create or update salary month sheet row (base, bonus, deduction, note).
     * PRD §10.1: Editable base for that month if salary changes.
     */
    public function updateMonthSheet(
        array $data,
        ?SalaryMonth $salaryMonth = null,
        ?int $userId = null
    ): SalaryMonth {
        if (! $salaryMonth) {
            $employeeId = $data['employee_id'];
            $periodDate = CarbonImmutable::parse($data['period'])->startOfMonth()->toDateString();

            $salaryMonth = SalaryMonth::firstOrCreate(
                ['employee_id' => $employeeId, 'period' => $periodDate],
                [
                    'base_salary' => $data['base_salary'],
                    'bonus' => $data['bonus'] ?? 0.00,
                    'deduction' => $data['deduction'] ?? 0.00,
                    'note' => $data['note'] ?? null,
                    'created_by' => $userId,
                ]
            );

            // If it already existed, update its values
            $old = $salaryMonth->toArray();
            $salaryMonth->update([
                'base_salary' => $data['base_salary'],
                'bonus' => $data['bonus'] ?? 0.00,
                'deduction' => $data['deduction'] ?? 0.00,
                'note' => $data['note'] ?? null,
                'updated_by' => $userId,
            ]);

            AuditService::log(
                'salary_month.updated',
                $salaryMonth,
                $old,
                $salaryMonth->fresh()->toArray(),
                $userId
            );

            return $salaryMonth->fresh();
        }

        $old = $salaryMonth->toArray();

        $salaryMonth->update([
            'base_salary' => $data['base_salary'],
            'bonus' => $data['bonus'] ?? 0.00,
            'deduction' => $data['deduction'] ?? 0.00,
            'note' => $data['note'] ?? null,
            'updated_by' => $userId,
        ]);

        AuditService::log(
            'salary_month.updated',
            $salaryMonth,
            $old,
            $salaryMonth->fresh()->toArray(),
            $userId
        );

        return $salaryMonth->fresh();
    }

    /**
     * Create a new employee profile.
     */
    public function createEmployee(array $data, ?int $userId = null): Employee
    {
        $employee = Employee::create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'designation' => $data['designation'] ?? null,
            'monthly_salary' => $data['monthly_salary'],
            'joined_at' => $data['joined_at'] ?? null,
            'left_at' => $data['left_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        AuditService::log(
            'employee.created',
            $employee,
            null,
            $employee->toArray(),
            $userId
        );

        return $employee;
    }

    /**
     * Update an employee profile.
     */
    public function updateEmployee(Employee $employee, array $data, ?int $userId = null): Employee
    {
        $old = $employee->toArray();

        $employee->update($data);

        AuditService::log(
            'employee.updated',
            $employee,
            $old,
            $employee->fresh()->toArray(),
            $userId
        );

        return $employee->fresh();
    }

    /**
     * Soft-delete (archive) an employee while retaining complete payroll history.
     */
    public function archiveEmployee(Employee $employee, ?int $userId = null): void
    {
        $old = ['id' => $employee->id, 'name' => $employee->name, 'is_active' => $employee->is_active];

        $employee->update(['is_active' => false]);
        $employee->delete();

        AuditService::log(
            'employee.archived',
            $employee,
            $old,
            null,
            $userId
        );
    }

    /**
     * Restore a soft-deleted employee.
     */
    public function restoreEmployee(Employee $employee, ?int $userId = null): void
    {
        $employee->restore();
        $employee->update(['is_active' => true]);

        AuditService::log(
            'employee.restored',
            $employee,
            null,
            $employee->fresh()->toArray(),
            $userId
        );
    }

    /**
     * Accrued salary expense for P&L for a period (PRD §10.3 & Prompt 12 requirement 7).
     * Salary expense is the payable earned for the period, NOT cash paid.
     */
    public function getEarnedSalaryExpense(string|CarbonInterface $from, string|CarbonInterface $to): float
    {
        $fromDate = CarbonImmutable::parse($from)->startOfMonth()->toDateString();
        $toDate = CarbonImmutable::parse($to)->endOfMonth()->toDateString();

        return (float) SalaryMonth::whereBetween('period', [$fromDate, $toDate])
            ->sum('payable');
    }

    /**
     * Total cash paid out to employees for Cash Summary (does not affect P&L expense a second time).
     */
    public function getCashSalaryPaid(string|CarbonInterface $from, string|CarbonInterface $to): float
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        return (float) SalaryPayment::whereBetween('paid_on', [$fromDate, $toDate])
            ->sum('amount');
    }

    /**
     * Create a new wage worker profile.
     * PRD §10.2: name, phone, role, wage basis, rate, is_active.
     */
    public function createWorker(array $data, ?int $userId = null): Worker
    {
        $worker = Worker::create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'] ?? null,
            'wage_basis' => $data['wage_basis'],
            'rate' => $data['rate'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        AuditService::log(
            'worker.created',
            $worker,
            null,
            $worker->toArray(),
            $userId
        );

        return $worker;
    }

    /**
     * Update worker profile.
     */
    public function updateWorker(Worker $worker, array $data, ?int $userId = null): Worker
    {
        $old = $worker->toArray();

        $worker->update($data);

        AuditService::log(
            'worker.updated',
            $worker,
            $old,
            $worker->fresh()->toArray(),
            $userId
        );

        return $worker->fresh();
    }

    /**
     * Archive worker (soft delete) while retaining complete history.
     */
    public function archiveWorker(Worker $worker, ?int $userId = null): void
    {
        $old = ['id' => $worker->id, 'name' => $worker->name, 'is_active' => $worker->is_active];

        $worker->update(['is_active' => false]);
        $worker->delete();

        AuditService::log(
            'worker.archived',
            $worker,
            $old,
            null,
            $userId
        );
    }

    /**
     * Restore worker.
     */
    public function restoreWorker(Worker $worker, ?int $userId = null): void
    {
        $worker->restore();
        $worker->update(['is_active' => true]);

        AuditService::log(
            'worker.restored',
            $worker,
            null,
            $worker->fresh()->toArray(),
            $userId
        );
    }

    /**
     * Record an earnings entry for a worker.
     * PRD §10.2: work_date/period, qty, rate, amount (calculated or overridden), note.
     */
    public function recordWorkerEarning(array $data, ?int $userId = null): WorkerEarning
    {
        $worker = Worker::withTrashed()->findOrFail($data['worker_id']);

        $rate = isset($data['rate']) ? (float) $data['rate'] : (float) $worker->rate;
        $qty = isset($data['qty']) ? (float) $data['qty'] : 1.0;

        if (isset($data['amount']) && is_numeric($data['amount']) && (float) $data['amount'] > 0) {
            $amount = (float) $data['amount'];
        } else {
            $amount = round($qty * $rate, 2);
        }

        $earning = WorkerEarning::create([
            'worker_id' => $worker->id,
            'work_date' => $data['work_date'],
            'period_start' => $data['period_start'] ?? null,
            'qty' => $qty,
            'rate' => $rate,
            'amount' => $amount,
            'note' => $data['note'] ?? null,
            'created_by' => $userId,
        ]);

        AuditService::log(
            'worker_earning.created',
            $earning,
            null,
            $earning->toArray(),
            $userId
        );

        return $earning;
    }

    /**
     * Update an earnings entry with audit trail.
     */
    public function updateWorkerEarning(WorkerEarning $earning, array $data, ?int $userId = null): WorkerEarning
    {
        $old = $earning->toArray();

        $qty = isset($data['qty']) ? (float) $data['qty'] : (float) $earning->qty;
        $rate = isset($data['rate']) ? (float) $data['rate'] : (float) $earning->rate;

        if (isset($data['amount']) && is_numeric($data['amount']) && (float) $data['amount'] >= 0) {
            $amount = (float) $data['amount'];
        } else {
            $amount = round($qty * $rate, 2);
        }

        $data['qty'] = $qty;
        $data['rate'] = $rate;
        $data['amount'] = $amount;
        $data['updated_by'] = $userId;

        $earning->update($data);

        AuditService::log(
            'worker_earning.updated',
            $earning,
            $old,
            $earning->fresh()->toArray(),
            $userId
        );

        return $earning->fresh();
    }

    /**
     * Record a wage payment.
     * PRD §10.2: paid_on, amount, method, note.
     */
    public function recordWorkerPayment(array $data, ?int $userId = null): WorkerPayment
    {
        $payment = WorkerPayment::create([
            'worker_id' => $data['worker_id'],
            'paid_on' => $data['paid_on'],
            'amount' => $data['amount'],
            'method' => $data['method'] ?? 'cash',
            'note' => $data['note'] ?? null,
            'created_by' => $userId,
        ]);

        AuditService::log(
            'worker_payment.created',
            $payment,
            null,
            $payment->toArray(),
            $userId
        );

        return $payment;
    }

    /**
     * Update a worker payment with audit trail.
     */
    public function updateWorkerPayment(WorkerPayment $payment, array $data, ?int $userId = null): WorkerPayment
    {
        $old = $payment->toArray();

        $payment->update($data);

        AuditService::log(
            'worker_payment.updated',
            $payment,
            $old,
            $payment->fresh()->toArray(),
            $userId
        );

        return $payment->fresh();
    }

    /**
     * Process weekly payout helper batch disbursements.
     * Skips blank or <= 0 selections (PRD §10.2 requirement 6).
     */
    public function processBatchWorkerPayout(array $data, ?int $userId = null): array
    {
        $paidOn = $data['paid_on'] ?? CarbonImmutable::now()->toDateString();
        $defaultMethod = $data['method'] ?? 'cash';
        $batchNote = $data['note'] ?? 'Weekly wage payout';

        $payouts = $data['payouts'] ?? [];
        $createdPayments = [];
        $totalAmount = 0.0;

        DB::transaction(function () use ($payouts, $paidOn, $defaultMethod, $batchNote, $userId, &$createdPayments, &$totalAmount) {
            foreach ($payouts as $item) {
                $amount = isset($item['amount']) ? (float) $item['amount'] : 0.0;

                // Do not create payment records for blank/zero selections
                if ($amount <= 0) {
                    continue;
                }

                $payment = WorkerPayment::create([
                    'worker_id' => $item['worker_id'],
                    'paid_on' => $paidOn,
                    'amount' => $amount,
                    'method' => $item['method'] ?? $defaultMethod,
                    'note' => $item['note'] ?? $batchNote,
                    'created_by' => $userId,
                ]);

                AuditService::log(
                    'worker_payment.created',
                    $payment,
                    null,
                    $payment->toArray(),
                    $userId
                );

                $createdPayments[] = $payment;
                $totalAmount += $amount;
            }
        });

        return [
            'count' => count($createdPayments),
            'total_amount' => round($totalAmount, 2),
            'payments' => $createdPayments,
        ];
    }

    /**
     * Get workers summary table, current balances, and weekly/monthly accrued totals.
     * PRD §10.2 & Prompt 13 requirements 4 & 5.
     */
    public function getWorkersSummary(array $filters = []): array
    {
        $now = CarbonImmutable::now();
        $startOfWeek = $now->startOfWeek()->toDateString();
        $endOfWeek = $now->endOfWeek()->toDateString();
        $startOfMonth = $now->startOfMonth()->toDateString();
        $endOfMonth = $now->endOfMonth()->toDateString();

        $workers = Worker::withTrashed()
            ->orderBy('name')
            ->get();

        $earningsTotals = WorkerEarning::groupBy('worker_id')
            ->selectRaw('worker_id, SUM(amount) as total_earned')
            ->pluck('total_earned', 'worker_id');

        $earningsWeek = WorkerEarning::whereBetween('work_date', [$startOfWeek, $endOfWeek])
            ->groupBy('worker_id')
            ->selectRaw('worker_id, SUM(amount) as week_earned')
            ->pluck('week_earned', 'worker_id');

        $earningsMonth = WorkerEarning::whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->groupBy('worker_id')
            ->selectRaw('worker_id, SUM(amount) as month_earned')
            ->pluck('month_earned', 'worker_id');

        $paymentsTotals = WorkerPayment::groupBy('worker_id')
            ->selectRaw('worker_id, SUM(amount) as total_paid')
            ->pluck('total_paid', 'worker_id');

        $rows = [];
        $totals = [
            'total_workers' => 0,
            'active_workers' => 0,
            'total_earned_week' => 0.0,
            'total_earned_month' => 0.0,
            'total_earned_all' => 0.0,
            'total_paid_all' => 0.0,
            'total_net_balance' => 0.0,
            'total_owed_by_tasa' => 0.0,
            'total_overpaid_by_worker' => 0.0,
        ];

        foreach ($workers as $w) {
            $totalEarned = round((float) ($earningsTotals->get($w->id) ?? 0.0), 2);
            $totalPaid = round((float) ($paymentsTotals->get($w->id) ?? 0.0), 2);
            $earnedWeek = round((float) ($earningsWeek->get($w->id) ?? 0.0), 2);
            $earnedMonth = round((float) ($earningsMonth->get($w->id) ?? 0.0), 2);
            $currentBalance = round($totalEarned - $totalPaid, 2);

            if ($currentBalance < 0) {
                $statusLabel = 'Owes Tasa (Over-paid)';
                $statusTone = 'advance'; // Distinct violet badge
            } elseif (abs($currentBalance) < 0.005) {
                $statusLabel = 'Settled';
                $statusTone = 'success';
            } else {
                $statusLabel = 'Tasa Owes Worker';
                $statusTone = 'warning';
            }

            $row = [
                'id' => $w->id,
                'name' => $w->name,
                'phone' => $w->phone,
                'role' => $w->role,
                'wage_basis' => $w->wage_basis,
                'rate' => (float) $w->rate,
                'is_active' => (bool) $w->is_active,
                'is_archived' => $w->trashed(),
                'earned_this_week' => $earnedWeek,
                'earned_this_month' => $earnedMonth,
                'total_earned' => $totalEarned,
                'total_paid' => $totalPaid,
                'current_balance' => $currentBalance,
                'status_label' => $statusLabel,
                'status_tone' => $statusTone,
            ];

            // Apply filters
            $matchesSearch = true;
            if (! empty($filters['search'])) {
                $term = strtolower(trim($filters['search']));
                $matchesSearch = str_contains(strtolower($w->name), $term)
                    || str_contains((string) $w->phone, $term)
                    || str_contains(strtolower((string) $w->role), $term);
            }

            $matchesBasis = true;
            if (! empty($filters['wage_basis']) && $filters['wage_basis'] !== 'all') {
                $matchesBasis = $w->wage_basis === $filters['wage_basis'];
            }

            $matchesStatus = true;
            if (! empty($filters['status']) && $filters['status'] !== 'all') {
                if ($filters['status'] === 'active') {
                    $matchesStatus = $w->is_active && ! $w->trashed();
                } elseif ($filters['status'] === 'archived') {
                    $matchesStatus = $w->trashed();
                } elseif ($filters['status'] === 'owed') {
                    $matchesStatus = $currentBalance > 0;
                } elseif ($filters['status'] === 'overpaid') {
                    $matchesStatus = $currentBalance < 0;
                }
            }

            if ($matchesSearch && $matchesBasis && $matchesStatus) {
                $rows[] = $row;
            }

            $totals['total_workers']++;
            if ($w->is_active && ! $w->trashed()) {
                $totals['active_workers']++;
            }
            $totals['total_earned_week'] += $earnedWeek;
            $totals['total_earned_month'] += $earnedMonth;
            $totals['total_earned_all'] += $totalEarned;
            $totals['total_paid_all'] += $totalPaid;
            $totals['total_net_balance'] += $currentBalance;

            if ($currentBalance > 0) {
                $totals['total_owed_by_tasa'] += $currentBalance;
            } elseif ($currentBalance < 0) {
                $totals['total_overpaid_by_worker'] += abs($currentBalance);
            }
        }

        foreach ($totals as $k => $v) {
            if (is_float($v)) {
                $totals[$k] = round($v, 2);
            }
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Get chronological running ledger statement for a wage worker.
     * PRD §10.2: Running ledger table (date, earned, paid, running balance).
     */
    public function getWorkerStatement(
        Worker $worker,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $now = CarbonImmutable::now();
        $startOfWeek = $now->startOfWeek()->toDateString();
        $endOfWeek = $now->endOfWeek()->toDateString();
        $startOfMonth = $now->startOfMonth()->toDateString();
        $endOfMonth = $now->endOfMonth()->toDateString();

        // 1. Calculate opening balance (all earnings and payments strictly before $fromDate)
        $openingBalance = 0.0;
        if ($fromDate) {
            $priorEarned = (float) WorkerEarning::where('worker_id', $worker->id)
                ->where('work_date', '<', $fromDate)
                ->sum('amount');
            $priorPaid = (float) WorkerPayment::where('worker_id', $worker->id)
                ->where('paid_on', '<', $fromDate)
                ->sum('amount');
            $openingBalance = round($priorEarned - $priorPaid, 2);
        }

        // 2. Fetch all earnings within range
        $earningsQuery = WorkerEarning::with(['creator:id,name', 'updater:id,name'])
            ->where('worker_id', $worker->id);
        if ($fromDate) {
            $earningsQuery->where('work_date', '>=', $fromDate);
        }
        if ($toDate) {
            $earningsQuery->where('work_date', '<=', $toDate);
        }
        $earnings = $earningsQuery->get();

        // 3. Fetch all payments within range
        $paymentsQuery = WorkerPayment::with('creator:id,name')
            ->where('worker_id', $worker->id);
        if ($fromDate) {
            $paymentsQuery->where('paid_on', '>=', $fromDate);
        }
        if ($toDate) {
            $paymentsQuery->where('paid_on', '<=', $toDate);
        }
        $payments = $paymentsQuery->get();

        // 4. Combine into chronological ledger
        $ledgerEntries = [];

        foreach ($earnings as $e) {
            $desc = match ($worker->wage_basis) {
                'per_day' => "{$e->qty} day(s) @ ৳ " . number_format((float) $e->rate, 2),
                'per_piece' => "{$e->qty} piece(s) @ ৳ " . number_format((float) $e->rate, 2),
                'fixed_weekly' => 'Fixed weekly' . ($e->period_start ? ' (' . CarbonImmutable::parse($e->period_start)->format('M d') . ' – ' . CarbonImmutable::parse($e->work_date)->format('M d, Y') . ')' : ''),
                default => "{$e->qty} unit(s) @ ৳ " . number_format((float) $e->rate, 2),
            };

            $ledgerEntries[] = [
                'id' => 'e_' . $e->id,
                'source_id' => $e->id,
                'type' => 'earning',
                'date' => $e->work_date->toDateString(),
                'period_start' => $e->period_start?->toDateString(),
                'description' => $desc,
                'note' => $e->note,
                'qty' => (float) $e->qty,
                'rate' => (float) $e->rate,
                'earned' => (float) $e->amount,
                'paid' => 0.0,
                'created_by' => $e->creator?->name,
            ];
        }

        foreach ($payments as $p) {
            $methodLabel = $p->method ? ucfirst(str_replace('_', ' ', $p->method)) : 'Cash';
            $ledgerEntries[] = [
                'id' => 'p_' . $p->id,
                'source_id' => $p->id,
                'type' => 'payment',
                'date' => $p->paid_on->toDateString(),
                'period_start' => null,
                'description' => "Wage payment ({$methodLabel})",
                'note' => $p->note,
                'qty' => null,
                'rate' => null,
                'earned' => 0.0,
                'paid' => (float) $p->amount,
                'created_by' => $p->creator?->name,
            ];
        }

        // Sort chronologically by date asc, then earning before payment on same date
        usort($ledgerEntries, function ($a, $b) {
            if ($a['date'] === $b['date']) {
                if ($a['type'] === $b['type']) {
                    return $a['id'] <=> $b['id'];
                }
                return $a['type'] === 'earning' ? -1 : 1;
            }
            return $a['date'] <=> $b['date'];
        });

        // Compute running balance
        $runningBalance = $openingBalance;
        $totalEarnedInRange = 0.0;
        $totalPaidInRange = 0.0;

        foreach ($ledgerEntries as &$entry) {
            $runningBalance += $entry['earned'] - $entry['paid'];
            $entry['running_balance'] = round($runningBalance, 2);
            $totalEarnedInRange += $entry['earned'];
            $totalPaidInRange += $entry['paid'];
        }
        unset($entry);

        // Overall totals
        $allEarned = (float) WorkerEarning::where('worker_id', $worker->id)->sum('amount');
        $allPaid = (float) WorkerPayment::where('worker_id', $worker->id)->sum('amount');
        $currentBalance = round($allEarned - $allPaid, 2);

        $earnedWeek = (float) WorkerEarning::where('worker_id', $worker->id)
            ->whereBetween('work_date', [$startOfWeek, $endOfWeek])
            ->sum('amount');

        $earnedMonth = (float) WorkerEarning::where('worker_id', $worker->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        return [
            'worker' => [
                'id' => $worker->id,
                'name' => $worker->name,
                'phone' => $worker->phone,
                'role' => $worker->role,
                'wage_basis' => $worker->wage_basis,
                'rate' => (float) $worker->rate,
                'is_active' => (bool) $worker->is_active,
                'is_archived' => $worker->trashed(),
            ],
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => round($runningBalance, 2),
            'current_balance' => $currentBalance,
            'total_earned_range' => round($totalEarnedInRange, 2),
            'total_paid_range' => round($totalPaidInRange, 2),
            'total_earned_all' => round($allEarned, 2),
            'total_paid_all' => round($allPaid, 2),
            'earned_this_week' => round($earnedWeek, 2),
            'earned_this_month' => round($earnedMonth, 2),
            'is_owed' => $currentBalance > 0,
            'is_overpaid' => $currentBalance < 0,
            'status_label' => $currentBalance < 0
                ? 'Owes Tasa (Over-paid)'
                : ($currentBalance > 0 ? 'Tasa Owes Worker' : 'Fully Settled'),
            'status_tone' => $currentBalance < 0
                ? 'advance'
                : ($currentBalance > 0 ? 'warning' : 'success'),
            'ledger' => $ledgerEntries,
            'filters' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
        ];
    }

    /**
     * Accrued wage expense for P&L for a period (PRD §10.3 & Prompt 13 requirement 8).
     * If setting "Cost price includes labour" is Yes (1), returns 0.00 to prevent double counting.
     */
    public function getEarnedWageExpense(string|CarbonInterface $from, string|CarbonInterface $to): float
    {
        $labourInCost = Setting::get('payroll.cost_price_includes_labour', '0');
        if ($labourInCost === '1' || $labourInCost === true || $labourInCost === 1) {
            return 0.00;
        }

        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        return (float) WorkerEarning::whereBetween('work_date', [$fromDate, $toDate])
            ->sum('amount');
    }

    /**
     * Total cash paid out to wage workers for Cash Summary.
     */
    public function getCashWagePaid(string|CarbonInterface $from, string|CarbonInterface $to): float
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        return (float) WorkerPayment::whereBetween('paid_on', [$fromDate, $toDate])
            ->sum('amount');
    }
}
