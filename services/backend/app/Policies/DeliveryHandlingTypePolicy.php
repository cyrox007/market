<?php

namespace App\Policies;

use App\Models\Shipping\DeliveryHandlingType;
use App\Models\User;

class DeliveryHandlingTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny delivery_handling_types');
    }

    public function view(User $user, DeliveryHandlingType $deliveryHandlingType): bool
    {
        return $user->can('view delivery_handling_types');
    }

    public function create(User $user): bool
    {
        return $user->can('create delivery_handling_types');
    }

    public function update(User $user, DeliveryHandlingType $deliveryHandlingType): bool
    {
        return $user->can('update delivery_handling_types');
    }

    public function delete(User $user, DeliveryHandlingType $deliveryHandlingType): bool
    {
        return $user->can('delete delivery_handling_types');
    }
}
