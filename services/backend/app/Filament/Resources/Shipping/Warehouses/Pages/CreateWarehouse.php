<?php

namespace App\Filament\Resources\Shipping\Warehouses\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Shipping\Warehouses\WarehouseResource;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(\App\Services\Address\WarehouseAddressResolver::class)->prepare($data);
    }
}
