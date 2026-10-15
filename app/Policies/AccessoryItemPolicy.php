<?php

namespace App\Policies;

use App\Models\AccessoryItem;
use App\Models\User;

class AccessoryItemPolicy
{
    /**
     * Determine whether the user can view accessory items.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('accessories.view');
    }

    /**
     * Determine whether the user can view the accessory item.
     */
    public function view(User $user, AccessoryItem $item): bool
    {
        return $user->can('accessories.view');
    }

    /**
     * Determine whether the user can create accessory items.
     */
    public function create(User $user): bool
    {
        return $user->can('accessories.create');
    }

    /**
     * Determine whether the user can update the accessory item.
     */
    public function update(User $user, AccessoryItem $item): bool
    {
        return $user->can('accessories.update');
    }

    /**
     * Determine whether the user can delete the accessory item.
     */
    public function delete(User $user, AccessoryItem $item): bool
    {
        return $user->can('accessories.delete');
    }

    /**
     * Determine whether the user can record a purchase.
     */
    public function recordPurchase(User $user): bool
    {
        return $user->can('accessories.create') || $user->can('accessories.update');
    }

    /**
     * Determine whether the user can record consumption.
     */
    public function recordConsumption(User $user): bool
    {
        return $user->can('accessories.create') || $user->can('accessories.update');
    }

    /**
     * Determine whether the user can adjust accessory stock.
     */
    public function adjustStock(User $user): bool
    {
        return $user->can('adjustments.create');
    }

    /**
     * Determine whether the user can view sensitive cost and rate data.
     */
    public function viewCosts(User $user): bool
    {
        return $user->hasRole('Owner')
            || $user->can('inventory.view_costs')
            || $user->can('inventory.cost.view');
    }
}
