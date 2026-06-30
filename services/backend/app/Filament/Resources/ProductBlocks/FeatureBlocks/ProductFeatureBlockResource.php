<?php

namespace App\Filament\Resources\ProductBlocks\FeatureBlocks;

use App\Filament\Resources\ProductBlocks\FeatureBlocks\Pages\CreateProductFeatureBlock;
use App\Filament\Resources\ProductBlocks\FeatureBlocks\Pages\EditProductFeatureBlock;
use App\Filament\Resources\ProductBlocks\FeatureBlocks\Pages\ListProductFeatureBlocks;
use App\Filament\Resources\ProductBlocks\FeatureBlocks\Schemas\ProductFeatureBlockForm;
use App\Filament\Resources\ProductBlocks\FeatureBlocks\Tables\ProductFeatureBlocksTable;
use App\Models\Product\ProductFeatureBlock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductFeatureBlockResource extends Resource
{
    protected static ?string $model = ProductFeatureBlock::class;

    protected static ?string $navigationLabel = 'Фичи товаров';

    protected static ?string $modelLabel = 'Фича товара';

    protected static ?string $pluralModelLabel = 'Фичи товаров';

    protected static ?string $policy = \App\Policies\ProductFeatureBlockPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Блоки товаров';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ProductFeatureBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductFeatureBlocksTable::configure($table);
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
            'index' => ListProductFeatureBlocks::route('/'),
            'create' => CreateProductFeatureBlock::route('/create'),
            'edit' => EditProductFeatureBlock::route('/{record}/edit'),
        ];
    }
}
