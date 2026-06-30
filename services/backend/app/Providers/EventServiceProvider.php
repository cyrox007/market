<?php

namespace App\Providers;

use App\Events\OrderCancelled;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\ProductWarehouseStockChanged;
use App\Events\UserAutoRegistered;
use App\Listeners\Inventory\HandleOrderCancelled;
use App\Listeners\Inventory\HandleOrderCompleted;
use App\Listeners\Inventory\HandleOrderCreated;
use App\Listeners\Inventory\SyncWarehouseStockTo1C;
use App\Listeners\Integration\DispatchOrderSyncTo1C;
use App\Listeners\Integration\DispatchOrderStatusSyncTo1C;
use App\Listeners\Mail\SendOrderCancelledMail;
use App\Listeners\Mail\SendOrderCreatedMail;
use App\Listeners\Mail\SendOrderStatusChangedMail;
use App\Listeners\Mail\SendUserRegisteredMail;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // События для управления остатками
        OrderCreated::class => [
            HandleOrderCreated::class,
            DispatchOrderSyncTo1C::class,
            SendOrderCreatedMail::class,
        ],
        OrderCancelled::class => [
            HandleOrderCancelled::class,
            SendOrderCancelledMail::class,
        ],
        OrderCompleted::class => [
            HandleOrderCompleted::class,
        ],
        OrderStatusChanged::class => [
            DispatchOrderStatusSyncTo1C::class,
            SendOrderStatusChangedMail::class,
        ],
        UserAutoRegistered::class => [
            SendUserRegisteredMail::class,
        ],
        ProductWarehouseStockChanged::class => [
            SyncWarehouseStockTo1C::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
