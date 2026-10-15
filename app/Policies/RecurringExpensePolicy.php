<?php

namespace App\Policies;

use App\Models\RecurringExpense;
use App\Models\User;

class RecurringExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('expenses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('expenses.create');
    }

    public function update(User $user, RecurringExpense $recurring): bool
    {
        return $user->hasRole('Owner') || $user->can('expenses.update');
    }

    public function delete(User $user, RecurringExpense $recurring): bool
    {
        return $user->hasRole('Owner') || $user->can('expenses.delete');
    }

    public function confirm(User $user, RecurringExpense $recurring): bool
    {
        return $user->hasRole('Owner') || $user->can('expenses.create');
    }
}
