<?php

namespace App\Filament\Resources\Shipping\ShippingLocations\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Shipping\ShippingLocations\ShippingLocationResource;

class CreateShippingLocation extends CreateRecord
{
    protected static string $resource = ShippingLocationResource::class;
}
