<?php

namespace Tests\Feature\Api;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OneCOrdersReadTest extends TestCase
{
    private const TOKEN = 'test-only-1c-orders-token-with-at-least-32-characters';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'onec_orders_read.enabled' => true,
            'onec_orders_read.token' => self::TOKEN,
            'onec_orders_read.per_minute' => 120,
        ]);
    }

    private function requestList(array $query = [])
    {
        return $this->withHeader('X-1C-Orders-Token', self::TOKEN)
            ->getJson('/api/v1/integration/1c/orders'.($query ? '?'.http_build_query($query) : ''));
    }

    private function createReleased(array $fields = []): Order
    {
        $attributes = array_replace([
            'cooling_until' => now()->subHours(2),
            'status' => 'assembling',
            'payment_method' => null,
        ], $fields);

        // Some intentionally inconsistent/legacy rows cannot be written via
        // Order::saving (which correctly blocks real business transitions).
        // Insert as manager_processing and mutate the test fixture directly.
        $wantedStatus = $attributes['status'];
        $unsafe = in_array($wantedStatus, ['new', 'awaiting_payment', 'accepted'], true)
            || ($attributes['cooling_until'] !== null
                && \Illuminate\Support\Carbon::parse($attributes['cooling_until'])->isFuture()
                && in_array($wantedStatus, ['assembling', 'assembled', 'shipped', 'in_transit', 'delivered'], true));
        if ($unsafe) {
            $attributes['status'] = 'manager_processing';
        }

        $order = Order::factory()->create($attributes);
        if ($unsafe) {
            DB::table('orders')->where('id', $order->id)->update(['status' => $wantedStatus]);
            return $order->fresh();
        }

        return $order;
    }

    public function test_token_is_required_and_feature_is_disabled_by_default(): void
    {
        $this->getJson('/api/v1/integration/1c/orders')->assertUnauthorized();
        $this->withHeader('X-1C-Orders-Token', 'wrong')
            ->getJson('/api/v1/integration/1c/orders')->assertUnauthorized();

        config(['onec_orders_read.enabled' => false]);
        $this->requestList()->assertUnauthorized();

        config(['onec_orders_read.enabled' => true, 'onec_orders_read.token' => 'short']);
        $this->requestList()->assertUnauthorized();
    }

    public function test_cooling_and_pre_release_statuses_are_never_visible_even_by_id(): void
    {
        $released = $this->createReleased();
        $cooling = $this->createReleased(['cooling_until' => now()->addHour()]);
        $legacy = $this->createReleased(['cooling_until' => null]);
        $new = $this->createReleased(['status' => 'new']);
        $manager = $this->createReleased(['status' => 'manager_processing']);
        $awaiting = $this->createReleased(['status' => 'awaiting_payment']);
        $cancelledEarly = $this->createReleased([
            'status' => 'cancelled',
            'cooling_until' => now()->subHours(2),
        ]);
        $cancelledEarly->statusHistory()->create([
            'status' => 'cancelled',
            'created_at' => now()->subHours(3),
        ]);

        $response = $this->requestList()->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $released->id);
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertSame('0', (string) $response->headers->getCacheControlDirective('max-age'));
        foreach ([$cooling, $legacy, $new, $manager, $awaiting, $cancelledEarly] as $hidden) {
            $this->requestList(['order_id' => $hidden->id])
                ->assertOk()->assertJsonCount(0, 'data');
        }
    }

    public function test_cancelled_order_is_visible_only_with_evidence_of_prior_release(): void
    {
        $preRelease = $this->createReleased([
            'status' => 'cancelled',
            'integration_sent_at' => now()->subHours(3),
        ]);
        $postRelease = $this->createReleased([
            'status' => 'cancelled',
            'integration_sent_at' => now()->subHour(),
        ]);
        $historyRelease = $this->createReleased(['status' => 'cancelled']);
        $historyRelease->statusHistory()->create([
            'status' => 'assembling',
            'created_at' => now()->subHour(),
        ]);

        $ids = collect($this->requestList(['status' => 'cancelled'])->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertNotContains($preRelease->id, $ids);
        $this->assertContains($postRelease->id, $ids);
        $this->assertContains($historyRelease->id, $ids);
    }

    public function test_filters_combine_and_next_page_uses_stable_id_cursor(): void
    {
        $first = $this->createReleased([
            'status' => 'assembling',
            'delivery_type' => 'delivery',
            'payment_method' => 'cash',
            'created_at' => '2026-11-20 12:00:00',
        ]);
        $second = $this->createReleased([
            'status' => 'assembled',
            'delivery_type' => 'delivery',
            'payment_method' => 'cash',
            'created_at' => '2026-11-21 12:00:00',
        ]);
        $this->createReleased(['status' => 'delivered', 'delivery_type' => 'pickup']);

        $query = [
            'status' => 'assembling,assembled',
            'delivery_type' => 'delivery',
            'payment_method' => 'cash',
            'created_from' => '2026-11-20',
            'created_to' => '2026-11-21',
            'limit' => 1,
        ];

        $page1 = $this->requestList($query)->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.next_after_id', $first->id)
            ->assertJsonCount(1, 'data');
        $max = $page1->json('meta.snapshot_max_id');

        $page2 = $this->requestList($query + [
            'after_id' => $first->id,
            'snapshot_max_id' => $max,
        ])->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('meta.next_after_id', null);
        $this->assertSame($max, $page2->json('meta.snapshot_max_id'));

        $this->requestList(['number' => $second->number])
            ->assertOk()->assertJsonPath('data.0.id', $second->id);
        $this->requestList(['cooling_from' => now()->addDays(10)->toDateString()])
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_rejects_invalid_status_dates_and_page_bounds(): void
    {
        $this->requestList(['status' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->requestList(['created_from' => '2026-11-23', 'created_to' => '2026-11-20'])
            ->assertUnprocessable()->assertJsonValidationErrors('created_to');
        $this->requestList(['limit' => 101])
            ->assertUnprocessable()->assertJsonValidationErrors('limit');
        $this->requestList(['delivery_type' => 'warehouse'])
            ->assertUnprocessable()->assertJsonValidationErrors('delivery_type');
    }

    public function test_exposes_order_line_snapshot_not_current_catalog_and_keeps_missing_identifiers_visible(): void
    {
        $order = $this->createReleased([
            'status' => 'assembling',
            'subtotal' => 3000,
            'delivery_cost' => 500,
            'assembly_cost' => 0,
            'total' => 3500,
            'address_snapshot' => 'Липецк, улица Старая, 10',
            'fulfillment_snapshot' => [
                'multi_warehouse' => true,
                'address_directory' => [
                    'external_id' => 'building-1',
                    'gar_guid' => 'guid-1',
                    'kladr_code' => '4800001',
                    'details' => ['apartment' => '14'],
                    'path' => [],
                ],
                'warehouse_allocations' => [
                    [
                        'warehouse_id' => 1, 'warehouse_external_id' => 'WH-A',
                        'items' => [['product_id' => 77, 'quantity' => 2, 'preorder_quantity' => 1]],
                    ],
                ],
                'handling_cost' => 200,
                'additional_services_cost' => 0,
            ],
        ]);
        $product = Product::factory()->create(['name' => 'Исторический товар']);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 2, 'price' => 1500, 'total' => 3000,
        ]);
        $snapshot = $item->display_snapshot;
        $this->assertNotNull($snapshot);
        // Simulate an old order whose product was renamed after the purchase.
        $product->update(['name' => 'Новое имя каталога']);
        DB::table('order_items')->where('id', $item->id)->update([
            'name' => 'Историческое название строки',
        ]);

        $response = $this->requestList(['order_id' => $order->id])
            ->assertOk()->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.items.0.name', 'Историческое название строки')
            ->assertJsonPath('data.0.items.0.unit_price', '1500.00')
            ->assertJsonPath('data.0.amounts.total', '3500.00')
            ->assertJsonPath('data.0.amounts.handling_in_delivery', '200.00')
            ->assertJsonPath('data.0.delivery.address.text', 'Липецк, улица Старая, 10')
            ->assertJsonPath('data.0.fulfillment.multi_warehouse', true)
            ->assertJsonPath('data.0.fulfillment.warehouse_allocations.0.items.0.preorder_quantity', 1)
            ->assertJsonPath('data.0.payment.is_paid', false)
            ->assertJsonMissingPath('data.0.integration_state')
            ->assertJsonMissingPath('data.0.items.0.product.stock');

        $this->assertSame($snapshot['article'] ?? null, $response->json('data.0.items.0.product_external_id'));
        $this->assertSame('snapshot', $response->json('data.0.items.0.display_source'));

        // Missing catalog identifiers remain null, rather than substituting
        // an unrelated internal product ID or silently dropping the line.
        DB::table('order_items')->where('id', $item->id)->update([
            'display_snapshot' => null,
        ]);
        $this->requestList(['order_id' => $order->id])->assertOk()
            ->assertJsonCount(1, 'data.0.items')
            ->assertJsonPath('data.0.items.0.display_source', 'legacy')
            ->assertJsonPath('data.0.items.0.product_external_id', null);
    }
}
