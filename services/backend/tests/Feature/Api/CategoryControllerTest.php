<?php

namespace Tests\Feature\Api;

use App\Models\Product\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_categories(): void
    {
        Category::factory()->count(5)->create([
            'parent_id' => null,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'products_count'],
                ],
            ]);
    }

    public function test_can_get_category_details(): void
    {
        $category = Category::factory()->create([
            'slug' => 'test-category',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/categories/{$category->slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'category' => [
                    'id',
                    'name',
                    'slug',
                    'products_count',
                ],
            ]);
    }

    public function test_can_get_category_tree(): void
    {
        $parent = Category::factory()->create([
            'parent_id' => null,
            'is_active' => true,
        ]);

        Category::factory()->count(3)->create([
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/categories/tree');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tree' => [
                    '*' => [
                        'id',
                        'name',
                        'children',
                    ],
                ],
            ]);
    }
}
