<?php

namespace App\Filament\Resources\ProductBlocks\DeliveryBlocks;

use App\Filament\Resources\ProductBlocks\DeliveryBlocks\Pages\CreateProductDeliveryBlock;
use App\Filament\Resources\ProductBlocks\DeliveryBlocks\Pages\EditProductDeliveryBlock;
use App\Filament\Resources\ProductBlocks\DeliveryBlocks\Pages\ListProductDeliveryBlocks;
use App\Filament\Resources\ProductBlocks\DeliveryBlocks\Schemas\ProductDeliveryBlockForm;
use App\Filament\Resources\ProductBlocks\DeliveryBlocks\Tables\ProductDeliveryBlocksTable;
use App\Models\Product\ProductDeliveryBlock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductDeliveryBlockResource extends Resource
{
    protected static ?string $model = ProductDeliveryBlock::class;

    protected static ?string $navigationLabel = 'Блоки доставки';

    protected static ?string $modelLabel = 'Блок доставки';

    protected static ?string $pluralModelLabel = 'Блоки доставки';

    protected static ?string $policy = \App\Policies\ProductDeliveryBlockPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Блоки товаров';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ProductDeliveryBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductDeliveryBlocksTable::configure($table);
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
            'index' => ListProductDeliveryBlocks::route('/'),
            'create' => CreateProductDeliveryBlock::route('/create'),
            'edit' => EditProductDeliveryBlock::route('/{record}/edit'),
        ];
    }
}
