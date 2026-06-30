<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProductCollection;
use App\Filament\Resources\Products\Pages\EditProductCollection;
use App\Filament\Resources\Products\Pages\ListProductCollections;
use App\Filament\Resources\Products\Pages\ViewProductCollection;
use App\Filament\Resources\Products\Schemas\ProductCollectionForm;
use App\Filament\Resources\Products\Tables\ProductCollectionsTable;
use App\Models\Product\ProductCollection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductCollectionResource extends Resource
{
    protected static ?string $model = ProductCollection::class;

    protected static ?string $navigationLabel = 'Подборки товаров';

    protected static ?string $modelLabel = 'Подборка товаров';

    protected static ?string $pluralModelLabel = 'Подборки товаров';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $policy = \App\Policies\ProductCollectionPolicy::class;

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    public static function form(Schema $schema): Schema
    {
        return ProductCollectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductCollectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Products\RelationManagers\ProductCollectionProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductCollections::route('/'),
            'create' => CreateProductCollection::route('/create'),
            'view' => ViewProductCollection::route('/{record}'),
            'edit' => EditProductCollection::route('/{record}/edit'),
        ];
    }
}
