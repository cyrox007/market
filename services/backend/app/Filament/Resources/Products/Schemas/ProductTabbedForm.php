<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ProductTabbedForm extends ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('product_editor')
                    ->tabs([
                        Tab::make('Основное')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 2])
                                    ->schema([
                                        Group::make([
                                            self::mainInfoSection(),
                                        ]),
                                        Group::make([
                                            self::categoriesSection(),
                                            self::statusPriceSection(),
                                        ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Характеристики')
                            ->icon('heroicon-m-adjustments-horizontal')
                            ->schema([
                                self::colorSection()->collapsed(false),
                                self::attributesSection()->collapsed(false),
                            ]),

                        Tab::make('Остатки и доставка')
                            ->icon('heroicon-m-truck')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 2])
                                    ->schema([
                                        Group::make([
                                            self::stockSection(),
                                            self::warehouseStocksSection(),
                                        ]),
                                        Group::make([
                                            self::dimensionsSection()->collapsed(false),
                                            self::taxShippingSection()->collapsed(false),
                                        ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Изображения')
                            ->icon('heroicon-m-photo')
                            ->schema([
                                self::imagesSection(),
                            ]),

                        Tab::make('SEO и 1С')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 2])
                                    ->schema([
                                        Group::make([
                                            self::seoSection()->collapsed(false),
                                        ]),
                                        Group::make([
                                            self::importSection()->collapsed(false),
                                        ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->contained(false)
                    ->scrollable(false)
                    ->persistTab()
                    ->id('product-form-tabs')
                    ->columnSpanFull(),
            ]);
    }
}
