<?php

namespace Tests\Feature\Product;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use App\Services\Product\ProductVariationAttributeService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductVariationAttributeServiceTest extends TestCase
{
    public function test_variable_product_uses_generic_color_and_size_attributes(): void
    {
        [$color, $white] = $this->attributeWithValue(Attribute::SLUG_COLOR, 'Цвет', 'color', 'Белый', 'belyi', '#ffffff');
        [$size, $large] = $this->attributeWithValue(Attribute::SLUG_SIZE, 'Размер', 'select', 'L', 'l');

        $parent = Product::factory()->create(['is_variable' => true]);
        $variant = Product::factory()->create([
            'parent_product_id' => $parent->id,
            'color' => 'Устаревший цвет',
            'color_code' => '#000000',
            'length' => 210,
            'width' => 160,
            'height' => 90,
        ]);

        $this->attachVariantValue($variant, $color, $white);
        $this->attachVariantValue($variant, $size, $large);

        $service = app(ProductVariationAttributeService::class);

        $this->assertSame(['Белый'], $service->colors($parent)->pluck('value')->all());
        $this->assertSame(['L'], $service->sizes($parent)->pluck('value')->all());
        $this->assertSame('#ffffff', $service->colors($parent)->first()->color_code);
    }

    public function test_physical_dimensions_are_never_exposed_as_commercial_size(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        Product::factory()->create([
            'parent_product_id' => $parent->id,
            'length' => 280,
            'width' => 180,
            'height' => 100,
        ]);

        $sizes = app(ProductVariationAttributeService::class)->sizes($parent);

        $this->assertTrue($sizes->isEmpty());
    }

    public function test_selected_variant_values_come_from_generic_pivot_not_legacy_columns(): void
    {
        [$color, $white] = $this->attributeWithValue(Attribute::SLUG_COLOR, 'Цвет', 'color', 'Белый', 'belyi', '#ffffff');
        [$size, $large] = $this->attributeWithValue(Attribute::SLUG_SIZE, 'Размер', 'select', 'L', 'l');

        $parent = Product::factory()->create(['is_variable' => true]);
        $variant = Product::factory()->create([
            'parent_product_id' => $parent->id,
            'color' => 'Красный legacy',
            'color_code' => '#ff0000',
            'length' => 210,
            'width' => 160,
        ]);
        $this->attachVariantValue($variant, $color, $white);
        $this->attachVariantValue($variant, $size, $large);

        $selected = app(ProductVariationAttributeService::class)->selectedForVariant($variant);

        $this->assertSame('Белый', $selected['color']['name']);
        $this->assertSame('#ffffff', $selected['color']['code']);
        $this->assertSame('L', $selected['size']['name']);
        $this->assertNotSame('210x160', $selected['size']['value']);
    }

    public function test_custom_variation_size_is_supported(): void
    {
        $size = Attribute::create([
            'name' => 'Размер',
            'slug' => Attribute::SLUG_SIZE,
            'type' => 'select',
            'is_filterable' => true,
            'is_use_in_variations' => true,
            'allow_custom_value' => true,
            'sort_order' => 1,
        ]);

        $parent = Product::factory()->create(['is_variable' => true]);
        $variant = Product::factory()->create(['parent_product_id' => $parent->id]);

        DB::table('product_variant_attributes')->insert([
            'product_id' => $variant->id,
            'attribute_id' => $size->id,
            'attribute_value_id' => null,
            'custom_value' => 'XXL Tall',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sizes = app(ProductVariationAttributeService::class)->sizes($parent);

        $this->assertSame('XXL Tall', $sizes->first()->value);
        $this->assertSame('xxl-tall', $sizes->first()->slug);
    }

    private function attributeWithValue(
        string $slug,
        string $name,
        string $type,
        string $value,
        string $valueSlug,
        ?string $colorCode = null,
    ): array {
        $attribute = Attribute::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'is_filterable' => true,
            'is_use_in_variations' => true,
            'allow_custom_value' => false,
            'sort_order' => 0,
        ]);

        $attributeValue = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => $value,
            'slug' => $valueSlug,
            'color_code' => $colorCode,
            'sort_order' => 0,
        ]);

        return [$attribute, $attributeValue];
    }

    private function attachVariantValue(Product $variant, Attribute $attribute, AttributeValue $value): void
    {
        DB::table('product_variant_attributes')->insert([
            'product_id' => $variant->id,
            'attribute_id' => $attribute->id,
            'attribute_value_id' => $value->id,
            'custom_value' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
