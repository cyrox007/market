<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Product\GetVariationAttributesForProductAction;
use App\Actions\Product\SyncVariantVariationAttributesAction;
use App\Filament\Forms\WarehouseStocksFormComponents;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Services\Inventory\WarehouseStockResolver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Vanilo\Product\Models\ProductState;

class ProductVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Торговые предложения';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        // Запрещаем просмотр торговых предложений для торговых предложений
        if ($ownerRecord->isVariant()) {
            return false;
        }
        // Показываем вкладку только для вариативных товаров (чтобы можно было добавить торговые предложения)
        if (!$ownerRecord->is_variable) {
            return false;
        }
        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        $parentProduct = $this->getOwnerRecord();

        // Основные поля вариации (идентичность и базовые данные)
        $components = [
            Section::make('Основные данные вариации')
                ->description('Название, SKU, цена и статус торгового предложения')
                ->schema([
                    TextInput::make('name')
                        ->label('Название вариации')
                        ->required()
                        ->maxLength(255)
                        ->default(fn() => $parentProduct->name)
                        ->helperText('Название вариации товара'),

                    TextInput::make('sku')
                        ->label('Артикул (SKU)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Уникальный артикул вариации'),

                    TextInput::make('price')
                        ->label('Цена')
                        ->numeric()
                        ->prefix('₽')
                        ->required()
                        ->default(fn() => $parentProduct->price)
                        ->helperText('Цена вариации'),

                    TextInput::make('original_price')
                        ->label('Старая цена')
                        ->numeric()
                        ->prefix('₽')
                        ->helperText('Цена до скидки'),

                    TextInput::make('stock')
                        ->label('Остаток (общий)')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->visible(fn (): bool => ! ProductStockSettings::getInstance()->warehouse_accounting_enabled)
                        ->helperText('Используется, если складской учёт выключен в настройках остатков.'),

                    Toggle::make('backorder')
                        ->label('Разрешить предзаказ')
                        ->default(false),

                    Select::make('state')
                        ->label('Статус')
                        ->options([
                            ProductState::ACTIVE => 'Активен',
                            ProductState::DRAFT => 'Черновик',
                            ProductState::INACTIVE => 'Неактивен',
                        ])
                        ->default(ProductState::ACTIVE)
                        ->required(),
                ])
                ->columns(1),
        ];

        // Параметры вариации, которые определяют её название/уникальность в системе
        $variantAttributeFields = [];
        $otherVariationAttributesSectionFields = [];
        $variationAttributes = app(GetVariationAttributesForProductAction::class)->execute($parentProduct);
        foreach ($variationAttributes as $attr) {
            $isVariantAttribute = $attr->slug === \App\Models\Product\Attribute::SLUG_VARIANT;
            // Строковые типы + числовой ввод (number_input) рендерим как поля ввода,
            // а select/number/color — как выбор из списка значений
            $isInputType = in_array($attr->type, ['string', 'text', 'number_input']);
            $options = $attr->orderedValues->pluck('value', 'id');
            $hasOptions = $options->isNotEmpty();
            
            // Если allow_custom_value включен И есть предопределенные значения —
            // для НЕ-списочных типов (не select) показываем Select + поле для ручного ввода.
            // Для чистых списков (type=select) даём только готовые значения без своего текста.
            if ($attr->allow_custom_value && $hasOptions && $attr->type !== 'select') {
                $selectField = Select::make('variation_attr_' . $attr->id)
                    ->label($attr->name . ' (из списка)')
                    ->options($options)
                    ->searchable()
                    ->helperText('Выберите значение из списка или введите своё ниже')
                    ->live()
                    ->afterStateUpdated(function ($state, $set, $get) use ($attr) {
                        // При выборе значения из списка очищаем кастомное поле
                        if (!empty($state)) {
                            $set('variation_custom_' . $attr->id, null);
                        }
                    });
                
                $textField = TextInput::make('variation_custom_' . $attr->id)
                    ->label($attr->name . ' (ручной ввод)')
                    ->maxLength(500)
                    ->numeric($attr->type === 'number_input')
                    ->helperText('Или введите произвольное значение (например, для комплекта у каждого товара свой набор)')
                    ->visible(fn ($get) => empty($get('variation_attr_' . $attr->id)))
                    ->required(function ($get) use ($attr, $isVariantAttribute) {
                        // Если атрибут обязательный (или это Вариант) и не выбрано значение из списка — требуем ручной ввод
                        return ($isVariantAttribute || $attr->is_required) && empty($get('variation_attr_' . $attr->id));
                    })
                    ->live()
                    ->afterStateUpdated(function ($state, $set, $get) use ($attr) {
                        // При вводе кастомного значения очищаем Select
                        if (!empty($state)) {
                            $set('variation_attr_' . $attr->id, null);
                        }
                    });

                if ($isVariantAttribute) {
                    $variantAttributeFields[] = $selectField;
                    $variantAttributeFields[] = $textField;
                } else {
                    $otherVariationAttributesSectionFields[] = $selectField;
                    $otherVariationAttributesSectionFields[] = $textField;
                }
            }
            // Для input-типов (string/text/number_input) без списков или без allow_custom_value — только поле ввода
            elseif ($isInputType) {
                $field = TextInput::make('variation_custom_' . $attr->id)
                    ->label($isVariantAttribute ? 'Вариант (название вариации)' : $attr->name)
                    ->maxLength(500)
                    ->numeric($attr->type === 'number_input')
                    ->required($isVariantAttribute || $attr->is_required);
                if ($isVariantAttribute) {
                    $variantAttributeFields[] = $field;
                } else {
                    $otherVariationAttributesSectionFields[] = $field;
                }
            }
            // Если allow_custom_value включен, но нет предопределенных значений - только текстовое поле
            elseif ($attr->allow_custom_value && !$hasOptions) {
                $field = TextInput::make('variation_custom_' . $attr->id)
                    ->label($attr->name)
                    ->maxLength(500)
                    ->required($isVariantAttribute || $attr->is_required)
                    ->helperText('Ручной ввод значения (например, для комплекта)');
                if ($isVariantAttribute) {
                    $variantAttributeFields[] = $field;
                } else {
                    $otherVariationAttributesSectionFields[] = $field;
                }
            }
            // Иначе - только Select с предопределенными значениями
            else {
                $field = Select::make('variation_attr_' . $attr->id)
                    ->label($attr->name)
                    ->options($options)
                    ->required(($isVariantAttribute || $attr->is_required) && $hasOptions)
                    ->helperText($isVariantAttribute
                        ? 'Основной параметр, который определяет название и уникальность вариации.'
                        : 'Параметр торгового предложения'
                    );
                if ($isVariantAttribute) {
                    $variantAttributeFields[] = $field;
                } else {
                    $otherVariationAttributesSectionFields[] = $field;
                }
            }
        }

        // Отдельный блок для ключевого атрибута "Вариант"
        if (!empty($variantAttributeFields)) {
            $components[] = Section::make('Вариант')
                ->description('Основной параметр, который определяет название вариации в системе и на сайте.')
                ->schema($variantAttributeFields)
                ->columns(2)
                ->collapsible()
                ->collapsed();
        }

        // Остальные параметры вариации
        if (!empty($otherVariationAttributesSectionFields)) {
            $components[] = Section::make('Параметры вариации')
                ->description('Атрибуты, которые отличают вариации друг от друга (цвет, размер, комплект и т.п.).')
                ->schema($otherVariationAttributesSectionFields)
                ->columns(2)
                ->collapsible()
                ->collapsed();
        }

        $components[] = Section::make('Склад (1С)')
            ->description('Идентификатор в 1С и остатки по складам. Данные обновляются через очередь integration-1c.')
            ->schema([
                TextInput::make('external_id')
                    ->label('Внешний ID 1С (external_id)')
                    ->maxLength(255)
                    ->helperText('UUID торгового предложения в кэше 1С. Обязателен для автоматической загрузки остатков по складам.')
                    ->columnSpanFull(),

                WarehouseStocksFormComponents::warehouseStocksRepeater()
                    ->visible(fn (): bool => ProductStockSettings::getInstance()->warehouse_accounting_enabled)
                    ->helperText('Остатки по складам. Склады подтягиваются из 1С при синхронизации (stockId).'),
            ])
            ->collapsible()
            ->collapsed();

        $components[] = Section::make('Изображения торгового предложения')
            ->description('Загрузка главного изображения и галереи для этого торгового предложения')
            ->schema([
                SpatieMediaLibraryFileUpload::make('images')
                    ->collection('images')
                    ->label('Главное изображение')
                    ->helperText('Основное изображение торгового предложения. Будет автоматически создана миниатюра 300x300px')
                    ->image()
                    ->imageEditor()
                    ->conversion('thumb')
                    ->maxSize(10240)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->columnSpanFull(),

                SpatieMediaLibraryFileUpload::make('gallery')
                    ->collection('gallery')
                    ->label('Галерея изображений')
                    ->helperText('Дополнительные изображения торгового предложения для галереи. Можно загрузить до 10 изображений')
                    ->multiple()
                    ->image()
                    ->imageEditor()
                    ->conversion('thumb')
                    ->maxFiles(10)
                    ->maxSize(10240)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->columnSpanFull(),
            ])
            ->collapsible();

        return $schema
            ->components($components)
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название торгового предложения')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Product $record): string => 'Торговое предложение')
                    ->url(fn(Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('variation_params')
                    ->label('Параметры')
                    ->html()
                    ->formatStateUsing(function (Product $record) {
                        $record->load('variantAttributes');
                        $valueIds = $record->variantAttributes->pluck('pivot.attribute_value_id')->filter()->unique()->values();
                        $attributeValues = $valueIds->isNotEmpty()
                            ? \App\Models\Product\AttributeValue::whereIn('id', $valueIds)->get()->keyBy('id')
                            : collect();
                        $parts = $record->variantAttributes->map(function ($attr) use ($attributeValues) {
                            $pivot = $attr->pivot;
                            $val = $pivot->custom_value !== null && $pivot->custom_value !== ''
                                ? $pivot->custom_value
                                : ($attributeValues->get($pivot->attribute_value_id)?->value ?? '—');
                            $isColor = $attr->type === 'color';
                            $colorCode = $isColor && $pivot->attribute_value_id
                                ? ($attributeValues->get($pivot->attribute_value_id)?->color_code ?? null)
                                : null;
                            if ($colorCode) {
                                $safe = htmlspecialchars($colorCode, ENT_QUOTES, 'UTF-8');
                                return '<span class="inline-flex items-center gap-1"><span style="width:12px;height:12px;border-radius:50%;background:' . $safe . ';border:1px solid #ccc;display:inline-block;vertical-align:middle" title="' . $safe . '"></span> ' . e($attr->name) . ': ' . e($val) . '</span>';
                            }
                            return e($attr->name) . ': ' . e($val);
                        });
                        return $parts->implode(', ') ?: '—';
                    }),

                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('stock')
                    ->label(fn (): string => ProductStockSettings::getInstance()->warehouse_accounting_enabled
                        ? 'Остаток (склады)'
                        : 'Остаток')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(function (Product $record): string {
                        if (! ProductStockSettings::getInstance()->warehouse_accounting_enabled) {
                            return (string) (int) ($record->getAttributes()['stock'] ?? 0);
                        }

                        $resolved = app(WarehouseStockResolver::class)->resolveForProduct($record, null);

                        return (string) (int) round($resolved ?? 0);
                    }),

                TextColumn::make('state')
                    ->label('Статус')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        ProductState::ACTIVE => 'success',
                        ProductState::DRAFT => 'gray',
                        ProductState::INACTIVE => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        ProductState::ACTIVE => 'Активен',
                        ProductState::DRAFT => 'Черновик',
                        ProductState::INACTIVE => 'Неактивен',
                        default => $state,
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Добавить торговое предложение')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        $parentProduct = $this->getOwnerRecord();

                        // ВАЖНО: Проверяем, что родительский товар не является вариацией
                        if ($parentProduct->isVariant()) {
                            Notification::make()
                                ->title('Ошибка')
                                ->body('Нельзя создавать торговые предложения для торговых предложений. Торговые предложения могут быть только у основного товара.')
                                ->danger()
                                ->send();
                            throw new \Exception('Нельзя создавать торговые предложения для торговых предложений');
                        }

                        $data['parent_product_id'] = $parentProduct->id;
                        $data['is_variable'] = false; // Торговые предложения не вариативные

                        // Подставляем данные родителя только если пользователь не заполнил поле (не перезаписываем введённые габариты и т.д.)
                        $data['description'] = (isset($data['description']) && $data['description'] !== '') ? $data['description'] : $parentProduct->description;
                        $data['excerpt'] = (isset($data['excerpt']) && $data['excerpt'] !== '') ? $data['excerpt'] : $parentProduct->excerpt;
                        $data['length'] = (isset($data['length']) && $data['length'] !== '' && $data['length'] !== null) ? $data['length'] : $parentProduct->length;
                        $data['width'] = (isset($data['width']) && $data['width'] !== '' && $data['width'] !== null) ? $data['width'] : $parentProduct->width;
                        $data['height'] = (isset($data['height']) && $data['height'] !== '' && $data['height'] !== null) ? $data['height'] : $parentProduct->height;
                        $data['weight'] = (isset($data['weight']) && $data['weight'] !== '' && $data['weight'] !== null) ? $data['weight'] : $parentProduct->weight;

                        return $data;
                    })
                    ->using(function (array $data, RelationManager $livewire): Product {
                        $parentProduct = $livewire->getOwnerRecord();

                        if ($parentProduct->isVariant()) {
                            throw new \Exception('Нельзя создавать торговые предложения для торговых предложений');
                        }

                        $data['parent_product_id'] = $parentProduct->id;

                        $formDataForSync = $data;
                        SyncVariantVariationAttributesAction::stripVariationFormKeys($data);

                        $variant = Product::create($data);

                        app(SyncVariantVariationAttributesAction::class)->execute($variant, $formDataForSync, $parentProduct);

                        if (!$parentProduct->is_variable) {
                            $parentProduct->update(['is_variable' => true]);
                        }

                        $parentProduct->flushCache();

                        return $variant;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->fillForm(function (Product $record): array {
                        $data = $record->toArray();
                        $parent = $record->parentProduct;
                        if (!$parent) {
                            return $data;
                        }
                        $record->load('variantAttributes');
                        $byAttr = $record->variantAttributes->keyBy('id');
                        $formAttrs = app(GetVariationAttributesForProductAction::class)->execute($parent);
                        foreach ($formAttrs as $attr) {
                            $v = $byAttr->get($attr->id);
                            if (!$v || !$v->pivot) {
                                continue;
                            }
                            $pivot = $v->pivot;
                            if ($pivot->custom_value !== null && $pivot->custom_value !== '') {
                                $data['variation_custom_' . $attr->id] = $pivot->custom_value;
                            } else {
                                $data['variation_attr_' . $attr->id] = $pivot->attribute_value_id;
                            }
                        }
                        return $data;
                    })
                    ->using(function (array $data, Product $record): Product {
                        $parent = $record->parentProduct;
                        if ($parent) {
                            app(SyncVariantVariationAttributesAction::class)->execute($record, $data, $parent);
                        }
                        SyncVariantVariationAttributesAction::stripVariationFormKeys($data);
                        $record->update($data);
                        if ($parent) {
                            $parent->flushCache();
                        }
                        return $record;
                    }),
                DeleteAction::make()
                    ->after(function (Product $record) {
                        $parentProduct = $record->parentProduct;
                        if ($parentProduct) {
                            // Если это была последняя вариация, отключаем is_variable
                            $variantsCount = $parentProduct->variants()->count();
                            if ($variantsCount === 0) {
                                $parentProduct->update(['is_variable' => false]);
                            }
                            // Сбрасываем кэш родителя после удаления вариации
                            $parentProduct->flushCache();
                        }
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make()
                        ->after(function ($records) {
                            // Проверяем родительские товары после массового удаления и сбрасываем их кэш
                            $parentIds = $records->pluck('parent_product_id')->unique()->filter();
                            foreach ($parentIds as $parentId) {
                                $parent = Product::find($parentId);
                                if ($parent) {
                                    $variantsCount = $parent->variants()->count();
                                    if ($variantsCount === 0 && $parent->is_variable) {
                                        $parent->update(['is_variable' => false]);
                                    }
                                    $parent->flushCache();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

}
