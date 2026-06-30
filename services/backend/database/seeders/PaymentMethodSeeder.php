<?php

namespace Database\Seeders;

use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'code' => 'card_in_store',
                'name' => 'Банковской картой онлайн (Райффайзен)',
                'description' => 'Оплата банковской картой через эквайринг Райффайзен',
                'icon' => 'ri-bank-card-line',
                'is_active' => true,
                'is_enabled' => true,
                'sort_order' => 0,
                'gateway' => 'raiffeisen_acquiring',
                'configuration' => [],
            ],
            [
                'code' => 'card_ecom',
                'name' => 'Банковской картой онлайн (Райффайзен e-commerce)',
                'description' => 'Оплата картой через pay.raif.ru — доступно для всех регионов',
                'icon' => 'ri-bank-card-line',
                'is_active' => true,
                'is_enabled' => true,
                'sort_order' => 1,
                'gateway' => 'raiffeisen_ecom',
                'configuration' => [],
            ],
            [
                'code' => 'card_sberbank',
                'name' => 'Банковской картой онлайн (Сбербанк)',
                'description' => 'Оплата банковской картой через эквайринг Сбербанка',
                'icon' => 'ri-bank-card-line',
                'is_active' => true,
                'is_enabled' => true,
                'sort_order' => 2,
                'gateway' => 'sberbank_acquiring',
                'configuration' => [],
            ],
            [
                'code' => 'card',
                'name' => 'Банковской картой',
                'description' => 'Оплата банковской картой (вручную)',
                'icon' => 'ri-bank-card-line',
                'is_active' => true,
                'is_enabled' => true,
                'sort_order' => 3,
                'gateway' => 'manual',
                'configuration' => [],
            ],
            [
                'code' => 'cash',
                'name' => 'Наличными при получении',
                'description' => 'Оплата курьеру или в магазине',
                'icon' => 'ri-money-dollar-circle-line',
                'is_active' => true,
                'is_enabled' => true,
                'sort_order' => 4,
                'gateway' => 'manual',
                'configuration' => [],
            ],
            [
                'code' => 'installment',
                'name' => 'Рассрочка 0%',
                'description' => 'На 6 или 12 месяцев без переплаты',
                'icon' => 'ri-calendar-line',
                'is_active' => true,
                'is_enabled' => true,
                'sort_order' => 5,
                'gateway' => 'manual',
                'configuration' => [],
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
