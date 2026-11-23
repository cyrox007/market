<?php

namespace App\Filament\Widgets;

class DashboardRoleChecks
{
    public static function canSeeOrders(): bool
    {
        $user = auth()->user();

        return $user && $user->can('viewAny orders');
    }

    public static function canSeeFinancial(): bool
    {
        $user = auth()->user();

        return $user && $user->can('viewAny orders')
            && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }

    public static function canSeeUsers(): bool
    {
        $user = auth()->user();

        return $user && $user->can('viewAny users');
    }

    public static function canSeeProducts(): bool
    {
        $user = auth()->user();

        return $user && $user->can('viewAny products');
    }

    public static function canSeeContent(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->can('viewAny articles')
            || $user->can('viewAny sliders')
            || $user->can('viewAny newsletter_subscribers')
        );
    }

    public static function canSeeFilamentInfo(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('super_admin');
    }
}
