<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Справочники цветов (базовые цвета, цветовые решения, элементы) — под правами характеристик.
 */
class ColorDictionaryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny attributes');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('view attributes');
    }

    public function create(User $user): bool
    {
        return $user->can('create attributes');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('update attributes');
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('delete attributes');
    }

    public function reorder(User $user): bool
    {
        return $user->can('update attributes');
    }
}
