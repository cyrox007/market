<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CatalogImportersWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CatalogSyncPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowDown;

    protected static ?string $navigationLabel = 'Синхронизация каталога';

    protected static ?string $title = 'Синхронизация каталога';

    protected static string|UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'catalog-sync';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->can('viewAny catalog_sync');
    }

    /**
     * @return array<class-string<\Filament\Widgets\Widget>>
     */
    protected function getFooterWidgets(): array
    {
        return [
            CatalogImportersWidget::class,
        ];
    }
}
