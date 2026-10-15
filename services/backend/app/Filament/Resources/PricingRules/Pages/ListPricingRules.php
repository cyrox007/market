<?php

namespace App\Filament\Resources\PricingRules\Pages;

class ListPricingRules extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = \App\Filament\Resources\PricingRules\PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('Создать ценовую политику'),
            \Filament\Actions\ActionGroup::make([
                \Filament\Actions\Action::make('productGroups')->label('Подборки товаров')->url(\App\Filament\Resources\PricingGroups\ProductGroups\ProductGroupResource::getUrl()),
                \Filament\Actions\Action::make('customerGroups')->label('Группы покупателей')->url(\App\Filament\Resources\PricingGroups\CustomerGroups\CustomerGroupResource::getUrl()),
                \Filament\Actions\Action::make('legacy')->label('Прежние региональные настройки')->url(\App\Filament\Resources\Products\ProductRegionRules\ProductRegionRuleResource::getUrl()),
            ])->label('Дополнительно')->button()];
    }
}
