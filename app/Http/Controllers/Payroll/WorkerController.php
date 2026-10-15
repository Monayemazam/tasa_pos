<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\BatchWorkerPayoutRequest;
use App\Http\Requests\Payroll\StoreWorkerRequest;
use App\Http\Requests\Payroll\UpdateWorkerRequest;
use App\Models\Worker;
use App\Models\WorkerEarning;
use App\Models\WorkerPayment;
use App\Services\Payroll\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkerController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {
    }

    /**
     * Display the Wage Workers module with Running Ledgers, Weekly Payout Helper, and Logs.
     * PRD §10.2 & Prompt 13.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Worker::class);

        $search = $request->input('search', '');
        $wageBasis = $request->input('wage_basis', 'all');
        $status = $request->input('status', 'all');

        $summary = $this->payrollService->getWorkersSummary([
            'search' => $search,
            'wage_basis' => $wageBasis,
            'status' => $status,
        ]);

        $allWorkers = Worker::withTrashed()
            ->orderBy('name')
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'name' => $w->name,
                'phone' => $w->phone,
                'role' => $w->role,
                'wage_basis' => $w->wage_basis,
                'rate' => (float) $w->rate,
                'is_active' => (bool) $w->is_active,
                'is_archived' => $w->trashed(),
            ]);

        $recentEarnings = WorkerEarning::with(['worker:id,name,role,wage_basis', 'creator:id,name'])
            ->orderBy('work_date', 'desc')
            ->paginate(20, ['*'], 'earnings_page')
            ->withQueryString();

        $recentPayments = WorkerPayment::with(['worker:id,name,role', 'creator:id,name'])
            ->orderBy('paid_on', 'desc')
            ->paginate(20, ['*'], 'payments_page')
            ->withQueryString();

        $user = $request->user();

        $tab = $request->input('tab', 'workers');

        return Inertia::render('payroll/Workers', [
            'workers' => $summary['rows'],
            'totals' => $summary['totals'],
            'allWorkers' => $allWorkers,
            'recentEarnings' => $recentEarnings,
            'recentPayments' => $recentPayments,
            'filters' => [
                'search' => $search,
                'wage_basis' => $wageBasis,
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
     * Store a newly created worker profile.
     */
    public function store(StoreWorkerRequest $request): RedirectResponse
    {
        $this->authorize('create', Worker::class);

        $worker = $this->payrollService->createWorker($request->validated(), $request->user()?->id);

        return back()->with('success', "Worker {$worker->name} profile created successfully.");
    }

    /**
     * Display the specified worker's ledger summary.
     */
    public function show(Worker $worker): JsonResponse
    {
        $this->authorize('view', $worker);

        $statement = $this->payrollService->getWorkerStatement($worker);

        return response()->json($statement);
    }

    /**
     * Update the worker profile.
     */
    public function update(UpdateWorkerRequest $request, Worker $worker): RedirectResponse
    {
        $this->authorize('update', $worker);

        $this->payrollService->updateWorker($worker, $request->validated(), $request->user()?->id);

        return back()->with('success', "Worker {$worker->name} profile updated successfully.");
    }

    /**
     * Soft-delete (archive) the worker.
     */
    public function destroy(Request $request, Worker $worker): RedirectResponse
    {
        $this->authorize('delete', $worker);

        $this->payrollService->archiveWorker($worker, $request->user()?->id);

        return back()->with('success', "Worker {$worker->name} archived successfully. Ledger records are preserved.");
    }

    /**
     * Restore an archived worker.
     */
    public function restore(Request $request, int $id): RedirectResponse
    {
        $worker = Worker::withTrashed()->findOrFail($id);

        $this->authorize('restore', $worker);

        $this->payrollService->restoreWorker($worker, $request->user()?->id);

        return back()->with('success', "Worker {$worker->name} restored successfully.");
    }

    /**
     * Display printable statement and chronological running ledger for a worker.
     * PRD §10.2 & Prompt 13 requirements 4, 5 & 7.
     */
    public function statement(Request $request, Worker $worker): Response
    {
        $this->authorize('view', $worker);

        $from = $request->input('from');
        $to = $request->input('to');

        $statement = $this->payrollService->getWorkerStatement($worker, $from, $to);

        return Inertia::render('payroll/WorkerStatement', [
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

    /**
     * Process weekly payout helper batch disbursements.
     * PRD §10.2 requirement 6: Do not create payment records for blank/zero selections.
     */
    public function batchPayout(BatchWorkerPayoutRequest $request): RedirectResponse
    {
        $this->authorize('create', WorkerPayment::class);

        $result = $this->payrollService->processBatchWorkerPayout(
            $request->validated(),
            $request->user()?->id
        );

        $amountFormatted = number_format($result['total_amount'], 2);

        return back()->with('success', "Successfully recorded weekly payouts of ৳ {$amountFormatted} for {$result['count']} worker(s).");
    }
}
