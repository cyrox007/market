<?php

namespace App\Filament\Resources\Products\Attributes\Pages;

use App\Filament\Resources\Products\Attributes\AttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttributes extends ListRecords
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/list_attributes.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/list_attributes.title');
    }

}

