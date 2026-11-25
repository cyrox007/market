<?php

namespace Tests\Unit;

use App\Http\Resources\ProductDetailResource;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProductSpecificationOrderTest extends TestCase
{
    public function test_variant_follows_weight_and_precedes_other_specifications(): void
    {
        $specifications = [
            ['name' => 'Ширина (мм)', 'value' => '1201', 'slug' => 'width'],
            ['name' => 'Вариант', 'value' => 'Белый текстурный', 'slug' => 'variant'],
            ['name' => 'Вес', 'value' => '25 кг', 'slug' => 'weight'],
            ['name' => 'Производитель', 'value' => 'Мебельный рай', 'slug' => 'proizvoditel'],
            ['name' => 'Размеры (ДxШxВ)', 'value' => '120 x 50 x 38 см', 'slug' => 'dimensions'],
            ['name' => 'Материал корпуса', 'value' => 'ЛДСП', 'slug' => 'material'],
        ];

        $resource = new ProductDetailResource(null);
        $method = new ReflectionMethod(ProductDetailResource::class, 'orderSpecifications');
        $ordered = $method->invoke($resource, $specifications);

        $this->assertSame(
            ['proizvoditel', 'dimensions', 'weight', 'variant', 'width', 'material'],
            array_column($ordered, 'slug')
        );
        $this->assertSame($specifications[1], $ordered[3]);

        $specifications[] = ['name' => 'Бренд', 'value' => 'Бренд товара', 'slug' => 'brand'];
        $ordered = $method->invoke($resource, $specifications);

        $this->assertSame(
            ['brand', 'proizvoditel', 'dimensions', 'weight', 'variant', 'width', 'material'],
            array_column($ordered, 'slug')
        );
    }
}
