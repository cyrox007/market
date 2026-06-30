<?php

namespace App\Policies;

use App\Models\Shipping\ShippingLocation;
use App\Models\User;

class ShippingLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny shipping_locations');
    }

    public function view(User $user, ShippingLocation $shippingLocation): bool
    {
        return $user->can('view shipping_locations');
    }

    public function create(User $user): bool
    {
        return $user->can('create shipping_locations');
    }

    public function update(User $user, ShippingLocation $shippingLocation): bool
    {
        return $user->can('update shipping_locations');
    }

    public function delete(User $user, ShippingLocation $shippingLocation): bool
    {
        return $user->can('delete shipping_locations');
    }
}
