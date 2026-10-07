<?php

namespace App\Filament\Resources\Shipping\Carriers\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Shipping\Carriers\CarrierResource;

class CreateCarrier extends CreateRecord
{
    protected static string $resource = CarrierResource::class;
}
