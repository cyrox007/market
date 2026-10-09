<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Models\Shipping\ShippingLocation;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\SEO\SEO;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('1. Адрес и товары магазина')
                    ->description('Магазин — страница для покупателей. Остатки хранятся на связанном складе, второй учёт товаров не создаётся.')
                    ->schema([
                        Select::make('store_stock_mode')->label('В этом магазине есть собственные остатки?')
                            ->options(['showroom' => 'Нет — только магазин / выставочный зал', 'warehouse' => 'Да — товары учитываются на складе этого магазина'])
                            ->default('showroom')->required()->live()
                            ->afterStateUpdated(fn ($state, $set) => $state === 'showroom' ? $set('warehouse_id', null) : null),
                        Select::make('warehouse_id')->label('Какой склад хранит остатки магазина?')
                            ->relationship('warehouse', 'name', modifyQueryUsing: fn ($query) => $query->where('source_type', 'physical')->where('stock_mode', 'quantity')->where('is_active', true))
                            ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name.' — ID 1С: '.$record->external_id)
                            ->searchable()->preload()->live()
                            ->visible(fn ($get): bool => $get('store_stock_mode') === 'warehouse')
                            ->required(fn ($get): bool => $get('store_stock_mode') === 'warehouse')
                            ->helperText('Выберите уже существующий склад по ID 1С. Если его нет, сначала создайте в «Доставка → Склады» и назначьте площадку. Не создавайте копию остатков.')
                            ->afterStateUpdated(function ($state, $set): void {
                                $warehouse = $state ? \App\Models\Inventory\Warehouse::find($state) : null;
                                if ($warehouse?->physical_site_id) {
                                    $set('physical_site_id', $warehouse->physical_site_id);
                                }
                            }),
                        \App\Filament\Forms\PhysicalSiteSelect::make()->columnSpanFull()
                            ->afterStateUpdated(function ($state, $set): void {
                                $site = $state ? \App\Models\Inventory\PhysicalSite::find($state) : null;
                                if ($site) {
                                    $set('city', $site->city);
                                    $set('address', $site->address);
                                }
                            }),
                    ])->columns(2),
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/admin_sv/store_resource.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                if (! $state) {
                                    return;
                                }
                                $set('slug', \Str::slug($state));
                            }),
                        TextInput::make('slug')
                            ->label(__('filament/admin_sv/store_resource.slug'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('city')
                            ->label(__('filament/admin_sv/store_resource.city'))
                            ->required(fn ($get): bool => blank($get('physical_site_id')))
                            ->readOnly(fn ($get): bool => filled($get('physical_site_id')))
                            ->helperText('При выбранной площадке город берётся из её адреса при сохранении.')
                            ->maxLength(255),
                        Select::make('shipping_location_id')
                            ->label('Регион')
                            ->relationship('region', 'name', modifyQueryUsing: fn ($query) => $query->where('type', 'region'))
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (ShippingLocation $record): string => $record->name)
                            ->helperText('Выберите регион, к которому относится магазин. Это определяет правила работы с корзиной для товаров в этом регионе.')
                            ->nullable(),
                        TextInput::make('address')
                            ->label(__('filament/admin_sv/store_resource.address'))
                            ->required(fn ($get): bool => blank($get('physical_site_id')))
                            ->readOnly(fn ($get): bool => filled($get('physical_site_id')))
                            ->helperText('При выбранной площадке используется её адрес из ГАР/КЛАДР. Ручные поля оставлены для старых магазинов.')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('phone')
                            ->label(__('filament/admin_sv/store_resource.phone'))
                            ->required()
                            ->tel()
                            ->maxLength(50)
                            ->helperText('Формат: +7 (XXX) XXX-XX-XX'),
                        TextInput::make('hours')
                            ->label(__('filament/admin_sv/store_resource.hours'))
                            ->maxLength(255)
                            ->helperText('Например: Пн-Вс: 10:00 - 22:00'),
                        TextInput::make('coordinates')
                            ->visible(fn ($get): bool => blank($get('physical_site_id')))
                            ->label(__('filament/admin_sv/store_resource.coordinates'))
                            ->maxLength(255)
                            ->helperText('Формат: Широта,Долгота (например: 55.751244, 37.618423)')
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $coords = explode(',', trim($state));
                                    if (count($coords) === 2) {
                                        $set('latitude', trim($coords[0]));
                                        $set('longitude', trim($coords[1]));
                                    }
                                }
                            }),
                        TextInput::make('latitude')
                            ->visible(fn ($get): bool => blank($get('physical_site_id')))
                            ->label(__('filament/admin_sv/store_resource.latitude'))
                            ->numeric()
                            ->step(0.00000001)
                            ->maxLength(10),
                        TextInput::make('longitude')
                            ->visible(fn ($get): bool => blank($get('physical_site_id')))
                            ->label(__('filament/admin_sv/store_resource.longitude'))
                            ->numeric()
                            ->step(0.00000001)
                            ->maxLength(11),
                        Textarea::make('yandex_map')
                            ->label(__('filament/admin_sv/store_resource.yandex_map'))
                            ->rows(4)
                            ->helperText('Код встраивания iframe для Яндекс карты или ссылка на карту. Можно получить на https://yandex.ru/map-constructor/')
                            ->columnSpanFull(),
                        TextInput::make('priority')
                            ->label(__('filament/admin_sv/store_resource.priority'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше в списке'),
                        Toggle::make('is_active')
                            ->label(__('filament/admin_sv/store_resource.is_active'))
                            ->default(true),
                    ])->columns(2),
                Section::make('Описание')
                    ->schema([
                        RichEditor::make('description')
                            ->label(__('filament/admin_sv/store_resource.description'))
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                Section::make('SEO настройки')
                    ->description('Управление мета данными')
                    ->schema([
                        SEO::make(),
                    ])
                    ->collapsible(),
                Section::make('Изображение магазина')
                    ->description('Загрузка изображения магазина. Изображение автоматически оптимизируется и создаются миниатюры')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->label('Изображение')
                            ->helperText('Основное изображение магазина. Будет автоматически создана миниатюра 300x300px, HD версия 1280x720px и Full HD версия 1920x1080px')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
