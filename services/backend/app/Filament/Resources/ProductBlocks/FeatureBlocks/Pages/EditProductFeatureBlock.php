<?php

namespace App\Filament\Resources\ProductBlocks\FeatureBlocks\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\ProductBlocks\FeatureBlocks\ProductFeatureBlockResource;
use Filament\Actions\DeleteAction;

class EditProductFeatureBlock extends EditRecord
{
    protected static string $resource = ProductFeatureBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
