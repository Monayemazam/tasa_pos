<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Determine whether the user can view any expenses.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('expenses.view');
    }

    /**
     * Determine whether the user can view the expense.
     */
    public function view(User $user, Expense $expense): bool
    {
        return $user->hasRole('Owner') || $user->can('expenses.view');
    }

    /**
     * Determine whether the user can create expenses.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('expenses.create');
    }

    /**
     * Determine whether the user can update the expense.
     */
    public function update(User $user, Expense $expense): bool
    {
        // System generated expenses (pathao_charge, return_courier) are immutable
        if (in_array($expense->source, ['pathao_charge', 'return_courier'], true)) {
            return false;
        }

        return $user->hasRole('Owner') || $user->can('expenses.update');
    }

    /**
     * Determine whether the user can delete the expense.
     */
    public function delete(User $user, Expense $expense): bool
    {
        // System generated expenses (pathao_charge, return_courier) cannot be deleted
        if (in_array($expense->source, ['pathao_charge', 'return_courier'], true)) {
            return false;
        }

        return $user->hasRole('Owner') || $user->can('expenses.delete');
    }

    /**
     * Determine whether the user can download the attachment.
     */
    public function downloadAttachment(User $user, Expense $expense): bool
    {
        return $user->hasRole('Owner') || $user->can('expenses.view');
    }
}
