<?php

namespace App\Filament\Resources\PricingRules\Pages;

class EditPricingRule extends \App\Filament\Resources\Pages\EditRecord
{
    protected static string $resource = \App\Filament\Resources\PricingRules\PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\DeleteAction::make()->label('Убрать правило')->modalDescription('Правило больше не применяется. История изменений и цены ранее созданных заказов сохраняются.')];
    }
}
