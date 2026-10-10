<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    /**
     * Determine whether the user can view the specific user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('users.manage') || $user->id === $model->id;
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        if ($model->hasRole('Owner') && ! $user->hasRole('Owner')) {
            return false;
        }

        return $user->can('users.manage') || $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the target user.
     * Owner user cannot be deleted by anyone.
     */
    public function delete(User $user, User $model): bool
    {
        if ($model->hasRole('Owner')) {
            return false;
        }

        return $user->can('users.manage') && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can deactivate the target user.
     * Owner user cannot be deactivated by anyone.
     */
    public function deactivate(User $user, User $model): bool
    {
        if ($model->hasRole('Owner')) {
            return false;
        }

        return $user->can('users.manage') && $user->id !== $model->id;
    }
}
