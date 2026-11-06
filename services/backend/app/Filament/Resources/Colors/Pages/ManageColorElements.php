<?php

namespace App\Filament\Resources\Colors\Pages;

use App\Filament\Resources\Colors\ColorElementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageColorElements extends ManageRecords
{
    protected static string $resource = ColorElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
