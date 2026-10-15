<?php

namespace App\Filament\Resources\PricingGroups\ProductGroups\Pages;

class ListGroups extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = \App\Filament\Resources\PricingGroups\ProductGroups\ProductGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('Создать подборку')];
    }
}
