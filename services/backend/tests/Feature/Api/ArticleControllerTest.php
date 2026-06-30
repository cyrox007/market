<?php

namespace Tests\Feature\Api;

use App\Models\News\Article;
use App\Models\News\NewsCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_articles(): void
    {
        Article::factory()->count(5)->create([
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/articles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'slug', 'excerpt'],
                ],
            ]);
    }

    public function test_can_get_article_details(): void
    {
        $article = Article::factory()->create([
            'slug' => 'test-article',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/articles/{$article->slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'article' => [
                    'id',
                    'title',
                    'slug',
                    'excerpt',
                    'content',
                    'full_path',
                    'category',
                    'author',
                ],
            ]);
    }

    public function test_can_filter_articles_by_category(): void
    {
        $category = NewsCategory::factory()->create();
        Article::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/articles?category_id={$category->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}

