<?php

namespace Database\Factories;

use App\Models\Mail\MailEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mail\MailEvent>
 */
class MailEventFactory extends Factory
{
    protected $model = MailEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'is_active' => true,
        ];
    }
}
