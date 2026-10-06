<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\Rooms\Schemas\RoomForm;
use Filament\Actions\DeleteAction;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return RoomForm::unpackAttributeFilters($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return RoomForm::packAttributeFilters($data);
    }
}
