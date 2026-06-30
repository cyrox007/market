<?php

namespace App\Filament\Resources\RegionShippingMethods\Pages;

use App\Filament\Resources\RegionShippingMethods\RegionShippingMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegionShippingMethods extends ListRecords
{
    protected static string $resource = RegionShippingMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
