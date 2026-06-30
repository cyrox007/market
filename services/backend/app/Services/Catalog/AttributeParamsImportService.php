<?php

namespace App\Services\Catalog;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttributeParamsImportService
{
    public function __construct(
        private readonly AttributeParamsXlsxReader $reader,
    ) {
    }

    /**
     * Импортировать атрибуты и их значения.
     *
     * @return array{
     *     created_attributes: int,
     *     updated_attributes: int,
     *     created_values: int,
     *     updated_values: int
     * }
     */
    public function import(?string $path = null): array
    {
        $reader = $path !== null
            ? new AttributeParamsXlsxReader($path)
            : $this->reader;

        $attributes = $reader->read();

        $createdAttributes = 0;
        $updatedAttributes = 0;
        $createdValues = 0;
        $updatedValues = 0;

        DB::transaction(function () use ($attributes, &$createdAttributes, &$updatedAttributes, &$createdValues, &$updatedValues): void {
            foreach ($attributes as $attributeData) {
                $name = $attributeData['name'];
                if ($name === '') {
                    continue;
                }

                $slug = Str::slug($name, '_');

                /** @var Attribute $attribute */
                $attribute = Attribute::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'type' => 'text',
                        'is_filterable' => false,
                        'is_use_in_variations' => false,
                        'allow_custom_value' => true,
                        'sort_order' => 0,
                    ]
                );

                if ($attribute->wasRecentlyCreated) {
                    $createdAttributes++;
                } else {
                    $updatedAttributes++;
                }

                $sortOrder = 0;
                foreach ($attributeData['values'] as $value) {
                    $valueSlug = Str::slug($value, '_');
                    /** @var AttributeValue $attributeValue */
                    $attributeValue = AttributeValue::query()->updateOrCreate(
                        [
                            'attribute_id' => $attribute->id,
                            'slug' => $valueSlug,
                        ],
                        [
                            'value' => $value,
                            'sort_order' => $sortOrder++,
                        ]
                    );

                    if ($attributeValue->wasRecentlyCreated) {
                        $createdValues++;
                    } else {
                        $updatedValues++;
                    }
                }

                // Привязка к категориям по имени (type/subtype) — пока только best-effort.
                foreach ($attributeData['categories'] as $categoryInfo) {
                    $names = array_filter([
                        $categoryInfo['subtype'] ?? null,
                        $categoryInfo['type'] ?? null,
                    ]);
                    if ($names === []) {
                        continue;
                    }

                    $category = Category::query()
                        ->whereIn('name', $names)
                        ->first();

                    if ($category === null) {
                        continue;
                    }

                    $attribute->categories()->syncWithoutDetaching([$category->id]);
                }
            }
        });

        return [
            'created_attributes' => $createdAttributes,
            'updated_attributes' => $updatedAttributes,
            'created_values' => $createdValues,
            'updated_values' => $updatedValues,
        ];
    }
}

