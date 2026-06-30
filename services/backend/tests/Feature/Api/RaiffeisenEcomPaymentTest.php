<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\ProcessRaiffeisenEcomCallbackJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RaiffeisenEcomPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_raiffeisen_ecom_callback_returns_200_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/payment/raiffeisen-ecom/callback', [
            'event' => 'PAYMENT',
            'data' => [
                'id' => 'payment-test-123',
                'publicId' => 'test-public-id',
                'amount' => 1500.50,
                'order' => ['id' => 'ORD20260210000001'],
                'status' => ['value' => 'SUCCESS', 'date' => '2025-02-12T12:00:00+03:00'],
            ],
        ], [
            'X-Api-Signature-SHA256' => 'test-signature',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        Queue::assertPushed(ProcessRaiffeisenEcomCallbackJob::class);
    }

    public function test_raiffeisen_ecom_callback_rejects_unsupported_event(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/payment/raiffeisen-ecom/callback', [
            'event' => 'SUBSCRIPTION',
            'data' => [],
        ]);

        $response->assertStatus(200);
        Queue::assertPushed(ProcessRaiffeisenEcomCallbackJob::class);
    }
}
