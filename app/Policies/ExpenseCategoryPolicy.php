<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    /**
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('expenses.view');
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('settings.manage') || $user->can('expenses.create');
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->hasRole('Owner') || $user->can('settings.manage');
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, ExpenseCategory $category): bool
    {
        // System categories cannot be deleted
        if ($category->is_system) {
            return false;
        }

        return $user->hasRole('Owner') || $user->can('settings.manage');
    }
}
