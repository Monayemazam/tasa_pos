<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    /**
     * Determine whether the user can view categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, ProductCategory $category): bool
    {
        return $user->can('products.update');
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->can('products.delete');
    }
}
