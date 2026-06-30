<?php

namespace Database\Factories;

use App\Models\Shipping\DeliveryHandlingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shipping\DeliveryHandlingType>
 */
class DeliveryHandlingTypeFactory extends Factory
{
    protected $model = DeliveryHandlingType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'code' => fake()->unique()->slug(),
            'description' => fake()->optional()->sentence(),
            'requires_floor' => fake()->boolean(),
            'max_floor' => fake()->optional()->numberBetween(1, 20),
            'requires_elevator' => fake()->boolean(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
