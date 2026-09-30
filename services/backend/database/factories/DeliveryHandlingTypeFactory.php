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
            'requires_floor' => false,
            'max_floor' => null,
            'requires_elevator' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
