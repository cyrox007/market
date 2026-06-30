<?php

namespace App\Filament\Resources\Products\ProductRegionRules;

use App\Filament\Resources\Products\ProductRegionRules\Pages\CreateProductRegionRule;
use App\Filament\Resources\Products\ProductRegionRules\Pages\EditProductRegionRule;
use App\Filament\Resources\Products\ProductRegionRules\Pages\ListProductRegionRules;
use App\Filament\Resources\Products\ProductRegionRules\Schemas\ProductRegionRuleForm;
use App\Filament\Resources\Products\ProductRegionRules\Tables\ProductRegionRulesTable;
use App\Models\Product\ProductRegionRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductRegionRuleResource extends Resource
{
    protected static ?string $model = ProductRegionRule::class;

    protected static ?string $navigationLabel = 'Правила работы с корзиной';

    protected static ?string $modelLabel = 'Правило работы с корзиной';

    protected static ?string $pluralModelLabel = 'Правила работы с корзиной';

    protected static ?string $policy = \App\Policies\ProductRegionRulePolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return ProductRegionRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductRegionRulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductRegionRules::route('/'),
            'create' => CreateProductRegionRule::route('/create'),
            'edit' => EditProductRegionRule::route('/{record}/edit'),
        ];
    }
}
