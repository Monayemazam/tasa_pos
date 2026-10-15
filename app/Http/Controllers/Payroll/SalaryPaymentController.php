<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreSalaryPaymentRequest;
use App\Http\Requests\Payroll\UpdateSalaryPaymentRequest;
use App\Models\SalaryPayment;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\RedirectResponse;

class SalaryPaymentController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {
    }

    /**
     * Record a new salary or advance payment.
     * PRD §10.1: Any number of payments on any date, amount, method, note, and type Salary or Advance.
     */
    public function store(StoreSalaryPaymentRequest $request): RedirectResponse
    {
        $this->authorize('create', SalaryPayment::class);

        $payment = $this->payrollService->recordPayment(
            $request->validated(),
            $request->user()?->id
        );

        $typeLabel = ucfirst($payment->type);

        return back()->with('success', "{$typeLabel} payment of ৳ " . number_format((float) $payment->amount, 2) . ' recorded successfully.');
    }

    /**
     * Update an existing payment entry with audit trail.
     */
    public function update(UpdateSalaryPaymentRequest $request, SalaryPayment $salaryPayment): RedirectResponse
    {
        $this->authorize('update', $salaryPayment);

        $payment = $this->payrollService->updatePayment(
            $salaryPayment,
            $request->validated(),
            $request->user()?->id
        );

        return back()->with('success', 'Payment record updated successfully.');
    }
}
