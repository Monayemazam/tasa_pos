<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        return $user->can('products.view');
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->can('products.update');
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.delete');
    }

    /**
     * Determine whether the user can import products.
     */
    public function import(User $user): bool
    {
        return $user->can('products.import');
    }

    /**
     * Determine whether the user can view product cost prices.
     */
    public function viewCost(User $user): bool
    {
        return $user->hasRole('Owner')
            || $user->can('inventory.view_costs')
            || $user->can('inventory.cost.view');
    }

    /**
     * Determine whether the user can add stock/production entry.
     */
    public function addStock(User $user, Product $product): bool
    {
        return $user->can('products.update') || $user->can('products.create');
    }

    /**
     * Determine whether the user can adjust stock.
     */
    public function adjustStock(User $user, Product $product): bool
    {
        return $user->can('adjustments.create');
    }

    /**
     * Determine whether the user can view stock movements.
     */
    public function viewMovements(User $user, Product $product): bool
    {
        return $user->can('products.view');
    }
}
