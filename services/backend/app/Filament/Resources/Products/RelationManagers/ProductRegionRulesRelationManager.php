<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product\Product;
use App\Models\Product\ProductRegionRule;
use App\Models\Shipping\ShippingLocation;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ProductRegionRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'regionRules';

    protected static ?string $title = 'Правила работы с корзиной';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        $parentProduct = $this->getOwnerRecord();

        return $schema
            ->components([
                Select::make('shipping_location_id')
                    ->label('Локация доставки')
                    ->relationship('region', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('type')->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (ShippingLocation $record): string {
                        $typeLabel = match($record->type) {
                            'federal_district' => 'ФО',
                            'region' => 'Регион',
                            'locality' => 'Город',
                            default => '',
                        };
                        $path = $record->getFullPathAttribute();
                        return $path . ($typeLabel ? " ({$typeLabel})" : '');
                    })
                    ->getSearchResultsUsing(function (string $search) {
                        return ShippingLocation::where('is_active', true)
                            ->where(function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                      ->orWhere('slug', 'like', "%{$search}%");
                            })
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function (ShippingLocation $location) {
                                $typeLabel = match($location->type) {
                                    'federal_district' => 'ФО',
                                    'region' => 'Регион',
                                    'locality' => 'Город',
                                    default => '',
                                };
                                $path = $location->getFullPathAttribute();
                                return [$location->id => $path . ($typeLabel ? " ({$typeLabel})" : '')];
                            });
                    })
                    ->required()
                    ->helperText('Выберите локацию доставки (федеральный округ, регион или город). Правило применяется к выбранной локации и всем её дочерним локациям.')
                    ->columnSpanFull(),

                TextInput::make('price_override')
                    ->label('Переопределение цены')
                    ->numeric()
                    ->prefix('₽')
                    ->helperText('Если указано, эта цена будет использоваться вместо базовой цены товара')
                    ->nullable(),

                Select::make('price_modifier_type')
                    ->label('Тип модификатора цены')
                    ->options([
                        'fixed' => 'Фиксированная сумма (+/-)',
                        'percent' => 'Процент (%)',
                        'multiply' => 'Множитель (×)',
                    ])
                    ->nullable()
                    ->helperText('Тип модификатора для изменения базовой цены')
                    ->reactive(),

                TextInput::make('price_modifier_value')
                    ->label('Значение модификатора')
                    ->numeric()
                    ->helperText(function ($get) {
                        $type = $get('price_modifier_type');
                        if ($type === 'fixed') {
                            return 'Фиксированная сумма (например: +100 или -50)';
                        } elseif ($type === 'percent') {
                            return 'Процент (например: 10 для +10% или -5 для -5%)';
                        } elseif ($type === 'multiply') {
                            return 'Множитель (например: 1.2 для увеличения на 20%)';
                        }
                        return 'Введите значение модификатора';
                    })
                    ->visible(fn ($get) => !empty($get('price_modifier_type')))
                    ->nullable(),

                Toggle::make('is_hidden')
                    ->label('Скрыть товар в регионе')
                    ->default(false)
                    ->helperText('Если включено, товар будет скрыт в этом регионе. По умолчанию все товары показываются.')
                    ->columnSpanFull(),

                TextInput::make('delivery_days_override')
                    ->label('Срок доставки (дни)')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Переопределение срока доставки для выбранной локации (в днях)')
                    ->nullable(),

                TextInput::make('priority')
                    ->label('Приоритет')
                    ->numeric()
                    ->default(0)
                    ->helperText('Чем больше число, тем выше приоритет правила. Используется при конфликтах правил.')
                    ->required(),

                Toggle::make('is_active')
                    ->label('Активность')
                    ->default(true)
                    ->helperText('Активно ли это правило')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('region.name')
                    ->label('Локация доставки')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(function ($record) {
                        if (!$record->region) {
                            return '—';
                        }
                        $typeLabel = match($record->region->type) {
                            'federal_district' => 'ФО',
                            'region' => 'Регион',
                            'locality' => 'Город',
                            default => '',
                        };
                        $path = $record->region->getFullPathAttribute();
                        return $path . ($typeLabel ? " ({$typeLabel})" : '');
                    }),

                TextColumn::make('price_override')
                    ->label('Переопределение цены')
                    ->money('RUB')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('price_modifier')
                    ->label('Модификатор цены')
                    ->formatStateUsing(function (ProductRegionRule $record) {
                        if (!$record->price_modifier_type || $record->price_modifier_value === null) {
                            return '—';
                        }

                        $type = match ($record->price_modifier_type) {
                            'fixed' => '₽',
                            'percent' => '%',
                            'multiply' => '×',
                            default => '',
                        };

                        $sign = $record->price_modifier_type === 'fixed' && $record->price_modifier_value > 0 ? '+' : '';
                        return $sign . $record->price_modifier_value . ' ' . $type;
                    }),

                TextColumn::make('is_hidden')
                    ->label('Скрыт')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? 'Да' : 'Нет')
                    ->badge()
                    ->color(fn ($state) => $state ? 'danger' : 'success'),

                TextColumn::make('delivery_days_override')
                    ->label('Срок доставки')
                    ->formatStateUsing(fn ($state) => $state ? $state . ' дн.' : '—')
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Приоритет')
                    ->sortable(),

                BooleanColumn::make('is_active')
                    ->label('Активно')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Добавить правило')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        $parentProduct = $this->getOwnerRecord();

                        // Автоматически заполняем product_id
                        $data['product_id'] = $parentProduct->id;
                        $data['variant_id'] = null; // Правила для товара, не для вариации

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
    }
}
