<?php

namespace App\Policies;

use App\Models\Product\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny reviews');
    }

    public function view(User $user, Review $review): bool
    {
        return $user->can('view reviews');
    }

    public function create(User $user): bool
    {
        return $user->can('create reviews');
    }

    public function update(User $user, Review $review): bool
    {
        return $user->can('update reviews');
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->can('delete reviews');
    }
}
