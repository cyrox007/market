<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny roles');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('view roles');
    }

    public function create(User $user): bool
    {
        return (! config('activity_audit.enabled') || $user->hasRole('super_admin')) && $user->can('create roles');
    }

    public function update(User $user, Role $role): bool
    {
        return (! config('activity_audit.enabled') || $user->hasRole('super_admin')) && $user->can('update roles');
    }

    public function delete(User $user, Role $role): bool
    {
        return (! config('activity_audit.enabled') || $user->hasRole('super_admin')) && $user->can('delete roles');
    }
}
