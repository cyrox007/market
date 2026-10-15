<?php

namespace App\Filament\Resources\PricingGroups\ProductGroups;

class ProductGroupResource extends \App\Filament\Resources\PricingGroups\GroupResource
{
    protected static ?string $model = \App\Models\Pricing\PricingProductGroup::class;

    protected static ?string $modelLabel = 'подборка товаров';

    protected static ?string $pluralModelLabel = 'Подборки для ценовых правил';

    public static function getPages(): array
    {
        return ['index' => Pages\ListGroups::route('/'), 'create' => Pages\CreateGroup::route('/create'), 'edit' => Pages\EditGroup::route('/{record}/edit')];
    }
}
