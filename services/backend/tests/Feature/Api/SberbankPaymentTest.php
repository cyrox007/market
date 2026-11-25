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

    public function test_duplicate_callback_aliases_are_deduplicated_but_new_status_is_queued(): void
    {
        Queue::fake();
        $id = 'a67b0ced-c9a4-4cfb-bce3-b9595afaafc1';
        $url = '/api/v1/payment/sberbank/callback';
        $this->postJson($url, ['mdOrder' => $id, 'orderNumber' => 'ORD1', 'operation' => 'deposited', 'status' => 1])->assertOk();
        $this->postJson($url, ['orderId' => $id, 'order_number' => 'ORD1', 'operation' => 'DEPOSITED', 'status' => '1', 'ignored' => 'value'])->assertOk();
        Queue::assertPushed(ProcessSberbankCallbackJob::class, 1);
        $this->postJson($url, ['mdOrder' => $id, 'orderNumber' => 'ORD1', 'operation' => 'refunded', 'status' => 1])->assertOk();
        Queue::assertPushed(ProcessSberbankCallbackJob::class, 2);
        $this->travel(61)->seconds();
        $this->postJson($url, ['mdOrder' => $id, 'orderNumber' => 'ORD1', 'operation' => 'deposited', 'status' => 1])->assertOk();
        Queue::assertPushed(ProcessSberbankCallbackJob::class, 3);
    }

    public function test_callback_rejects_malformed_fields_before_queueing(): void
    {
        Queue::fake();
        $url = '/api/v1/payment/sberbank/callback';
        foreach ([[], ['mdOrder' => ['array']], ['mdOrder' => 'bad-id'], ['orderNumber' => str_repeat('X', 65)],
            ['orderNumber' => 'ORD1', 'status' => ['bad']], ['orderNumber' => 'ORD1', 'operation' => str_repeat('x', 65)]] as $payload) {
            $this->postJson($url, $payload)->assertUnprocessable();
        }
        Queue::assertNothingPushed();
    }

    public function test_callback_has_per_ip_and_global_rate_limits(): void
    {
        Queue::fake();
        config(['payment.sberbank_acquiring.callback_per_ip' => 2, 'payment.sberbank_acquiring.callback_global' => 3]);
        $url = '/api/v1/payment/sberbank/callback';
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
        $this->postJson($url, ['orderNumber' => 'ORD1'])->assertOk();
        $this->postJson($url, ['orderNumber' => 'ORD2'])->assertOk();
        $this->postJson($url, ['orderNumber' => 'ORD3'])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11']);
        $this->postJson($url, ['orderNumber' => 'ORD4'])->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.12']);
        $this->postJson($url, ['orderNumber' => 'ORD5'])->assertStatus(429);
        Queue::assertPushed(ProcessSberbankCallbackJob::class, 3);
    }

}
