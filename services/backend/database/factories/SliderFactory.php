<?php

namespace Database\Factories;

use App\Models\Page\Slider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Page\Slider>
 */
class SliderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Slider::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'link' => fake()->url(),
            'button_text' => fake()->words(2, true),
            'badge_text' => 'Помощь онлайн',
            'badge_link' => null,
            'badge_icon' => 'ri-flashlight-fill',
            'is_active' => true,
            'priority' => fake()->numberBetween(0, 100),
            'slug' => fake()->slug(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}


