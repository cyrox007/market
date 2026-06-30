<?php

namespace Database\Factories;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order\Order>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::NEW ->value,
            'subtotal' => fake()->randomFloat(2, 1000, 50000),
            'total' => fn(array $attributes) => $attributes['subtotal'] + 500,
            'delivery_cost' => 500,
            'assembly_cost' => 0,
            'payment_method' => fake()->randomElement(['card', 'cash', 'installment']),
            'delivery_type' => fake()->randomElement(['delivery', 'pickup']),
            'delivery_date' => fake()->dateTimeBetween('+1 day', '+7 days'),
            'delivery_time' => '10:00 - 14:00',
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->email(),
            'address_id' => null,
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
