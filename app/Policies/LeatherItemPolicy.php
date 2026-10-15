<?php

namespace App\Policies;

use App\Models\LeatherItem;
use App\Models\User;

class LeatherItemPolicy
{
    /**
     * Determine whether the user can view leather items.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('leather.view');
    }

    /**
     * Determine whether the user can view the leather item.
     */
    public function view(User $user, LeatherItem $item): bool
    {
        return $user->can('leather.view');
    }

    /**
     * Determine whether the user can create leather items.
     */
    public function create(User $user): bool
    {
        return $user->can('leather.create');
    }

    /**
     * Determine whether the user can update the leather item.
     */
    public function update(User $user, LeatherItem $item): bool
    {
        return $user->can('leather.update');
    }

    /**
     * Determine whether the user can delete the leather item.
     */
    public function delete(User $user, LeatherItem $item): bool
    {
        return $user->can('leather.delete');
    }

    /**
     * Determine whether the user can record a purchase.
     */
    public function recordPurchase(User $user): bool
    {
        return $user->can('leather.create') || $user->can('leather.update');
    }

    /**
     * Determine whether the user can record consumption.
     */
    public function recordConsumption(User $user): bool
    {
        return $user->can('leather.create') || $user->can('leather.update');
    }

    /**
     * Determine whether the user can adjust leather stock.
     */
    public function adjustStock(User $user): bool
    {
        return $user->can('adjustments.create');
    }

    /**
     * Determine whether the user can view leather purchase rates and costs.
     */
    public function viewCost(User $user): bool
    {
        return $user->hasRole('Owner')
            || $user->can('inventory.view_costs')
            || $user->can('inventory.cost.view');
    }
}
