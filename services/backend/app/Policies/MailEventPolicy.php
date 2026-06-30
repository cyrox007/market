<?php

namespace App\Policies;

use App\Models\Mail\MailEvent;
use App\Models\User;

class MailEventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny mail_events');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MailEvent $model): bool
    {
        return $user->can('view mail_events');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create mail_events');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MailEvent $model): bool
    {
        return $user->can('update mail_events');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MailEvent $model): bool
    {
        return $user->can('delete mail_events');
    }
}
