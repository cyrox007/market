<?php
namespace Tests\Feature\Shipping;

use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentRefund;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderImmutabilityTest extends TestCase
{
    public function test_sealed_order_rejects_address_payment_method_and_warehouse_substitution(): void
    {
        $order = Order::factory()->create(['delivery_type' => 'delivery', 'address_snapshot' => 'Липецк, исходный дом', 'fulfillment_snapshot' => ['warehouse_id' => 1, 'address_directory' => ['external_id' => 'original-house'], 'stock_state' => 'reserved']]);
        $order->sealCheckout();
        foreach (['address_snapshot' => 'Другой дом', 'address_id' => 999, 'delivery_type' => 'pickup', 'payment_method' => 'another-card',
            'number' => 'OTHER-BANK-ORDER', 'fulfillment_snapshot' => ['warehouse_id' => 2, 'address_directory' => ['external_id' => 'other-house']]] as $field => $value) {
            $fresh = $order->fresh();
            try { $fresh->update([$field => $value]); $this->fail('Substitution accepted: '.$field); }
            catch (ValidationException $error) { $this->assertNotEmpty($error->errors()); }
        }
        $order->update(['fulfillment_snapshot' => array_merge($order->fulfillment_snapshot, ['stock_state' => 'released'])]);
        $this->assertSame('released', $order->fresh()->fulfillment_snapshot['stock_state']);
        $this->assertSame('Липецк, исходный дом', $order->fresh()->address_snapshot);
    }

    public function test_changing_address_book_does_not_change_order_address_response(): void
    {
        $address = \App\Models\Address\Address::factory()->create(['city' => 'Липецк', 'street' => 'Исходная', 'house' => '1']);
        $order = Order::factory()->create(['address_id' => $address->id, 'address_snapshot' => 'Липецк, Исходная, 1']);
        $order->sealCheckout();
        $address->update(['city' => 'Другой город', 'house' => '999']);
        $response = (new \App\Http\Resources\OrderResource($order->fresh()->load(['address', 'items', 'statusHistory'])))->resolve(request());
        $this->assertSame('Липецк', $response['address']['city']);
        $this->assertSame('1', $response['address']['house']);
        $this->assertSame('Липецк, Исходная, 1', $response['address']['full_address']);
        $payload = app(\App\Actions\Integration\Integration1C\BuildOrderSyncPayloadAction::class)->execute($order->fresh());
        $this->assertSame('Липецк, Исходная, 1', $payload['address']);
    }

    public function test_paid_payment_and_refund_cannot_be_rebound_to_another_order(): void
    {
        $method = PaymentMethod::create(['name' => 'Card', 'code' => 'immutable-card', 'gateway' => 'manual']);
        $order = Order::factory()->create(['total' => 1000]);
        $payment = $order->payments()->create(['payment_method_id' => $method->id, 'currency' => 'RUB', 'amount' => 1000, 'amount_paid' => 1000, 'status' => 'paid']);
        foreach (['payable_id' => 999, 'payment_method_id' => 999, 'currency' => 'USD', 'amount' => 1] as $field => $value) {
            try { $payment->fresh()->update([$field => $value]); $this->fail('Paid identity changed'); }
            catch (ValidationException $error) { $this->assertNotEmpty($error->errors()); }
        }
        $refund = PaymentRefund::create(['payment_id' => $payment->id, 'refund_id' => 'original-refund', 'amount' => 1000, 'gateway' => 'manual', 'status' => 'pending']);
        $this->expectException(ValidationException::class);
        $refund->update(['payment_id' => 999]);
    }

    public function test_unpaid_card_cannot_be_manually_assembled_or_put_on_legacy_timeout(): void
    {
        PaymentMethod::create(['name' => 'Card', 'code' => 'unpaid-card', 'gateway' => 'raiffeisen_ecom']);
        $order = Order::factory()->create(['status' => 'manager_processing', 'cooling_until' => now()->subMinute(), 'payment_method' => 'unpaid-card']);
        foreach (['assembling', 'awaiting_payment', 'accepted'] as $status) {
            try { $order->fresh()->update(['status' => $status]); $this->fail('Invalid unpaid transition'); }
            catch (ValidationException $error) { $this->assertArrayHasKey('status', $error->errors()); }
        }
    }

    public function test_bank_refund_request_has_only_original_order_reference_and_amount_no_destination_card(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['refundStatus' => 'COMPLETED'])]);
        $client = new \App\Services\Payment\RaiffeisenEcomClient('merchant', 'test-secret', true);
        $client->createRefund('ORIGINAL-ORDER', 'ORIGINAL-REFUND', 1000);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), '/orders/ORIGINAL-ORDER/refunds/ORIGINAL-REFUND')
            && array_keys($request->data()) === ['amount'] && (float) $request['amount'] === 1000.0);
    }
}
