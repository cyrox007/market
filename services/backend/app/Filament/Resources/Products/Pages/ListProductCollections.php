<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductCollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductCollections extends ListRecords
{
    protected static string $resource = ProductCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Подборки товаров';
    }

    public static function getNavigationLabel(): string
    {
        return 'Подборки товаров';
    }
}
