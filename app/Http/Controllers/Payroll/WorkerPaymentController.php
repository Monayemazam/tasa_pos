<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreWorkerPaymentRequest;
use App\Http\Requests\Payroll\UpdateWorkerPaymentRequest;
use App\Models\WorkerPayment;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\RedirectResponse;

class WorkerPaymentController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {
    }

    /**
     * Record a wage payment.
     * PRD §10.2: date, amount, method, note.
     */
    public function store(StoreWorkerPaymentRequest $request): RedirectResponse
    {
        $this->authorize('create', WorkerPayment::class);

        $payment = $this->payrollService->recordWorkerPayment(
            $request->validated(),
            $request->user()?->id
        );

        return back()->with('success', "Wage payment of ৳ " . number_format((float) $payment->amount, 2) . " recorded successfully.");
    }

    /**
     * Update an existing payment entry with audit trail.
     */
    public function update(UpdateWorkerPaymentRequest $request, WorkerPayment $workerPayment): RedirectResponse
    {
        $this->authorize('update', $workerPayment);

        $payment = $this->payrollService->updateWorkerPayment(
            $workerPayment,
            $request->validated(),
            $request->user()?->id
        );

        return back()->with('success', 'Wage payment record updated successfully.');
    }
}
