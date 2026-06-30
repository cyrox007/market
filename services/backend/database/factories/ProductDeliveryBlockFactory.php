<?php

namespace Database\Factories;

use App\Models\Product\ProductDeliveryBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product\ProductDeliveryBlock>
 */
class ProductDeliveryBlockFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = ProductDeliveryBlock::class;

    public function definition(): array
    {
        $icons = ['ri-truck-line', 'ri-map-pin-line', 'ri-tools-line'];
        $colors = ['red', 'yellow', 'green'];
        $color = fake()->randomElement($colors);

        return [
            'title' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'icon' => fake()->randomElement($icons),
            'icon_color' => "{$color}-600",
            'bg_color' => "{$color}-100",
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
