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
use Illuminate\Support\Arr;

class AdditionalServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'additionalServices';

    protected static ?string $title = 'Дополнительные услуги';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Настройки услуги для региона')
                    ->schema([
                        TextInput::make('price')
                            ->label('Цена для региона')
                            ->numeric()
                            ->prefix('₽')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('Если не указана, будет использована базовая цена услуги'),

                        Toggle::make('is_active')
                            ->label('Активна для региона')
                            ->default(true),

                        Section::make('Дополнительно')
                            ->collapsed()
                            ->schema([
                                TextInput::make('sort_order')
                                    ->label('Порядок сортировки')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Чем меньше число, тем выше услуга в списке'),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),

                TextColumn::make('code')
                    ->label('Код')
                    ->searchable(),

                TextColumn::make('icon')
                    ->label('Иконка')
                    ->formatStateUsing(fn ($state) => $state ? "<i class='{$state} text-xl text-red-600'></i>" : '—')
                    ->html(),

                TextColumn::make('price_type')
                    ->label('Тип цены')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'fixed' => 'Фиксированная',
                        'from' => 'От X',
                        'custom' => 'Отдельно',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'fixed' => 'success',
                        'from' => 'warning',
                        'custom' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('pivot.price')
                    ->label('Цена для региона')
                    ->money('RUB')
                    ->default('—')
                    ->formatStateUsing(function ($state, $record) {
                        if ($state !== null) {
                            return $state;
                        }
                        return $record->base_price ?? '—';
                    }),

                TextColumn::make('base_price')
                    ->label('Базовая цена')
                    ->money('RUB')
                    ->default('—'),

                IconColumn::make('pivot.is_active')
                    ->label('Активна')
                    ->boolean(),

                TextColumn::make('pivot.sort_order')
                    ->label('Сортировка')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn(AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('price')
                            ->label('Цена для региона')
                            ->numeric()
                            ->prefix('₽')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('Если не указана, будет использована базовая цена услуги'),
                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Активна для региона')
                            ->default(true),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->form(fn (EditAction $action): array => [
                        TextInput::make('price')
                            ->label('Цена для региона')
                            ->numeric()
                            ->prefix('₽')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('Если не указана, будет использована базовая цена услуги'),
                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Активна для региона')
                            ->default(true),
                    ])
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        // Загружаем данные из pivot таблицы
                        $pivot = $this->getOwnerRecord()->additionalServices()
                            ->where('additional_services.id', $record->id)
                            ->first()?->pivot;
                        
                        if ($pivot) {
                            $data['price'] = $pivot->price;
                            $data['sort_order'] = $pivot->sort_order;
                            $data['is_active'] = $pivot->is_active;
                        }
                        
                        return $data;
                    })
                    ->using(function (array $data, $record): void {
                        // Обновляем данные в pivot таблице
                        $this->getOwnerRecord()->additionalServices()->updateExistingPivot($record->id, [
                            'price' => $data['price'] ?? null,
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
