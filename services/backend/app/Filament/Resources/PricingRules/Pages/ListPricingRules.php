<?php

namespace App\Filament\Resources\PricingRules\Pages;

class ListPricingRules extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = \App\Filament\Resources\PricingRules\PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('Создать ценовое правило')];
    }
}
