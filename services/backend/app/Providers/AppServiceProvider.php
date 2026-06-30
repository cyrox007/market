<?php

namespace App\Providers;

use App\Actions\Inventory\Stock\AdjustWarehouseStockAction;
use App\Models\Shipping\ShippingLocation;
use App\Models\Inventory\ProductWarehouseStock;
use App\Observers\ProductWarehouseStockObserver;
use App\Services\Inventory\Contracts\InventorySyncInterface;
use App\Services\Inventory\Integrations\Integration1CApiService;
use App\Services\Inventory\StockAvailabilityService;
use App\Services\Inventory\StockService;
use App\Services\Shipping\Contracts\ShippingCostCalculatorInterface;
use App\Services\Shipping\Contracts\ShippingMethodProviderInterface;
use App\Services\Payment\Contracts\PaymentMethodAvailabilityInterface;
use App\Services\Shipping\ShippingCostCalculator;
use App\Services\Shipping\ShippingMethodProvider;
use App\Services\Shipping\DeliveryHandlingProvider;
use App\Services\Shipping\CarrierService;
use App\Services\Shipping\ShippingCalculationService;
use App\Services\Shipping\Contracts\DeliveryHandlingProviderInterface;
use App\Services\Payment\PaymentMethodAvailabilityService;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod as AppPaymentMethod;
use App\Models\Shipping\Carrier;
use App\Models\Shipping\AdditionalService;
use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Eloquent\Relations\Relation;
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

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрируем сервис синхронизации с 1С, если включен
        if (config('services.integration_1c.enabled', false) || config('services.onec.enabled', false)) {
            $this->app->singleton(InventorySyncInterface::class, function ($app) {
                return new Integration1CApiService();
            });
        }

        // Регистрируем StockService с опциональной синхронизацией
        $this->app->singleton(StockService::class, function ($app) {
            $sync = null;
            if (config('services.integration_1c.enabled', false) || config('services.onec.enabled', false)) {
                $sync = $app->make(InventorySyncInterface::class);
            }
            return new StockService(
                $sync,
                $app->make(AdjustWarehouseStockAction::class),
                $app->make(StockAvailabilityService::class),
            );
        });

        // Регистрируем интерфейсы для расчета стоимости доставки и оплаты
        $this->app->singleton(DeliveryHandlingProviderInterface::class, DeliveryHandlingProvider::class);
        $this->app->singleton(ShippingCostCalculatorInterface::class, function ($app) {
            return new ShippingCostCalculator(
                $app->make(CarrierService::class),
                $app->make(ShippingCalculationService::class),
                $app->make(DeliveryHandlingProviderInterface::class)
            );
        });
        $this->app->singleton(ShippingMethodProviderInterface::class, ShippingMethodProvider::class);
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
        Gate::before(function ($user, string $ability): ?bool {
            if ($user instanceof User && $user->hasRole('super_admin')) {
                return true;
            }

            return null;
        });

        ProductWarehouseStock::observe(ProductWarehouseStockObserver::class);

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
    }
}
