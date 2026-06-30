<?php

namespace App\Policies;

use App\Models\Payment\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny payment_methods');
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('view payment_methods');
    }

    public function create(User $user): bool
    {
        return $user->can('create payment_methods');
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('update payment_methods');
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('delete payment_methods');
    }
}
