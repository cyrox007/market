<?php
namespace Tests\Feature\Api;

use App\Models\Order\Order;
use App\Models\Order\OrderStatusHistory;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentRefund;
use App\Models\User;
use App\Services\Order\CustomerOrderPresentation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerOrderPresentationTest extends TestCase
{
    private function userOrder(array $data = []): array
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'new', 'payment_method' => 'cash', 'total' => 100, ...$data]);
        return [$user, $order];
    }

    private function payment(Order $order, float $amount = 100): object
    {
        $method = PaymentMethod::firstOrCreate(['code' => 'test_card'], ['name' => 'Карта', 'gateway' => 'sberbank_acquiring', 'is_active' => true]);
        return $order->payments()->create(['payment_method_id' => $method->id, 'status' => 'paid', 'amount' => $amount, 'amount_paid' => $amount, 'currency' => 'RUB']);
    }

    public function test_summary_is_owner_scoped_and_counts_groups_with_stable_last_order(): void
    {
        [$user, $order] = $this->userOrder();
        Order::factory()->create(['user_id' => $user->id, 'status' => 'delivered']);
        $last = Order::factory()->create(['user_id' => $user->id, 'status' => 'cancelled']);
        Order::factory()->create(['status' => 'new']);
        $this->actingAs($user)->getJson('/api/v1/orders/summary')->assertOk()
            ->assertJsonPath('total', 3)->assertJsonPath('active', 1)->assertJsonPath('completed', 1)->assertJsonPath('cancelled', 1)
            ->assertJsonPath('last_order.id', $last->id)->assertJsonMissingPath('last_order.contact_email')->assertJsonMissingPath('last_order.items');
    }

    public function test_empty_summary_and_unauthenticated_access(): void
    {
        $this->getJson('/api/v1/orders/summary')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/orders/summary')
            ->assertOk()->assertExactJson(['total' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0, 'last_order' => null]);
    }

    public function test_compact_list_is_paginated_filtered_and_excludes_detail_relations(): void
    {
        [$user, $order] = $this->userOrder();
        $last = Order::factory()->create(['user_id' => $user->id, 'status' => 'assembling']);
        Order::factory()->create(['user_id' => $user->id, 'status' => 'delivered']);
        $this->actingAs($user)->getJson('/api/v1/orders?view=compact&group=active&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.id', $last->id)
            ->assertJsonPath('data.0.presentation.group', 'active')->assertJsonMissingPath('data.0.address')->assertJsonMissingPath('data.0.status_history');
        $this->actingAs($user)->getJson('/api/v1/orders?view=compact&group=active&per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', $order->id);
        $this->actingAs($user)->getJson('/api/v1/orders?view=compact&per_page=51')->assertUnprocessable();
        $this->actingAs($user)->getJson('/api/v1/orders?group=invalid')->assertUnprocessable();
    }

    public function test_legacy_list_and_detail_keep_existing_fields_and_add_presentation(): void
    {
        [$user, $order] = $this->userOrder();
        $this->actingAs($user)->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.0.id', $order->id)
            ->assertJsonStructure(['data' => [['items', 'status_history', 'payment_status', 'presentation', 'paid_at']]])->assertJsonMissingPath('meta');
        $this->actingAs($user)->getJson('/api/v1/orders/'.$order->id)->assertOk()->assertJsonPath('order.presentation.payment.status', 'pay_on_receipt');
    }

    public function test_paid_is_a_separate_badge_with_confirmed_history_date_not_order_date(): void
    {
        [, $order] = $this->userOrder(['payment_method' => 'test_card']);
        $payment = $this->payment($order);
        $at = now()->subMinutes(5)->startOfSecond();
        $payment->history()->create(['old_status' => 'pending', 'new_status' => 'paid']);
        DB::table('payment_history')->where('payment_id', $payment->id)->update(['created_at' => $at]);
        $view = app(CustomerOrderPresentation::class)->present($order);
        $this->assertSame('paid', $view['payment']['status']);
        $this->assertSame($at->toISOString(), $view['payment']['paid_at']);
        $this->assertSame('new', $view['status']['code']);
        $this->assertNotContains('paid', array_column($view['progress'], 'code'));
    }

    public function test_payment_without_history_does_not_fabricate_date(): void
    {
        [, $order] = $this->userOrder();
        $this->payment($order);
        $view = app(CustomerOrderPresentation::class)->present($order);
        $this->assertSame('paid', $view['payment']['status']);
        $this->assertNull($view['payment']['paid_at']);
    }

    public function test_delivery_and_pickup_use_distinct_steps_without_fake_pickup_readiness(): void
    {
        [, $order] = $this->userOrder(['status' => 'assembled', 'delivery_type' => 'pickup']);
        $view = app(CustomerOrderPresentation::class)->present($order);
        $steps = collect($view['progress'])->keyBy('code');
        $this->assertSame('current', $steps['preparing']['state']);
        $this->assertSame('pending', $steps['ready_for_pickup']['state']);
        $this->assertNull($steps['ready_for_pickup']['occurred_at']);
        [, $delivered] = $this->userOrder(['status' => 'delivered', 'delivery_type' => 'pickup']);
        $this->assertSame('Получен', app(CustomerOrderPresentation::class)->present($delivered)['status']['label']);
        [, $moving] = $this->userOrder(['status' => 'in_transit', 'delivery_type' => 'delivery']);
        $movingView = app(CustomerOrderPresentation::class)->present($moving);
        $this->assertSame('Заказ в пути', $movingView['message']);
        $this->assertSame('current', collect($movingView['progress'])->keyBy('code')['shipping']['state']);
    }

    public function test_cancelled_and_refunds_do_not_imply_completed_delivery_or_returned_money(): void
    {
        [, $order] = $this->userOrder(['status' => 'cancelled']);
        $payment = $this->payment($order);
        OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'assembling']);
        OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'cancelled']);
        $view = app(CustomerOrderPresentation::class)->present($order);
        $this->assertSame('cancelled', $view['group']);
        $this->assertSame('paid', $view['payment']['status']);
        $this->assertSame('skipped', collect($view['progress'])->keyBy('code')['received']['state']);
        $this->assertNotContains('current', array_column($view['progress'], 'state'));
        PaymentRefund::create(['payment_id' => $payment->id, 'refund_id' => 'R1', 'amount' => 30, 'status' => 'completed', 'gateway' => 'sberbank_acquiring']);
        $this->assertSame('partially_refunded', app(CustomerOrderPresentation::class)->present($order->fresh())['payment']['status']);
        PaymentRefund::create(['payment_id' => $payment->id, 'refund_id' => 'R2', 'amount' => 70, 'status' => 'in_progress', 'gateway' => 'sberbank_acquiring']);
        $this->assertSame('pending', app(CustomerOrderPresentation::class)->present($order->fresh())['payment']['refund_status']);
        PaymentRefund::where('refund_id', 'R2')->update(['status' => 'completed']);
        $this->assertSame('refunded', app(CustomerOrderPresentation::class)->present($order->fresh())['payment']['status']);
    }

    public function test_unknown_logistics_status_stays_active_and_does_not_expose_an_internal_label(): void
    {
        [, $order] = $this->userOrder(['status' => 'carrier_custom_state']);
        $view = app(CustomerOrderPresentation::class)->present($order);
        $this->assertSame('active', $view['group']);
        $this->assertSame('Обрабатывается', $view['status']['label']);
        config(['customer_orders.statuses.carrier_custom_state' => ['label' => 'На сортировке', 'step' => 'shipping']]);
        $this->assertSame('На сортировке', app(CustomerOrderPresentation::class)->present($order)['status']['label']);
    }
    public function test_compact_page_query_count_does_not_grow_per_order(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(12)->create(['user_id' => $user->id]);
        $this->actingAs($user);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->getJson('/api/v1/orders?view=compact&per_page=1')->assertOk();
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->actingAs($user)->getJson('/api/v1/orders?view=compact&per_page=12')->assertOk()->assertJsonCount(12, 'data');
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($small + 2, $large);
    }

    public function test_new_logistics_code_keeps_latest_known_progress_and_can_be_configured(): void
    {
        [, $order] = $this->userOrder(['status' => 'carrier_custom_state', 'delivery_type' => 'delivery']);
        OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'shipped']);
        $view = app(CustomerOrderPresentation::class)->present($order);
        $this->assertSame('current', collect($view['progress'])->keyBy('code')['shipping']['state']);
        $this->assertSame('pending', collect($view['progress'])->keyBy('code')['received']['state']);
    }

}
