<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\Schemas\ProductClassicForm;
use App\Filament\Resources\Products\Schemas\ProductInfolist;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $policy = \App\Policies\ProductPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Товары';

    public static function form(Schema $schema): Schema
    {
        return ProductClassicForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Products\RelationManagers\ProductVariantsRelationManager::class,
            \App\Filament\Resources\Products\RelationManagers\ProductVariationAttributeSelectionRelationManager::class,
            \App\Filament\Resources\Products\RelationManagers\ProductReviewsRelationManager::class,
            \App\Filament\Resources\Products\RelationManagers\ProductRegionRulesRelationManager::class,
            \App\Filament\Resources\Products\RelationManagers\VariantRegionRulesRelationManager::class,
            \App\Filament\Resources\Products\RelationManagers\RelatedProductsRelationManager::class,
            \App\Filament\Resources\Products\RelationManagers\ProductBundleProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/product_resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament/admin_sv/product_resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/admin_sv/product_resource.plural_model_label');
    }
}
