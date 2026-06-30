<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessSberbankCallbackJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SberbankPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_sberbank_callback_returns_200_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/payment/sberbank/callback', [
            'mdOrder' => 'a67b0ced-c9a4-4cfb-bce3-b9595afaafc1',
            'orderNumber' => 'ORD20260210000001',
            'operation' => 'deposited',
            'status' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        Queue::assertPushed(ProcessSberbankCallbackJob::class);
    }
}
