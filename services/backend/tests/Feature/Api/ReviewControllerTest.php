<?php

namespace Tests\Feature\Api;

use App\Models\Product\Product;
use App\Models\Product\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_approved_reviews_for_product(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        // Создаем одобренные отзывы
        Review::factory()->count(3)->create([
            'product_id' => $product->id,
            'is_approved' => true,
        ]);

        // Создаем неодобренные отзывы (не должны отображаться)
        Review::factory()->count(2)->create([
            'product_id' => $product->id,
            'is_approved' => false,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'rating', 'comment', 'created_at'],
                ],
            ])
            ->assertJsonCount(3, 'data'); // Только одобренные отзывы
    }

    public function test_reviews_are_sorted_by_created_at_desc(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $oldReview = Review::factory()->create([
            'product_id' => $product->id,
            'is_approved' => true,
            'created_at' => now()->subDays(5),
        ]);

        $newReview = Review::factory()->create([
            'product_id' => $product->id,
            'is_approved' => true,
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Первый отзыв должен быть новее
        $this->assertEquals($newReview->id, $data[0]['id']);
        $this->assertEquals($oldReview->id, $data[1]['id']);
    }

    public function test_returns_empty_array_when_no_reviews(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews");

        $response->assertStatus(200)
            ->assertJson(['data' => []]);
    }

    public function test_returns_404_when_product_not_found(): void
    {
        $response = $this->getJson('/api/v1/products/99999/reviews');

        $response->assertStatus(404);
    }

    public function test_can_create_review_without_authentication(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $reviewData = [
            'name' => 'Иван Иванов',
            'email' => 'ivan@example.com',
            'rating' => 5,
            'comment' => 'Отличный товар! Очень доволен покупкой.',
        ];

        $response = $this->postJson("/api/v1/products/{$product->id}/reviews", $reviewData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'review' => ['id', 'name', 'rating', 'comment'],
            ]);

        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'name' => 'Иван Иванов',
            'email' => 'ivan@example.com',
            'rating' => 5,
            'is_approved' => false, // Новые отзывы требуют модерации
        ]);
    }

    public function test_can_create_review_without_email(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $reviewData = [
            'name' => 'Петр Петров',
            'rating' => 4,
            'comment' => 'Хороший товар, но есть небольшие недостатки.',
        ];

        $response = $this->postJson("/api/v1/products/{$product->id}/reviews", $reviewData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'name' => 'Петр Петров',
            'email' => null,
            'rating' => 4,
        ]);
    }

    public function test_validation_requires_name(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/reviews", [
            'rating' => 5,
            'comment' => 'Комментарий',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_validation_requires_rating(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/reviews", [
            'name' => 'Иван',
            'comment' => 'Комментарий',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_validation_rating_must_be_between_1_and_5(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/reviews", [
            'name' => 'Иван',
            'rating' => 6,
            'comment' => 'Комментарий',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_validation_requires_comment(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/reviews", [
            'name' => 'Иван',
            'rating' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_validation_comment_must_be_at_least_10_characters(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/reviews", [
            'name' => 'Иван',
            'rating' => 5,
            'comment' => 'Коротко',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_validation_email_must_be_valid(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/reviews", [
            'name' => 'Иван',
            'email' => 'invalid-email',
            'rating' => 5,
            'comment' => 'Достаточно длинный комментарий для валидации',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_product_rating_and_reviews_count_are_calculated_correctly(): void
    {
        $product = Product::factory()->create([
            'state' => 'active',
            'parent_product_id' => null,
        ]);

        // Создаем одобренные отзывы с разными рейтингами
        Review::factory()->create([
            'product_id' => $product->id,
            'rating' => 5,
            'is_approved' => true,
        ]);

        Review::factory()->create([
            'product_id' => $product->id,
            'rating' => 4,
            'is_approved' => true,
        ]);

        Review::factory()->create([
            'product_id' => $product->id,
            'rating' => 3,
            'is_approved' => true,
        ]);

        // Неодобренный отзыв не должен учитываться
        Review::factory()->create([
            'product_id' => $product->id,
            'rating' => 5,
            'is_approved' => false,
        ]);

        // Перезагружаем продукт для получения актуальных данных
        $product->refresh();

        // Средний рейтинг: (5 + 4 + 3) / 3 = 4.0
        $this->assertEquals(4.0, $product->rating);
        $this->assertEquals(3, $product->reviews_count);
    }
}


