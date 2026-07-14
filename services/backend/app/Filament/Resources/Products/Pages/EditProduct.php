<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected array $productAttributesData = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Одна колонка в корне — двухколоночная раскладка задаётся в ProductForm через Grid.
     */
    public function defaultForm(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->columns(1)
            ->inlineLabel($this->hasInlineLabels())
            ->model($this->getRecord())
            ->operation('edit')
            ->statePath('data');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Автоматически включаем is_variable, если у товара есть вариации
        $product = $this->record;
        if ($product && $product->variants()->count() > 0) {
            $data['is_variable'] = true;
        } elseif ($product && $product->variants()->count() === 0 && isset($data['is_variable']) && $data['is_variable']) {
            // Если пользователь пытается включить is_variable, но вариаций нет, оставляем как есть
            // (возможно, он планирует добавить вариации)
        }

        // Извлекаем характеристики для последующей обработки в afterSave
        // Удаляем их из данных, чтобы не пытаться сохранить напрямую
        $this->productAttributesData = $data['product_attributes'] ?? [];
        unset($data['product_attributes']);

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $product = $this->record;

        // RichEditor (TipTap) падает с "reading 'length'", если excerpt/description не строка (null/array/undefined).
        $data['excerpt'] = is_string($data['excerpt'] ?? null) ? $data['excerpt'] : '';
        $data['description'] = is_string($data['description'] ?? null) ? $data['description'] : '';

        if ($product) {
            // Загружаем существующие характеристики для формы
            $attributes = $product->attributes()->withPivot('attribute_value_id', 'custom_value')->get();
            $data['product_attributes'] = $attributes->map(function ($attribute) {
                return [
                    'attribute_id' => $attribute->id,
                    'attribute_value_id' => $attribute->pivot->attribute_value_id,
                    'custom_value' => $attribute->pivot->custom_value,
                ];
            })->toArray();
        } else {
            $data['product_attributes'] = [];
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $product = $this->record;
        if ($product) {
            // Обрабатываем характеристики товара (и для обычных, и для вариативных — у последних это производитель и др.)
            DB::transaction(function () use ($product) {
                // Удаляем все существующие характеристики
                DB::table('product_product_attributes')
                    ->where('product_id', $product->id)
                    ->delete();

                // Добавляем новые характеристики
                if (!empty($this->productAttributesData)) {
                    $attributesData = [];
                    foreach ($this->productAttributesData as $attr) {
                        $attributeId = $attr['attribute_id'] ?? null;
                        $attributeValueId = $attr['attribute_value_id'] ?? null;
                        $customValue = $attr['custom_value'] ?? null;

                        if (!$attributeId) {
                            continue;
                        }

                        $attribute = \App\Models\Product\Attribute::find($attributeId);
                        // Если attribute_value_id — массив (множественный выбор)
                        if (is_array($attributeValueId)) {
                            foreach ($attributeValueId as $singleValueId) {
                                if (!empty($singleValueId)) {
                                    $attributeValue = AttributeValue::find($singleValueId);
                                    if ($attributeValue && $attributeValue->attribute_id == $attributeId) {
                                        $attributesData[] = [
                                            'product_id' => $product->id,
                                            'attribute_id' => $attributeId,
                                            'attribute_value_id' => $singleValueId,
                                            'custom_value' => null,
                                            'created_at' => now(),
                                            'updated_at' => now(),
                                        ];
                                    }
                                }
                            }
                            continue;
                        }

                        // Одно значение
                        if (!empty($attributeValueId)) {
                            $attributeValue = AttributeValue::find($attributeValueId);
                            if ($attributeValue && $attributeValue->attribute_id == $attributeId) {
                                $attributesData[] = [
                                    'product_id' => $product->id,
                                    'attribute_id' => $attributeId,
                                    'attribute_value_id' => $attributeValueId,
                                    'custom_value' => null,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            }
                            continue;
                        }

                        $customValueStr = $customValue !== null && $customValue !== '' ? (string) $customValue : null;

                        // Если нет attribute_value_id, но есть ручной ввод — сохраняем его
                        // ТОЛЬКО для атрибутов, у которых разрешён custom_value.
                        if (
                            $customValueStr !== null
                            && $attribute
                            && $attribute->allow_custom_value
                        ) {
                            $attributesData[] = [
                                'product_id' => $product->id,
                                'attribute_id' => $attributeId,
                                'attribute_value_id' => null,
                                'custom_value' => $customValueStr,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    if (!empty($attributesData)) {
                        DB::table('product_product_attributes')->insert($attributesData);
                    }
                }
            });
        }

        // Автоматически включаем is_variable, если появились вариации. НЕ сбрасываем в false при 0 вариациях —
        // пользователь может пометить товар как вариативный и затем добавить торговые предложения.
        if ($product) {
            $variantsCount = $product->variants()->count();
            if ($variantsCount > 0 && !$product->is_variable) {
                $product->update(['is_variable' => true]);
            }

            // Сбрасываем кэш товаров на бекенде сразу после сохранения
            $product->flushCache();

            // ВРЕМЕННО: полный сброс кэша каталога (убрать для высоконагруженных проектов)
            if (config('cache.clear_catalog_on_product_change', true)) {
                Product::flushAllProductCaches();
            }
        }
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/edit_product.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/edit_product.title');
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('index');
    }
}
