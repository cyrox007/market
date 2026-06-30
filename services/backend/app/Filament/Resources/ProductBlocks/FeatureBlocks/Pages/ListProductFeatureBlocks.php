<?php

namespace App\Filament\Resources\ProductBlocks\FeatureBlocks\Pages;

use App\Filament\Resources\ProductBlocks\FeatureBlocks\ProductFeatureBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductFeatureBlocks extends ListRecords
{
    protected static string $resource = ProductFeatureBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
