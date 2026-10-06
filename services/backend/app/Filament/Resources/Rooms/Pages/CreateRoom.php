<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\Rooms\Schemas\RoomForm;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return RoomForm::packAttributeFilters($data);
    }
}
