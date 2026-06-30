<?php

namespace App\Filament\Resources\RegionShippingMethods\Schemas;

use App\Models\Shipping\ShippingLocation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Vanilo\Shipment\Models\ShippingMethod;

class RegionShippingMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
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
                            ->helperText('Выберите локацию доставки (федеральный округ, регион или город). Метод доставки будет доступен для выбранной локации и всех её дочерних локаций.')
                            ->columnSpanFull(),

                        Select::make('shipping_method_id')
                            ->label('Метод доставки')
                            ->relationship('shippingMethod', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(function (ShippingMethod $record): string {
                                $carrier = $record->carrier;
                                $carrierName = $carrier ? " ({$carrier->name})" : '';
                                return $record->name . $carrierName;
                            })
                            ->required()
                            ->helperText('Выберите метод доставки из Vanilo Shipping')
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше метод доставки в списке')
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->helperText('Неактивные методы доставки не будут отображаться на сайте'),
                    ])
                    ->columns(2),
            ]);
    }
}
