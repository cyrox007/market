<?php

namespace App\Filament\Widgets;

use App\Models\Product\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class ProductsStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Товары';

    protected ?string $description = 'Каталог и наличие';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeProducts();
    }

    protected function getStats(): array
    {
        $cacheKey = 'dashboard_products_stats_' . (auth()->id() ?? 'guest');
        $stats = Cache::remember($cacheKey, 120, function () {
            $total = Product::count();

            return [
                'total' => $total,
            ];
        });

        return [
            Stat::make('Всего товаров', number_format($stats['total'], 0, ',', ' '))
                ->description('В каталоге')
                ->descriptionIcon(Heroicon::OutlinedCube)
                ->color('primary')
                ->icon(Heroicon::OutlinedCube),
        ];
    }
}
