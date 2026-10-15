<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkerEarning;

class WorkerEarningPolicy
{
    /**
     * Determine whether the user can view any worker earnings.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.view');
    }

    /**
     * Determine whether the user can view the worker earning.
     */
    public function view(User $user, WorkerEarning $workerEarning): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.view');
    }

    /**
     * Determine whether the user can create worker earnings.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.create');
    }

    /**
     * Determine whether the user can update the worker earning.
     */
    public function update(User $user, WorkerEarning $workerEarning): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.update');
    }

    /**
     * Determine whether the user can delete the worker earning.
     */
    public function delete(User $user, WorkerEarning $workerEarning): bool
    {
        // Earning ledger history cannot be hard deleted per PRD §10.2 and BR-4
        return false;
    }
}
