<?php

namespace App\Policies;

use App\Models\SalaryPayment;
use App\Models\User;

class SalaryPaymentPolicy
{
    /**
     * Determine whether the user can view any salary payments.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.view');
    }

    /**
     * Determine whether the user can view the salary payment.
     */
    public function view(User $user, SalaryPayment $salaryPayment): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.view');
    }

    /**
     * Determine whether the user can create salary payments.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.create');
    }

    /**
     * Determine whether the user can update the salary payment.
     */
    public function update(User $user, SalaryPayment $salaryPayment): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.update');
    }

    /**
     * Determine whether the user can delete the salary payment.
     */
    public function delete(User $user, SalaryPayment $salaryPayment): bool
    {
        // Salary payments cannot be hard deleted per PRD §10.1 and Prompt 12 requirement 9
        return false;
    }
}
