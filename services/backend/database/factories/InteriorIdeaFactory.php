<?php

namespace Database\Factories;

use App\Models\Page\InteriorIdea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Page\InteriorIdea>
 */
class InteriorIdeaFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = InteriorIdea::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
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
