<?php

namespace App\Filament\Resources\RegionShippingMethods\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\RegionShippingMethods\RegionShippingMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditRegionShippingMethod extends EditRecord
{
    protected static string $resource = RegionShippingMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
