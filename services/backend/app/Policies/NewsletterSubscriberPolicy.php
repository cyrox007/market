<?php

namespace App\Policies;

use App\Models\Newsletter\NewsletterSubscriber;
use App\Models\User;

class NewsletterSubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny newsletter_subscribers');
    }

    public function view(User $user, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return $user->can('view newsletter_subscribers');
    }

    public function create(User $user): bool
    {
        return $user->can('create newsletter_subscribers');
    }

    public function delete(User $user, NewsletterSubscriber $newsletterSubscriber): bool
    {
        return $user->can('delete newsletter_subscribers');
    }
}
