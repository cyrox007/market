<?php

namespace Database\Factories;

use App\Models\Page\InteriorIdea;
use App\Models\Page\InteriorIdeaHotspot;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Page\InteriorIdeaHotspot>
 */
class InteriorIdeaHotspotFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = InteriorIdeaHotspot::class;

    public function definition(): array
    {
        return [
            'interior_idea_id' => InteriorIdea::factory(),
            'product_id' => Product::factory(),
            'x' => fake()->randomFloat(2, 0, 100),
            'y' => fake()->randomFloat(2, 0, 100),
            'priority' => fake()->numberBetween(0, 100),
        ];
    }
}
