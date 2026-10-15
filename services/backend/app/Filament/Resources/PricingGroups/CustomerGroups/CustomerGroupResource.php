<?php

namespace App\Filament\Resources\PricingGroups\CustomerGroups;

class CustomerGroupResource extends \App\Filament\Resources\PricingGroups\GroupResource
{
    protected static ?string $model = \App\Models\Pricing\PricingCustomerGroup::class;

    protected static ?string $modelLabel = 'группа покупателей';

    protected static ?string $pluralModelLabel = 'Группы покупателей';

    public static function getPages(): array
    {
        return ['index' => Pages\ListGroups::route('/'), 'create' => Pages\CreateGroup::route('/create'), 'edit' => Pages\EditGroup::route('/{record}/edit')];
    }
}
