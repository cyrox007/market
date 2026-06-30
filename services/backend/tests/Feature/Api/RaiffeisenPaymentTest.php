<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessRaiffeisenCallbackJob;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RaiffeisenPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_raiffeisen_callback_returns_200_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/payment/raiffeisen/callback', [
            'orderId' => 'ORD20260210000001',
            'status' => 'PAID',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        Queue::assertPushed(ProcessRaiffeisenCallbackJob::class);
    }

    public function test_payment_config_returns_403_for_unauthorized_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $token = $otherUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}/payment-config");

        $response->assertStatus(403);
    }

    public function test_payment_config_returns_404_when_no_payment_for_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}/payment-config");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Платёж не найден');
    }
}
