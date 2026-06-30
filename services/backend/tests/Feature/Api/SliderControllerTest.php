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
}


