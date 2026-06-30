<?php

namespace App\Policies;

use App\Models\Page\Slider;
use App\Models\User;

class SliderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny sliders');
    }

    public function view(User $user, Slider $slider): bool
    {
        return $user->can('view sliders');
    }

    public function create(User $user): bool
    {
        return $user->can('create sliders');
    }

    public function update(User $user, Slider $slider): bool
    {
        return $user->can('update sliders');
    }

    public function delete(User $user, Slider $slider): bool
    {
        return $user->can('delete sliders');
    }
}
