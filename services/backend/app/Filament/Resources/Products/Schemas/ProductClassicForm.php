<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\Products\Attributes\AttributeQuickCreate;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeTemplate;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use App\Models\Product\ProductDocument;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use RalphJSmit\Filament\SEO\SEO;
use Vanilo\Product\Models\ProductState;

class ProductClassicForm extends ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Group::make([
                            self::mainInfoSection(),
                            self::categoriesSection(),
                            self::pricesSection(),
                            self::statusSection(),
                            self::manufacturerSection(),
                            self::stockSection(),
                            self::warehouseStocksSection(),
                        ]),

                        Group::make([
                            self::imagesSection(),
                            self::documentsSection(),
                            self::dimensionsSection(),
                            self::taxShippingSection(),
                            self::seoSection(),
                            self::importSection(),
                        ]),
                    ])
                    ->columnSpanFull(),

                self::attributesSection()->columnSpanFull(),
            ]);
    }

    protected static function documentsSection(): Section
    {
        return Section::make('Инструкции и документы')
            ->description('Файлы для скачивания на карточке товара: PDF, Word, Excel, текст или изображение, до 10MB')
            ->schema([
                Repeater::make('documents')
                    ->label('Документы')
                    ->hiddenLabel()
                    ->relationship('documents')
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->grid(2)
                    ->extraAttributes(['class' => 'product-documents'])
                    ->schema([
                        TextInput::make('title')
                            ->label('Название')
                            ->hiddenLabel()
                            ->placeholder('Название')
                            ->extraInputAttributes(['title' => 'Подпись на сайте. Если пусто — имя файла'])
                            ->extraFieldWrapperAttributes(['class' => 'product-documents-title'])
                            ->maxLength(255),
                        SpatieMediaLibraryFileUpload::make('file')
                            ->label('Файл')
                            ->collection(ProductDocument::MEDIA_COLLECTION)
                            ->preserveFilenames()
                            ->acceptedFileTypes(ProductDocument::MIME_TYPES)
                            ->maxSize(10240)
                            ->downloadable()
                            ->openable()
                            ->required(),
                        Toggle::make('is_published')
                            ->label('Опубликован')
                            ->extraFieldWrapperAttributes(['class' => 'product-documents-published'])
                            ->default(true),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Добавить документ'),
            ])
            ->collapsible();
    }

    protected static function seoSection(): Section
    {
        return Section::make('SEO')
            ->description('Поисковая оптимизация товара')
            ->schema([
                SEO::make()
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->collapsed();
    }

    protected static function pricesSection(): Section
    {
        return Section::make('Цены')
            ->description('Цена продажи и цена до скидки')
            ->schema([
                TextInput::make('price')
                    ->label(__('filament/admin_sv/product_resource.price'))
                    ->numeric()
                    ->prefix('₽')
                    // У вариативного цена «от» считается по вариантам
                    ->required(fn ($get): bool => ! $get('is_variable'))
                    ->helperText('Цена продажи. Показывается на карточке товара и в заказе.'),

                TextInput::make('original_price')
                    ->label(__('filament/admin_sv/product_resource.original_price'))
                    ->numeric()
                    ->prefix('₽')
                    ->helperText('Старая цена — отображается зачёркнутой рядом с ценой продажи.'),
            ])
            ->columns(2);
    }

    protected static function statusSection(): Section
    {
        return Section::make('Статус и вариативность')
            ->description('Видимость товара, приоритет и наличие торговых предложений')
            ->schema([
                Select::make('state')
                    ->label(__('filament/admin_sv/product_resource.state'))
                    ->options([
                        ProductState::DRAFT => 'Черновик',
                        ProductState::ACTIVE => 'Активен',
                        ProductState::INACTIVE => 'Неактивен',
                    ])
                    ->required()
                    ->default(ProductState::DRAFT)
                    ->helperText('Статус видимости на сайте')
                    ->columnSpanFull(),

                TextInput::make('priority')
                    ->label(__('filament/admin_sv/product_resource.priority'))
                    ->numeric()
                    ->default(0)
                    ->helperText('Меньше число — выше в списке')
                    ->columnSpanFull(),

                Toggle::make('is_variable')
                    ->label(__('filament/admin_sv/product_resource.is_variable'))
                    ->helperText(function ($record) {
                        if (! $record) {
                            return 'Вариации (цвет, размер) — во вкладке «Торговые предложения» после сохранения.';
                        }
                        $variantsCount = $record->variants()->count();
                        if ($variantsCount > 0) {
                            return "У товара {$variantsCount} вариаций. Удалите все, чтобы отключить.";
                        }
                        return $record->is_variable
                            ? 'Вариации добавляются во вкладке «Торговые предложения».'
                            : 'Вкладка «Торговые предложения» появится после сохранения.';
                    })
                    ->default(false)
                    ->disabled(fn ($record) => $record && $record->variants()->count() > 0)
                    ->dehydrated()
                    ->live()
                    ->columnSpanFull(),

                Placeholder::make('variants_info')
                    ->label('Торговые предложения')
                    ->content(function ($record) {
                        if (! $record) {
                            return '—';
                        }
                        $variantsCount = $record->variants()->count();
                        return $variantsCount === 0
                            ? 'Нет. Добавьте во вкладке «Торговые предложения».'
                            : "{$variantsCount} шт. Управление во вкладке «Торговые предложения».";
                    })
                    ->visible(fn ($record) => $record !== null)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function attributesSection(): Section
    {
        return Section::make('Характеристики товара')
            ->description('Габариты для фильтров: «Ширина (мм)», «Глубина (мм)», «Высота (мм)» — только числа без «мм». Цвет и торговый размер вариативного товара задавайте у предложений; общие свойства наследуются, если у предложения нет своего значения.')
            ->key('product_attributes_section')
            ->headerActions([self::applyAttributeTemplateAction()])
            ->schema([
                Repeater::make('product_attributes')
                    ->label('Характеристики')
                    ->extraAttributes(['class' => 'product-attributes-table'])
                    ->table([
                        TableColumn::make('Характеристика')->markAsRequired(),
                        TableColumn::make('Значение'),
                        TableColumn::make('Своё значение'),
                    ])
                    ->schema([
                        Select::make('attribute_id')
                            ->label('Характеристика')
                            ->options(fn (?Product $record) => Attribute::query()
                                ->assignableToProduct($record)
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($set) {
                                $set('attribute_value_id', null);
                                $set('custom_value', null);
                            })
                            ->createOptionForm(AttributeQuickCreate::form())
                            ->createOptionUsing(fn (array $data) => AttributeQuickCreate::create($data)),

                        \App\Filament\Forms\AttributeValueSelect::make('attribute_value_id')
                            ->label('Значение')
                            ->options(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (! $attributeId) {
                                    return [];
                                }

                                return AttributeValue::query()
                                    ->where('attribute_id', $attributeId)
                                    ->orderBy('sort_order')
                                    ->orderBy('value')
                                    ->pluck('value', 'id');
                            })
                            ->multiple(function ($get) {
                                $attributeId = $get('attribute_id');
                                return $attributeId && (bool) Attribute::query()->find($attributeId)?->is_multiple;
                            })
                            ->searchable()
                            ->live()
                            ->disabled(fn ($get) => ! $get('attribute_id'))
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state !== null && $state !== '' && $state !== []) {
                                    $set('custom_value', null);
                                }
                            })
                            ->createOptionForm([
                                TextInput::make('value')->label('Значение')->required()->maxLength(255),
                                ColorPicker::make('color_code')
                                    ->label('Цвет')
                                    ->visible(function ($get) {
                                        $attrId = $get('../../attribute_id');
                                        return $attrId && Attribute::find($attrId)?->type === 'color';
                                    }),
                            ])
                            ->createOptionUsing(function (array $data, $get) {
                                $attributeId = $get('attribute_id');
                                if (! $attributeId) {
                                    return null;
                                }
                                $slug = Str::slug($data['value']);
                                $base = $slug;
                                $i = 2;
                                while (AttributeValue::where('attribute_id', $attributeId)->where('slug', $slug)->exists()) {
                                    $slug = $base . '-' . $i++;
                                }
                                return AttributeValue::create([
                                    'attribute_id' => $attributeId,
                                    'value' => $data['value'],
                                    'slug' => $slug,
                                    'color_code' => $data['color_code'] ?? null,
                                ])->id;
                            }),

                        TextInput::make('custom_value')
                            ->label('Своё значение')
                            ->maxLength(1000)
                            ->visible(function ($get) {
                                $attributeId = $get('attribute_id');
                                return $attributeId && (bool) Attribute::query()->find($attributeId)?->allow_custom_value;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state !== null && trim((string) $state) !== '') {
                                    $set('attribute_value_id', null);
                                }
                            }),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Добавить характеристику')
                    ->columnSpanFull(),
            ])
            ->collapsible()
            ->collapsed(false);
    }

    protected static function applyAttributeTemplateAction(): Action
    {
        return Action::make('applyAttributeTemplate')
            ->label('Применить шаблон')
            ->icon('heroicon-o-queue-list')
            ->color('gray')
            ->modalHeading('Применить шаблон характеристик')
            ->modalSubmitActionLabel('Добавить характеристики')
            ->schema([
                Select::make('template_id')
                    ->label('Шаблон')
                    ->hiddenLabel()
                    ->placeholder('Шаблон')
                    ->options(fn () => AttributeTemplate::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data, Get $get, Set $set, ?Product $record): void {
                $rows = $get('product_attributes') ?? [];
                $template = AttributeTemplate::query()->findOrFail($data['template_id']);
                $template->increment('applied_count');
                $attributeIds = $template->attributeIdsToAdd(array_column($rows, 'attribute_id'), $record);

                foreach ($attributeIds as $attributeId) {
                    $rows[(string) Str::uuid()] = [
                        'attribute_id' => $attributeId,
                        'attribute_value_id' => null,
                        'custom_value' => null,
                    ];
                }

                $set('product_attributes', $rows);

                Notification::make()
                    ->title($attributeIds === []
                        ? 'Все характеристики шаблона уже есть у товара'
                        : 'Добавлено характеристик: ' . count($attributeIds))
                    ->success()
                    ->send();
            });
    }
}
