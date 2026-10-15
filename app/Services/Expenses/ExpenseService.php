<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    /**
     * Forbidden category names / patterns to enforce strict PRD §3 boundary rules (BR-5, BR-9).
     * Material purchases are inventory assets, and payroll is managed in the Payroll module.
     */
    protected const FORBIDDEN_CATEGORY_PATTERNS = [
        'leather',
        'raw material',
        'accessory purchase',
        'salary',
        'wage',
        'payroll',
        'worker payout',
        'employee salary',
    ];

    /**
     * Get paginated expenses with filters applied.
     */
    public function getFilteredExpenses(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Expense::query()
            ->with([
                'category:id,name,pl_group,is_system',
                'creator:id,name',
                'sale:id,invoice_no',
                'courierOrder:id,consignment_id,status',
            ])
            ->latest('expense_date')
            ->latest('id');

        // Search
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('vendor', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('sale', fn ($sq) => $sq->where('invoice_no', 'like', "%{$search}%"))
                    ->orWhereHas('courierOrder', fn ($coq) => $coq->where('consignment_id', 'like', "%{$search}%"));
            });
        }

        // Date range
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);
        if ($dateFrom) {
            $query->whereDate('expense_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('expense_date', '<=', $dateTo);
        }

        // Category filter
        if (! empty($filters['category_id']) && $filters['category_id'] !== 'all') {
            $query->where('expense_category_id', $filters['category_id']);
        }

        // PL Group filter
        if (! empty($filters['pl_group']) && $filters['pl_group'] !== 'all') {
            $plGroup = $filters['pl_group'];
            $query->whereHas('category', fn ($cq) => $cq->where('pl_group', $plGroup));
        }

        // Payment method filter
        if (! empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Source filter
        if (! empty($filters['source']) && $filters['source'] !== 'all') {
            $query->where('source', $filters['source']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Compute aggregated expense analytics for the filtered date range.
     */
    public function getExpenseAnalytics(array $filters): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        $query = Expense::query()->whereNull('deleted_at');

        if ($dateFrom) {
            $query->whereDate('expense_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('expense_date', '<=', $dateTo);
        }

        $allExpenses = $query->with('category:id,name,pl_group')->get();

        $totalAmount = (float) $allExpenses->sum('amount');
        $totalCount = $allExpenses->count();

        // Group by category
        $byCategory = $allExpenses->groupBy('expense_category_id')->map(function ($group) use ($totalAmount) {
            $first = $group->first();
            $amount = (float) $group->sum('amount');
            return [
                'category_id' => $first->expense_category_id,
                'name' => $first->category?->name ?? 'Uncategorized',
                'pl_group' => $first->category?->pl_group ?? 'other',
                'amount' => $amount,
                'count' => $group->count(),
                'percentage' => $totalAmount > 0 ? round(($amount / $totalAmount) * 100, 1) : 0,
            ];
        })->sortByDesc('amount')->values()->all();

        // Group by PL Group
        $byPlGroup = [
            'marketing_facebook' => 0.0,
            'marketing_other' => 0.0,
            'courier' => 0.0,
            'packaging' => 0.0,
            'rent_utilities' => 0.0,
            'other' => 0.0,
        ];

        foreach ($allExpenses as $exp) {
            $group = $exp->category?->pl_group ?? 'other';
            if (isset($byPlGroup[$group])) {
                $byPlGroup[$group] += (float) $exp->amount;
            } else {
                $byPlGroup['other'] += (float) $exp->amount;
            }
        }

        // Group by Payment Method
        $byPaymentMethod = $allExpenses->groupBy('payment_method')->map(fn ($group) => (float) $group->sum('amount'))->all();

        return [
            'total_amount' => $totalAmount,
            'total_count' => $totalCount,
            'facebook_ads_total' => $byPlGroup['marketing_facebook'],
            'other_marketing_total' => $byPlGroup['marketing_other'],
            'courier_total' => $byPlGroup['courier'],
            'packaging_total' => $byPlGroup['packaging'],
            'rent_utilities_total' => $byPlGroup['rent_utilities'],
            'other_total' => $byPlGroup['other'],
            'by_category' => $byCategory,
            'by_pl_group' => $byPlGroup,
            'by_payment_method' => $byPaymentMethod,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * Create a new business expense with validation and private attachment handling.
     */
    public function createExpense(array $data, ?UploadedFile $attachment, User $user): Expense
    {
        $category = ExpenseCategory::findOrFail($data['expense_category_id']);

        $this->guardAgainstBoundaryViolations($category->name, $data['note'] ?? '');

        if (! $category->is_active) {
            throw ValidationException::withMessages([
                'expense_category_id' => 'Cannot log expenses to an inactive category.',
            ]);
        }

        $attachmentPath = null;
        if ($attachment && $attachment->isValid()) {
            $attachmentPath = $attachment->store('expenses', 'local');
        }

        $expense = Expense::create([
            'expense_category_id' => $category->id,
            'expense_date' => $data['expense_date'] ?? now()->toDateString(),
            'amount' => round((float) $data['amount'], 2),
            'payment_method' => $data['payment_method'] ?? 'cash',
            'vendor' => ! empty($data['vendor']) ? trim($data['vendor']) : null,
            'note' => ! empty($data['note']) ? trim($data['note']) : null,
            'attachment_path' => $attachmentPath,
            'source' => $data['source'] ?? 'manual',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        AuditService::log('expense_created', Expense::class, null, [
            'id' => $expense->id,
            'category' => $category->name,
            'amount' => $expense->amount,
            'date' => $expense->expense_date->toDateString(),
            'method' => $expense->payment_method,
            'vendor' => $expense->vendor,
        ]);

        return $expense;
    }

    /**
     * Update an existing manual or recurring expense.
     */
    public function updateExpense(Expense $expense, array $data, ?UploadedFile $attachment, User $user): Expense
    {
        if (in_array($expense->source, ['pathao_charge', 'return_courier'], true)) {
            throw new Exception("System courier charges are automatically linked to logistics and cannot be manually modified.");
        }

        $oldValues = $expense->toArray();

        $category = ExpenseCategory::findOrFail($data['expense_category_id']);
        $this->guardAgainstBoundaryViolations($category->name, $data['note'] ?? '');

        $attachmentPath = $expense->attachment_path;

        if ($attachment && $attachment->isValid()) {
            // Delete previous attachment if exists
            if ($attachmentPath && Storage::disk('local')->exists($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }
            $attachmentPath = $attachment->store('expenses', 'local');
        } elseif (! empty($data['remove_attachment']) && $data['remove_attachment'] == '1') {
            if ($attachmentPath && Storage::disk('local')->exists($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }
            $attachmentPath = null;
        }

        $expense->update([
            'expense_category_id' => $category->id,
            'expense_date' => $data['expense_date'] ?? $expense->expense_date,
            'amount' => round((float) $data['amount'], 2),
            'payment_method' => $data['payment_method'] ?? $expense->payment_method,
            'vendor' => ! empty($data['vendor']) ? trim($data['vendor']) : null,
            'note' => ! empty($data['note']) ? trim($data['note']) : null,
            'attachment_path' => $attachmentPath,
            'updated_by' => $user->id,
        ]);

        AuditService::log('expense_updated', Expense::class, $oldValues, [
            'id' => $expense->id,
            'category' => $category->name,
            'amount' => $expense->amount,
            'date' => $expense->expense_date->toDateString(),
            'method' => $expense->payment_method,
            'vendor' => $expense->vendor,
        ]);

        return $expense;
    }

    /**
     * Delete an expense (soft-delete with audit).
     */
    public function deleteExpense(Expense $expense, User $user): bool
    {
        if (in_array($expense->source, ['pathao_charge', 'return_courier'], true)) {
            throw new Exception("System courier charges cannot be deleted directly.");
        }

        $old = [
            'id' => $expense->id,
            'amount' => $expense->amount,
            'category_id' => $expense->expense_category_id,
            'date' => $expense->expense_date?->toDateString(),
            'source' => $expense->source,
        ];

        $expense->delete();

        AuditService::log('expense_deleted', Expense::class, $old, null);

        return true;
    }

    /**
     * Get active recurring expense templates awaiting 1-click confirmation for the current month.
     */
    public function getPendingRecurringExpenses(): Collection
    {
        $currentPeriod = now()->format('Y-m');

        return RecurringExpense::query()
            ->where('is_active', true)
            ->where(function ($q) use ($currentPeriod) {
                $q->whereNull('last_confirmed_period')
                    ->orWhere('last_confirmed_period', '!=', $currentPeriod);
            })
            ->with('category:id,name,pl_group')
            ->orderBy('day_of_month')
            ->get();
    }

    /**
     * Confirm and generate an expense instance from a recurring template (BR-9).
     * Never silently posts; guarantees exactly 1 instance per template per month.
     */
    public function confirmRecurringExpense(RecurringExpense $recurring, User $user): Expense
    {
        $currentPeriod = now()->format('Y-m');

        // Check if already confirmed for this period
        if ($recurring->last_confirmed_period === $currentPeriod) {
            throw ValidationException::withMessages([
                'recurring' => "This recurring expense has already been confirmed for {$currentPeriod}.",
            ]);
        }

        // Duplicate check on expenses table
        $existing = Expense::where('recurring_expense_id', $recurring->id)
            ->whereYear('expense_date', now()->year)
            ->whereMonth('expense_date', now()->month)
            ->first();

        if ($existing) {
            $recurring->update(['last_confirmed_period' => $currentPeriod]);
            throw ValidationException::withMessages([
                'recurring' => "An expense instance already exists for this template in {$currentPeriod}.",
            ]);
        }

        return DB::transaction(function () use ($recurring, $user, $currentPeriod) {
            $category = $recurring->category;

            $expense = Expense::create([
                'expense_category_id' => $recurring->expense_category_id,
                'expense_date' => now()->toDateString(),
                'amount' => $recurring->amount,
                'payment_method' => $recurring->payment_method ?: 'cash',
                'vendor' => $recurring->vendor,
                'note' => $recurring->note ?: "Monthly recurring {$category?->name} for " . now()->format('F Y'),
                'source' => 'recurring',
                'recurring_expense_id' => $recurring->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $recurring->update([
                'last_confirmed_period' => $currentPeriod,
            ]);

            AuditService::log('recurring_expense_confirmed', RecurringExpense::class, null, [
                'recurring_expense_id' => $recurring->id,
                'expense_id' => $expense->id,
                'period' => $currentPeriod,
                'amount' => $expense->amount,
                'confirmed_by' => $user->id,
            ]);

            return $expense;
        });
    }

    /**
     * Create a recurring expense template.
     */
    public function createRecurringExpense(array $data, User $user): RecurringExpense
    {
        $category = ExpenseCategory::findOrFail($data['expense_category_id']);
        $this->guardAgainstBoundaryViolations($category->name, $data['note'] ?? '');

        $recurring = RecurringExpense::create([
            'expense_category_id' => $category->id,
            'amount' => round((float) $data['amount'], 2),
            'vendor' => ! empty($data['vendor']) ? trim($data['vendor']) : null,
            'note' => ! empty($data['note']) ? trim($data['note']) : null,
            'payment_method' => $data['payment_method'] ?? 'cash',
            'day_of_month' => (int) ($data['day_of_month'] ?? 1),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);

        AuditService::log('recurring_expense_created', RecurringExpense::class, null, $recurring->toArray());

        return $recurring;
    }

    /**
     * Update a recurring expense template.
     */
    public function updateRecurringExpense(RecurringExpense $recurring, array $data, User $user): RecurringExpense
    {
        $old = $recurring->toArray();

        $updateData = [];
        if (! empty($data['expense_category_id'])) {
            $category = ExpenseCategory::findOrFail($data['expense_category_id']);
            $this->guardAgainstBoundaryViolations($category->name, $data['note'] ?? $recurring->note ?? '');
            $updateData['expense_category_id'] = $category->id;
        }

        if (isset($data['amount'])) {
            $updateData['amount'] = round((float) $data['amount'], 2);
        }
        if (array_key_exists('vendor', $data)) {
            $updateData['vendor'] = ! empty($data['vendor']) ? trim($data['vendor']) : null;
        }
        if (array_key_exists('note', $data)) {
            $updateData['note'] = ! empty($data['note']) ? trim($data['note']) : null;
        }
        if (array_key_exists('payment_method', $data)) {
            $updateData['payment_method'] = $data['payment_method'] ?? 'cash';
        }
        if (isset($data['day_of_month'])) {
            $updateData['day_of_month'] = (int) $data['day_of_month'];
        }
        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = (bool) $data['is_active'];
        }

        $recurring->update($updateData);

        AuditService::log('recurring_expense_updated', RecurringExpense::class, $old, $recurring->toArray());

        return $recurring;
    }

    /**
     * Delete a recurring expense template.
     */
    public function deleteRecurringExpense(RecurringExpense $recurring, User $user): bool
    {
        $old = $recurring->toArray();
        $recurring->delete();

        AuditService::log('recurring_expense_deleted', RecurringExpense::class, $old, null);

        return true;
    }

    /**
     * Create an expense category with P&L group validation.
     */
    public function createCategory(array $data): ExpenseCategory
    {
        $this->guardAgainstBoundaryViolations($data['name'], '');

        if (! array_key_exists($data['pl_group'], ExpenseCategory::PL_GROUPS)) {
            throw ValidationException::withMessages([
                'pl_group' => 'Invalid P&L mapping group specified.',
            ]);
        }

        $category = ExpenseCategory::create([
            'name' => trim($data['name']),
            'pl_group' => $data['pl_group'],
            'is_system' => false,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);

        AuditService::log('expense_category_created', ExpenseCategory::class, null, $category->toArray());

        return $category;
    }

    /**
     * Update an expense category.
     */
    public function updateCategory(ExpenseCategory $category, array $data): ExpenseCategory
    {
        $old = $category->toArray();

        $updateData = [];

        // System category names and pl_groups are protected
        if (! $category->is_system) {
            $this->guardAgainstBoundaryViolations($data['name'] ?? $category->name, '');
            if (! empty($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (! empty($data['pl_group'])) {
                if (! array_key_exists($data['pl_group'], ExpenseCategory::PL_GROUPS)) {
                    throw ValidationException::withMessages(['pl_group' => 'Invalid P&L group specified.']);
                }
                $updateData['pl_group'] = $data['pl_group'];
            }
        }

        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = (bool) $data['is_active'];
        }

        $category->update($updateData);

        AuditService::log('expense_category_updated', ExpenseCategory::class, $old, $category->toArray());

        return $category;
    }

    /**
     * Delete an expense category (blocked if system or if expenses exist).
     */
    public function deleteCategory(ExpenseCategory $category): bool
    {
        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => "System category '{$category->name}' is protected and cannot be deleted.",
            ]);
        }

        if ($category->expenses()->count() > 0) {
            throw ValidationException::withMessages([
                'category' => "Cannot delete category '{$category->name}' because expenses are linked to it. You can deactivate it instead.",
            ]);
        }

        if ($category->recurringExpenses()->count() > 0) {
            throw ValidationException::withMessages([
                'category' => "Cannot delete category '{$category->name}' because recurring templates exist for it.",
            ]);
        }

        $old = $category->toArray();
        $category->delete();

        AuditService::log('expense_category_deleted', ExpenseCategory::class, $old, null);

        return true;
    }

    /**
     * Enforce strict PRD boundary: prevent registering payroll or material purchases as operating expenses.
     */
    protected function guardAgainstBoundaryViolations(string $name, string $note): void
    {
        $haystack = strtolower("{$name} {$note}");

        foreach (self::FORBIDDEN_CATEGORY_PATTERNS as $pattern) {
            if (str_contains($haystack, $pattern)) {
                throw ValidationException::withMessages([
                    'name' => "Material purchases (leather/accessories) are inventory assets tracked in the Inventory module, and payroll must be recorded in the Payroll module. They cannot be logged as ordinary operating expenses (PRD BR-5 & BR-9).",
                ]);
            }
        }
    }

    /**
     * Resolve date range from preset or custom inputs.
     */
    protected function resolveDateRange(array $filters): array
    {
        $preset = $filters['date_preset'] ?? 'this_month';
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        if ($preset === 'today') {
            return [now()->toDateString(), now()->toDateString()];
        }
        if ($preset === 'yesterday') {
            return [now()->subDay()->toDateString(), now()->subDay()->toDateString()];
        }
        if ($preset === 'last_7_days') {
            return [now()->subDays(6)->toDateString(), now()->toDateString()];
        }
        if ($preset === 'this_month') {
            return [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        }
        if ($preset === 'last_month') {
            return [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()];
        }
        if ($preset === 'last_30_days') {
            return [now()->subDays(29)->toDateString(), now()->toDateString()];
        }

        return [$from, $to];
    }
}
