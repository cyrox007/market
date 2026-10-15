<?php

namespace App\Filament\Resources\PricingGroups\CustomerGroups\Pages;

class ListGroups extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = \App\Filament\Resources\PricingGroups\CustomerGroups\CustomerGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('Создать группу покупателей')];
    }
}
