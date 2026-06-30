<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompareControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_compare_list(): void
    {
        $response = $this->getJson('/api/v1/compare');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'products',
                'count',
            ]);
    }

    public function test_can_add_product_to_compare(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
        ]);

        $response = $this->postJson("/api/v1/compare/{$product->id}");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'count',
            ]);
    }

    public function test_cannot_add_duplicate_product_to_compare(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
        ]);

        $this->postJson("/api/v1/compare/{$product->id}");

        $response = $this->postJson("/api/v1/compare/{$product->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Товар уже в списке сравнения',
            ]);
    }

    public function test_cannot_add_more_than_five_products_to_compare(): void
    {
        $products = Product::factory()->count(6)->create([
            'state' => 'active',
        ]);

        foreach ($products->take(5) as $product) {
            $this->postJson("/api/v1/compare/{$product->id}");
        }

        $response = $this->postJson("/api/v1/compare/{$products[5]->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Максимум 5 товаров для сравнения',
            ]);
    }

    public function test_can_remove_product_from_compare(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
        ]);

        $this->postJson("/api/v1/compare/{$product->id}");

        $response = $this->deleteJson("/api/v1/compare/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'count',
            ]);
    }

    public function test_can_clear_compare_list(): void
    {
        $products = Product::factory()->count(3)->create([
            'state' => 'active',
        ]);

        foreach ($products as $product) {
            $this->postJson("/api/v1/compare/{$product->id}");
        }

        $response = $this->deleteJson('/api/v1/compare');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Список сравнения очищен',
            ]);

        $compareResponse = $this->getJson('/api/v1/compare');
        $compareResponse->assertJson(['count' => 0]);
    }

    public function test_can_get_compare_count(): void
    {
        $products = Product::factory()->count(2)->create([
            'state' => 'active',
        ]);

        foreach ($products as $product) {
            $this->postJson("/api/v1/compare/{$product->id}");
        }

        $response = $this->getJson('/api/v1/compare/count');

        $response->assertStatus(200)
            ->assertJson(['count' => 2]);
    }
}
