<?php

namespace Database\Factories;

use App\Models\News\NewsCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\News\NewsCategory>
 */
class NewsCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = NewsCategory::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(2, true),
            'slug' => fake()->slug(),
            'parent_id' => null,
            'description' => fake()->sentence(),
            'is_active' => true,
            'priority' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}

