<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Models\Product\Room;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use RalphJSmit\Filament\SEO\SEO;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $set('slug', \Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('URL (slug)')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('parent_id')
                            ->label('Родительская комната')
                            ->options(fn () => Room::query()->orderBy('name')->get()
                                ->filter(fn (Room $r) => $r->depth() < Room::MAX_DEPTH)
                                ->mapWithKeys(function (Room $r) {
                                    $root = $r->rootAncestor()->name;
                                    $label = $r->name === $root
                                        ? e($r->name)
                                        : e($r->name) . ' <span style="color:#9ca3af;font-size:.85em">' . e($root) . '</span>';

                                    return [$r->id => $label];
                                })
                                ->all())
                            ->allowHtml()
                            ->searchable()
                            ->placeholder('Корневая')
                            ->helperText('Максимальная вложенность — 4 уровня')
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state && $state == $get('id')) {
                                    $set('parent_id', null);
                                }
                            }),
                        TextInput::make('priority')
                            ->label('Приоритет')
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше в списке'),
                        Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true),
                    ])->columns(2),

                Section::make('Продуктовые категории')
                    ->description('Категории каталога, товары которых показываются в этой комнате')
                    ->schema([
                        Select::make('productCategories')
                            ->label('Категории')
                            ->relationship('productCategories', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Товары выбранных категорий (с потомками) попадут в комнату'),
                    ])
                    ->collapsible(),

                Section::make('Фильтр товаров')
                    ->description('Необязательный фильтр, применяемый к товарам комнаты по умолчанию')
                    ->schema([
                        TextInput::make('filters.price_min')
                            ->label('Цена от')
                            ->numeric()
                            ->minValue(fn (Get $get) => self::parentEffectiveFilters($get('parent_id'))['price_min'] ?? null)
                            ->helperText(fn (Get $get) => ($v = self::parentEffectiveFilters($get('parent_id'))['price_min'] ?? null)
                                ? "Наследуется от родителя: не ниже {$v}" : null),
                        TextInput::make('filters.price_max')
                            ->label('Цена до')
                            ->numeric()
                            ->maxValue(fn (Get $get) => self::parentEffectiveFilters($get('parent_id'))['price_max'] ?? null)
                            ->helperText(fn (Get $get) => ($v = self::parentEffectiveFilters($get('parent_id'))['price_max'] ?? null)
                                ? "Наследуется от родителя: не выше {$v}" : null),
                        Select::make('filters.colors')
                            ->label('Цвета')
                            ->multiple()
                            ->searchable()
                            ->options(function (Get $get) {
                                $all = self::colorOptions();
                                $inherited = self::parentEffectiveFilters($get('parent_id'))['colors'] ?? null;

                                return $inherited === null ? $all : array_intersect_key($all, array_flip($inherited));
                            })
                            ->helperText('У наследника цвета ограничены выбором родителя'),
                    ])->columns(2)
                    ->collapsible(),

                Section::make('Характеристики')
                    ->description('Отбор товаров комнаты по характеристикам: выберите характеристику и её значения')
                    ->schema([
                        Repeater::make('attribute_filters')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('attribute')
                                    ->label('Характеристика')
                                    ->options(self::attributeOptions())
                                    ->required()
                                    ->live()
                                    ->distinct()
                                    ->searchable(),
                                Select::make('values')
                                    ->label('Значения')
                                    ->multiple()
                                    ->required()
                                    ->searchable()
                                    ->options(function (Get $get) {
                                        $attrSlug = $get('attribute');
                                        $all = self::valueOptions($attrSlug);
                                        $inherited = self::parentEffectiveFilters($get('../../parent_id'))['attributes'][$attrSlug] ?? null;

                                        return $inherited === null ? $all : array_intersect_key($all, array_flip($inherited));
                                    }),
                            ])
                            ->columns(2)
                            ->addActionLabel('Добавить характеристику')
                            ->defaultItems(0),
                    ])
                    ->collapsible(),

                Section::make('SEO настройки')
                    ->description('Управление мета данными')
                    ->schema([
                        SEO::make(),
                    ])
                    ->collapsible(),

                Section::make('Изображение комнаты')
                    ->description('Баннер комнаты (миниатюра 300x300, HD, Full HD создаются автоматически)')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->label('Главное изображение')
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

    /**
     * Цвета существующих товаров (код => название).
     */
    private static function colorOptions(): array
    {
        return Product::query()
            ->whereNotNull('color')
            ->where('color', '!=', '')
            ->distinct()
            ->orderBy('color')
            ->pluck('color')
            ->mapWithKeys(fn ($c) => [Str::slug($c) => $c])
            ->all();
    }

    /**
     * Унаследованные (эффективные) фильтры родителя — для ограничения выбора наследника.
     */
    private static function parentEffectiveFilters($parentId): array
    {
        if (! $parentId) {
            return [];
        }
        $parent = Room::find($parentId);

        return $parent ? $parent->effectiveFilters() : [];
    }

    /**
     * Все характеристики, у которых есть значения (для выбора в конструкторе).
     */
    private static function attributeOptions(): array
    {
        return Attribute::whereHas('values')->orderBy('name')->pluck('name', 'slug')->all();
    }

    /**
     * Значения выбранной характеристики (код => отображаемое значение).
     */
    private static function valueOptions(?string $attributeSlug): array
    {
        if (! $attributeSlug) {
            return [];
        }
        $attribute = Attribute::where('slug', $attributeSlug)->first();
        if (! $attribute) {
            return [];
        }

        return $attribute->values->mapWithKeys(fn ($v) => [$v->slug => ($v->value ?: $v->slug)])->all();
    }

    /**
     * Блоки конструктора характеристик → filters.attributes (код => [значения]).
     * Вызывается при сохранении (create/edit).
     */
    public static function packAttributeFilters(array $data): array
    {
        $filters = $data['filters'] ?? [];
        $attributes = [];
        foreach ($data['attribute_filters'] ?? [] as $row) {
            $slug = $row['attribute'] ?? null;
            $values = $row['values'] ?? [];
            if ($slug && ! empty($values)) {
                $attributes[$slug] = array_values($values);
            }
        }
        if (! empty($attributes)) {
            $filters['attributes'] = $attributes;
        } else {
            unset($filters['attributes']);
        }
        $data['filters'] = ! empty($filters) ? $filters : null;
        unset($data['attribute_filters']);

        return $data;
    }

    /**
     * filters.attributes (код => [значения]) → блоки конструктора.
     * Вызывается при заполнении формы редактирования.
     */
    public static function unpackAttributeFilters(array $data): array
    {
        $rows = [];
        foreach ($data['filters']['attributes'] ?? [] as $slug => $values) {
            $rows[] = ['attribute' => $slug, 'values' => array_values((array) $values)];
        }
        $data['attribute_filters'] = $rows;

        return $data;
    }
}
