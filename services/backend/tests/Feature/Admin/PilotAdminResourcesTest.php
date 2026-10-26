<?php
namespace Tests\Feature\Admin;

use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotAdminResourcesTest extends TestCase
{
    public function test_manager_can_record_cash_payment_without_ending_cooling(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        \App\Models\Payment\PaymentMethod::create(['name' => 'Наличные', 'code' => 'cash', 'gateway' => 'manual', 'is_active' => true]);
        $order = \App\Models\Order\Order::factory()->create(['status' => 'manager_processing', 'cooling_until' => now()->addHour(), 'payment_method' => 'cash', 'total' => 1000]);
        Livewire::test(\App\Filament\Resources\Orders\Pages\EditOrder::class, ['record' => $order->id])
            ->callAction('mark_paid')->assertHasNoActionErrors();
        $this->assertTrue(app(\App\Services\Order\OrderCooling::class)->isPaid($order->fresh()));
        $this->assertSame('manager_processing', $order->fresh()->status);
    }

    public function test_pilot_resources_render_for_super_admin(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        Livewire::test(\App\Filament\Resources\PrivatePriceLinks\Pages\ListPrivatePriceLinks::class)->assertSuccessful();
        Livewire::test(\App\Filament\Resources\PrivatePriceLinks\Pages\CreatePrivatePriceLink::class)->assertSuccessful();
        Livewire::test(\App\Filament\Resources\CancellationRequests\Pages\ListCancellationRequests::class)->assertSuccessful();
        Livewire::test(\App\Filament\Resources\RefundTasks\Pages\ListRefundTasks::class)->assertSuccessful();
    }

    public function test_customer_cannot_manage_private_prices(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertFalse(\App\Filament\Resources\PrivatePriceLinks\PrivatePriceLinkResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\PrivatePriceLinks\PrivatePriceLinkResource::canCreate());
    }

    public function test_private_price_product_search_returns_concrete_skus(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $sku = \App\Models\Product\Product::factory()->create(['name' => 'Бруно тест поиска', 'is_variable' => false]);
        $parent = \App\Models\Product\Product::factory()->create(['name' => 'Бруно группа', 'is_variable' => true]);
        $page = Livewire::test(\App\Filament\Resources\PrivatePriceLinks\Pages\CreatePrivatePriceLink::class);
        $field = collect($page->instance()->getSchema('form')->getFlatComponents())
            ->first(fn ($component) => $component instanceof \Filament\Forms\Components\Select && $component->getName() === 'product_id');
        $results = $field->getSearchResults('Бруно');
        $this->assertArrayHasKey($sku->id, $results);
        $this->assertArrayNotHasKey($parent->id, $results);
    }
}
