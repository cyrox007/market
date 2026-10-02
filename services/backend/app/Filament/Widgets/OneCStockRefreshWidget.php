<?php

namespace App\Filament\Widgets;

use App\Jobs\Integration\DispatchAllProductStockRefreshFrom1CJob;
use App\Models\Product\Product;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class OneCStockRefreshWidget extends Widget
{
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.one-c-stock-refresh-widget';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && $user->can('viewAny catalog_sync');
    }

    public function refreshAllStocks(): void
    {
        DispatchAllProductStockRefreshFrom1CJob::dispatch();

        Notification::make()
            ->title('Обновление остатков поставлено в очередь')
            ->body('Будут обновлены только остатки существующих товаров и торговых предложений. Карточки товаров не изменяются.')
            ->success()
            ->send();
    }

    public function getViewData(): array
    {
        return [
            'positionsWithExternalId' => Product::query()
                ->whereNotNull('external_id')
                ->where('external_id', '!=', '')
                ->count(),
        ];
    }
}
