<?php

namespace App\Filament\Resources\Colors\Pages;

use App\Filament\Resources\Colors\BaseColorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBaseColors extends ManageRecords
{
    protected static string $resource = BaseColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
