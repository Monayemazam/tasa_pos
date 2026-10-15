<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreWorkerEarningRequest;
use App\Http\Requests\Payroll\UpdateWorkerEarningRequest;
use App\Models\WorkerEarning;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\RedirectResponse;

class WorkerEarningController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {
    }

    /**
     * Record a new earning entry for a wage worker.
     * PRD §10.2: date/period, quantity, rate, calculated earned amount, note.
     */
    public function store(StoreWorkerEarningRequest $request): RedirectResponse
    {
        $this->authorize('create', WorkerEarning::class);

        $earning = $this->payrollService->recordWorkerEarning(
            $request->validated(),
            $request->user()?->id
        );

        return back()->with('success', "Recorded earning of ৳ " . number_format((float) $earning->amount, 2) . " successfully.");
    }

    /**
     * Update an existing earning entry with audit trail.
     */
    public function update(UpdateWorkerEarningRequest $request, WorkerEarning $workerEarning): RedirectResponse
    {
        $this->authorize('update', $workerEarning);

        $earning = $this->payrollService->updateWorkerEarning(
            $workerEarning,
            $request->validated(),
            $request->user()?->id
        );

        return back()->with('success', 'Earning record updated successfully.');
    }
}
