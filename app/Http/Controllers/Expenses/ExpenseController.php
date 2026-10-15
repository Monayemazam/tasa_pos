<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\StoreExpenseCategoryRequest;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\StoreRecurringExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseCategoryRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Http\Requests\Expenses\UpdateRecurringExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Services\Expenses\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {
    }

    /**
     * Display a listing of the expenses with summary cards, filters, and recurring reminders.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $filters = [
            'search' => $request->input('search', ''),
            'date_preset' => $request->input('date_preset', 'this_month'),
            'date_from' => $request->input('date_from', ''),
            'date_to' => $request->input('date_to', ''),
            'category_id' => $request->input('category_id', 'all'),
            'pl_group' => $request->input('pl_group', 'all'),
            'payment_method' => $request->input('payment_method', 'all'),
            'source' => $request->input('source', 'all'),
        ];

        $expenses = $this->expenseService->getFilteredExpenses($filters, 15);
        $analytics = $this->expenseService->getExpenseAnalytics($filters);
        $pendingRecurring = $this->expenseService->getPendingRecurringExpenses();

        $allCategories = ExpenseCategory::orderBy('name')->get();
        $recurringTemplates = RecurringExpense::with('category')->orderBy('day_of_month')->get();

        $user = $request->user();

        return Inertia::render('expenses/Expenses', [
            'expenses' => $expenses,
            'analytics' => $analytics,
            'pendingRecurring' => $pendingRecurring,
            'categories' => $allCategories,
            'recurringTemplates' => $recurringTemplates,
            'plGroups' => ExpenseCategory::PL_GROUPS,
            'filters' => $filters,
            'can' => [
                'create' => $user->can('create', Expense::class),
                'edit' => $user->hasRole('Owner') || $user->can('expenses.update'),
                'delete' => $user->hasRole('Owner') || $user->can('expenses.delete'),
                'manage_categories' => $user->hasRole('Owner') || $user->can('settings.manage'),
                'manage_recurring' => $user->hasRole('Owner') || $user->can('expenses.create'),
            ],
        ]);
    }

    /**
     * Store a newly created expense.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->expenseService->createExpense(
            $request->validated(),
            $request->file('attachment'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Expense recorded successfully.');
    }

    /**
     * Update the specified expense.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $this->expenseService->updateExpense(
            $expense,
            $request->validated(),
            $request->file('attachment'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Expense updated successfully.');
    }

    /**
     * Soft-delete the specified expense.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $this->expenseService->deleteExpense($expense, request()->user());

        return redirect()->back()->with('success', 'Expense deleted successfully.');
    }

    /**
     * Securely download or view private attachment.
     */
    public function downloadAttachment(Expense $expense): StreamedResponse
    {
        $this->authorize('downloadAttachment', $expense);

        if (! $expense->attachment_path || ! Storage::disk('local')->exists($expense->attachment_path)) {
            abort(404, 'Receipt attachment not found or already removed.');
        }

        $filename = basename($expense->attachment_path);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $downloadName = "expense-receipt-{$expense->id}.{$ext}";

        return Storage::disk('local')->download($expense->attachment_path, $downloadName);
    }

    /**
     * Store a new custom expense category.
     */
    public function storeCategory(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $this->expenseService->createCategory($request->validated());

        return redirect()->back()->with('success', 'Expense category created successfully.');
    }

    /**
     * Update an expense category.
     */
    public function updateCategory(UpdateExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $this->expenseService->updateCategory($category, $request->validated());

        return redirect()->back()->with('success', 'Expense category updated successfully.');
    }

    /**
     * Delete an expense category.
     */
    public function destroyCategory(ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $this->expenseService->deleteCategory($category);

        return redirect()->back()->with('success', 'Expense category deleted successfully.');
    }

    /**
     * Store a recurring expense template.
     */
    public function storeRecurring(StoreRecurringExpenseRequest $request): RedirectResponse
    {
        $this->expenseService->createRecurringExpense($request->validated(), $request->user());

        return redirect()->back()->with('success', 'Recurring expense template created successfully.');
    }

    /**
     * Update a recurring expense template.
     */
    public function updateRecurring(UpdateRecurringExpenseRequest $request, RecurringExpense $recurring): RedirectResponse
    {
        $this->authorize('update', $recurring);

        $this->expenseService->updateRecurringExpense($recurring, $request->validated(), $request->user());

        return redirect()->back()->with('success', 'Recurring expense template updated successfully.');
    }

    /**
     * Delete a recurring expense template.
     */
    public function destroyRecurring(RecurringExpense $recurring): RedirectResponse
    {
        $this->authorize('delete', $recurring);

        $this->expenseService->deleteRecurringExpense($recurring, request()->user());

        return redirect()->back()->with('success', 'Recurring expense template deleted successfully.');
    }

    /**
     * 1-Click confirm a pending recurring expense for the current month.
     */
    public function confirmRecurring(RecurringExpense $recurring): RedirectResponse
    {
        $this->authorize('confirm', $recurring);

        $expense = $this->expenseService->confirmRecurringExpense($recurring, request()->user());

        return redirect()->back()->with(
            'success',
            "Confirmed and logged {$recurring->category?->name} expense (৳" . number_format((float) $expense->amount, 2) . ") for " . now()->format('F Y') . '.'
        );
    }
}
