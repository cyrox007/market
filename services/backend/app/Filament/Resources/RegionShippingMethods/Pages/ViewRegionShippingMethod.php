<?php

namespace App\Filament\Resources\RegionShippingMethods\Pages;

use App\Filament\Resources\RegionShippingMethods\RegionShippingMethodResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRegionShippingMethod extends ViewRecord
{
    protected static string $resource = RegionShippingMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
