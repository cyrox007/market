<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
