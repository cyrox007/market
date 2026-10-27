<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\Order\OperatorOrder;
use Filament\Facades\Filament;
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
        $order = Order::factory()->create(['status' => 'assembling', 'address_snapshot' => 'Исходный адрес']);
        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->fillForm(['contact_name' => 'Уточнённое имя', 'contact_phone' => '+79001234567'])
            ->set('data.status', 'delivered')->set('data.address_snapshot', 'Подмена')->call('save')->assertHasNoFormErrors();
        $this->assertSame('assembling', $order->fresh()->status);
        $this->assertSame('Исходный адрес', $order->fresh()->address_snapshot);
        $this->assertSame('Уточнённое имя', $order->fresh()->contact_name);
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
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertSuccessful()->assertSee('Товары заказа');
        Livewire::test(EditOrder::class, ['record' => $order->id])->assertForbidden();
    }
}
