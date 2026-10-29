<?php

namespace Tests\Feature\Services;

use App\Actions\Integration\Integration1C\SyncOrdersTo1CAction;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderReceiverAcknowledgementTest extends TestCase
{
    public function test_http_success_requires_a_receipt_for_every_order(): void
    {
        config(['services.integration_1c.enabled' => true, 'services.integration_1c.base_url' => 'https://receiver.invalid']);
        $orders = [['orderId' => 11], ['orderId' => 12]];
        foreach ([[], ['success' => true], [['orderId' => 11, 'id' => 1, 'status' => 'assembling']],
            [['orderId' => 11, 'id' => 1, 'status' => 'assembling'], ['orderId' => 12, 'id' => 0, 'status' => 'error']],
            [['orderId' => 11, 'id' => 1, 'status' => 'assembling'], ['orderId' => 11, 'id' => 1, 'status' => 'assembling']]] as $receipts) {
            Http::fake(['*' => Http::response($receipts, 200)]);
            $this->assertFalse(app(SyncOrdersTo1CAction::class)->execute($orders));
        }
    }

    public function test_verified_receipts_and_retry_keep_the_same_idempotency_key(): void
    {
        config(['services.integration_1c.enabled' => true, 'services.integration_1c.base_url' => 'https://receiver.invalid']);
        Http::fake(['*' => Http::response([['orderId' => 11, 'id' => 90, 'status' => 'assembling']], 200)]);
        $sender = app(SyncOrdersTo1CAction::class);
        $this->assertTrue($sender->execute([['orderId' => 11]]));
        $this->assertTrue($sender->execute([['orderId' => 11]]));
        $keys = Http::recorded()->map(fn ($pair) => $pair[0]->header('Idempotency-Key')[0]);
        $this->assertCount(1, $keys->unique());
    }
}
