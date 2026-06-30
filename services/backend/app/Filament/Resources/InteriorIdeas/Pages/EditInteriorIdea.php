<?php

namespace App\Filament\Resources\InteriorIdeas\Pages;

use App\Filament\Resources\InteriorIdeas\InteriorIdeaResource;
use App\Models\Page\InteriorIdea;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInteriorIdea extends EditRecord
{
    protected static string $resource = InteriorIdeaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        InteriorIdea::flushListCache();
    }
}
