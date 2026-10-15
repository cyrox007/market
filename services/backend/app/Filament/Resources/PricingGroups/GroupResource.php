<?php

namespace App\Filament\Resources\PricingGroups;

use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

abstract class GroupResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $policy = \App\Policies\PricingRulePolicy::class;

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    public static function form(Schema $schema): Schema
    {
        $customers = static::getModel() === \App\Models\Pricing\PricingCustomerGroup::class;

        return $schema->components([
            TextInput::make('name')->label('Название группы')->required()->maxLength(255),
            \App\Filament\Resources\PricingRules\PricingRuleResource::selector($customers ? 'user_ids' : 'product_ids', $customers ? 'Покупатели' : 'Товары и варианты', $customers ? \App\Models\User::class : \App\Models\Product\Product::class)
                ->required()->helperText('Состав этой группы используется всеми связанными ценовыми правилами. Изменение состава действует сразу.'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Название')->searchable(), TextColumn::make('updated_at')->label('Изменено')->dateTime('d.m.Y H:i')])->recordActions([EditAction::make()]);
    }
}
