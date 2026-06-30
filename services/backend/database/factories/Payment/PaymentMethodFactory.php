<?php

namespace Database\Factories\Payment;

use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        $code = 'pm_' . Str::lower($this->faker->unique()->bothify('????##'));

        return [
            'code' => $code,
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'icon' => null,
            'is_active' => true,
            'is_enabled' => true,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'gateway' => 'manual',
            'configuration' => [],
        ];
    }
}
