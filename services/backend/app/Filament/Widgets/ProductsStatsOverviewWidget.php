<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Product;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ProductsStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Каталог';

    protected ?string $description = 'Активные позиции, а не скрытые и архивные записи';

    public static function canView(): bool
    {
        return DashboardRoleChecks::canSeeProducts();
    }

    protected function getStats(): array
    {
        $stats = Cache::remember('dashboard_catalog_v2', 120, static function (): array {
            return [
                'active' => Product::query()->catalogItems()->count(),
                'backorder' => Product::query()->catalogItems()->where('backorder', true)->count(),
                'no_own_media' => Product::query()->catalogItems()->whereDoesntHave('media')->count(),
            ];
        });
        $url = ProductResource::getUrl('index');

        return [
            Stat::make('Активных позиций каталога', number_format($stats['active'], 0, ',', ' '))
                ->description('Товары и активные предложения')
                ->icon(Heroicon::OutlinedCube)
                ->color('primary')
                ->url($url),
            Stat::make('Разрешён предзаказ', number_format($stats['backorder'], 0, ',', ' '))
                ->description('Признак разрешения, не обещание наличия')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('info')
                ->url($url),
            Stat::make('Без собственных изображений', number_format($stats['no_own_media'], 0, ',', ' '))
                ->description('Предложения могут наследовать фото родителя')
                ->icon(Heroicon::OutlinedPhoto)
                ->color($stats['no_own_media'] > 0 ? 'warning' : 'success')
                ->url($url),
        ];
    }
}
