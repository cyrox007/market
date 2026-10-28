<?php

namespace App\Policies;

use App\Models\Mail\MailEventTemplate;
use App\Models\User;

class MailEventTemplatePolicy
{
    // Reuse the established mail-event permissions instead of adding an unseeded role.
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny mail_events');
    }

    public function view(User $user, MailEventTemplate $record): bool
    {
        return $user->can('view mail_events');
    }

    public function create(User $user): bool
    {
        return $user->can('create mail_events');
    }

    public function update(User $user, MailEventTemplate $record): bool
    {
        return $user->can('update mail_events');
    }

    public function delete(User $user, MailEventTemplate $record): bool
    {
        return $user->can('delete mail_events');
    }
}
