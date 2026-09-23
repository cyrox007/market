<?php

namespace App\Policies;

use App\Models\Inventory\Warehouse;
use App\Models\User;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny warehouses');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('view warehouses');
    }

    public function create(User $user): bool
    {
        return $user->can('create warehouses');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('update warehouses');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('delete warehouses');
    }
}
