<?php

namespace App\Policies;

use App\Models\Order\OneClickLead;
use App\Models\User;

class OneClickLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny orders');
    }

    public function view(User $user, OneClickLead $lead): bool
    {
        return $user->can('view orders');
    }

    public function update(User $user, OneClickLead $lead): bool
    {
        return $user->can('update orders');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, OneClickLead $lead): bool
    {
        return false;
    }
}
