<?php

namespace App\Policies;

use App\Models\SalaryMonth;
use App\Models\User;

class SalaryMonthPolicy
{
    /**
     * Determine whether the user can view any salary months.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.view');
    }

    /**
     * Determine whether the user can view the salary month.
     */
    public function view(User $user, SalaryMonth $salaryMonth): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.view');
    }

    /**
     * Determine whether the user can create salary months.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.create');
    }

    /**
     * Determine whether the user can update the salary month.
     */
    public function update(User $user, ?SalaryMonth $salaryMonth = null): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.update');
    }

    /**
     * Determine whether the user can delete the salary month.
     */
    public function delete(User $user, SalaryMonth $salaryMonth): bool
    {
        // Salary month history cannot be hard deleted per PRD §10.1 and Prompt 12 requirement 9
        return false;
    }
}
