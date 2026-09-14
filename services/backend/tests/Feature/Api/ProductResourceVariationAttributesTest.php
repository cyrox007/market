<?php

namespace Tests\Feature\Api;

use App\Http\Resources\ProductResource;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductResourceVariationAttributesTest extends TestCase
{
    public function test_resource_uses_canonical_variation_color_and_size_and_keeps_dimensions_as_specs(): void
    {
        $color = Attribute::create([
            'name' => 'Цвет',
            'slug' => Attribute::SLUG_COLOR,
            'type' => 'color',
            'is_filterable' => true,
            'is_use_in_variations' => true,
            'allow_custom_value' => false,
            'sort_order' => 0,
        ]);
        $white = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Белый',
            'slug' => 'belyi',
            'color_code' => '#ffffff',
            'sort_order' => 0,
        ]);

        $size = Attribute::create([
            'name' => 'Размер',
            'slug' => Attribute::SLUG_SIZE,
            'type' => 'select',
            'is_filterable' => true,
            'is_use_in_variations' => true,
            'allow_custom_value' => false,
            'sort_order' => 1,
        ]);
        $large = AttributeValue::create([
            'attribute_id' => $size->id,
            'value' => 'L',
            'slug' => 'l',
            'sort_order' => 0,
        ]);

        $parent = Product::factory()->create([
            'is_variable' => true,
            'price' => 9999,
            'length' => 300,
            'width' => 200,
            'height' => 100,
        ]);
        $variant = Product::factory()->create([
            'parent_product_id' => $parent->id,
            'price' => 5000,
            'stock' => 1,
            'color' => 'Legacy red',
            'color_code' => '#ff0000',
            'length' => 210,
            'width' => 160,
            'height' => 90,
        ]);

        foreach ([[$color, $white], [$size, $large]] as [$attribute, $value]) {
            DB::table('product_variant_attributes')->insert([
                'product_id' => $variant->id,
                'attribute_id' => $attribute->id,
                'attribute_value_id' => $value->id,
                'custom_value' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $parent->load('variants');
        $data = (new ProductResource($parent))->toArray(Request::create('/api/v1/products/test', 'GET'));

        $this->assertSame('Белый', $data['default_color']['name']);
        $this->assertSame('#ffffff', $data['default_color']['code']);
        $this->assertSame('L', $data['default_size']['name']);
        $this->assertSame('L', $data['default_size']['value']);
        $this->assertSame('300 x 200 x 100 см', $data['specifications']['dimensions']);
        $this->assertSame('Белый', $data['colors'][0]['name']);
        $this->assertSame('#ffffff', $data['colors'][0]['code']);
        $this->assertNotSame('210 x 160 см', $data['default_size']['name']);
    }

    public function test_resource_does_not_invent_size_when_only_dimensions_exist(): void
    {
        $parent = Product::factory()->create(['is_variable' => true]);
        Product::factory()->create([
            'parent_product_id' => $parent->id,
            'price' => 5000,
            'length' => 210,
            'width' => 160,
            'height' => 90,
        ]);

        $parent->load('variants');
        $data = (new ProductResource($parent))->toArray(Request::create('/api/v1/products/test', 'GET'));

        $this->assertNull($data['default_size']);
    }
}
