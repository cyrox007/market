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

    private function variableKit(): array
    {
        $kit = Product::factory()->create([
            'name' => 'Шкаф + антресоль', 'is_variable' => true, 'is_sellable_bundle' => true,
            'price' => 0, 'stock' => 0,
        ]);
        $wardrobes = Product::factory()->create(['name' => 'Шкаф Бруно', 'is_variable' => true]);
        $mezzanines = Product::factory()->create(['name' => 'Антресоль Бруно', 'is_variable' => true]);
        $kit->sellableBundleGroups()->attach([
            $wardrobes->id => ['sort_order' => 0],
            $mezzanines->id => ['sort_order' => 1],
        ]);
        $offers = [];
        foreach (['white' => ['Белый', 1500, 700], 'graphite' => ['Графит', 1800, 850]] as $key => $row) {
            [$color, $wardrobePrice, $mezzPrice] = $row;
            $virtual = Product::factory()->create([
                'name' => 'Комплект '.$color, 'parent_product_id' => $kit->id,
                'price' => 99999, 'stock' => 0, 'sku' => 'KIT-'.$key,
            ]);
            $wardrobe = Product::factory()->create([
                'name' => 'Шкаф '.$color, 'parent_product_id' => $wardrobes->id,
                'price' => $wardrobePrice, 'stock' => 5, 'sku' => 'WARD-'.$key,
                'external_id' => '1c-ward-'.$key,
            ]);
            $mezz = Product::factory()->create([
                'name' => 'Антресоль '.$color, 'parent_product_id' => $mezzanines->id,
                'price' => $mezzPrice, 'stock' => 5, 'sku' => 'MEZZ-'.$key,
                'external_id' => '1c-mezz-'.$key,
            ]);
            app(SellableBundle::class)->saveOfferComposition($virtual, [
                ['group_id' => $wardrobes->id, 'product_id' => $wardrobe->id, 'quantity' => 1],
                ['group_id' => $mezzanines->id, 'product_id' => $mezz->id, 'quantity' => 1],
            ]);
            $offers[$key] = [$virtual, $wardrobe, $mezz];
        }
        return [$kit, $wardrobes, $mezzanines, $offers];
    }

    public function test_fixed_offer_price_is_independent_and_survives_component_price_updates(): void
    {
        [$root, , , $offers] = $this->variableKit();
        [$white, $wardrobe] = $offers['white'];
        $white->update(['bundle_price' => 1900.01]);
        $wardrobe->update(['price' => 1700]);
        $this->assertEquals(1900.01, $white->fresh()->price);
        $this->assertEquals(1900.01, app(SellableBundle::class)->quote($root->id, $white->id)['subtotal']);
        $this->assertEquals(2650, app(SellableBundle::class)->quote($root->id, $offers['graphite'][0]->id)['subtotal']);
        $white->update(['bundle_price' => null]);
        $this->assertEquals(2400, $white->fresh()->price);
    }

    public function test_fixed_bundle_price_matches_preview_order_snapshot_and_1c_to_the_kopeck(): void
    {
        Queue::fake();
        Http::fake(fn ($request) => Http::response([
            'target' => str_ends_with($request->url(), '/region48')
                ? ['kind' => 'region', 'externalId' => 'region48', 'name' => 'Липецкая область']
                : ['kind' => 'building', 'externalId' => 'house', 'name' => '1'],
            'path' => [['kind' => 'region', 'externalId' => 'region48', 'name' => 'Липецкая область'],
                ['kind' => 'locality', 'externalId' => 'lipetsk', 'name' => 'Липецк']],
            'label' => 'Тестовый адрес',
        ]));
        [$root, , $part] = $this->kit();
        $root->update(['bundle_price' => 1000.01]);
        $warehouse = Warehouse::create(['name' => 'Central', 'is_active' => true]);
        $warehouse->productStocks()->create(['product_id' => $root->id, 'quantity' => 10]);
        $warehouse->productStocks()->create(['product_id' => $part->id, 'quantity' => 10]);
        $warehouse->deliveryProfiles()->create(['name' => 'Область', 'coverage_type' => 'locations',
            'directory_territories' => [['region_id' => 'region48']], 'base_price' => 500, 'service_tariffs' => []]);
        PaymentMethod::create(['name' => 'Тестовая оплата', 'code' => 'cash', 'gateway' => 'manual', 'is_active' => true, 'is_enabled' => true]);
        $preview = $this->postJson('/api/v1/orders/one-click', ['bundle_id' => $root->id, 'quantity' => 3])
            ->assertOk()->assertJsonPath('subtotal', 3000.03);
        $body = ['bundle_id' => $root->id, 'bundle_quantity' => 3, 'items' => $preview->json('items'),
            'directory_external_id' => 'house', 'delivery_type' => 'delivery', 'warehouse_id' => $warehouse->id,
            'payment_method' => 'cash', 'expected_subtotal' => 3000.03,
            'contact_name' => 'Тест', 'contact_phone' => '+79001234567', 'contact_email' => 'test@example.test',
            'accept_offer' => true, 'accept_personal_data' => true];
        $this->postJson('/api/v1/shipping/quote', $body)->assertOk()->assertJsonPath('subtotal', 3000.03);
        $created = $this->postJson('/api/v1/orders', $body)->assertCreated();
        $order = Order::findOrFail($created->json('order.id'));
        $this->assertEquals(3000.03, $order->subtotal);
        $this->assertEquals(3500.03, $order->total);
        $this->assertEquals(3000.03, $order->items->sum('total'));
        $this->assertSame(300003, array_sum(array_map(fn ($row) => (int) round($row['total'] * 100), $order->bundle_snapshot['components'])));
        $payload = app(BuildOrderSyncPayloadAction::class)->execute($order);
        $this->assertSame(300003, array_sum(array_map(fn ($row) => (int) round($row['promoPrice'] * 100) * $row['quantity'], $payload['products'])));
        $root->update(['bundle_price' => 9999]);
        $this->assertEquals(3000.03, $order->fresh()->subtotal);
    }

    public function test_catalog_offer_uses_physical_component_stock_and_price(): void
    {
        [$kit, , , $offers] = $this->variableKit();
        [$offer, $wardrobe, $mezz] = $offers['white'];
        $warehouse = Warehouse::create(['name' => 'Central', 'is_active' => true, 'stock_mode' => 'quantity']);
        $warehouse->productStocks()->create(['product_id' => $wardrobe->id, 'quantity' => 3]);
        $warehouse->productStocks()->create(['product_id' => $mezz->id, 'quantity' => 2]);
        $rows = collect($this->getJson('/api/v1/products?search='.urlencode('Комплект Белый'))->assertOk()->json('data'));
        $row = $rows->firstWhere('id', $offer->id);
        $this->assertNotNull($row);
        $this->assertEquals(2200, $row['price']);
        $this->assertSame(2, $row['stock']);
        $this->assertTrue($row['available_to_order']);
        $this->assertTrue($row['in_stock']);
        $this->assertSame([], $row['stocks']);
    }

    public function test_color_offer_owns_concrete_components_not_virtual_main_sku(): void
    {
        [$kit, $wardrobes, $mezzanines, $offers] = $this->variableKit();
        [$white, $whiteWardrobe, $whiteMezz] = $offers['white'];
        [$graphite, $graphiteWardrobe, $graphiteMezz] = $offers['graphite'];

        $this->postJson('/api/v1/orders/one-click', ['bundle_id' => $kit->id, 'quantity' => 1])
            ->assertUnprocessable();
        $whiteQuote = $this->postJson('/api/v1/orders/one-click', [
            'bundle_id' => $kit->id, 'primary_sku_id' => $white->id, 'quantity' => 2,
        ])->assertOk();
        $whiteQuote->assertJsonPath('subtotal', 4400)
            ->assertJsonPath('items.0.product_id', $whiteWardrobe->id)
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.1.product_id', $whiteMezz->id)
            ->assertJsonPath('items.1.quantity', 2);
        $this->assertNotContains($white->id, array_column($whiteQuote->json('items'), 'product_id'));
        $this->postJson('/api/v1/orders/one-click', [
            'bundle_id' => $kit->id, 'primary_sku_id' => $graphite->id, 'quantity' => 1,
        ])->assertOk()->assertJsonPath('subtotal', 2650)
            ->assertJsonPath('items.0.product_id', $graphiteWardrobe->id)
            ->assertJsonPath('items.1.product_id', $graphiteMezz->id);
    }

    public function test_mismatched_color_or_missing_group_rejects_the_entire_variant(): void
    {
        [$kit, $wardrobes, $mezzanines, $offers] = $this->variableKit();
        [$white, $whiteWardrobe] = $offers['white'];
        $other = $offers['graphite'][2];
        try {
            app(SellableBundle::class)->saveOfferComposition($white, [
                ['group_id' => $wardrobes->id, 'product_id' => $whiteWardrobe->id, 'quantity' => 1],
                ['group_id' => $mezzanines->id, 'product_id' => $whiteWardrobe->id, 'quantity' => 1],
            ]);
            $this->fail('Нельзя указывать SKU шкафа в группе антресолей.');
        } catch (ValidationException) {
            // Previous valid mapping remains unchanged when save is rejected.
        }
        $this->assertCount(2, app(SellableBundle::class)->composition($kit->id, $white->id)['items']);

        $white->sellableBundleComponents()->detach($offers['white'][2]->id);
        $this->postJson('/api/v1/orders/one-click', [
            'bundle_id' => $kit->id, 'primary_sku_id' => $white->id, 'quantity' => 1,
        ])->assertUnprocessable();
        // Other colors remain independent.
        $this->postJson('/api/v1/orders/one-click', [
            'bundle_id' => $kit->id, 'primary_sku_id' => $offers['graphite'][0]->id, 'quantity' => 1,
        ])->assertOk();
    }

    public function test_parent_detail_contains_color_specific_components_and_prices(): void
    {
        [$kit, , , $offers] = $this->variableKit();
        $response = $this->getJson('/api/v1/products/'.$kit->slug)->assertOk();
        $byId = collect($response->json('product.variants'))->keyBy('id');
        $this->assertSame(2200, (int) $byId[$offers['white'][0]->id]['price']);
        $this->assertSame(2650, (int) $byId[$offers['graphite'][0]->id]['price']);
        $this->assertSame($offers['white'][1]->id, $byId[$offers['white'][0]->id]['bundle_components'][0]['product_id']);
        $this->assertSame($offers['graphite'][2]->id, $byId[$offers['graphite'][0]->id]['bundle_components'][1]['product_id']);
        $this->assertSame([], $response->json('product.sellable_bundle_components'));
    }

    public function test_base_catalogue_price_updates_when_physical_sku_price_changes(): void
    {
        [$kit, , , $offers] = $this->variableKit();
        [$white, $wardrobe] = $offers['white'];
        $this->assertEquals(2200, $white->fresh()->price);
        $this->assertEquals(2200, $kit->fresh()->price);

        $wardrobe->update(['price' => 1700]);
        $this->assertEquals(2400, $white->fresh()->price);
        $this->assertEquals(2400, app(SellableBundle::class)->quote($kit->id, $white->id)['subtotal']);
        $this->assertEquals(2400, $kit->fresh()->price);
        // The graphite offer remains independent.
        $this->assertEquals(2650, $offers['graphite'][0]->fresh()->price);
        Product::factory()->create([
            'parent_product_id' => $kit->id, 'sku' => 'KIT-UNCONFIGURED',
            'price' => 0, 'stock' => 0,
        ]);
        $this->assertEquals(2400, $kit->fresh()->price, 'Новый вариант без состава не должен обнулять цену каталога.');
    }

    public function test_chosen_color_reserves_only_its_parts_and_sends_them_to_1c(): void
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
        [$kit, , , $offers] = $this->variableKit();
        [$virtual, $wardrobe, $mezz] = $offers['white'];

        $warehouse = Warehouse::create(['name' => 'Main', 'is_active' => true]);
        $warehouse->productStocks()->create(['product_id' => $wardrobe->id, 'quantity' => 2]);
        $warehouse->productStocks()->create(['product_id' => $mezz->id, 'quantity' => 3]);
        $warehouse->deliveryProfiles()->create([
            'name' => 'Доставка', 'coverage_type' => 'locations',
            'directory_territories' => [['region_id' => 'region48']],
            'base_price' => 500, 'service_tariffs' => [],
        ]);
        PaymentMethod::create(['name' => 'Наличные', 'code' => 'cash',
            'gateway' => 'manual', 'is_active' => true, 'is_enabled' => true]);
        $body = [
            'bundle_id' => $kit->id, 'primary_sku_id' => $virtual->id, 'bundle_quantity' => 1,
            'items' => [
                ['product_id' => $wardrobe->id, 'quantity' => 1],
                ['product_id' => $mezz->id, 'quantity' => 1],
            ],
            'expected_subtotal' => 2200,
            'directory_external_id' => 'house', 'delivery_type' => 'delivery',
            'warehouse_id' => $warehouse->id, 'payment_method' => 'cash',
            'contact_name' => 'Покупатель', 'contact_phone' => '+79001234567',
            'contact_email' => 'kit-white@example.test',
            'accept_offer' => true, 'accept_personal_data' => true,
        ];
        $created = $this->withHeader('Idempotency-Key', 'variant-kit-white')
            ->postJson('/api/v1/orders', $body)->assertCreated();

        $order = Order::findOrFail($created->json('order.id'));
        $this->assertSame([$wardrobe->id, $mezz->id], $order->items->pluck('product_id')->all());
        $this->assertNotContains($virtual->id, $order->items->pluck('product_id')->all());
        $this->assertSame($virtual->id, $order->bundle_snapshot['primary_sku_id']);
        $this->assertSame($kit->id, $order->bundle_snapshot['bundle_id']);
        $this->assertSame('WARD-white', $order->bundle_snapshot['components'][0]['sku']);
        $this->assertSame('MEZZ-white', $order->bundle_snapshot['components'][1]['sku']);
        $this->assertEquals(2200, $order->subtotal);
        $this->assertEquals(1, $warehouse->productStocks()->where('product_id', $wardrobe->id)->first()->reserved_quantity);
        $this->assertEquals(1, $warehouse->productStocks()->where('product_id', $mezz->id)->first()->reserved_quantity);

        $payload = app(BuildOrderSyncPayloadAction::class)->execute($order);
        $this->assertCount(2, $payload['products']);
        $this->assertSame(['1c-ward-white'], $payload['products'][0]['modifications']);
        $this->assertSame(['1c-mezz-white'], $payload['products'][1]['modifications']);

        // Changing this offer's mapping later does not alter ordered SKUs.
        $virtual->sellableBundleComponents()->updateExistingPivot($mezz->id, ['quantity' => 2]);
        $this->assertSame(1, $order->fresh()->bundle_snapshot['components'][1]['quantity']);
        $this->withHeader('X-Order-Token', $created->json('guest_order_token'))
            ->postJson('/api/v1/order-access/'.$order->id.'/cancel')->assertOk();
        $this->assertEquals(0, $warehouse->productStocks()->where('product_id', $wardrobe->id)->first()->reserved_quantity);
        $this->assertEquals(0, $warehouse->productStocks()->where('product_id', $mezz->id)->first()->reserved_quantity);
    }

    public function test_inactive_or_ambiguous_components_block_checkout(): void
    {
        [$root, , $extra] = $this->kit();
        $extra->update(['state' => 'inactive']);
        $this->postJson('/api/v1/orders/one-click', ['bundle_id' => $root->id, 'quantity' => 1])
            ->assertUnprocessable();
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
