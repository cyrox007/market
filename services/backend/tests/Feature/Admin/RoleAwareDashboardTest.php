<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ContentStatsOverviewWidget;
use App\Filament\Widgets\DashboardRoleChecks;
use App\Filament\Widgets\FinancialOverviewWidget;
use App\Filament\Widgets\LatestOrdersTableWidget;
use App\Filament\Widgets\OrdersChartWidget;
use App\Filament\Widgets\OrdersStatsOverviewWidget;
use App\Filament\Widgets\ProductsStatsOverviewWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Models\Order\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleAwareDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    public function test_dashboard_has_a_curated_layout_without_unrelated_system_widgets(): void
    {
        $page = new Dashboard();
        $this->assertSame('Рабочая панель', $page->getTitle());
        $this->assertSame(['default' => 1, 'xl' => 2], $page->getColumns());
        $this->assertSame([
            QuickActionsWidget::class,
            OrdersStatsOverviewWidget::class,
            LatestOrdersTableWidget::class,
            OrdersChartWidget::class,
            ProductsStatsOverviewWidget::class,
            ContentStatsOverviewWidget::class,
            FinancialOverviewWidget::class,
            \App\Filament\Widgets\UsersStatsWidget::class,
        ], $page->getWidgets());
    }

    public function test_only_granted_modules_expose_their_widgets(): void
    {
        Permission::findOrCreate('viewAny orders', 'web');
        $operator = User::factory()->create();
        $operator->givePermissionTo('viewAny orders');
        $this->actingAs($operator);

        $this->assertTrue(DashboardRoleChecks::canSeeOrders());
        $this->assertFalse(DashboardRoleChecks::canSeeContent());
        $this->assertFalse(DashboardRoleChecks::canSeeFinancial());
        $this->assertFalse(DashboardRoleChecks::canSeeProducts());
        $this->assertFalse(DashboardRoleChecks::canSeeUsers());

        Permission::findOrCreate('viewAny sliders', 'web');
        $operator->givePermissionTo('viewAny sliders');

        $this->assertTrue(ContentStatsOverviewWidget::canView());
        Livewire::test(ContentStatsOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Слайдеров')
            ->assertDontSee('Статей')
            ->assertDontSee('Подписчиков рассылки');
    }

    public function test_order_counters_include_old_unprocessed_orders(): void
    {
        Permission::findOrCreate('viewAny orders', 'web');
        $operator = User::factory()->create();
        $operator->givePermissionTo('viewAny orders');
        $this->actingAs($operator);

        Order::factory()->create([
            'status' => 'manager_processing',
            'created_at' => now()->subDays(5),
        ]);

        Livewire::test(OrdersStatsOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('На проверке менеджера')
            ->assertSee('1');
    }

    public function test_work_queue_includes_failures_and_pending_orders_but_not_delivered_orders(): void
    {
        $pending = Order::factory()->create(['status' => 'manager_processing', 'created_at' => now()->subDays(2)]);
        $finished = Order::factory()->create(['status' => 'delivered']);
        $failed = Order::factory()->create(['status' => 'delivered', 'integration_state' => 'failed']);

        $method = new ReflectionMethod(LatestOrdersTableWidget::class, 'getTableQuery');
        $ids = $method->invoke(new LatestOrdersTableWidget())->pluck('id')->all();

        $this->assertContains($pending->id, $ids);
        $this->assertContains($failed->id, $ids);
        $this->assertNotContains($finished->id, $ids);
        $this->assertSame($pending->id, $ids[0]);
    }

    public function test_order_amounts_are_not_mislabeled_as_paid_revenue(): void
    {
        $content = file_get_contents(app_path('Filament/Widgets/FinancialOverviewWidget.php'));
        $this->assertStringContainsString('Стоимость заказов (не оплаченная выручка)', $content);
        $this->assertStringNotContainsString("Stat::make('Выручка", $content);
    }
}
