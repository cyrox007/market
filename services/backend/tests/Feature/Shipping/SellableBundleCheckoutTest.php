<?php

namespace Tests\Feature\Shipping;

use App\Actions\Integration\Integration1C\BuildOrderSyncPayloadAction;
use App\Models\Inventory\Warehouse;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use App\Services\Product\SellableBundle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SellableBundleCheckoutTest extends TestCase
{
    private function kit(): array
    {
        $root = Product::factory()->create(['name' => 'Шкаф', 'sku' => 'WARDROBE', 'price' => 1500,
            'is_sellable_bundle' => true, 'stock' => 10, 'backorder' => false, 'external_id' => '1c-wardrobe']);
        $extraParent = Product::factory()->create(['name' => 'Антресоль', 'is_variable' => true]);
        $extra = Product::factory()->create(['name' => 'Антресоль синяя', 'sku' => 'MEZZ-BLUE', 'price' => 700,
            'parent_product_id' => $extraParent->id, 'stock' => 10, 'external_id' => '1c-blue']);
        $root->sellableBundleComponents()->attach($extra->id, ['quantity' => 2, 'sort_order' => 1]);
        return [$root, $extraParent, $extra];
    }

    public function test_a_concrete_sku_and_quantity_are_required_for_kit(): void
    {
        [$root, $extraParent, $extra] = $this->kit();
        $composition = app(SellableBundle::class)->composition($root->id, null, 3);
        $this->assertSame([
            ['product_id' => $root->id, 'quantity' => 3],
            ['product_id' => $extra->id, 'quantity' => 6],
        ], $composition['items']);

        $this->assertFalse(app(SellableBundle::class)->componentIsSellable($extraParent, $root));
        $this->assertTrue(app(SellableBundle::class)->componentIsSellable($extra, $root));
        $this->expectException(ValidationException::class);
        app(SellableBundle::class)->composition($root->id, null, 501);
    }

    public function test_one_click_returns_server_priced_items_for_complete_kit(): void
    {
        [$root, , $extra] = $this->kit();
        $this->postJson('/api/v1/orders/one-click', ['bundle_id' => $root->id, 'quantity' => 2])
            ->assertOk()->assertJsonPath('mode', 'direct_bundle_checkout')
            ->assertJsonPath('items.0.product_id', $root->id)
            ->assertJsonPath('items.1.product_id', $extra->id)
            ->assertJsonPath('items.1.quantity', 4)
            ->assertJsonPath('subtotal', 5800);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_submitting_arbitrary_skus_or_stale_kit_is_rejected(): void
    {
        [$root, , $extra] = $this->kit();
        $composition = app(SellableBundle::class)->composition($root->id, null, 1);
        $other = Product::factory()->create();
        $this->expectException(ValidationException::class);
        app(SellableBundle::class)->assertMatches($composition, [
            ['product_id' => $root->id, 'quantity' => 1],
            ['product_id' => $other->id, 'quantity' => 2],
        ]);
    }

    public function test_bundle_checkout_reserves_all_skus_and_keeps_snapshot_even_after_admin_edits(): void
    {
        Queue::fake();
        Http::fake(function ($request) {
            $region = ['kind' => 'region', 'externalId' => 'region48', 'name' => 'Липецкая область'];
            return Http::response([
                'target' => str_ends_with($request->url(), '/region48') ? $region
                    : ['kind' => 'building', 'externalId' => 'house', 'name' => '1'],
                'path' => [$region, ['kind' => 'locality', 'externalId' => 'lipetsk', 'name' => 'Липецк']],
                'label' => 'Липецк, тестовый дом 1',
            ]);
        });
        [$root, $extraParent, $extra] = $this->kit();
        $warehouse = Warehouse::create(['name' => 'Central', 'is_active' => true]);
        $warehouse->productStocks()->create(['product_id' => $root->id, 'quantity' => 5]);
        $warehouse->productStocks()->create(['product_id' => $extra->id, 'quantity' => 5]);
        $warehouse->deliveryProfiles()->create([
            'name' => 'Область', 'coverage_type' => 'locations',
            'directory_territories' => [['region_id' => 'region48']],
            'base_price' => 500, 'service_tariffs' => [],
        ]);
        PaymentMethod::create(['name' => 'Наличные', 'code' => 'cash',
            'gateway' => 'manual', 'is_active' => true, 'is_enabled' => true]);
        $body = [
            'bundle_id' => $root->id, 'bundle_quantity' => 1,
            'items' => [['product_id' => $root->id, 'quantity' => 1], ['product_id' => $extra->id, 'quantity' => 2]],
            'expected_subtotal' => 2900,
            'directory_external_id' => 'house', 'delivery_type' => 'delivery',
            'warehouse_id' => $warehouse->id, 'payment_method' => 'cash',
            'contact_name' => 'Покупатель', 'contact_phone' => '+79001234567',
            'contact_email' => 'kit@example.test', 'accept_offer' => true, 'accept_personal_data' => true,
        ];
        $created = $this->withHeader('Idempotency-Key', 'sellable-kit-test-one')
            ->postJson('/api/v1/orders', $body)->assertCreated();

        $order = Order::findOrFail($created->json('order.id'));
        $this->assertCount(2, $order->items);
        $this->assertSame($root->id, $order->bundle_snapshot['bundle_id']);
        $this->assertSame(2, $order->bundle_snapshot['components'][1]['quantity']);
        $this->assertEquals(2900, $order->subtotal);
        $this->assertSame(1, (int) $warehouse->productStocks()->where('product_id', $root->id)->first()->reserved_quantity);
        $this->assertSame(2, (int) $warehouse->productStocks()->where('product_id', $extra->id)->first()->reserved_quantity);

        // Existing 1C contract uses separate catalogue lines (including modifications).
        $payload = app(BuildOrderSyncPayloadAction::class)->execute($order);
        $this->assertCount(2, $payload['products']);
        $this->assertSame([1, 2], array_column($payload['products'], 'quantity'));
        $this->assertSame(['1c-blue'], $payload['products'][1]['modifications']);

        $root->sellableBundleComponents()->updateExistingPivot($extra->id, ['quantity' => 3]);
        $this->assertSame(2, $order->fresh()->bundle_snapshot['components'][1]['quantity']);
        $this->withHeader('X-Order-Token', $created->json('guest_order_token'))
            ->postJson('/api/v1/order-access/'.$order->id.'/cancel')->assertOk();
        $this->assertEquals(0, $warehouse->productStocks()->where('product_id', $root->id)->first()->reserved_quantity);
        $this->assertEquals(0, $warehouse->productStocks()->where('product_id', $extra->id)->first()->reserved_quantity);
    }
}
