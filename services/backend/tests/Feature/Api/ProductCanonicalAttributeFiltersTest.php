<?php

namespace Tests\Feature\Api;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCanonicalAttributeFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_filters_color_and_commercial_size_through_canonical_variant_attributes(): void
    {
        [$color, $gray] = $this->attributeWithValue(Attribute::SLUG_COLOR, 'Цвет', 'Серый', 'seryi', 'color', '#808080');
        [, $blue] = $this->attributeWithValue(Attribute::SLUG_COLOR, 'Цвет', 'Синий', 'sinii', 'color', '#0000ff');
        [$size, $queen] = $this->attributeWithValue(Attribute::SLUG_SIZE, 'Размер', '160x200', '160x200');
        [, $king] = $this->attributeWithValue(Attribute::SLUG_SIZE, 'Размер', '180x200', '180x200');

        $expected = Product::factory()->create([
            'name' => 'Expected',
            'slug' => 'expected-canonical',
            'state' => Product::ACTIVE,
            'is_variable' => true,
            'parent_product_id' => null,
        ]);
        $expectedVariant = Product::factory()->create([
            'name' => 'Expected variant',
            'slug' => 'expected-variant',
            'state' => Product::ACTIVE,
            'parent_product_id' => $expected->id,
            'color' => 'Legacy Red',
            'length' => 999,
            'width' => 888,
        ]);
        $this->attachVariantValue($expectedVariant, $color, $gray);
        $this->attachVariantValue($expectedVariant, $size, $queen);

        $other = Product::factory()->create([
            'name' => 'Other',
            'slug' => 'other-canonical',
            'state' => Product::ACTIVE,
            'is_variable' => true,
            'parent_product_id' => null,
        ]);
        $otherVariant = Product::factory()->create([
            'name' => 'Other variant',
            'slug' => 'other-variant',
            'state' => Product::ACTIVE,
            'parent_product_id' => $other->id,
            'color' => 'Серый',
            'length' => 160,
            'width' => 200,
        ]);
        $this->attachVariantValue($otherVariant, $color, $blue);
        $this->attachVariantValue($otherVariant, $size, $king);

        $response = $this->getJson('/api/v1/products?colors=seryi&sizes=160x200');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$expected->id], $ids);
    }

    public function test_physical_dimensions_alone_never_match_commercial_size_filter(): void
    {
        $dimensionsOnly = Product::factory()->create([
            'name' => 'Dimensions only',
            'slug' => 'dimensions-only-filter',
            'state' => Product::ACTIVE,
            'parent_product_id' => null,
            'length' => 180,
            'width' => 90,
            'height' => 75,
        ]);

        $response = $this->getJson('/api/v1/products?sizes=180x90');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertFalse($ids->contains($dimensionsOnly->id));
    }

    public function test_product_show_selects_variant_by_canonical_color_and_size_query_params(): void
    {
        [$color, $gray] = $this->attributeWithValue(Attribute::SLUG_COLOR, 'Цвет', 'Серый', 'seryi', 'color', '#808080');
        [$size, $queen] = $this->attributeWithValue(Attribute::SLUG_SIZE, 'Размер', '160x200', '160x200');

        $parent = Product::factory()->create([
            'name' => 'Variable product',
            'slug' => 'variable-canonical-show',
            'state' => Product::ACTIVE,
            'is_variable' => true,
            'parent_product_id' => null,
        ]);
        $variant = Product::factory()->create([
            'name' => 'Gray Queen',
            'slug' => 'gray-queen-variant',
            'state' => Product::ACTIVE,
            'parent_product_id' => $parent->id,
            'color' => 'Wrong legacy color',
            'length' => 1,
            'width' => 2,
        ]);
        $this->attachVariantValue($variant, $color, $gray);
        $this->attachVariantValue($variant, $size, $queen);

        $response = $this->getJson('/api/v1/products/' . $parent->slug . '?color=seryi&size=160x200');

        $response->assertOk();
        $this->assertSame($variant->id, (int) $response->json('product.id'));
    }

    public function test_filter_metadata_uses_canonical_values_and_does_not_publish_dimensions_as_sizes(): void
    {
        [$color, $gray] = $this->attributeWithValue(Attribute::SLUG_COLOR, 'Цвет', 'Серый', 'seryi', 'color', '#808080');
        [$size, $queen] = $this->attributeWithValue(Attribute::SLUG_SIZE, 'Размер', 'Queen', 'queen');
        $category = Category::factory()->create(['slug' => 'canonical-meta-category']);

        $product = Product::factory()->create([
            'name' => 'Meta product',
            'slug' => 'meta-product',
            'state' => Product::ACTIVE,
            'parent_product_id' => null,
            'length' => 280,
            'width' => 180,
        ]);
        $product->taxons()->attach($category->id);
        $product->attributes()->attach($color->id, ['attribute_value_id' => $gray->id]);
        $product->attributes()->attach($size->id, ['attribute_value_id' => $queen->id]);

        $response = $this->getJson('/api/v1/products?category_id=' . $category->id);

        $response->assertOk();
        $colorSlugs = collect($response->json('meta.filters.colors'))->pluck('slug');
        $sizeValues = collect($response->json('meta.filters.sizes'))->pluck('value');

        $this->assertTrue($colorSlugs->contains('seryi'));
        $this->assertTrue($sizeValues->contains('Queen'));
        $this->assertFalse($sizeValues->contains('280x180'));
    }

    public function test_custom_commercial_size_can_filter_variable_product_by_slug(): void
    {
        $size = Attribute::create([
            'name' => 'Размер',
            'slug' => Attribute::SLUG_SIZE,
            'type' => 'select',
            'is_filterable' => true,
            'is_use_in_variations' => true,
            'allow_custom_value' => true,
        ]);

        $parent = Product::factory()->create([
            'name' => 'Custom size parent',
            'slug' => 'custom-size-parent',
            'state' => Product::ACTIVE,
            'is_variable' => true,
            'parent_product_id' => null,
        ]);
        $variant = Product::factory()->create([
            'name' => 'Custom size variant',
            'slug' => 'custom-size-variant',
            'state' => Product::ACTIVE,
            'parent_product_id' => $parent->id,
        ]);
        DB::table('product_variant_attributes')->insert([
            'product_id' => $variant->id,
            'attribute_id' => $size->id,
            'attribute_value_id' => null,
            'custom_value' => 'Special XL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/products?sizes=special-xl');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertTrue($ids->contains($parent->id));
    }

    /**
     * @return array{0: Attribute, 1: AttributeValue}
     */
    private function attributeWithValue(
        string $slug,
        string $name,
        string $value,
        string $valueSlug,
        string $type = 'select',
        ?string $colorCode = null,
    ): array {
        $attribute = Attribute::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'type' => $type,
                'is_filterable' => true,
                'is_use_in_variations' => true,
                'allow_custom_value' => $slug === Attribute::SLUG_SIZE,
            ],
        );
        $attribute->forceFill([
            'is_filterable' => true,
            'is_use_in_variations' => true,
            'allow_custom_value' => $slug === Attribute::SLUG_SIZE,
            'type' => $type,
        ])->saveQuietly();

        $attributeValue = AttributeValue::query()->create([
            'attribute_id' => $attribute->id,
            'value' => $value,
            'slug' => $valueSlug,
            'color_code' => $colorCode,
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
