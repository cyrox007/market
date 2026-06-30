<?php

namespace App\Policies;

use App\Models\Shipping\Carrier;
use App\Models\User;

class CarrierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny carriers');
    }

    public function view(User $user, Carrier $carrier): bool
    {
        return $user->can('view carriers');
    }

    public function create(User $user): bool
    {
        return $user->can('create carriers');
    }

    public function update(User $user, Carrier $carrier): bool
    {
        return $user->can('update carriers');
    }

    public function delete(User $user, Carrier $carrier): bool
    {
        return $user->can('delete carriers');
    }
}
