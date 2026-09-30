<?php

namespace Tests\Feature\Product;

use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCanonicalVariationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_named_helpers_are_backed_by_canonical_attributes_only(): void
    {
        $color = Attribute::create([
            'name' => 'Цвет',
            'slug' => Attribute::SLUG_COLOR,
            'type' => 'color',
            'is_filterable' => true,
            'is_use_in_variations' => true,
        ]);
        $gray = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Серый',
            'slug' => 'seryi',
            'color_code' => '#808080',
        ]);
        $blue = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Синий',
            'slug' => 'sinii',
            'color_code' => '#0000ff',
        ]);

        $size = Attribute::create([
            'name' => 'Размер',
            'slug' => Attribute::SLUG_SIZE,
            'type' => 'select',
            'is_filterable' => true,
            'is_use_in_variations' => true,
            'allow_custom_value' => true,
        ]);
        $queen = AttributeValue::create([
            'attribute_id' => $size->id,
            'value' => '160x200',
            'slug' => '160x200',
        ]);
        $king = AttributeValue::create([
            'attribute_id' => $size->id,
            'value' => '180x200',
            'slug' => '180x200',
        ]);

        $parent = Product::factory()->create([
            'state' => Product::ACTIVE,
            'is_variable' => true,
            'parent_product_id' => null,
        ]);
        $grayQueen = Product::factory()->create([
            'state' => Product::ACTIVE,
            'parent_product_id' => $parent->id,
            'color' => 'Legacy Red',
            'color_code' => '#ff0000',
            'length' => 999,
            'width' => 888,
        ]);
        $blueKing = Product::factory()->create([
            'state' => Product::ACTIVE,
            'parent_product_id' => $parent->id,
            'color' => 'Legacy Green',
            'color_code' => '#00ff00',
            'length' => 160,
            'width' => 200,
        ]);

        $this->attach($grayQueen, $color, $gray);
        $this->attach($grayQueen, $size, $queen);
        $this->attach($blueKing, $color, $blue);
        $this->attach($blueKing, $size, $king);

        $colors = $parent->getAvailableColors();
        $sizes = $parent->getAvailableSizes();

        $this->assertEqualsCanonicalizing(['seryi', 'sinii'], $colors->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(['160x200', '180x200'], $sizes->pluck('slug')->all());
        $this->assertFalse($colors->pluck('value')->contains('Legacy Red'));
        $this->assertFalse($sizes->pluck('value')->contains('999x888'));

        $this->assertTrue($parent->getVariantByAttributes('seryi', '160x200')->is($grayQueen));
        $this->assertTrue($parent->getVariantByAttributes('Синий', '180x200')->is($blueKing));

        $sizesForGray = $parent->getAvailableSizesForColor('seryi');
        $colorsForQueen = $parent->getAvailableColorsForSize('160x200');

        $this->assertSame(['160x200'], $sizesForGray->pluck('slug')->all());
        $this->assertSame(['seryi'], $colorsForQueen->pluck('slug')->all());
    }

    public function test_simple_product_helpers_use_regular_canonical_attributes_not_dimensions_or_legacy_color(): void
    {
        $color = Attribute::create([
            'name' => 'Цвет',
            'slug' => Attribute::SLUG_COLOR,
            'type' => 'color',
            'is_filterable' => true,
            'is_use_in_variations' => true,
        ]);
        $gray = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Серый',
            'slug' => 'seryi',
            'color_code' => '#808080',
        ]);
        $size = Attribute::create([
            'name' => 'Размер',
            'slug' => Attribute::SLUG_SIZE,
            'type' => 'select',
            'is_filterable' => true,
            'is_use_in_variations' => true,
        ]);
        $compact = AttributeValue::create([
            'attribute_id' => $size->id,
            'value' => 'Compact',
            'slug' => 'compact',
        ]);

        $product = Product::factory()->create([
            'is_variable' => false,
            'parent_product_id' => null,
            'color' => 'Legacy Black',
            'length' => 100,
            'width' => 50,
            'height' => 40,
        ]);
        $product->attributes()->attach($color->id, ['attribute_value_id' => $gray->id]);
        $product->attributes()->attach($size->id, ['attribute_value_id' => $compact->id]);

        $this->assertSame(['seryi'], $product->getAvailableColors()->pluck('slug')->all());
        $this->assertSame(['compact'], $product->getAvailableSizes()->pluck('slug')->all());
        $this->assertFalse($product->getAvailableSizes()->pluck('value')->contains('100x50'));
    }

    private function attach(Product $variant, Attribute $attribute, AttributeValue $value): void
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
