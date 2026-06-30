<?php

namespace Database\Factories;

use App\Models\Shipping\ShippingLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shipping\ShippingLocation>
 */
class ShippingLocationFactory extends Factory
{
    protected $model = ShippingLocation::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->city(),
            'slug' => fake()->unique()->slug(),
            'code' => fake()->optional()->numerify('##'),
            'type' => fake()->randomElement(['federal_district', 'region', 'locality']),
            'location_type' => fake()->optional()->randomElement(['city', 'town', 'village']),
            'postal_code' => fake()->optional()->postcode(),
            'delivery_price' => fake()->randomFloat(2, 0, 2000),
            'free_delivery_threshold' => fake()->optional()->randomFloat(2, 10000, 50000),
            'delivery_days_min' => fake()->numberBetween(1, 3),
            'delivery_days_max' => fake()->numberBetween(3, 7),
            'requires_assembly' => fake()->boolean(),
            'assembly_price' => fake()->optional()->randomFloat(2, 0, 5000),
            'assembly_days' => fake()->optional()->numberBetween(1, 3),
            'min_order_amount' => fake()->optional()->randomFloat(2, 0, 5000),
            'max_order_weight' => fake()->optional()->randomFloat(2, 0, 1000),
            'max_order_volume' => fake()->optional()->randomFloat(2, 0, 10),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
