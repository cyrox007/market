<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Page\Slider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSlidersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_slider_endpoint_returns_top_block_only(): void
    {
        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_MAIN,
            'title' => 'Верхний слайд',
            'priority' => 10,
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_BOTTOM,
            'slot' => Slider::SLOT_MAIN,
            'title' => 'Нижний баннер',
            'priority' => 10,
        ]);

        $response = $this->getJson('/api/v1/sliders');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Верхний слайд')
            ->assertJsonPath('data.0.placement', Slider::PLACEMENT_TOP);
    }

    public function test_slider_endpoint_filters_each_upper_position(): void
    {
        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_MAIN,
            'title' => 'Main',
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_RIGHT_TOP,
            'title' => 'Right top',
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_RIGHT_BOTTOM,
            'title' => 'Right bottom',
        ]);

        $this->getJson('/api/v1/sliders?placement=top&slot=right_top')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Right top')
            ->assertJsonPath('data.0.slot', Slider::SLOT_RIGHT_TOP);

        $this->getJson('/api/v1/sliders?placement=top&slot=right_bottom')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Right bottom')
            ->assertJsonPath('data.0.slot', Slider::SLOT_RIGHT_BOTTOM);
    }

    public function test_home_endpoint_returns_frontend_ready_three_part_upper_block(): void
    {
        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_MAIN,
            'title' => 'Top main 1',
            'priority' => 10,
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_MAIN,
            'title' => 'Top main 2',
            'priority' => 20,
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_RIGHT_TOP,
            'title' => 'Right top',
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_RIGHT_BOTTOM,
            'title' => 'Right bottom',
        ]);

        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_BOTTOM,
            'slot' => Slider::SLOT_MAIN,
            'title' => 'Bottom',
        ]);

        $response = $this->getJson('/api/v1/sliders/home');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.top.main')
            ->assertJsonPath('data.top.main.0.title', 'Top main 1')
            ->assertJsonPath('data.top.main.1.title', 'Top main 2')
            ->assertJsonPath('data.top.right_top.title', 'Right top')
            ->assertJsonPath('data.top.right_bottom.title', 'Right bottom')
            ->assertJsonCount(1, 'data.bottom')
            ->assertJsonPath('data.bottom.0.title', 'Bottom');
    }

    public function test_activating_new_fixed_right_card_disables_previous_one(): void
    {
        $old = Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_RIGHT_TOP,
            'title' => 'Старая карточка',
            'is_active' => true,
        ]);

        $new = Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_RIGHT_TOP,
            'title' => 'Новая карточка',
            'is_active' => true,
        ]);

        $this->assertFalse($old->fresh()->is_active);
        $this->assertTrue($new->fresh()->is_active);
    }

    public function test_external_image_urls_are_returned_when_no_media_is_uploaded(): void
    {
        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_BOTTOM,
            'slot' => Slider::SLOT_MAIN,
            'title' => 'Demo',
            'image_url' => '/home/banner-1.jpg',
            'mobile_image_url' => '/home/banner-1-mobile.jpg',
        ]);

        $response = $this->getJson('/api/v1/sliders?placement=bottom');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.image', '/home/banner-1.jpg')
            ->assertJsonPath('data.0.image_fullhd', '/home/banner-1.jpg')
            ->assertJsonPath('data.0.image_mobile', '/home/banner-1-mobile.jpg');
    }

    public function test_mobile_image_falls_back_to_desktop_image(): void
    {
        Slider::factory()->create([
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_MAIN,
            'title' => 'Fallback',
            'image_url' => '/home/hero-1.jpg',
            'mobile_image_url' => null,
        ]);

        $response = $this->getJson('/api/v1/sliders?placement=top&slot=main');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.image', '/home/hero-1.jpg')
            ->assertJsonPath('data.0.image_mobile', '/home/hero-1.jpg');
    }

    public function test_unknown_placement_and_slot_are_rejected(): void
    {
        $this->getJson('/api/v1/sliders?placement=unknown')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unknown slider placement.');

        $this->getJson('/api/v1/sliders?placement=top&slot=unknown')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unknown slider slot.');
    }
}
