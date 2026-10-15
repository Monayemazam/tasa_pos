<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;

class WorkerPolicy
{
    /**
     * Determine whether the user can view any workers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.view');
    }

    /**
     * Determine whether the user can view the worker.
     */
    public function view(User $user, Worker $worker): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.view');
    }

    /**
     * Determine whether the user can create workers.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.create');
    }

    /**
     * Determine whether the user can update the worker.
     */
    public function update(User $user, Worker $worker): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.update');
    }

    /**
     * Determine whether the user can delete (archive) the worker.
     */
    public function delete(User $user, Worker $worker): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.delete');
    }

    /**
     * Determine whether the user can restore the worker.
     */
    public function restore(User $user, Worker $worker): bool
    {
        return $user->hasRole('Owner') || $user->can('payroll.delete');
    }
}
