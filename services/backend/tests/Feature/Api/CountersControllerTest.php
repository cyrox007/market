<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Единый эндпоинт счётчиков шапки: корзина + избранное + сравнение одним запросом.
 */
class CountersControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_counters_zero_for_empty_session(): void
    {
        $response = $this->getJson('/api/v1/counters');

        $response->assertStatus(200)
            ->assertJson(['cart' => 0, 'wishlist' => 0, 'compare' => 0]);
    }

    public function test_counters_aggregate_wishlist_and_compare(): void
    {
        $products = Product::factory()->count(3)->create(['state' => 'active']);

        // В одной сессии: два товара в сравнение, один в избранное.
        $this->postJson("/api/v1/compare/{$products[0]->id}");
        $this->postJson("/api/v1/compare/{$products[1]->id}");
        $this->postJson("/api/v1/wishlist/{$products[2]->id}");

        $response = $this->getJson('/api/v1/counters');

        $response->assertStatus(200)
            ->assertJson(['cart' => 0, 'wishlist' => 1, 'compare' => 2]);
    }
}
