<?php

namespace App\Filament\Resources\ProductBlocks\DeliveryBlocks\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\ProductBlocks\DeliveryBlocks\ProductDeliveryBlockResource;
use Filament\Actions\DeleteAction;

class EditProductDeliveryBlock extends EditRecord
{
    protected static string $resource = ProductDeliveryBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
