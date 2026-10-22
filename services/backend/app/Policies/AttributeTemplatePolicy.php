<?php

namespace App\Policies;

use App\Models\Product\AttributeTemplate;
use App\Models\User;

/**
 * Шаблоны — часть работы с характеристиками: права те же, что у характеристик.
 */
class AttributeTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny attributes');
    }

    public function view(User $user, AttributeTemplate $template): bool
    {
        return $user->can('view attributes');
    }

    public function create(User $user): bool
    {
        return $user->can('create attributes');
    }

    public function update(User $user, AttributeTemplate $template): bool
    {
        return $user->can('update attributes');
    }

    public function delete(User $user, AttributeTemplate $template): bool
    {
        return $user->can('delete attributes');
    }
}
