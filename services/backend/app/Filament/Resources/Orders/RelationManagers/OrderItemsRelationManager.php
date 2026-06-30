<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = null;

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label(__('filament/admin_sv/order_items_relation_manager.product.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(function ($record): ?string {
                        $product = $record->product;

                        return $product
                            ? ProductResource::getUrl('edit', ['record' => $product])
                            : null;
                    })
                    ->openUrlInNewTab(),
                TextColumn::make('product.sku')
                    ->label(__('filament/admin_sv/order_items_relation_manager.product.sku'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label(__('filament/admin_sv/order_items_relation_manager.quantity'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('price')
                    ->label(__('filament/admin_sv/order_items_relation_manager.price'))
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('filament/admin_sv/order_items_relation_manager.total'))
                    ->money('RUB')
                    ->sortable()
                    ->weight('bold')
                    ->summarize([
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->money('RUB')
                            ->label('Общая сумма'),
                    ]),
            ])
            ->defaultSort('id', 'asc');
    }
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/admin_sv/order_items_relation_manager.title');
    }

}
