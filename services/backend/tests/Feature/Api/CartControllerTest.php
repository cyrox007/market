<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_cart(): void
    {
        $response = $this->getJson('/api/v1/cart');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items',
                'subtotal',
                'item_count',
                'is_empty',
            ]);
    }

    public function test_can_add_product_to_cart(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'parent_product_id' => null,
        ]);

        $response = $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'item' => ['id', 'product_id', 'quantity', 'price'],
                'message',
            ]);
    }

    public function test_can_add_variant_to_cart(): void
    {
        // Создаем вариативный товар
        $parentProduct = Product::factory()->create([
            'state' => 'active',
            'is_variable' => true,
            'parent_product_id' => null,
        ]);

        // Создаем вариацию
        $variant = Product::factory()->create([
            'parent_product_id' => $parentProduct->id,
            'color' => 'Серый',
            'color_code' => '#808080',
            'length' => 280,
            'width' => 180,
            'state' => 'active',
            'price' => 1500,
            'stock' => 10,
        ]);

        // Пытаемся добавить вариативный товар без параметров - должна быть ошибка
        $response = $this->postJson('/api/v1/cart', [
            'product_id' => $parentProduct->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Для вариативного товара необходимо указать variation_attributes',
            ]);

        // Добавляем саму вариацию по ID (актуальный контракт API)
        $response = $this->postJson('/api/v1/cart', [
            'product_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'item' => ['id', 'product_id', 'quantity', 'price'],
                'message',
            ])
            ->assertJson([
                'item' => [
                    'product_id' => $variant->id, // Должна быть добавлена вариация
                ],
            ]);
    }

    public function test_cannot_add_variant_with_invalid_parameters(): void
    {
        // Создаем вариативный товар
        $parentProduct = Product::factory()->create([
            'state' => 'active',
            'is_variable' => true,
            'parent_product_id' => null,
        ]);

        // Создаем вариацию
        Product::factory()->create([
            'parent_product_id' => $parentProduct->id,
            'color' => 'Серый',
            'length' => 280,
            'width' => 180,
            'state' => 'active',
        ]);

        // Пытаемся добавить с несуществующими параметрами
        $response = $this->postJson('/api/v1/cart', [
            'product_id' => $parentProduct->id,
            'quantity' => 1,
            'color' => 'Несуществующий цвет',
            'size' => '999x999',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Для вариативного товара необходимо указать variation_attributes',
            ]);
    }

    public function test_can_update_cart_item(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'parent_product_id' => null,
        ]);
        $addResponse = $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // Проверяем, что товар успешно добавлен
        $addResponse->assertStatus(201)
            ->assertJsonStructure([
                'item' => ['id', 'product_id', 'quantity', 'price'],
            ]);

        // Получаем ID из ответа
        $itemId = $addResponse->json('item.id');

        // Если ID не получен из ответа, получаем его из корзины
        if (!$itemId) {
            $cartResponse = $this->getJson('/api/v1/cart');
            $items = $cartResponse->json('items');
            $this->assertNotEmpty($items, 'Cart should have items');
            $itemId = $items[0]['id'];
        }

        $this->assertNotNull($itemId, 'Item ID should not be null');

        $response = $this->putJson("/api/v1/cart/{$itemId}", [
            'quantity' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'item' => ['quantity' => 3],
            ]);
    }

    public function test_can_remove_item_from_cart(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'parent_product_id' => null,
        ]);
        $addResponse = $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $addResponse->assertStatus(201);

        // Получаем ID из ответа или из корзины
        $itemId = $addResponse->json('item.id');
        if (!$itemId) {
            $cartResponse = $this->getJson('/api/v1/cart');
            $items = $cartResponse->json('items');
            $this->assertNotEmpty($items, 'Cart should have items');
            $itemId = $items[0]['id'];
        }

        $response = $this->deleteJson("/api/v1/cart/{$itemId}");

        $response->assertStatus(200);
    }

    public function test_can_clear_cart(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'parent_product_id' => null,
        ]);

        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->deleteJson('/api/v1/cart');

        $response->assertStatus(200);

        $cartResponse = $this->getJson('/api/v1/cart');
        $cartResponse->assertJson(['is_empty' => true]);
    }

    public function test_can_get_cart_count(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'parent_product_id' => null,
        ]);

        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->getJson('/api/v1/cart/count');

        // Cart::itemCount() возвращает общее количество единиц товара (quantity), а не количество позиций
        // Если добавили товар с quantity=2, то count=2
        $response->assertStatus(200)
            ->assertJson(['count' => 2]);
    }

    public function test_can_get_cart_with_region_id_returns_structure_and_subtotal(): void
    {
        $location = ShippingLocation::factory()->create([
            'type' => 'locality',
            'name' => 'Москва',
            'slug' => 'moscow',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'state' => 'active',
            'price' => 1000,
            'parent_product_id' => null,
        ]);

        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertStatus(201);

        $response = $this->getJson('/api/v1/cart?region_id=' . $location->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items',
                'subtotal',
                'item_count',
                'is_empty',
            ])
            ->assertJson(['is_empty' => false]);

        $this->assertIsArray($response->json('items'));
        $this->assertGreaterThanOrEqual(1, count($response->json('items')));
        $this->assertGreaterThan(0, (float) $response->json('subtotal'));
    }
}
