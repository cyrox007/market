<?php

namespace App\Filament\Resources\InteriorIdeas\Pages;

use App\Filament\Resources\InteriorIdeas\InteriorIdeaResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Models\Page\InteriorIdea;

class CreateInteriorIdea extends CreateRecord
{
    protected static string $resource = InteriorIdeaResource::class;

    protected function afterCreate(): void
    {
        InteriorIdea::flushListCache();
    }
}
