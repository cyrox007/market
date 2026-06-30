<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Product;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Перед созданием товара гарантируем, что поля,
     * которые не могут быть NULL в БД, не уйдут как null.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // SKU в таблице products не допускает NULL, поэтому
        // подставляем пустую строку, если поле не заполнено.
        // После создания модель Product::created запишет ID, если sku пустой.
        if (!array_key_exists('sku', $data) || $data['sku'] === null) {
            $data['sku'] = '';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Сбрасываем кэш товаров на бекенде сразу после создания
        $this->record->flushCache();

        // ВРЕМЕННО: полный сброс кэша каталога (убрать для высоконагруженных проектов)
        if (config('cache.clear_catalog_on_product_change', true)) {
            Product::flushAllProductCaches();
        }
    }
}
