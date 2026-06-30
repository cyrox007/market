<?php

namespace App\Filament\Resources\Products\ProductRegionRules\Schemas;

use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductRegionRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        Select::make('product_id')
                            ->label('Товар')
                            ->relationship('product', 'name', modifyQueryUsing: fn ($query) => $query->whereNull('parent_product_id'))
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name . ' (SKU: ' . $record->sku . ')')
                            ->nullable()
                            ->helperText('Выберите товар. Если не указан, правило применяется ко всем товарам.')
                            ->reactive()
                            ->afterStateUpdated(fn ($set) => $set('variant_id', null)),

                        Select::make('variant_id')
                            ->label('Торговое предложение')
                            ->relationship('variant', 'name', modifyQueryUsing: function ($query, $get) {
                                $productId = $get('product_id');
                                if ($productId) {
                                    return $query->where('parent_product_id', $productId);
                                }
                                return $query->whereNotNull('parent_product_id');
                            })
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name . ' (SKU: ' . $record->sku . ')')
                            ->nullable()
                            ->helperText('Выберите торговое предложение. Если не указано, правило применяется ко всем предложениям товара.')
                            ->visible(fn ($get) => !empty($get('product_id'))),

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
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Правила цены')
                    ->schema([
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
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Видимость и доставка')
                    ->schema([
                        Toggle::make('is_hidden')
                            ->label('Скрыть товар в локации')
                            ->default(false)
                            ->helperText('Если включено, товар будет скрыт в выбранной локации и всех её дочерних локациях. По умолчанию все товары показываются.')
                            ->columnSpanFull(),

                        TextInput::make('delivery_days_override')
                            ->label('Срок доставки (дни)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Переопределение срока доставки для выбранной локации (в днях)')
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Управление')
                    ->schema([
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
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
