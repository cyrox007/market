<?php

namespace App\Filament\Resources\InteriorIdeas\Pages;

use App\Filament\Resources\InteriorIdeas\InteriorIdeaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInteriorIdeas extends ListRecords
{
    protected static string $resource = InteriorIdeaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
