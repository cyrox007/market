<?php

namespace App\Policies;

use App\Models\Page\Store;
use App\Models\User;

class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny stores');
    }

    public function view(User $user, Store $store): bool
    {
        return $user->can('view stores');
    }

    public function create(User $user): bool
    {
        return $user->can('create stores');
    }

    public function update(User $user, Store $store): bool
    {
        return $user->can('update stores');
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->can('delete stores');
    }
}
