<?php

namespace App\Policies;

use App\Models\User;

class StockAdjustmentPolicy
{
    /**
     * Determine whether the user can view stock adjustments.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('adjustments.view');
    }

    /**
     * Determine whether the user can create stock adjustments.
     */
    public function create(User $user): bool
    {
        return $user->can('adjustments.create');
    }
}
