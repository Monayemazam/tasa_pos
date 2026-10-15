<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner')
            || $user->can('customers.view')
            || $user->can('sales.create')
            || $user->can('sales.view');
    }

    /**
     * Determine whether the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->hasRole('Owner')
            || $user->can('customers.view')
            || $user->can('sales.create')
            || $user->can('sales.view');
    }

    /**
     * Determine whether the user can create customers.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Owner')
            || $user->can('customers.update')
            || $user->can('sales.create');
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->hasRole('Owner')
            || $user->can('customers.update')
            || $user->can('sales.create');
    }

    /**
     * Determine whether the user can delete the customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasRole('Owner');
    }
}
