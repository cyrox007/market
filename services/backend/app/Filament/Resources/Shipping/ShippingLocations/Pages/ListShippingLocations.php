<?php

namespace App\Filament\Resources\Shipping\ShippingLocations\Pages;

use App\Filament\Resources\Shipping\ShippingLocations\ShippingLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingLocations extends ListRecords
{
    protected static string $resource = ShippingLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/list_shipping_locations.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/list_shipping_locations.title');
    }

}
