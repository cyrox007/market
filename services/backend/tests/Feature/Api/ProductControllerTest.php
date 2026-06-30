<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_products(): void
    {
        // Создаем только основные товары (не вариации)
        Product::factory()->count(5)->create([
            'state' => 'active',
            'parent_product_id' => null, // ВАЖНО: только основные товары
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'price', 'image'],
                ],
            ]);
    }

    public function test_can_filter_products_by_category(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->getJson('/api/v1/products?category_id=1');

        $response->assertStatus(200);
    }

    public function test_can_filter_products_by_colors(): void
    {
        // Создаем вариативный товар с вариациями разных цветов
        $parentProduct = Product::factory()->create([
            'state' => 'active',
            'is_variable' => true,
            'parent_product_id' => null,
        ]);

        // Создаем вариации с разными цветами
        Product::factory()->create([
            'parent_product_id' => $parentProduct->id,
            'color' => 'Серый',
            'color_code' => '#808080',
            'state' => 'active',
        ]);

        Product::factory()->create([
            'parent_product_id' => $parentProduct->id,
            'color' => 'Синий',
            'color_code' => '#0000FF',
            'state' => 'active',
        ]);

        $response = $this->getJson('/api/v1/products?colors=Серый');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);
    }

    public function test_can_filter_products_by_sizes(): void
    {
        // Создаем вариативный товар с вариациями разных размеров
        $parentProduct = Product::factory()->create([
            'state' => 'active',
            'is_variable' => true,
            'parent_product_id' => null,
        ]);

        // Создаем вариации с разными размерами
        Product::factory()->create([
            'parent_product_id' => $parentProduct->id,
            'length' => 280,
            'width' => 180,
            'state' => 'active',
        ]);

        $response = $this->getJson('/api/v1/products?sizes=280x180');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);
    }

    public function test_can_get_product_variant_by_color_and_size(): void
    {
        // Создаем вариативный товар
        $parentProduct = Product::factory()->create([
            'state' => 'active',
            'is_variable' => true,
            'parent_product_id' => null,
            'slug' => 'test-variable-product',
        ]);

        // Создаем вариацию
        $variant = Product::factory()->create([
            'parent_product_id' => $parentProduct->id,
            'color' => 'Серый',
            'color_code' => '#808080',
            'length' => 280,
            'width' => 180,
            'state' => 'active',
        ]);

        $response = $this->getJson("/api/v1/products/{$parentProduct->slug}?color=Серый&size=280x180");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'product' => [
                    'id',
                    'name',
                    'sku',
                    'price',
                ],
            ]);

        // Актуальный API возвращает родительский товар + список variants.
        $this->assertSame($parentProduct->id, (int) $response->json('product.id'));
        $variantIds = collect(data_get($response->json(), 'product.variants', []))->pluck('id')->all();
        $this->assertContains($variant->id, $variantIds);
    }

    public function test_can_search_products(): void
    {
        Product::factory()->create([
            'name' => 'Test Product',
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->getJson('/api/v1/products/search?q=Test');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_featured_products(): void
    {
        Product::factory()->count(3)->create([
            'state' => 'active',
            'units_sold' => 10,
            'parent_product_id' => null,
        ]);

        $response = $this->getJson('/api/v1/products/featured');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'price'],
                ],
            ]);
    }

    public function test_can_get_new_products(): void
    {
        Product::factory()->create([
            'state' => 'active',
            'created_at' => now(),
            'parent_product_id' => null,
        ]);

        $response = $this->getJson('/api/v1/products/new');

        $response->assertStatus(200);
    }

    public function test_can_get_sale_products(): void
    {
        Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'original_price' => 1500,
            'parent_product_id' => null,
        ]);

        $response = $this->getJson('/api/v1/products/sale');

        $response->assertStatus(200);
    }

    public function test_can_get_product_details(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'slug' => 'test-product',
            'parent_product_id' => null,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'product' => [
                    'id',
                    'name',
                    'slug',
                    'price',
                    'description',
                    'images',
                    'feature_blocks',
                    'delivery_blocks',
                ],
            ]);
    }

    public function test_product_details_includes_feature_blocks_from_category(): void
    {
        $category = \App\Models\Product\Category::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'slug' => 'test-product',
            'parent_product_id' => null,
        ]);
        $product->taxons()->attach($category->id);

        $featureBlock = \App\Models\Product\ProductFeatureBlock::factory()->create([
            'is_active' => true,
        ]);
        $category->featureBlocks()->attach($featureBlock->id);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('product.feature_blocks.0.id', $featureBlock->id)
            ->assertJsonPath('product.feature_blocks.0.title', $featureBlock->title);
    }

    public function test_product_details_includes_delivery_blocks_from_category(): void
    {
        $category = \App\Models\Product\Category::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'slug' => 'test-product',
            'parent_product_id' => null,
        ]);
        $product->taxons()->attach($category->id);

        $deliveryBlock = \App\Models\Product\ProductDeliveryBlock::factory()->create([
            'is_active' => true,
        ]);
        $category->deliveryBlocks()->attach($deliveryBlock->id);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('product.delivery_blocks.0.id', $deliveryBlock->id)
            ->assertJsonPath('product.delivery_blocks.0.title', $deliveryBlock->title);
    }

    public function test_product_details_prioritizes_product_blocks_over_category_blocks(): void
    {
        $category = \App\Models\Product\Category::factory()->create();
        $product = Product::factory()->create([
            'state' => 'active',
            'slug' => 'test-product',
            'parent_product_id' => null,
        ]);
        $product->taxons()->attach($category->id);

        $productBlock = \App\Models\Product\ProductFeatureBlock::factory()->create([
            'title' => 'Product Block',
            'is_active' => true,
        ]);
        $categoryBlock = \App\Models\Product\ProductFeatureBlock::factory()->create([
            'title' => 'Category Block',
            'is_active' => true,
        ]);

        $product->featureBlocks()->attach($productBlock->id);
        $category->featureBlocks()->attach($categoryBlock->id);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('product.feature_blocks.0.id', $productBlock->id)
            ->assertJsonPath('product.feature_blocks.0.title', 'Product Block');
    }

    public function test_can_get_related_products(): void
    {
        // Vanilo ProductState не содержит "published", используем валидное состояние
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);
        Product::factory()->count(3)->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/related");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);
    }

    public function test_related_products_prefers_manual_relations(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        // Товар из той же категории, который бы попал во fallback
        $fallbackProduct = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        // Явно сопутствующий товар
        $manualRelated = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        // Свяжем товары как сопутствующие через relation
        $product->relatedProducts()->syncWithoutDetaching([$manualRelated->id]);

        $response = $this->getJson("/api/v1/products/{$product->id}/related");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $manualRelated->id]);
    }

    public function test_related_products_fallbacks_when_no_manual_relations(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        // Нет ручных связей и категорий — создаем просто ещё один активный товар
        $other = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/related");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);
    }

    public function test_product_slug_must_be_unique(): void
    {
        Product::factory()->create([
            'state' => 'active',
            'slug' => 'unique-product-slug',
            'parent_product_id' => null,
        ]);

        $response = $this->getJson('/api/v1/products/unique-product-slug');
        $response->assertStatus(200)
            ->assertJsonPath('product.slug', 'unique-product-slug');

        // Попытка создать второй товар с тем же slug должна вызвать ошибку уникальности (на уровне БД)
        $this->expectException(\Illuminate\Database\QueryException::class);
        Product::factory()->create([
            'state' => 'active',
            'slug' => 'unique-product-slug',
            'parent_product_id' => null,
        ]);
    }
}
