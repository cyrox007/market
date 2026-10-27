<?php

namespace App\Providers;

use App\Models\Shipping\ShippingLocation;
use App\Models\Inventory\ProductWarehouseStock;
use App\Observers\ProductWarehouseStockObserver;
use App\Services\Inventory\Contracts\InventorySyncInterface;
use App\Services\Inventory\Integrations\Integration1CApiService;
use App\Services\Inventory\StockAvailabilityService;
use App\Services\Inventory\StockService;
use App\Services\Payment\Contracts\PaymentMethodAvailabilityInterface;
use App\Services\Payment\PaymentMethodAvailabilityService;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod as AppPaymentMethod;
use App\Models\Shipping\Carrier;
use App\Models\Shipping\AdditionalService;
use App\Models\Payment\PaymentMethod;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Vanilo\Payment\PaymentGateways;
use Vanilo\Payment\Gateways\NullGateway;
use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Payment\Gateways\RaiffeisenAcquiringGateway;
use App\Payment\Gateways\RaiffeisenEcomGateway;
use App\Payment\Gateways\SberbankAcquiringGateway;
use App\Services\Gateway\DatabaseGatewayLogger;
use App\Services\Payment\RaiffeisenEcomClientFactory;
use App\Services\Payment\RaiffeisenEcomRefundService;
use Filament\Forms\Components\BaseFileUpload;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрируем сервис синхронизации с 1С, если включен
        if (config('services.integration_1c.enabled', false)) {
            $this->app->singleton(InventorySyncInterface::class, function ($app) {
                return new Integration1CApiService();
            });
        }

        // Регистрируем StockService с опциональной синхронизацией
        $this->app->singleton(StockService::class, function ($app) {
            $sync = null;
            if (config('services.integration_1c.enabled', false)) {
                $sync = $app->make(InventorySyncInterface::class);
            }
            return new StockService(
                $sync,
                $app->make(StockAvailabilityService::class),
            );
        });

        // Регистрируем интерфейсы для расчета стоимости доставки и оплаты
        $this->app->singleton(PaymentMethodAvailabilityInterface::class, PaymentMethodAvailabilityService::class);
        $this->app->singleton(GatewayLoggerInterface::class, DatabaseGatewayLogger::class);
        $this->app->singleton(RaiffeisenEcomClientFactory::class);
        $this->app->singleton(RaiffeisenEcomRefundService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Session::extend('database', fn ($app) => new \App\Session\OperatorDatabaseSessionHandler(
            $app['db']->connection(config('session.connection')),
            config('session.table'),
            (int) config('session.lifetime'),
            $app,
        ));

        // Delivered only in a successful Livewire response, never on validation failure.
        \Illuminate\Support\Facades\Event::listen(\Filament\Resources\Events\RecordSaved::class, function (\Illuminate\Database\Eloquent\Model $record, array $data, \Filament\Resources\Pages\Page $page): void {
            $page->dispatch('admin-form-saved', componentId: $page->getId());
        });

        foreach ([\App\Models\Pricing\PricingRule::class, \App\Models\Pricing\PricingProductGroup::class, \App\Models\Pricing\PricingCustomerGroup::class] as $pricingModel) {
            \Illuminate\Support\Facades\Gate::policy($pricingModel, \App\Policies\PricingRulePolicy::class);
        }
        Gate::policy(\App\Models\Order\OneClickLead::class, \App\Policies\OneClickLeadPolicy::class);
        Gate::before(function ($user, string $ability): ?bool {
            if ($user instanceof User && $user->hasRole('super_admin')) {
                return true;
            }

            return null;
        });

        ProductWarehouseStock::observe(ProductWarehouseStockObserver::class);
        \Vanilo\Payment\Models\PaymentProxy::modelClass()::observe(\App\Observers\PaidPaymentIdentityObserver::class);

        // Р-4: лимиты частоты на чувствительных эндпоинтах (login — по IP и email).
        RateLimiter::for('auth-strict', fn (Request $r) => Limit::perMinute(10)->by($r->ip() . '|' . mb_strtolower(trim((string) $r->input('email')))));
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('auth-settings', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('orders', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('writes', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));

        foreach (['address-read' => 'read_requests_per_minute', 'address-geo' => 'geo_requests_per_minute'] as $name => $setting) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute(
                max(1, (int) config('address_directory.'.$setting))
            )->by($request->user() ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'message' => 'Слишком много запросов. Повторите после указанного времени ожидания.',
                    'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                ], 429, $headers)));
        }

        // Morph map для Vanilo Payment (payable_type = 'order' → Order::class)
        Relation::morphMap([
            'order' => Order::class,
        ]);

        // Модель способа оплаты — своя (payment_methods таблица проекта)
        \Konekt\Concord\Facades\Concord::registerModel(
            \Vanilo\Payment\Contracts\PaymentMethod::class,
            AppPaymentMethod::class
        );

        // Платёжные шлюзы
        PaymentGateways::register('manual', NullGateway::class);
        PaymentGateways::register('raiffeisen_acquiring', RaiffeisenAcquiringGateway::class);
        PaymentGateways::register('raiffeisen_ecom', RaiffeisenEcomGateway::class);
        PaymentGateways::register('sberbank_acquiring', SberbankAcquiringGateway::class);

        // Устанавливаем русский язык по умолчанию для приложения
        app()->setLocale('ru');

        // Явно указываем модель для параметра address в API роутах, чтобы избежать конфликта с Vanilo
        Route::bind('address', function ($value) {
            return \App\Models\Address\Address::findOrFail($value);
        });

        // Добавляем обратную связь shippingLocations для модели Carrier
        // Это необходимо для работы Filament RelationManager и других компонентов
        Carrier::resolveRelationUsing('shippingLocations', function (Carrier $carrierModel) {
            return $carrierModel->belongsToMany(
                ShippingLocation::class,
                'carrier_shipping_location',
                'carrier_id',
                'shipping_location_id'
            )->withPivot(['base_price', 'free_delivery_threshold', 'delivery_days_min', 'delivery_days_max', 'is_active', 'sort_order'])
                ->withTimestamps()
                ->orderBy('carrier_shipping_location.sort_order');
        });

        // Добавляем обратную связь shippingLocations для модели PaymentMethod
        // Это необходимо для работы Filament RelationManager и других компонентов
        PaymentMethod::resolveRelationUsing('shippingLocations', function (PaymentMethod $paymentMethodModel) {
            return $paymentMethodModel->belongsToMany(
                ShippingLocation::class,
                'payment_method_shipping_location',
                'payment_method_id',
                'shipping_location_id'
            )->withPivot(['is_active', 'sort_order'])
                ->withTimestamps()
                ->orderBy('payment_method_shipping_location.sort_order');
        });

        // Добавляем обратную связь shippingLocations для модели AdditionalService
        // Это необходимо для работы Filament RelationManager и других компонентов
        AdditionalService::resolveRelationUsing('shippingLocations', function (AdditionalService $additionalServiceModel) {
            return $additionalServiceModel->belongsToMany(
                ShippingLocation::class,
                'shipping_location_additional_service',
                'additional_service_id',
                'shipping_location_id'
            )->withPivot(['price', 'is_active', 'sort_order'])
                ->withTimestamps()
                ->orderBy('shipping_location_additional_service.sort_order');
        });

        BaseFileUpload::configureUsing(function (BaseFileUpload $component) {
            $component->appendFiles();
        });
    }
}
