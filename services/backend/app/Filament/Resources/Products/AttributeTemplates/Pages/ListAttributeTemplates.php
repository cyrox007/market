<?php

namespace App\Filament\Resources\Products\AttributeTemplates\Pages;

use App\Filament\Resources\Products\AttributeTemplates\AttributeTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttributeTemplates extends ListRecords
{
    protected static string $resource = AttributeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
