<?php

namespace App\Filament\Resources\Products\AttributeTemplates\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Products\AttributeTemplates\AttributeTemplateResource;
use Filament\Actions\DeleteAction;

class EditAttributeTemplate extends EditRecord
{
    protected static string $resource = AttributeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
