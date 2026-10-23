<?php

namespace App\Services\Catalog\Transfer;

use App\Actions\Inventory\Sync\ApplyOneCStockSnapshotAction;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use App\Services\Product\ProductManufacturer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferProductWriter
{
    public function applyTable(int $rowId): Product
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \DomainException('Применение разрешено только локально.');
        }
        $row = DB::table('catalog_transfer_rows')->find($rowId);
        if (! $row) {
            throw new \DomainException('Строка переноса не найдена.');
        }
        $source = json_decode($row->source ?? '{}', true);
        if (isset($source['variant_group']) && $source['variant_group'] !== '') {
            return app(TransferVariantWriter::class)->apply($rowId, fn (int $id) => $this->applySingle($id));
        }

        return $this->applySingle($rowId);
    }

    private function applySingle(int $rowId): Product
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \DomainException('Применение разрешено только локально.');
        }

        return DB::transaction(function () use ($rowId): Product {
            $row = DB::table('catalog_transfer_rows')->where('id', $rowId)->lockForUpdate()->first();
            if ($row->table_applied_at) {
                return Product::findOrFail($row->applied_product_id);
            }
            $siblings = DB::table('catalog_transfer_rows')->where('run_id', $row->run_id)->where('code', $row->code);
            if ($row->code === '' || (clone $siblings)->distinct()->count('old_id') > 1 || (clone $siblings)->distinct()->count('price') > 1) {
                throw new \DomainException('Пустой код 1С или конфликт данных таблиц.');
            }
            $data = [];
            foreach ((clone $siblings)->pluck('source') as $source) {
                foreach (json_decode($source ?? '{}', true)['product_data'] ?? [] as $key => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if (isset($data[$key]) && (string) $data[$key] !== (string) $value) {
                        throw new \DomainException('Разные значения поля '.$key.' для одного кода 1С.');
                    }
                    $data[$key] = $value;
                }
            }
            $matches = Product::query()->where(fn ($query) => $query->where('external_id', $row->code)->orWhere('sku', $row->code))->lockForUpdate()->get();
            if ($matches->count() > 1) {
                throw new \DomainException('SKU и внешний код указывают на несколько товаров.');
            }
            $product = $matches->first();
            $created = $product === null;
            $source = json_decode($row->source ?? '{}', true);
            $grouped = isset($source['variant_group']) && $source['variant_group'] !== '';
            if (! $created && ($source['inherited_name'] ?? false)) {
                unset($data['name']);
            }
            $oldId = $row->old_id ?? (clone $siblings)->whereNotNull('old_id')->value('old_id');
            if ($product && filled($product->external_id) && $product->external_id !== $row->code && (string) $product->external_id !== (string) $oldId) {
                throw new \DomainException('У найденного SKU уже указан другой код 1С.');
            }
            if ($created && empty($data['name'])) {
                throw new \DomainException('Для создания товара требуется название из таблицы.');
            }
            if ($created) {
                $product = new Product;
                $product->state = Product::ACTIVE;
                $product->stock = 0;
                $product->backorder = false;
                $product->units_sold = 0;
                $base = (Str::slug($data['name']) ?: 'product').'-'.substr(sha1($row->code), 0, 12);
                $slug = $base;
                for ($suffix = 2; Product::where('slug', $slug)->exists(); $suffix++) {
                    $slug = $base.'-'.$suffix;
                }
                $product->slug = $slug;
            }
            $product->external_id = $row->code;
            $product->sku = $row->code;
            foreach (['name', 'description', 'excerpt'] as $field) {
                if (isset($data[$field])) {
                    $product->{$field} = $field === 'name' ? html_entity_decode($data[$field]) : strip_tags($data[$field]);
                }
            }
            validator(['name' => $product->name], ['name' => 'required|string|max:255'])->validate();
            $price = $row->price ?? (clone $siblings)->whereNotNull('price')->value('price');
            if ($created && $price === null && ! $grouped) {
                throw new \DomainException('Для создания товара требуется цена из таблицы.');
            }
            if ($created && $price === null && $grouped) {
                $product->price = 0;
                $product->state = 'draft';
            }
            if ($price !== null) {
                $product->price = $price;
            }
            if (isset($data['status'])) {
                $product->state = in_array(strtolower((string) $data['status']), ['true', '1', 'active', 'yes'], true) ? 'active' : 'inactive';
            }
            if ($created && $price === null && $grouped) {
                $product->state = 'draft';
            }
            $product->save();
            if (! empty($data['manufacturer'])) {
                app(ProductManufacturer::class)->save($product, $data['manufacturer']);
            }
            foreach ($this->tableCharacteristics($data) as $name => $value) {
                $this->characteristic($product, $name, $value);
            }
            $seo = array_filter(['title' => $data['meta_title'] ?? null, 'description' => $data['meta_description'] ?? null], fn ($value) => $value !== null && $value !== '');
            if ($seo) {
                $product->seo()->updateOrCreate([], $seo);
            }
            DB::table('catalog_transfer_rows')->where('id', $rowId)->update([
                'applied_product_id' => $product->id, 'product_created' => $created,
                'table_applied_at' => now(), 'message' => $created ? 'Создан из таблицы. Ожидает дополнения.' : 'Обновлён из таблицы. Ожидает дополнения.', 'updated_at' => now(),
            ]);
            app(CatalogTransfer::class)->log($row->run_id, ($created ? 'Создан' : 'Обновлён').' товар ID '.$product->id.' из таблицы.', $row->code);

            return $product;
        }, 3);
    }

    public function applyApi(int $rowId, array $data): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \DomainException('Применение разрешено только локально.');
        }
        DB::transaction(function () use ($rowId, $data): void {
            $row = DB::table('catalog_transfer_rows')->where('id', $rowId)->lockForUpdate()->first();
            if ($row->api_applied_at) {
                return;
            }
            $product = Product::whereKey($row->applied_product_id)->lockForUpdate()->firstOrFail();
            $source = json_decode($row->source ?? '{}', true);
            $grouped = isset($source['variant_group']) && $source['variant_group'] !== '';
            $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
            if ($grouped) {
                if (isset($data['external_id']) && (string) $data['external_id'] !== (string) $row->code) {
                    throw new \DomainException('Ответ API относится к другому коду 1С.');
                }
                if (is_string($payload['name'] ?? null) && trim($payload['name']) !== '') {
                    $product->name = html_entity_decode(strip_tags(trim($payload['name'])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    validator(['name' => $product->name], ['name' => 'required|string|max:255'])->validate();
                }
                if (is_numeric($payload['weight'] ?? null) && is_finite((float) $payload['weight']) && (float) $payload['weight'] >= 0) {
                    $product->weight = $payload['weight'];
                }
                if (is_string($payload['manufacturer'] ?? null) && trim($payload['manufacturer']) !== '') {
                    app(ProductManufacturer::class)->save($product, trim($payload['manufacturer']));
                }
            }
            $protected = [];
            foreach (DB::table('catalog_transfer_rows')->where('run_id', $row->run_id)->where('code', $row->code)->pluck('source') as $source) {
                $protected += json_decode($source ?? '{}', true)['product_data'] ?? [];
            }
            foreach ($data['characteristics'] as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                $values = $item['values'] ?? [];
                $value = $grouped && is_array($values)
                    ? implode(' / ', array_values(array_unique(array_filter(array_map(fn ($value) => is_scalar($value) ? trim((string) $value) : '', $values), fn ($value) => $value !== ''))))
                    : trim((string) (is_array($values) ? ($values[0] ?? '') : $values));
                if ($name === '' || $value === '') {
                    continue;
                }
                $slug = Attribute::canonicalSlugForName($name);
                if (! $grouped && $slug === Attribute::SLUG_MANUFACTURER && ! empty($protected['manufacturer'])) {
                    continue;
                }
                $protectedSlugs = array_map(fn ($label) => Attribute::canonicalSlugForName($label), array_keys($this->tableCharacteristics($protected)));
                if ($grouped || ! in_array($slug, $protectedSlugs, true)) {
                    $this->characteristic($product, $name, $value);
                }
            }
            // Existing inventory action creates missing warehouses by 1C ID and applies a complete stock snapshot.
            if ($grouped) {
                $apiPrice = $data['price'] ?? null;
                if (is_numeric($apiPrice) && is_finite((float) $apiPrice) && (float) $apiPrice >= 0) {
                    $product->price = $apiPrice;
                    if ((string) $product->state === 'draft') {
                        $tableStatus = strtolower((string) ($protected['status'] ?? 'true'));
                        $product->state = in_array($tableStatus, ['true', '1', 'active', 'yes'], true) ? 'active' : 'inactive';
                    }
                }
                $product->save();
            }
            app(ApplyOneCStockSnapshotAction::class)->execute($product, $data['stocks']);
            if ($product->parent_product_id) {
                $parent = Product::findOrFail($product->parent_product_id);
                if ((string) $parent->state === 'draft' && (string) $product->state === 'active') {
                    $parent->state = 'active';
                    $parent->save();
                }
                $attributeIds = app(\App\Actions\Product\GetVariationAttributesForProductAction::class)->execute($parent)->pluck('id')->all();
                $dimensionSlugs = array_map(fn ($name) => Attribute::canonicalSlugForName($name), ['Цвет', 'Размер', 'Ширина', 'Высота', 'Длина', 'Глубина', 'Ширина (мм)', 'Высота (мм)', 'Глубина (мм)']);
                $apiAttributeIds = $product->attributes()->where(fn ($query) => $query->where('is_use_in_variations', true)->orWhereIn('product_attributes.slug', $dimensionSlugs))->pluck('product_attributes.id')->all();
                $parent->variationAttributeSelection()->syncWithoutDetaching(array_unique(array_merge($attributeIds, $apiAttributeIds)));
                $label = mb_substr((string) $product->name, 0, max(0, 250 - mb_strlen((string) $product->sku))).' — '.$product->sku;
                $form = app(\App\Actions\Product\BuildMergeVariantFormDataAction::class)->execute($parent, $product, $label);
                app(\App\Actions\Product\SyncVariantVariationAttributesAction::class)->execute($product, $form, $parent);
            }
            $product->flushCache();
            DB::table('catalog_transfer_rows')->where('id', $rowId)->update(['api_applied_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    private function tableCharacteristics(array $data): array
    {
        $result = [];
        if (! empty($data['model'])) {
            $result['Модель'] = $data['model'];
        }
        $unit = mb_strtolower(trim($data['length_unit'] ?? 'мм'));
        $factor = match ($unit) {
            'мм', 'mm' => 1, 'см', 'cm' => 10, 'м', 'm' => 1000, default => null
        };
        foreach (['width' => 'Ширина (мм)', 'height' => 'Высота (мм)', 'length' => 'Глубина (мм)'] as $key => $label) {
            if ($factor !== null && isset($data[$key]) && is_numeric($data[$key]) && (float) $data[$key] >= 0) {
                $result[$label] = (string) ((float) $data[$key] * $factor);
            }
        }

        return $result;
    }

    private function characteristic(Product $product, string $name, string $value): void
    {
        $slug = Attribute::canonicalSlugForName($name);
        if ($slug === Attribute::SLUG_MANUFACTURER) {
            app(ProductManufacturer::class)->save($product, $value);

            return;
        }
        $attribute = Attribute::firstOrCreate(['slug' => $slug], ['name' => $name, 'type' => $slug === Attribute::SLUG_COLOR ? 'color' : 'text',
            'is_filterable' => true, 'allow_custom_value' => true, 'is_use_in_variations' => in_array($slug, [Attribute::SLUG_COLOR, Attribute::SLUG_SIZE], true)]);
        $value = mb_substr($value, 0, 255);
        $valueSlug = mb_substr(Str::slug($value), 0, 180).'-'.substr(sha1($value), 0, 12);
        $attributeValue = AttributeValue::firstOrCreate(['attribute_id' => $attribute->id, 'slug' => $valueSlug], ['value' => $value]);
        $product->attributes()->syncWithoutDetaching([$attribute->id => ['attribute_value_id' => $attributeValue->id, 'custom_value' => null]]);
    }
}
