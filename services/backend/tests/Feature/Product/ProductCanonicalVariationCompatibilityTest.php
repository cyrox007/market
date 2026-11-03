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
            'length' => 999,
            'width' => 888,
        ]);
        $blueKing = Product::factory()->create([
            'state' => Product::ACTIVE,
            'parent_product_id' => $parent->id,
            'length' => 160,
            'width' => 200,
        ]);

        $this->attach($grayQueen, $size, $queen);
        $this->attach($blueKing, $size, $king);

        $sizes = $parent->getAvailableSizes();

        $this->assertEqualsCanonicalizing(['160x200', '180x200'], $sizes->pluck('slug')->all());
        $this->assertFalse($sizes->pluck('value')->contains('999x888'));

        $this->assertTrue($parent->getVariantByVariationAttributes([Attribute::SLUG_SIZE => '160x200'])->is($grayQueen));
        $this->assertTrue($parent->getVariantByVariationAttributes([Attribute::SLUG_SIZE => '180x200'])->is($blueKing));
    }

    public function test_simple_product_helpers_use_regular_canonical_attributes_not_dimensions(): void
    {
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
            'length' => 100,
            'width' => 50,
            'height' => 40,
        ]);
        $product->attributes()->attach($size->id, ['attribute_value_id' => $compact->id]);

        $this->assertSame(['compact'], $product->getAvailableSizes()->pluck('slug')->all());
        $this->assertFalse($product->getAvailableSizes()->pluck('value')->contains('100x50'));
    }

    private function attach(Product $variant, Attribute $attribute, AttributeValue $value): void
    {
        DB::table('product_product_attributes')->insert([
            'product_id' => $variant->id,
            'attribute_id' => $attribute->id,
            'attribute_value_id' => $value->id,
            'custom_value' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
