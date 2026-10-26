<?php

namespace Tests\Feature\Services;

use App\Actions\Order\AutoCancelExpiredUnpaidOrderAction;
use App\Models\Order\Order;
use Tests\TestCase;

class OrderAuditRegressionTest extends TestCase
{
    public function test_kladr_fallback_uses_nearest_ancestor_and_preserves_building_gar(): void
    {
        $service = app(\App\Services\Address\FulfillmentAddress::class);
        $path = [['kind' => 'region', 'kladrCode' => '4800000000000'],
            ['kind' => 'locality', 'kladrCode' => '4800000100000'],
            ['kind' => 'street', 'kladrCode' => '']];
        $codes = $service->integrationCodes(['gar_guid' => 'house-guid', 'kladr_code' => '', 'path' => $path]);
        $this->assertSame('4800000100000', $codes['addressKladrCode']);
        $this->assertSame('locality', $codes['addressKladrLevel']);
        $this->assertSame('house-guid', $codes['addressGarGuid']);
        $this->assertNull($codes['addressBuildingKladrCode']);
        $own = $service->integrationCodes(['gar_guid' => 'house-guid', 'kladr_code' => 'house-code', 'path' => $path]);
        $this->assertSame('house-code', $own['addressKladrCode']);
        $this->assertSame('building', $own['addressKladrLevel']);
        $this->assertSame('house-code', $own['addressBuildingKladrCode']);
        $missing = $service->integrationCodes(['gar_guid' => 'house-guid', 'path' => []]);
        $this->assertNull($missing['addressKladrCode']);
        $this->assertNull($missing['addressKladrLevel']);
        $this->assertSame('house-guid', $missing['addressGarGuid']);
    }

    public function test_guest_payment_config_access_is_repeatable_and_cancelled_order_cannot_pay(): void
    {
        $method = \App\Models\Payment\PaymentMethod::create(['name' => 'Card', 'code' => 'audit-card', 'gateway' => 'raiffeisen_ecom']);
        $order = Order::factory()->create(['status' => 'awaiting_payment', 'payment_method' => $method->code]);
        $this->withSession(['last_created_order_id' => $order->id]);
        // No payment record: both calls must reach the same 404, not lose access after the first call.
        $this->getJson('/api/v1/orders/'.$order->id.'/payment-config')->assertNotFound();
        $this->getJson('/api/v1/orders/'.$order->id.'/payment-config')->assertNotFound();
        $order->update(['status' => 'cancelled']);
        $order->payments()->create(['payment_method_id' => $method->id, 'currency' => 'RUB',
            'amount' => 1000, 'amount_paid' => 0, 'status' => 'pending']);
        $this->getJson('/api/v1/orders/'.$order->id.'/payment-config')->assertUnprocessable();
    }

    public function test_new_failed_attempt_does_not_hide_an_earlier_successful_payment(): void
    {
        $order = Order::factory()->create(['status' => 'awaiting_payment', 'created_at' => now()->subHours(2)]);
        $method = \App\Models\Payment\PaymentMethod::create(['name' => 'Cash', 'code' => 'audit-cash', 'gateway' => 'manual']);
        foreach ([['paid', 1000], ['pending', 0]] as [$status, $paid]) {
            $order->payments()->create(['payment_method_id' => $method->id, 'currency' => 'RUB',
                'amount' => 1000, 'amount_paid' => $paid, 'status' => $status]);
        }
        $this->assertFalse(app(AutoCancelExpiredUnpaidOrderAction::class)->execute($order));
        $this->assertSame('awaiting_payment', $order->fresh()->status);
    }

    public function test_street_kladr_is_not_misrepresented_as_building_code(): void
    {
        $order = Order::factory()->create(['fulfillment_snapshot' => ['address_directory' => [
            'gar_guid' => 'gar-building', 'kladr_code' => null,
            'path' => [['kind' => 'street', 'kladrCode' => '48000001000047800'], ['kind' => 'building', 'kladrCode' => null]],
        ]]]);
        $p = app(\App\Actions\Integration\Integration1C\BuildOrderSyncPayloadAction::class)->execute($order);
        $this->assertSame('48000001000047800', $p['addressKladrCode']);
        $this->assertSame('street', $p['addressKladrLevel']);
        $this->assertNull($p['addressBuildingKladrCode']);
        $this->assertSame('gar-building', $p['addressGarGuid']);
        $this->assertSame([['kind' => 'street', 'code' => '48000001000047800']], $p['addressKladrHierarchy']);
    }

    public function test_early_unpaid_job_cannot_cancel_before_deadline(): void
    {
        config(['orders.unpaid_auto_cancel_minutes' => 10]);
        $order = Order::factory()->create(['status' => 'awaiting_payment', 'created_at' => now()->subMinutes(9)]);
        $this->assertFalse(app(AutoCancelExpiredUnpaidOrderAction::class)->execute($order));
        $this->assertSame('awaiting_payment', $order->fresh()->status);
    }

    public function test_failed_external_delivery_throws_so_queue_can_retry(): void
    {
        $order = Order::factory()->create();
        $sender = \Mockery::mock(\App\Actions\Integration\Integration1C\SyncOrdersTo1CAction::class);
        $sender->shouldReceive('execute')->once()->andReturn(false);
        $this->expectException(\RuntimeException::class);
        (new \App\Jobs\Integration\SyncOrderTo1CJob($order->id))->handle(
            app(\App\Actions\Integration\Integration1C\BuildOrderSyncPayloadAction::class), $sender);
    }

    public function test_delivery_lift_is_not_charged_twice_and_pickup_contains_warehouse(): void
    {
        $order = Order::factory()->create(['delivery_type' => 'delivery', 'delivery_cost' => 800,
            'address_snapshot' => 'Адрес выдачи', 'fulfillment_snapshot' => [
                'warehouse_external_id' => 'LP00001', 'handling_cost' => 300,
                'address_directory' => ['external_id' => 'building', 'kladr_code' => '4800000100000', 'gar_guid' => 'gar-id'],
            ]]);
        $p = app(\App\Actions\Integration\Integration1C\BuildOrderSyncPayloadAction::class)->execute($order);
        $this->assertFalse($p['pickFromStore']);
        $this->assertSame('LP00001', $p['warehouseExternalId']);
        $this->assertSame('4800000100000', $p['addressKladrCode']);
        $this->assertSame(300.0, $p['liftCost']);
        $this->assertSame(500.0, $p['deliveryCost']);
        $this->assertSame(800.0, $p['liftCost'] + $p['deliveryCost']);
        $order->update(['delivery_type' => 'pickup', 'delivery_cost' => 0,
            'fulfillment_snapshot' => ['warehouse_external_id' => 'LP00001']]);
        $p = app(\App\Actions\Integration\Integration1C\BuildOrderSyncPayloadAction::class)->execute($order);
        $this->assertTrue($p['pickFromStore']);
        $this->assertSame('LP00001', $p['warehouseExternalId']);
        $this->assertSame(0.0, $p['deliveryCost']);
        $this->assertSame(0.0, $p['liftCost']);
    }
}
