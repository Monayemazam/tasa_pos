<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /**
     * Determine whether the user can view any sales.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('sales.view');
    }

    /**
     * Determine whether the user can view the sale.
     */
    public function view(User $user, Sale $sale): bool
    {
        return $user->hasRole('Owner') || $user->can('sales.view');
    }

    /**
     * Determine whether the user can create sales.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('sales.create');
    }

    /**
     * Determine whether the user can edit price at sale time.
     */
    public function editPrice(User $user): bool
    {
        return $user->hasRole('Owner') || $user->can('sales.edit_price');
    }

    /**
     * Determine whether the user can add payment to the sale.
     */
    public function addPayment(User $user, Sale $sale): bool
    {
        return $user->hasRole('Owner') || $user->can('sales.add_payment');
    }

    /**
     * Determine whether the user can cancel the sale.
     */
    public function cancel(User $user, Sale $sale): bool
    {
        return $user->hasRole('Owner') || $user->can('sales.cancel');
    }

    /**
     * Determine whether the user can process returns on the sale.
     */
    public function return(User $user, Sale $sale): bool
    {
        return $user->hasRole('Owner') || $user->can('sales.return');
    }
}
