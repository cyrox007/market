<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Jobs\Integration\SyncOrderTo1CJob;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\OrderStatusHistoryRelationManager;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\User;
use App\Services\Order\OperatorOrder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderOperatorWorkspaceTest extends TestCase
{
    private function operator(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        return $user;
    }

    public function test_old_bookmark_and_edit_show_one_workspace_without_legacy_fields(): void
    {
        $this->operator();
        $order = Order::factory()->create(['status' => 'manager_processing', 'cooling_until' => now()->addHour(),
            'address_snapshot' => 'Липецк, сохранённый адрес', 'fulfillment_snapshot' => ['warehouse_external_id' => 'LP00001']]);
        foreach ([EditOrder::class] as $page) {
            Livewire::test($page, ['record' => $order->id])->assertSuccessful()
                ->assertSee('Сейчас по заказу')->assertSee('Товары заказа')->assertSee('Липецк, сохранённый адрес')
                ->assertDontSee('Локация доставки')->assertDontSee('Служба доставки')
                ->assertFormFieldDoesNotExist('status')->assertFormFieldDoesNotExist('payment_method')
                ->assertActionHidden('next_step');
        }
        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->assertRedirect(\App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $order]));
    }

    public function test_order_list_and_attention_tabs_render(): void
    {
        $this->operator();
        $order = Order::factory()->create(['status' => 'assembling', 'integration_state' => 'failed']);
        Livewire::test(ListOrders::class)->assertSuccessful()->assertCanSeeTableRecords([$order])
            ->assertSee('Ошибки передачи')->assertSee('Просьбы отменить')->assertSee('Ошибка передачи');
    }

    public function test_next_action_rechecks_status_and_does_not_skip_steps(): void
    {
        $user = $this->operator();
        $order = Order::factory()->create(['status' => 'assembling']);
        $service = app(OperatorOrder::class);
        $service->advance($order->id, 'assembled', $user->id);
        $this->assertSame('assembled', $order->fresh()->status);
        $this->expectException(ValidationException::class);
        $service->advance($order->id, 'assembled', $user->id);
    }

    public function test_stale_cancel_action_cannot_cancel_after_deadline(): void
    {
        $this->operator();
        $order = Order::factory()->create(['status' => 'manager_processing', 'cooling_until' => now()->subSecond()]);
        $this->expectException(ValidationException::class);
        app(OperatorOrder::class)->cancel($order->id, 'Старая форма');
    }

    public function test_contact_save_does_not_accept_forged_order_fields(): void
    {
        $this->operator();
        $order = Order::factory()->create(['status' => 'assembling', 'address_snapshot' => 'Исходный адрес', 'comment' => 'Комментарий покупателя']);
        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->callAction(TestAction::make('edit_contacts')->schemaComponent('customer', 'form'), [
                'contact_name' => 'Уточнённое имя', 'contact_phone' => '+79001234567',
                'comment' => 'Уточнённый комментарий', 'status' => 'delivered', 'address_snapshot' => 'Подмена',
            ])->assertHasNoFormErrors();
        $this->assertSame('assembling', $order->fresh()->status);
        $this->assertSame('Исходный адрес', $order->fresh()->address_snapshot);
        $this->assertSame('Уточнённый комментарий', $order->fresh()->comment);
        $this->assertSame('Уточнённое имя', $order->fresh()->contact_name);
    }

    public function test_contact_phone_is_stored_canonical_and_validated(): void
    {
        $this->operator();
        $order = Order::factory()->create(['status' => 'assembling']);
        $action = TestAction::make('edit_contacts')->schemaComponent('customer', 'form');
        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->callAction($action, ['contact_phone' => '8 (900) 123-45-67'])->assertHasNoFormErrors()
            ->assertSee('+7 (900) 123-45-67')
            ->callAction($action, ['contact_phone' => '12345'])->assertHasFormErrors(['contact_phone']);
        $this->assertSame('+79001234567', $order->fresh()->contact_phone);
    }

    public function test_contact_change_resends_only_orders_already_sent_to_1c(): void
    {
        $this->operator();
        config(['services.integration_1c.enabled' => true]);
        Queue::fake();
        $sent = Order::factory()->create(['status' => 'assembling', 'contact_phone' => '+79001234567', 'integration_state' => 'sent', 'integration_sent_at' => now()]);
        $pending = Order::factory()->create(['status' => 'assembling', 'contact_phone' => '+79001234567']);
        foreach ([$sent, $pending] as $order) {
            Livewire::test(EditOrder::class, ['record' => $order->id])
                ->callAction(TestAction::make('edit_contacts')->schemaComponent('customer', 'form'), ['contact_name' => 'Новое имя'])
                ->assertHasNoFormErrors();
        }
        Queue::assertPushed(SyncOrderTo1CJob::class, 1);
        Queue::assertPushed(SyncOrderTo1CJob::class, fn (SyncOrderTo1CJob $job) => $job->uniqueId() === 'order:'.$sent->id);
    }

    public function test_cancellation_request_is_reviewed_on_the_order_card(): void
    {
        $this->operator();
        $order = Order::factory()->create(['status' => 'assembling']);
        $order->cancellationRequest()->create(['reason' => 'Передумал покупать', 'status' => 'new']);
        Livewire::test(EditOrder::class, ['record' => $order->id])->assertSee('Передумал покупать')
            ->callAction('review_cancellation', ['status' => 'resolved', 'resolution' => 'Отказано, заказ уже собран'])
            ->assertHasNoFormErrors()->assertActionHidden('review_cancellation');
        $this->assertSame('resolved', $order->cancellationRequest()->first()->status);
        $this->assertSame('assembling', $order->fresh()->status);
    }

    public function test_card_shows_term_promised_at_checkout(): void
    {
        $this->operator();
        $delivery = Order::factory()->create(['delivery_type' => 'delivery', 'fulfillment_snapshot' => [
            'delivery' => ['date_min' => '2026-11-20', 'date_max' => '2026-11-23', 'days_min' => 3, 'days_max' => 6]]]);
        $pickup = Order::factory()->create(['delivery_type' => 'pickup', 'fulfillment_snapshot' => [
            'ready' => ['date_min' => '2026-11-19', 'date_max' => '2026-11-19', 'days_min' => 1, 'days_max' => 1]]]);
        Livewire::test(EditOrder::class, ['record' => $delivery->id])->assertSee('Срок доставки')->assertSee('20.11.2026 – 23.11.2026 (3–6 дн.)');
        Livewire::test(EditOrder::class, ['record' => $pickup->id])->assertSee('Срок готовности')->assertSee('19.11.2026 (1 дн.)');
    }

    public function test_customer_block_links_account_and_other_orders(): void
    {
        $this->operator();
        $customer = User::factory()->create(['email' => 'buyer@example.test']);
        $order = Order::factory()->create(['user_id' => $customer->id, 'guest_checkout' => true]);
        $other = Order::factory()->create(['user_id' => $customer->id]);
        $stranger = Order::factory()->create();
        Livewire::test(EditOrder::class, ['record' => $order->id])->assertSee('buyer@example.test')->assertSee('Без авторизации')
            ->assertSee(\App\Filament\Resources\Users\UserResource::getUrl('edit', ['record' => $customer]));
        Livewire::test(ListOrders::class)->filterTable('user_id', $customer->id)
            ->assertCanSeeTableRecords([$order, $other])->assertCanNotSeeTableRecords([$stranger]);
    }

    public function test_item_name_links_to_site_product_page(): void
    {
        $this->operator();
        $order = Order::factory()->create();
        $product = \App\Models\Product\Product::factory()->create(['slug' => 'divan-test']);
        $order->items()->create(['product_type' => 'product', 'product_id' => $product->id, 'name' => 'Диван тестовый', 'quantity' => 1, 'price' => 100]);
        Livewire::test(EditOrder::class, ['record' => $order->id])->assertSeeHtml('href="'.url('/product/divan-test').'"');
    }

    public function test_status_history_is_shown_in_its_own_tab(): void
    {
        $user = $this->operator();
        $order = Order::factory()->create(['status' => 'assembling']);
        $order->changeStatus(OrderStatus::ASSEMBLED, 'Собран на складе', $user->id);
        Livewire::test(OrderStatusHistoryRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditOrder::class])
            ->assertSuccessful()->assertCanSeeTableRecords($order->statusHistory)->assertSee('Собран на складе');
    }

    public function test_print_uses_frozen_address_warehouse_and_service_costs(): void
    {
        $this->operator();
        $order = Order::factory()->create(['address_snapshot' => 'Исходный адрес печати',
            'fulfillment_snapshot' => ['warehouse_external_id' => 'LP00001', 'additional_services_cost' => 50,
                'additional_services' => [['name' => 'Упаковка', 'price' => 50]]]]);
        $this->get(route('admin.orders.print', $order->id))->assertOk()->assertSee('Исходный адрес печати')
            ->assertSee('LP00001')->assertSee('Упаковка')->assertDontSee('Локация');
    }

    public function test_unpaid_pickup_has_an_explanation_and_cannot_be_completed(): void
    {
        $user = $this->operator();
        \App\Models\Payment\PaymentMethod::create(['name' => 'Наличные', 'code' => 'operator-cash', 'gateway' => 'manual']);
        $order = Order::factory()->create(['status' => 'assembled', 'delivery_type' => 'pickup', 'payment_method' => 'operator-cash']);
        Livewire::test(EditOrder::class, ['record' => $order->id])->assertActionDisabled('next_step')
            ->assertSee('Сначала подтвердите получение оплаты');
        $this->expectException(ValidationException::class);
        app(OperatorOrder::class)->advance($order->id, 'delivered', $user->id);
    }

    public function test_read_only_employee_can_still_view_without_editing(): void
    {
        $this->operator();
        $user = User::factory()->create();
        foreach (['view orders', 'viewAny orders'] as $permission) {
            $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($user);
        $order = Order::factory()->create();
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertSuccessful()->assertSee('Товары заказа')
            ->assertDontSee('Изменить');
        $this->assertTrue(OrderStatusHistoryRelationManager::canViewForRecord($order, ViewOrder::class));
        Livewire::test(EditOrder::class, ['record' => $order->id])->assertForbidden();
    }
}
