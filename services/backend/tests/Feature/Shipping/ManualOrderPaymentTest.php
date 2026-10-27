<?php

namespace Tests\Feature\Shipping;

use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Services\Order\ManualOrderPayment;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManualOrderPaymentTest extends TestCase
{
    public function test_bank_payment_cannot_be_marked_paid_or_cancelled_manually(): void
    {
        $method = PaymentMethod::create(['name' => 'Bank', 'code' => 'bank', 'gateway' => 'raiffeisen_ecom']);
        $order = Order::factory()->create(['payment_method' => 'bank']);
        $payment = $order->payments()->create(['payment_method_id' => $method->id, 'currency' => 'RUB', 'amount' => 1000, 'amount_paid' => 0, 'status' => 'pending']);
        foreach ([true, false] as $paid) {
            try { app(ManualOrderPayment::class)->record($order->id, $payment->id, $paid); $this->fail('Bank state overwritten'); }
            catch (ValidationException $error) { $this->assertArrayHasKey('payment', $error->errors()); }
        }
        $this->assertSame('pending', (string) $payment->fresh()->getRawOriginal('status'));
    }

    public function test_stale_admin_form_cannot_erase_a_received_payment(): void
    {
        $method = PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'gateway' => 'manual']);
        $order = Order::factory()->create(['payment_method' => 'cash', 'status' => 'manager_processing', 'cooling_until' => now()->addHour(), 'total' => 1000]);
        $service = app(ManualOrderPayment::class);
        $payment = $service->record($order->id, null, true);
        $this->assertSame('manager_processing', $order->fresh()->status);
        $this->expectException(ValidationException::class);
        $service->record($order->id, $payment->id, false);
    }

    public function test_payment_from_another_order_is_not_editable(): void
    {
        $method = PaymentMethod::create(['name' => 'Cash', 'code' => 'cash', 'gateway' => 'manual']);
        $order = Order::factory()->create(['payment_method' => 'cash', 'status' => 'manager_processing']);
        $other = Order::factory()->create();
        $payment = $other->payments()->create(['payment_method_id' => $method->id, 'currency' => 'RUB', 'amount' => 1000, 'amount_paid' => 0, 'status' => 'pending']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(ManualOrderPayment::class)->record($order->id, $payment->id, true);
    }
}
