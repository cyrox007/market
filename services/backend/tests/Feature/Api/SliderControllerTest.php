<?php

namespace Tests\Feature\Api;

use App\Models\Page\Slider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SliderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_sliders(): void
    {
        Slider::factory()->count(5)->create([
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/sliders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'description', 'link', 'button_text', 'slug'],
                ],
            ]);
    }

    public function test_can_get_slider_details(): void
    {
        $slider = Slider::factory()->create([
            'slug' => 'test-slider',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/sliders/{$slider->slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'slider' => [
                    'id',
                    'title',
                    'description',
                    'link',
                    'button_text',
                    'slug',
                    'full_path',
                ],
            ]);
    }

    public function test_slider_description_is_returned_as_plain_text_for_home_components(): void
    {
        Slider::factory()->create([
            'description' => '<p>Первая строка<br>Вторая &amp; третья</p>',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/sliders')
            ->assertOk()
            ->assertJsonPath('data.0.description', "Первая строка\nВторая & третья");
    }
}


