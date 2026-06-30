<?php

namespace App\Policies;

use App\Models\Product\Attribute;
use App\Models\User;

class AttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny attributes');
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $user->can('view attributes');
    }

    public function create(User $user): bool
    {
        return $user->can('create attributes');
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $user->can('update attributes');
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return $user->can('delete attributes');
    }
}
