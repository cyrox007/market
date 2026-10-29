<?php

namespace App\Filament\Resources\Stores\Schemas;

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
                Section::make('Адрес и связь со складом')->columnSpanFull()->compact()
                    ->schema([
                        Select::make('store_stock_mode')->label('Тип магазина')
                            ->options(['showroom' => 'Самостоятельный магазин', 'warehouse' => 'Магазин со складом'])
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
                        \App\Filament\Forms\PhysicalSiteSelect::make()->key('store_address')->label('Адрес магазина')->required()
                            ->placeholder('Выберите сохранённый адрес или добавьте новый из справочника')
                            ->helperText('Новый адрес: кнопка «Выбрать из справочника». Для магазина со складом используется общий адрес.')
                            ->createOptionAction(fn (\Filament\Actions\Action $action) => $action
                                ->label('Выбрать из справочника')->icon(null)->button()
                                ->modalHeading('Адрес магазина из справочника')->modalSubmitActionLabel('Использовать адрес'))
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, $set): void {
                                $site = $state ? \App\Models\Inventory\PhysicalSite::find($state) : null;
                                if ($site) {
                                    $set('city', $site->city);
                                    $set('address', $site->address);
                                }
                            }),
                    ])->columns(2),
                Section::make('Магазин и контакты')->columnSpanFull()->compact()
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
                        TextInput::make('phone')
                            ->label(__('filament/admin_sv/store_resource.phone'))
                            ->required()
                            ->tel()
                            ->telRegex('/^[+0-9()\s.\-]+$/')
                            ->rule(static fn (): \Closure => static function (string $attribute, mixed $value, \Closure $fail): void {
                                if (! \App\Support\PhoneNumber::isValidRu(\App\Support\PhoneNumber::normalize((string) $value))) {
                                    $fail('Введите полный российский номер: +7 (800) 222-85-86 или 8 (800) 222-85-86.');
                                }
                            })
                            ->dehydrateStateUsing(fn ($state) => \App\Support\PhoneNumber::normalize($state))
                            ->maxLength(50)
                            ->helperText('Можно вводить с +7 или 8, со скобками, пробелами и дефисами.'),
                        TextInput::make('hours')
                            ->label(__('filament/admin_sv/store_resource.hours'))
                            ->maxLength(255)
                            ->helperText('Текст для покупателей, например: Пн-Вс: 10:00–22:00. Не участвует в проверке доступности самовывоза — заполните расписание ниже.'),
                        Toggle::make('pickup_enabled')->label('Выдавать заказы в этом магазине')->default(true)
                            ->helperText('Самовывоз доступен только при связанном действующем физическом складе с достаточным остатком.'),
                        TextInput::make('timezone')->label('Часовой пояс')->default('Europe/Moscow')->required()->rules(['timezone']),
                        \Filament\Forms\Components\Repeater::make('opening_hours')->label('График по дням недели')->collapsed()->columnSpanFull()->defaultItems(0)->maxItems(7)
                            ->schema([
                                Select::make('day')->label('День')->options([1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'])->required(),
                                Toggle::make('is_closed')->label('Выходной')->default(false),
                                \Filament\Forms\Components\Repeater::make('intervals')->label('Часы работы, включая перерывы')->defaultItems(0)->schema([
                                    TextInput::make('opens')->label('Открытие')->placeholder('09:00')->required()->rules(['date_format:H:i']),
                                    TextInput::make('closes')->label('Закрытие')->placeholder('18:00')->required()->rules(['date_format:H:i']),
                                ])->columns(2)->columnSpanFull(),
                            ])->columns(2)->helperText('1 — понедельник, 7 — воскресенье. Неуказанный день неизвестен, а не выходной. Текстовое hours сохраняется отдельно.'),
                        Section::make('Оформление карты')->collapsed()->columnSpanFull()->schema([
                        Textarea::make('yandex_map')
                            ->label(__('filament/admin_sv/store_resource.yandex_map'))
                            ->rows(4)
                            ->helperText('Только оформление страницы магазина. Не задаёт адрес, координаты и не участвует в расчёте доставки или самовывоза.')
                            ->columnSpanFull(),
                        ]),
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
                    ->columnSpanFull()->collapsed()
                    ->schema([
                        RichEditor::make('description')
                            ->label(__('filament/admin_sv/store_resource.description'))
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                Section::make('SEO настройки')
                    ->columnSpanFull()->collapsed()
                    ->description('Управление мета данными')
                    ->schema([
                        SEO::make(),
                    ])
                    ->collapsible(),
                Section::make('Изображение магазина')
                    ->columnSpanFull()->collapsed()
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
