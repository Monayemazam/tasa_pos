<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreEmployeeRequest;
use App\Http\Requests\Payroll\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Services\Payroll\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {
    }

    /**
     * Display the Salaried Employees module with Monthly Sheet, Profiles, and Payment Records.
     * PRD §10.1 & Prompt 12.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $month = $request->input('month', CarbonImmutable::now()->format('Y-m'));
        $search = $request->input('search', '');
        $status = $request->input('status', 'all');

        $monthlySheet = $this->payrollService->getMonthlySheet($month, [
            'search' => $search,
            'status' => $status,
        ]);

        $allEmployees = Employee::withTrashed()
            ->orderBy('name')
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'name' => $emp->name,
                'phone' => $emp->phone,
                'designation' => $emp->designation,
                'monthly_salary' => (float) $emp->monthly_salary,
                'joined_at' => $emp->joined_at?->toDateString(),
                'left_at' => $emp->left_at?->toDateString(),
                'is_active' => (bool) $emp->is_active,
                'is_archived' => $emp->trashed(),
            ]);

        $paymentsQuery = SalaryPayment::with(['employee:id,name,phone', 'creator:id,name'])
            ->orderBy('paid_on', 'desc');

        if ($month) {
            $periodDate = CarbonImmutable::parse($month)->startOfMonth()->toDateString();
            $paymentsQuery->where('period', $periodDate);
        }

        $recentPayments = $paymentsQuery->paginate(20)->withQueryString();

        $user = $request->user();

        $tab = $request->input('tab', 'sheet');

        return Inertia::render('payroll/Employees', [
            'month' => $month,
            'period' => $monthlySheet['period'],
            'periodLabel' => $monthlySheet['period_label'],
            'sheetRows' => $monthlySheet['rows'],
            'totals' => $monthlySheet['totals'],
            'employees' => $allEmployees,
            'payments' => $recentPayments,
            'filters' => [
                'month' => $month,
                'search' => $search,
                'status' => $status,
                'tab' => $tab,
            ],
            'can' => [
                'create' => $user->hasRole('Owner') || $user->can('payroll.create'),
                'update' => $user->hasRole('Owner') || $user->can('payroll.update'),
                'delete' => $user->hasRole('Owner') || $user->can('payroll.delete'),
            ],
        ]);
    }

    /**
     * Store a newly created employee profile.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $this->payrollService->createEmployee($request->validated(), $request->user()?->id);

        // Auto-generate salary month for current month if employee is active
        if ($employee->is_active) {
            $this->payrollService->generateMonthlySheets(CarbonImmutable::now(), $request->user()?->id);
        }

        return back()->with('success', "Employee {$employee->name} profile created successfully.");
    }

    /**
     * Display the specified employee profile.
     */
    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        $statement = $this->payrollService->getEmployeeStatement($employee);

        return response()->json($statement);
    }

    /**
     * Update the employee profile.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $this->payrollService->updateEmployee($employee, $request->validated(), $request->user()?->id);

        return back()->with('success', "Employee {$employee->name} profile updated successfully.");
    }

    /**
     * Soft-delete (archive) the employee.
     * Schema & PRD §10.1: No hard delete of payroll history; soft-delete only.
     */
    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $this->payrollService->archiveEmployee($employee, $request->user()?->id);

        return back()->with('success', "Employee {$employee->name} archived successfully. Payroll records are preserved.");
    }

    /**
     * Restore an archived employee.
     */
    public function restore(Request $request, int $id): RedirectResponse
    {
        $employee = Employee::withTrashed()->findOrFail($id);

        $this->authorize('restore', $employee);

        $this->payrollService->restoreEmployee($employee, $request->user()?->id);

        return back()->with('success', "Employee {$employee->name} restored successfully.");
    }

    /**
     * Display printable statement and chronological ledger for an employee.
     * PRD §10.1 & Prompt 12 requirement 8.
     */
    public function statement(Request $request, Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $from = $request->input('from');
        $to = $request->input('to');

        $statement = $this->payrollService->getEmployeeStatement($employee, $from, $to);

        return Inertia::render('payroll/Statement', [
            'statement' => $statement,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
            'can' => [
                'create' => $request->user()->hasRole('Owner') || $request->user()->can('payroll.create'),
                'update' => $request->user()->hasRole('Owner') || $request->user()->can('payroll.update'),
            ],
        ]);
    }
}
