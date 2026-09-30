<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Page\Slider;
use App\Models\Product\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSlidersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_slider_endpoint_keeps_returning_only_home_hero_items(): void
    {
        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_HOME_HERO,
            'title' => 'Второй',
            'priority' => 20,
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_HOME_HERO,
            'title' => 'Первый',
            'priority' => 10,
        ]);

        $category = Category::factory()->create([
            'name' => 'Диваны',
            'slug' => 'divany',
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_HOME_CATEGORIES,
            'category_id' => $category->id,
            'title' => 'Диваны',
            'priority' => 0,
        ]);

        Slider::factory()->inactive()->create([
            'placement' => Slider::PLACEMENT_HOME_HERO,
            'title' => 'Выключенный',
            'priority' => 0,
        ]);

        $response = $this->getJson('/api/v1/sliders');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Первый')
            ->assertJsonPath('data.1.title', 'Второй')
            ->assertJsonPath('data.0.placement', Slider::PLACEMENT_HOME_HERO);
    }

    public function test_category_carousel_endpoint_resolves_category_name_and_link(): void
    {
        $category = Category::factory()->create([
            'name' => 'Кухни',
            'slug' => 'kuhni',
            'priority' => 100,
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_HOME_CATEGORIES,
            'category_id' => $category->id,
            'title' => 'Техническое название',
            'link' => '/should-not-be-used',
            'priority' => 5,
        ]);

        $response = $this->getJson(
            '/api/v1/sliders?placement=' . Slider::PLACEMENT_HOME_CATEGORIES
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.placement', Slider::PLACEMENT_HOME_CATEGORIES)
            ->assertJsonPath('data.0.title', 'Кухни')
            ->assertJsonPath('data.0.link', '/catalog/kuhni')
            ->assertJsonPath('data.0.category.id', $category->id)
            ->assertJsonPath('data.0.category.name', 'Кухни');
    }

    public function test_home_endpoint_returns_both_managed_slider_blocks(): void
    {
        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_HOME_HERO,
            'title' => 'Главный слайд',
            'priority' => 0,
        ]);

        $category = Category::factory()->create([
            'name' => 'Спальни',
            'slug' => 'spalni',
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_HOME_CATEGORIES,
            'category_id' => $category->id,
            'title' => 'Спальни',
            'priority' => 0,
        ]);

        $response = $this->getJson('/api/v1/sliders/home');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.hero')
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.hero.0.title', 'Главный слайд')
            ->assertJsonPath('data.categories.0.title', 'Спальни');
    }

    public function test_unknown_slider_placement_is_rejected(): void
    {
        $this->getJson('/api/v1/sliders?placement=unknown')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unknown slider placement.');
    }
}
