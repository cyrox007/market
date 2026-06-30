<?php

namespace App\Filament\Resources\Shipping\ShippingLocations\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CarriersRelationManager extends RelationManager
{
    protected static string $relationship = 'carriers';

    protected static ?string $title = 'Службы доставки';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Настройки службы доставки для локации')
                    ->schema([
                        TextInput::make('base_price')
                            ->label('Базовая стоимость доставки')
                            ->numeric()
                            ->prefix('₽')
                            ->helperText('Стоимость для этой службы в этой локации'),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше служба в списке на сайте (оформление заказа)'),

                        Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true)
                            ->helperText('Активна ли эта служба доставки для данной локации'),

                        Section::make('Сроки и порог бесплатной доставки')
                            ->description('Необязательно')
                            ->collapsed()
                            ->schema([
                                TextInput::make('free_delivery_threshold')
                                    ->label('Порог бесплатной доставки')
                                    ->numeric()
                                    ->prefix('₽')
                                    ->helperText('Минимальная сумма заказа для бесплатной доставки'),

                                TextInput::make('delivery_days_min')
                                    ->label('Минимальный срок доставки (дни)')
                                    ->numeric()
                                    ->minValue(1),

                                TextInput::make('delivery_days_max')
                                    ->label('Максимальный срок доставки (дни)')
                                    ->numeric()
                                    ->minValue(1),
                            ])->columns(2),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(function ($query) {
                $query->orderBy('carrier_shipping_location.sort_order');
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pivot.base_price')
                    ->label('Базовая цена')
                    ->money('RUB')
                    ->default('—')
                    ->sortable(),

                TextColumn::make('pivot.free_delivery_threshold')
                    ->label('Порог бесплатной доставки')
                    ->money('RUB')
                    ->default('—')
                    ->sortable(),

                TextColumn::make('pivot.delivery_days_min')
                    ->label('Срок доставки (мин)')
                    ->suffix(' дн.')
                    ->default('—')
                    ->sortable(),

                TextColumn::make('pivot.delivery_days_max')
                    ->label('Срок доставки (макс)')
                    ->suffix(' дн.')
                    ->default('—')
                    ->sortable(),

                TextColumn::make('pivot.sort_order')
                    ->label('Сортировка')
                    ->sortable(),

                IconColumn::make('pivot.is_active')
                    ->label('Активна')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Section::make('Тариф и приоритет')
                            ->schema([
                                TextInput::make('base_price')
                                    ->label('Базовая стоимость доставки')
                                    ->numeric()
                                    ->prefix('₽'),
                                TextInput::make('sort_order')
                                    ->label('Порядок сортировки')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Чем меньше число, тем выше служба в списке на сайте (оформление заказа)'),
                                Toggle::make('is_active')
                                    ->label('Активна')
                                    ->default(true),
                            ])->columns(2),
                        Section::make('Сроки и порог бесплатной доставки')
                            ->description('Необязательно')
                            ->collapsed()
                            ->schema([
                                TextInput::make('free_delivery_threshold')
                                    ->label('Порог бесплатной доставки')
                                    ->numeric()
                                    ->prefix('₽'),
                                TextInput::make('delivery_days_min')
                                    ->label('Минимальный срок доставки (дни)')
                                    ->numeric()
                                    ->minValue(1),
                                TextInput::make('delivery_days_max')
                                    ->label('Максимальный срок доставки (дни)')
                                    ->numeric()
                                    ->minValue(1),
                            ])->columns(2),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        Section::make('Тариф и приоритет')
                            ->schema([
                                TextInput::make('base_price')
                                    ->label('Базовая стоимость доставки')
                                    ->numeric()
                                    ->prefix('₽')
                                    ->helperText('Стоимость для этой службы в этой локации'),
                                TextInput::make('sort_order')
                                    ->label('Порядок сортировки')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Чем меньше число, тем выше служба в списке на сайте (оформление заказа)'),
                                Toggle::make('is_active')
                                    ->label('Активна')
                                    ->default(true)
                                    ->helperText('Активна ли эта служба доставки для данной локации'),
                            ])->columns(2),
                        Section::make('Сроки и порог бесплатной доставки')
                            ->description('Необязательно')
                            ->collapsed()
                            ->schema([
                                TextInput::make('free_delivery_threshold')
                                    ->label('Порог бесплатной доставки')
                                    ->numeric()
                                    ->prefix('₽')
                                    ->helperText('Минимальная сумма заказа для бесплатной доставки'),
                                TextInput::make('delivery_days_min')
                                    ->label('Минимальный срок доставки (дни)')
                                    ->numeric()
                                    ->minValue(1),
                                TextInput::make('delivery_days_max')
                                    ->label('Максимальный срок доставки (дни)')
                                    ->numeric()
                                    ->minValue(1),
                            ])->columns(2),
                    ])
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        // Загружаем данные из pivot таблицы
                        $pivot = $this->getOwnerRecord()->carriers()
                            ->where('carriers.id', $record->id)
                            ->first()?->pivot;
                        
                        if ($pivot) {
                            $data['base_price'] = $pivot->base_price;
                            $data['free_delivery_threshold'] = $pivot->free_delivery_threshold;
                            $data['delivery_days_min'] = $pivot->delivery_days_min;
                            $data['delivery_days_max'] = $pivot->delivery_days_max;
                            $data['sort_order'] = $pivot->sort_order;
                            $data['is_active'] = $pivot->is_active;
                        }
                        
                        return $data;
                    })
                    ->using(function (array $data, $record): void {
                        // Обновляем данные в pivot таблице
                        $this->getOwnerRecord()->carriers()->updateExistingPivot($record->id, [
                            'base_price' => $data['base_price'] ?? null,
                            'free_delivery_threshold' => $data['free_delivery_threshold'] ?? null,
                            'delivery_days_min' => $data['delivery_days_min'] ?? null,
                            'delivery_days_max' => $data['delivery_days_max'] ?? null,
                            'sort_order' => $data['sort_order'] ?? 0,
                            'is_active' => $data['is_active'] ?? true,
                        ]);
                    }),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}