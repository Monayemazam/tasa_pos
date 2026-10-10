<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.manage');
    }

    /**
     * Determine whether the user can view the role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.manage');
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can('roles.manage');
    }

    /**
     * Determine whether the user can update the role.
     * Owner role is locked and cannot be renamed or have permissions altered.
     */
    public function update(User $user, Role $role): bool
    {
        if ($role->is_locked) {
            return false;
        }

        return $user->can('roles.manage');
    }

    /**
     * Determine whether the user can delete the role.
     * Locked Owner role cannot be deleted.
     */
    public function delete(User $user, Role $role): bool
    {
        if ($role->is_locked) {
            return false;
        }

        return $user->can('roles.manage');
    }
}
