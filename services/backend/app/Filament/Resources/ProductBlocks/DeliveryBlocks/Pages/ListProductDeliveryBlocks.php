<?php

namespace App\Filament\Resources\ProductBlocks\DeliveryBlocks\Pages;

use App\Filament\Resources\ProductBlocks\DeliveryBlocks\ProductDeliveryBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductDeliveryBlocks extends ListRecords
{
    protected static string $resource = ProductDeliveryBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
