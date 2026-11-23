<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminUserPresenceWidget;
use App\Filament\Widgets\ContentStatsOverviewWidget;
use App\Filament\Widgets\FinancialOverviewWidget;
use App\Filament\Widgets\LatestOrdersTableWidget;
use App\Filament\Widgets\OrdersChartWidget;
use App\Filament\Widgets\OrdersStatsOverviewWidget;
use App\Filament\Widgets\CatalogCardAttentionWidget;
use App\Filament\Widgets\UsersStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * One workplace for all roles. Filament filters every widget via canView()
 * before mounting it, so permissions govern both the UI and data queries.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Рабочая панель';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 2];
    }

    public function getWidgets(): array
    {
        return [
            AdminUserPresenceWidget::class,
            LatestOrdersTableWidget::class,
            OrdersStatsOverviewWidget::class,
            OrdersChartWidget::class,
            CatalogCardAttentionWidget::class,
            ContentStatsOverviewWidget::class,
            FinancialOverviewWidget::class,
            UsersStatsWidget::class,
        ];
    }
}
