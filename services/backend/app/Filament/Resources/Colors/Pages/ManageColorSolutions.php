<?php

namespace App\Filament\Resources\Colors\Pages;

use App\Filament\Resources\Colors\ColorSolutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageColorSolutions extends ManageRecords
{
    protected static string $resource = ColorSolutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
