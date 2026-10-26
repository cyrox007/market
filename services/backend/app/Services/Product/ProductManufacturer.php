<?php

namespace App\Services\Product;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** A remembered characteristic value, not a separate manufacturer entity. */
class ProductManufacturer
{
    public function attribute(): Attribute
    {
        $attribute = Attribute::firstOrCreate(['slug' => Attribute::SLUG_MANUFACTURER], [
            'name' => 'Производитель', 'type' => 'string', 'is_filterable' => true,
            'allow_custom_value' => true, 'is_multiple' => false, 'is_use_in_variations' => false,
        ]);
        if (! $attribute->is_filterable) {
            $attribute->update(['is_filterable' => true]);
        }

        return $attribute;
    }

    public function options(): array
    {
        $values = AttributeValue::whereHas('attribute', fn ($q) => $q->where('slug', Attribute::SLUG_MANUFACTURER))
            ->orderBy('value')->get()->map(fn ($v) => ['id' => $v->id, 'name' => $v->value, 'slug' => $v->slug]);
        $attributeId = Attribute::where('slug', Attribute::SLUG_MANUFACTURER)->value('id');
        foreach (['product_product_attributes'] as $table) {
            foreach (DB::table($table)->where('attribute_id', $attributeId)->whereNotNull('custom_value')->distinct()->pluck('custom_value') as $name) {
                if (trim($name) !== '') {
                    $values->push(['id' => null, 'name' => trim($name), 'slug' => Str::slug(trim($name)) ?: 'value-'.substr(sha1(mb_strtolower(trim($name))), 0, 12)]);
                }
            }
        }

        return $values->unique(fn ($v) => mb_strtolower($v['name']))->sortBy('name')->values()->all();
    }

    public function value(Product $product): ?string
    {
        $product->loadMissing('manufacturerAttributes.values');
        $row = $product->manufacturerAttributes->first();
        if ($row) {
            return $row->values->firstWhere('id', $row->pivot->attribute_value_id)?->value ?? $row->pivot->custom_value;
        }
        if ($product->parent_product_id && $product->parentProduct) {
            return $this->value($product->parentProduct);
        }

        return $product->manufacturer?->name;
    }

    public function save(Product $product, ?string $name): void
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));
        validator(['manufacturer_name' => $name], ['manufacturer_name' => 'nullable|string|max:255'])->validate();
        DB::transaction(function () use ($product, $name): void {
            $attribute = $this->attribute();
            foreach (['product_product_attributes'] as $table) {
                DB::table($table)->where('product_id', $product->id)->where('attribute_id', $attribute->id)->delete();
            }
            if ($name !== '') {
                $slug = mb_substr(Str::slug($name), 0, 240) ?: 'manufacturer-'.substr(sha1(mb_strtolower($name)), 0, 16);
                $value = AttributeValue::where('attribute_id', $attribute->id)->get()
                    ->first(fn ($v) => mb_strtolower($v->value) === mb_strtolower($name));
                if (! $value) {
                    if (AttributeValue::where('attribute_id', $attribute->id)->where('slug', $slug)->exists()) {
                        $slug .= '-'.substr(sha1($name), 0, 10);
                    }
                    $value = AttributeValue::firstOrCreate(['attribute_id' => $attribute->id, 'slug' => $slug], ['value' => $name]);
                }
                DB::table('product_product_attributes')->insert([
                    'product_id' => $product->id, 'attribute_id' => $attribute->id, 'attribute_value_id' => $value->id,
                    'custom_value' => null, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            // Do not let the obsolete entity resurrect a cleared value.
            $product->forceFill(['manufacturer_id' => null])->saveQuietly();
        });
        $product->unsetRelation('manufacturerAttributes')->unsetRelation('variantManufacturerAttributes')->unsetRelation('attributeValues')->unsetRelation('manufacturer');
        Product::flushAllProductCaches();
    }

    public function filter(Builder $query, string $name): Builder
    {
        $attributeId = Attribute::where('slug', Attribute::SLUG_MANUFACTURER)->value('id');
        return $query->where(function ($q) use ($attributeId, $name): void {
            foreach (['product_product_attributes'] as $table) {
                $q->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from($table.' as manufacturer_row')
                    ->leftJoin('product_attribute_values as manufacturer_value', 'manufacturer_value.id', '=', 'manufacturer_row.attribute_value_id')
                    ->where(fn ($p) => $p->whereColumn('manufacturer_row.product_id', 'products.id')
                        ->orWhereIn('manufacturer_row.product_id', DB::table('products as manufacturer_sku')->select('manufacturer_sku.id')->whereColumn('manufacturer_sku.parent_product_id', 'products.id')))
                    ->where('manufacturer_row.attribute_id', $attributeId)
                    ->where(fn ($v) => $v->where('manufacturer_value.value', $name)->orWhere('manufacturer_row.custom_value', $name)));
            }
        });
    }
}
