<?php

namespace App\Policies;

use App\Models\Page\InteriorIdea;
use App\Models\User;

class InteriorIdeaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny interior_ideas');
    }

    public function view(User $user, InteriorIdea $interiorIdea): bool
    {
        return $user->can('view interior_ideas');
    }

    public function create(User $user): bool
    {
        return $user->can('create interior_ideas');
    }

    public function update(User $user, InteriorIdea $interiorIdea): bool
    {
        return $user->can('update interior_ideas');
    }

    public function delete(User $user, InteriorIdea $interiorIdea): bool
    {
        return $user->can('delete interior_ideas');
    }
}
