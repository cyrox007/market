<?php

namespace Database\Factories\Shipping;

use App\Models\Shipping\Carrier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Carrier>
 */
class CarrierFactory extends Factory
{
    protected $model = Carrier::class;

    public function definition(): array
    {
        return [
            'name' => 'Carrier ' . $this->faker->unique()->company(),
        ];
    }
}
