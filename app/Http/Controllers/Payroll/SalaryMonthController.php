<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\GenerateMonthlySheetsRequest;
use App\Http\Requests\Payroll\UpdateSalaryMonthRequest;
use App\Models\SalaryMonth;
use App\Services\Payroll\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class SalaryMonthController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {
    }

    /**
     * Generate monthly salary sheet rows on demand.
     */
    public function generate(GenerateMonthlySheetsRequest $request): RedirectResponse
    {
        $this->authorize('create', SalaryMonth::class);

        $period = $request->input('period') ?: CarbonImmutable::now()->format('Y-m');

        $count = $this->payrollService->generateMonthlySheets($period, $request->user()?->id);

        return back()->with('success', "Generated monthly salary sheet for {$count} active employee(s).");
    }

    /**
     * Update base salary, bonus, deduction, or note for a specific month.
     * PRD §10.1: Editable base for that month if salary changes.
     */
    public function update(UpdateSalaryMonthRequest $request, ?SalaryMonth $salaryMonth = null): RedirectResponse
    {
        $this->authorize('update', $salaryMonth ?? SalaryMonth::class);

        $this->payrollService->updateMonthSheet(
            $request->validated(),
            $salaryMonth,
            $request->user()?->id
        );

        return back()->with('success', 'Salary month sheet updated successfully.');
    }
}
