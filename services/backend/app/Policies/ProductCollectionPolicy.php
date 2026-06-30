<?php

namespace App\Policies;

use App\Models\Product\ProductCollection;
use App\Models\User;

class ProductCollectionPolicy
{
    private function canManage(User $user): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $user->hasPermissionTo('viewAny product_collections');
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return $this->canManage($user) || $user->can('viewAny product_collections');
    }

    public function view(User $user, ProductCollection $productCollection): bool
    {
        return $this->canManage($user) || $user->can('view product_collections');
    }

    public function create(User $user): bool
    {
        return $this->canManage($user) || $user->can('create product_collections');
    }

    public function update(User $user, ProductCollection $productCollection): bool
    {
        return $this->canManage($user) || $user->can('update product_collections');
    }

    public function delete(User $user, ProductCollection $productCollection): bool
    {
        return $this->canManage($user) || $user->can('delete product_collections');
    }
}
