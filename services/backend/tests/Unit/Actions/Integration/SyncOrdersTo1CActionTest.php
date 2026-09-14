<?php

namespace Tests\Unit\Actions\Integration;

use App\Actions\Integration\Integration1C\SyncOrdersTo1CAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncOrdersTo1CActionTest extends TestCase
{
    public function test_it_uses_current_legacy_endpoint_bearer_and_orders_envelope(): void
    {
        config()->set('services.integration_1c.enabled', true);
        config()->set('services.integration_1c.base_url', 'https://integration.test');
        config()->set('services.integration_1c.api_key', 'test-integration-secret');
        config()->set('services.integration_1c.timeout', 30);
        config()->set('services.integration_1c.orders_path', '/api/v1/integration/1c/orders');

        Http::fake([
            'https://integration.test/*' => Http::response([], 200),
        ]);

        $orders = [
            [
                'orderId' => 123,
                'pickFromStore' => false,
            ],
        ];

        $result = app(SyncOrdersTo1CAction::class)->execute($orders);

        $this->assertTrue($result);
        Http::assertSent(function (Request $request) use ($orders): bool {
            return $request->url() === 'https://integration.test/api/v1/integration/1c/orders'
                && $request->hasHeader('Authorization', 'Bearer test-integration-secret')
                && $request->data() === ['orders' => $orders];
        });
    }

    public function test_it_returns_false_on_unsuccessful_http_response(): void
    {
        config()->set('services.integration_1c.enabled', true);
        config()->set('services.integration_1c.base_url', 'https://integration.test');
        config()->set('services.integration_1c.api_key', 'test-integration-secret');
        config()->set('services.integration_1c.orders_path', '/api/v1/integration/1c/orders');

        Http::fake([
            'https://integration.test/*' => Http::response(['message' => 'fail'], 500),
        ]);

        $result = app(SyncOrdersTo1CAction::class)->execute([
            ['orderId' => 123],
        ]);

        $this->assertFalse($result);
    }

    public function test_empty_batch_is_a_noop_success(): void
    {
        Http::fake();

        $this->assertTrue(app(SyncOrdersTo1CAction::class)->execute([]));
        Http::assertNothingSent();
    }
}
