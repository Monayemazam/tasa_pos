<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkerPayment;

class WorkerPaymentPolicy
{
    /**
     * Determine whether the user can view any worker payments.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.view');
    }

    /**
     * Determine whether the user can view the worker payment.
     */
    public function view(User $user, WorkerPayment $workerPayment): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.view');
    }

    /**
     * Determine whether the user can create worker payments.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.create');
    }

    /**
     * Determine whether the user can update the worker payment.
     */
    public function update(User $user, WorkerPayment $workerPayment): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.update');
    }

    /**
     * Determine whether the user can delete the worker payment.
     */
    public function delete(User $user, WorkerPayment $workerPayment): bool
    {
        // Payment ledger history cannot be hard deleted per PRD §10.2 and BR-4
        return false;
    }
}
