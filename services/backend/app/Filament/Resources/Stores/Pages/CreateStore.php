<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Stores\StoreResource;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(\App\Services\Address\PhysicalSiteSetup::class)->store($data);
    }
}
