<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_wishlist(): void
    {
        $response = $this->getJson('/api/v1/wishlist');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);
    }

    public function test_can_add_product_to_wishlist(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
        ]);

        $response = $this->postJson("/api/v1/wishlist/{$product->id}");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function test_cannot_add_duplicate_product_to_wishlist(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
        ]);

        $this->postJson("/api/v1/wishlist/{$product->id}");

        $response = $this->postJson("/api/v1/wishlist/{$product->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Товар уже в избранном',
            ]);
    }

    public function test_can_remove_product_from_wishlist(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
        ]);

        $this->postJson("/api/v1/wishlist/{$product->id}");

        $response = $this->deleteJson("/api/v1/wishlist/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function test_can_toggle_product_in_wishlist(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
        ]);

        $response = $this->postJson("/api/v1/wishlist/{$product->id}/toggle");
        $response->assertStatus(200)
            ->assertJson(['in_wishlist' => true]);

        $response = $this->postJson("/api/v1/wishlist/{$product->id}/toggle");
        $response->assertStatus(200)
            ->assertJson(['in_wishlist' => false]);
    }

    public function test_can_get_wishlist_count(): void
    {
        $products = Product::factory()->count(3)->create([
            'state' => 'active',
        ]);

        foreach ($products as $product) {
            $this->postJson("/api/v1/wishlist/{$product->id}");
        }

        $response = $this->getJson('/api/v1/wishlist/count');

        $response->assertStatus(200)
            ->assertJson(['count' => 3]);
    }
}
