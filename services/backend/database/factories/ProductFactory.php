<?php

namespace Database\Factories;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product\Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->slug(),
            'sku' => fake()->unique()->numerify('SKU-#####'),
            'price' => fake()->randomFloat(2, 1000, 50000),
            'original_price' => null,
            'state' => 'active',
            'description' => fake()->paragraph(),
            'excerpt' => fake()->sentence(),
            'stock' => fake()->numberBetween(0, 100),
            'backorder' => false,
            'units_sold' => fake()->numberBetween(0, 1000),
        ];
    }

    public function onSale(): static
    {
        return $this->state(fn(array $attributes) => [
            'original_price' => $attributes['price'] * 1.3,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn(array $attributes) => [
            'units_sold' => fake()->numberBetween(100, 1000),
        ]);
    }
}
