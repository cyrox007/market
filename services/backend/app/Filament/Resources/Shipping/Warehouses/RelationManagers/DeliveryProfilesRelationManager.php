<?php

namespace App\Filament\Resources\Shipping\Warehouses\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveryProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveryProfiles';

    protected static ?string $title = 'Зоны, цены и сроки';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Основные условия')->description('Один профиль описывает, куда склад доставляет и на каких условиях.')->schema([
                TextInput::make('name')->label('Название направления')->placeholder('Например, Липецкое направление')->required()->maxLength(255)->columnSpanFull()
                    ->helperText('Обязательно. Короткое название условий доставки; помогает отличать профили одного склада.'),
                Select::make('coverage_type')->label('Как определить зону')->options([
                    'locations' => 'Выбранные регионы и населённые пункты',
                    'radius' => 'Радиус от склада',
                    'hybrid' => 'Территории и радиус одновременно',
                ])->default('locations')->live()->required()->columnSpanFull()
                    ->helperText('Обязательно. Территории — по справочнику; радиус — по координатам; совместный режим требует выполнения обоих условий.'),
                TextInput::make('radius_km')->label('Радиус по прямой')->numeric()->minValue(0.01)->suffix('км')
                    ->helperText('Обязательно для радиуса и совместного режима. Максимальное расстояние по прямой от склада; без координат склада и покупателя такой профиль не применяется.')
                    ->required(fn ($get) => in_array($get('coverage_type'), ['radius', 'hybrid'], true))
                    ->visible(fn ($get) => in_array($get('coverage_type'), ['radius', 'hybrid'], true)),
                \Filament\Forms\Components\Repeater::make('directory_territories')->label('Покрытие по ГАР / КЛАДР')
                    ->required(fn ($get) => in_array($get('coverage_type'), ['locations', 'hybrid'], true))
                    ->minItems(1)
                    ->helperText('Обязательно для территорий и совместного режима. Добавьте хотя бы одну территорию. Регион включает его дочерние адреса; населённый пункт сужает покрытие.')
                    ->schema([
                        \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('region_id'), 'регионы')->label('1. Регион')->searchable()->live()->required()
                            ->helperText('Обязательно для этой территории. Выберите субъект страны из ГАР.')
                            ->options(fn (): array => app(\App\Services\Address\WarehouseAddressOptions::class)->initial('regions', null))
                            ->getSearchResultsUsing(fn (string $search): array => app(\App\Services\Address\WarehouseAddressOptions::class)->search('regions', null, $search))
                            ->getOptionLabelUsing(fn ($value) => $value ? (app(\App\Services\Address\AddressDirectoryClient::class)->hierarchy($value)['label'] ?? $value) : null)
                            ->afterStateUpdated(fn ($set) => $set('locality_id', null)),
                        \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('locality_id'), 'населённые пункты', ['region_id'])->label('2. Населённый пункт (необязательно)')->searchable()
                            ->options(fn ($get): array => filled($get('region_id'))
                                ? app(\App\Services\Address\WarehouseAddressOptions::class)->search('localities', $get('region_id'), '') : [])
                            ->helperText('Необязательно: пусто означает весь регион. Для конкретного города или села введите начало названия; первоначально показаны первые 50 вариантов.')
                            ->placeholder('Весь выбранный регион')->disabled(fn ($get) => blank($get('region_id')))
                            ->getSearchResultsUsing(fn (string $search, $get): array => app(\App\Services\Address\WarehouseAddressOptions::class)->search('localities', $get('region_id'), $search))
                            ->getOptionLabelUsing(fn ($value) => $value ? (app(\App\Services\Address\AddressDirectoryClient::class)->hierarchy($value)['label'] ?? $value) : null),
                    ])->columns(2)->defaultItems(0)->addActionLabel('Добавить территорию')
                    ->visible(fn ($get) => in_array($get('coverage_type'), ['locations', 'hybrid'], true))->columnSpanFull(),
                \Filament\Schemas\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('base_price')->label('Стоимость доставки')->numeric()->minValue(0)->default(0)->prefix('₽')->required()
                        ->helperText('Обязательно. Базовая цена перевозки за заказ, без подъёма и сборки. 0 — без базовой платы; доплата за километры считается отдельно.'),
                    TextInput::make('delivery_days_min')->label('Перевозка от')->numeric()->minValue(0)->default(0)->suffix('дн.')->required()
                        ->helperText('Обязательно. Минимальная длительность перевозки после подготовки заказа. 0 — без дополнительного дня перевозки.'),
                    TextInput::make('delivery_days_max')->label('Перевозка до')->numeric()->minValue(0)->gte('delivery_days_min')->default(0)->suffix('дн.')->required()
                        ->helperText('Обязательно. Максимальная длительность перевозки; не меньше значения «от». Подготовка склада прибавляется отдельно.'),
                ])->columnSpanFull(),
                Toggle::make('is_active')->label('Профиль активен')->default(true)->columnSpanFull()
                    ->helperText('Выключенный профиль не участвует в новых расчётах. Сохранённые условия прежних заказов не переписываются.'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Дополнительный расчёт')->collapsed()->schema([
                TextInput::make('price_per_km')->label('Доплата за километр по прямой')->numeric()->minValue(0)->default(0)->prefix('₽')
                    ->helperText('Необязательно: 0 отключает доплату. Прибавляется к базовой цене за каждый километр по прямой. Нужны координаты склада и покупателя.'),
                TextInput::make('free_delivery_threshold')->label('Бесплатно от суммы')->numeric()->minValue(0)->prefix('₽')
                    ->helperText('Необязательно. Пусто — без порога. При достижении суммы товаров обнуляется перевозка, но не подъём, сборка и другие услуги.'),
                TextInput::make('priority')->label('Приоритет')->numeric()->minValue(0)->default(100)->required()
                    ->helperText('Обязательно, по умолчанию 100. Меньшее число проверяется первым: у одного склада выбирается первый подходящий профиль.'),
            ])->columns(['default' => 1, 'md' => 3])->columnSpanFull(),
            Section::make('Услуги этого профиля')
                ->description('Новые тарифы заменяют прежние целиком: проверьте все услуги перед сохранением.')->schema([
                    Toggle::make('service_tariffs.requires_assembly')->label('Обязательная сборка')->default(false)->live()
                        ->helperText('Необязательно, по умолчанию выключено. Если включено, сборка добавляется к заказу даже без выбора покупателем; укажите её цену.'),
                    TextInput::make('service_tariffs.assembly_price')->label('Стоимость сборки')->numeric()->minValue(0)->prefix('₽')
                        ->helperText('Обязательно при обязательной сборке; иначе необязательно. Пусто — сборка недоступна, 0 — бесплатна. Фиксированная сумма за заказ.')
                        ->required(fn ($get) => (bool) $get('service_tariffs.requires_assembly')),
                    TextInput::make('service_tariffs.assembly_days')->label('Срок сборки')->integer()->minValue(0)->suffix('дн.')
                        ->helperText('Необязательно. Отдельный срок сборки в днях; не прибавляется автоматически к сроку перевозки. Пусто — срок не указан.'),
                    \Filament\Forms\Components\Repeater::make('service_tariffs.handling')->label('Тарифы подъёма')->defaultItems(0)
                        ->helperText('Необязательно. Без записей подъём недоступен. Каждый тип добавляйте один раз.')->schema([
                            Select::make('id')->label('Тип обработки')->options(fn () => \App\Models\Shipping\DeliveryHandlingType::where('is_active', true)->pluck('name', 'id'))
                                ->required()->searchable()->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->helperText('Обязательно для записи. Определяет способ подъёма и ограничения по этажу.'),
                            TextInput::make('base_price')->label('Базовая стоимость')->numeric()->minValue(0)->prefix('₽')
                                ->helperText('Необязательно, если заданы специальные цены. Полная цена подъёма за заказ при отсутствии тарифа для выбранного этажа; не цена за этаж.'),
                            TextInput::make('elevator_price')->label('Фиксированная стоимость для типа «Лифт»')->numeric()->minValue(0)->prefix('₽')
                                ->helperText('Необязательно. Применяется только к типу с кодом elevator и имеет приоритет над базовой ценой и таблицей этажей.'),
                            \Filament\Forms\Components\Repeater::make('floor_prices')->label('Полная стоимость для конкретного этажа')->defaultItems(0)->schema([
                                TextInput::make('floor')->label('Этаж')->integer()->minValue(0)->maxValue(300)->required()
                                    ->helperText('Обязательно для строки. Этаж покупателя; один этаж нельзя указать дважды.'),
                                TextInput::make('price')->label('Стоимость')->numeric()->minValue(0)->prefix('₽')->required()
                                    ->helperText('Обязательно для строки. Полная стоимость подъёма на этот этаж, не доплата к базовой цене. 0 — бесплатно.'),
                            ])->columns(2),
                        ])->columnSpanFull(),
                    \Filament\Forms\Components\Repeater::make('service_tariffs.additional')->label('Дополнительные услуги')->defaultItems(0)
                        ->helperText('Необязательно. Покупателю доступны только перечисленные активные услуги с фиксированной ценой.')->schema([
                            Select::make('id')->label('Услуга')->options(fn () => \App\Models\Shipping\AdditionalService::where('is_active', true)->where('price_type', 'fixed')->pluck('name', 'id'))
                                ->required()->searchable()->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->helperText('Обязательно для записи. Название из справочника услуг; не добавляйте одну услугу дважды.'),
                            TextInput::make('price')->label('Стоимость в этом профиле')->numeric()->minValue(0)->prefix('₽')->required()
                                ->helperText('Обязательно для записи. Фиксированная цена услуги за заказ именно для этого профиля. 0 — бесплатно.'),
                        ])->columns(2)->columnSpanFull(),
                ])->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Направление')->searchable(),
            TextColumn::make('coverage_summary')->label('Территории ГАР / КЛАДР')
                ->state(fn ($record): string => $record->coverage_type === 'radius' ? "Радиус {$record->radius_km} км" : collect($record->directory_territories ?? [])->pluck('label')->implode('; ')),
            TextColumn::make('base_price')->label('Цена')->money('RUB'),
            TextColumn::make('delivery_days_min')->label('Срок')->formatStateUsing(fn ($state, $record) => "{$state}–{$record->delivery_days_max} дн."),
            IconColumn::make('is_active')->label('Активен')->boolean(),
        ])->emptyStateHeading('Условия доставки пока не настроены')
            ->emptyStateDescription('Добавьте направление, стоимость и срок доставки.')
            ->headerActions([
                CreateAction::make()->label('Добавить профиль доставки')
                    ->modalHeading('Новый профиль доставки')->modalWidth(\Filament\Support\Enums\Width::FourExtraLarge)
                    ->createAnother(false)->mutateDataUsing(fn (array $data): array => $this->tariffData($data)),
            ])->recordActions([
                EditAction::make()->modalHeading('Редактирование профиля доставки')->modalWidth(\Filament\Support\Enums\Width::FourExtraLarge)
                    ->mutateDataUsing(fn (array $data): array => $this->tariffData($data)),
                DeleteAction::make(),
            ])->defaultSort('priority');
    }

    public function tariffData(array $data): array
    {
        unset($data['use_profile_tariffs']);
        $data['service_tariffs'] = $data['service_tariffs'] ?? [];

        return $data;
    }
}
