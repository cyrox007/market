<?php

namespace Tests\Feature\Shipping;

use App\Mail\OrderCreatedMail;
use App\Models\Mail\MailEvent;
use App\Models\Mail\MailEventLog;
use App\Models\Mail\MailEventTemplate;
use App\Models\Order\Order;
use App\Services\Mail\MailEventService;
use App\Services\Order\GuestOrderAccess;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GuestOrderEmailAccessTest extends TestCase
{
    private function guestOrder(): Order
    {
        return Order::factory()->create(['user_id' => null, 'guest_token_hash' => hash('sha256', str_repeat('a', 64))]);
    }

    private function token(Order $order): string
    {
        parse_str(parse_url(app(GuestOrderAccess::class)->emailUrl($order), PHP_URL_FRAGMENT), $fragment);
        return $fragment['access'];
    }

    public function test_mail_capability_opens_only_the_correct_order_without_a_session(): void
    {
        config(['app.frontend_url' => 'https://shop.test']);
        $order = $this->guestOrder(); $other = Order::factory()->create(['user_id' => null, 'guest_token_hash' => hash('sha256', str_repeat('b', 64))]); $token = $this->token($order);
        $this->assertNotSame(str_repeat('a', 64), $token);
        $this->assertStringStartsWith('https://shop.test/orders/'.$order->id.'#access=', app(GuestOrderAccess::class)->emailUrl($order));
        $this->getJson('/api/v1/order-access/'.$order->id, ['X-Order-Token' => $token])->assertOk()->assertJsonPath('order.id', $order->id);
        $this->getJson('/api/v1/order-access/'.$other->id, ['X-Order-Token' => $token])->assertForbidden();
        $this->getJson('/api/v1/order-access/'.$order->id)->assertForbidden();
    }

    public function test_repeated_opening_preserves_access_without_rotating_the_existing_checkout_key(): void
    {
        $order = $this->guestOrder(); $access = app(GuestOrderAccess::class); $url = $access->emailUrl($order);
        $this->assertSame($url, $access->emailUrl($order->fresh()));
        $this->assertTrue($access->valid($order, str_repeat('a', 64)));
        $this->assertTrue($access->valid($order, $this->token($order)));
        $this->assertSame(hash('sha256', str_repeat('a', 64)), $order->fresh()->guest_token_hash);
    }

    public function test_email_access_expires_from_order_creation_not_from_the_last_opening(): void
    {
        $order = $this->guestOrder(); $token = $this->token($order);
        $this->travel(29)->days();
        $this->getJson('/api/v1/order-access/'.$order->id, ['X-Order-Token' => $token])->assertOk();
        $this->travel(2)->days();
        $this->getJson('/api/v1/order-access/'.$order->id, ['X-Order-Token' => $token])->assertForbidden();
        $this->assertNull(app(GuestOrderAccess::class)->emailUrl($order->fresh()));
    }

    public function test_the_same_email_capability_passes_the_payment_access_check(): void
    {
        $order = $this->guestOrder();
        $this->getJson('/api/v1/orders/'.$order->id.'/payment-config', ['X-Order-Token' => $this->token($order)])
            ->assertNotFound()->assertJsonPath('message', 'Платёж не найден');
    }

    public function test_the_sent_text_contains_the_link_but_mail_history_does_not(): void
    {
        Mail::fake();
        $event = MailEvent::factory()->create(['code' => 'order.created', 'is_active' => true]);
        MailEventTemplate::factory()->create(['mail_event_id' => $event->id, 'subject' => 'Ваш заказ {{order_number}}',
            'body' => 'Заказ {{order_number}} принят.', 'is_active' => true, 'is_default' => true]);
        $order = $this->guestOrder(); $url = app(GuestOrderAccess::class)->emailUrl($order); $token = $this->token($order);
        $this->assertTrue(app(MailEventService::class)->sendMailable('order.created', 'customer@example.test', new OrderCreatedMail($order), ['order_number' => $order->number]));
        Mail::assertSent(OrderCreatedMail::class, function (OrderCreatedMail $mail) use ($url): bool {
            $mail->assertSeeInText($url)->assertSeeInText('принят.');
            return true;
        });
        $this->assertStringNotContainsString($token, json_encode(MailEventLog::firstOrFail()->toArray()));
        $this->assertStringNotContainsString($token, serialize(new \App\Events\OrderCreated($order)));
    }

    public function test_default_mail_renders_without_a_custom_template_and_legacy_orders_get_no_guest_key(): void
    {
        $order = $this->guestOrder();
        (new OrderCreatedMail($order))->assertSeeInText('Открыть заказ без регистрации:');
        $order->forceFill(['guest_token_hash' => null])->save();
        (new OrderCreatedMail($order))->assertDontSeeInText('#access=');
    }

    public function test_logging_transport_and_logging_failover_do_not_receive_the_secret_email(): void
    {
        Mail::fake();
        $order = $this->guestOrder();
        config(['mail.mailers.private-failover' => ['transport' => 'failover', 'mailers' => ['smtp', 'log']]]);
        foreach (['log', 'private-failover'] as $driver) {
            config(['mail.default' => $driver]);
            $this->assertFalse(app(MailEventService::class)->sendMailable('order.created', 'customer@example.test', new OrderCreatedMail($order)));
        }
        Mail::assertNothingSent();
        $this->assertDatabaseCount('mail_event_logs', 0);
    }
}
