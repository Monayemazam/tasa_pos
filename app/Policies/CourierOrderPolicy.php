<?php

namespace App\Policies;

use App\Models\CourierOrder;
use App\Models\User;

class CourierOrderPolicy
{
    /**
     * Determine whether the user can view any courier orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('courier.view');
    }

    /**
     * Determine whether the user can view the courier order.
     */
    public function view(User $user, CourierOrder $courierOrder): bool
    {
        return $user->hasRole('Owner') || $user->can('courier.view');
    }

    /**
     * Determine whether the user can retry dispatching the courier order.
     */
    public function retry(User $user, CourierOrder $courierOrder): bool
    {
        return $user->hasRole('Owner') || $user->can('courier.retry');
    }

    /**
     * Determine whether the user can settle COD for the courier order.
     */
    public function settleCod(User $user, CourierOrder $courierOrder): bool
    {
        return $user->hasRole('Owner') || $user->can('courier.settle_cod');
    }

    /**
     * Determine whether the user can update the courier order.
     */
    public function update(User $user, CourierOrder $courierOrder): bool
    {
        return $user->hasRole('Owner') || $user->can('courier.update') || $user->can('courier.retry');
    }
}

