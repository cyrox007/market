<?php

namespace App\Filament\Resources\Shipping\ShippingLocations\Pages;

use App\Filament\Resources\Shipping\ShippingLocations\ShippingLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingLocation extends EditRecord
{
    protected static string $resource = ShippingLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_shipping_location.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_shipping_location.title');
    }

}
