<?php

namespace App\Filament\Widgets;

use Filament\Widgets\FilamentInfoWidget as BaseFilamentInfoWidget;

class FilamentInfoForSuperAdminWidget extends BaseFilamentInfoWidget
{
    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeFilamentInfo();
    }
}
