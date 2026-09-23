<?php

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\Validation\ValidateStockAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Address\Address;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Order\OrderStatus;
use App\Models\Product\Product;
use App\Models\Payment\PaymentMethod;
use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use App\Services\Product\ProductRegionRuleService;
use App\Services\Shipping\Contracts\ShippingCostCalculatorInterface;
use App\Services\Shipping\Contracts\ShippingMethodProviderInterface;
use App\Services\Payment\Contracts\PaymentMethodAvailabilityInterface;
use App\Services\Shipping\ShippingCalculationService;
use App\Services\Shipping\CarrierService;
use App\Services\Shipping\WarehouseDeliveryOptionsService;
use App\Models\Shipping\AdditionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Events\OrderCreated;
use App\Events\UserAutoRegistered;
use App\Jobs\CancelUnpaidOrderJob;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Vanilo\Cart\Facades\Cart;
use Vanilo\Payment\Factories\PaymentFactory;
use Vanilo\Payment\PaymentGateways;
use Vanilo\Shipment\Models\ShippingMethod;
use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Services\Inventory\StockAvailabilityService;
use App\Payment\Gateways\RaiffeisenAcquiringGateway;
use App\Payment\Gateways\RaiffeisenEcomGateway;
use App\Payment\Gateways\SberbankAcquiringGateway;

class OrderController extends Controller
{
    private function buildAddressSnapshot(?Address $address, ?array $rawAddress, ?ShippingLocation $shippingLocation): ?string
    {
        if ($address) {
            return $address->full_address;
        }

        if (!$rawAddress) {
            return null;
        }

        $parts = array_filter([
            $rawAddress['city'] ?? null,
            $rawAddress['street'] ?? null,
            !empty($rawAddress['house']) ? ('д. ' . $rawAddress['house']) : null,
            !empty($rawAddress['apartment']) ? ('кв. ' . $rawAddress['apartment']) : null,
            !empty($rawAddress['entrance']) ? ('подъезд ' . $rawAddress['entrance']) : null,
        ], fn ($part) => filled($part));

        $snapshot = implode(', ', $parts);

        if ($shippingLocation?->name) {
            $snapshot .= ($snapshot ? PHP_EOL : '') . 'Локация доставки: ' . $shippingLocation->name;
        }

        return $snapshot ?: null;
    }

    private function normalizeAddressField(?string $value): string
    {
        return mb_strtolower(trim((string) ($value ?? '')));
    }

    /**
     * Базовый URL витрины для success/fail URL (как в SberbankAcquiringGateway).
     */
    private function resolveFrontendBaseUrl(): string
    {
        $url = rtrim((string) config('app.frontend_url', ''), '/');
        if ($url !== '') {
            return $url;
        }

        return rtrim((string) env('APP_FRONTEND_URL', env('FRONTEND_URL', request()->getSchemeAndHttpHost())), '/');
    }

    public function __construct(
        protected ValidateStockAction $validateStockAction,
        protected ShippingCalculationService $shippingService,
        protected CarrierService $carrierService,
        protected ProductRegionRuleService $regionRuleService,
        protected ShippingCostCalculatorInterface $shippingCostCalculator,
        protected ShippingMethodProviderInterface $shippingMethodProvider,
        protected PaymentMethodAvailabilityInterface $paymentMethodAvailability,
        protected GatewayLoggerInterface $gatewayLog,
        protected StockAvailabilityService $stockAvailabilityService,
        protected WarehouseDeliveryOptionsService $warehouseDeliveryOptionsService
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $user = \Illuminate\Support\Facades\Auth::user() ?? $request->user();

        if (!$user) {
            return response()->json(['message' => 'Необходима авторизация'], 401);
        }

        $query = Order::with([
            'items.product',
            'address',
            'statusHistory',
            'region',
            'shippingLocation',
            'deliveryWarehouse',
            'warehouseDeliveryMethod',
            'shippingMethod.carrier',
            'deliveryHandlingType',
            'additionalServices'
        ])
            ->orderBy('created_at', 'desc')
            ->where('user_id', $user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->get();

        return OrderResource::collection($orders);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            if (Cart::isEmpty()) {
                return response()->json([
                    'message' => 'Корзина пуста',
                ], 422);
            }

            // Обрабатываем адрес если передан как JSON строка в query параметре
            $requestData = $request->all();
            if (isset($requestData['address']) && is_string($requestData['address'])) {
                $decodedAddress = json_decode($requestData['address'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedAddress)) {
                    $requestData['address'] = $decodedAddress;
                    $request->merge($requestData);
                }
            }

            $validated = $request->validate([
                'contact_name' => 'required|string|max:255',
                'contact_phone' => 'required|string|max:20',
                'contact_email' => 'required|email|max:255',
                'payment_method' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        if (!PaymentMethod::where('code', $value)->active()->exists()) {
                            $fail('Выбранный метод оплаты недоступен.');
                        }
                    },
                ],
                'delivery_type' => 'required|in:delivery,pickup',

                // Поля для новой системы доставки (сохраняем даже для pickup для информации)
                'shipping_location_id' => 'nullable|exists:shipping_locations,id',
                'region_id' => 'nullable|exists:shipping_locations,id',
                'shipping_method_id' => 'nullable|integer',
                'delivery_warehouse_id' => 'nullable|exists:warehouses,id',
                'warehouse_delivery_method_id' => 'nullable|exists:warehouse_delivery_methods,id',
                'delivery_handling_type_id' => 'nullable|exists:delivery_handling_types,id',
                'delivery_floor' => 'nullable|integer|min:1|max:20',
                'requires_assembly' => 'nullable|boolean',
                'additional_services' => 'nullable|array',
                'additional_services.*.id' => 'required|exists:additional_services,id',
                'additional_services.*.price' => 'nullable|numeric|min:0',

                // Старые поля (для обратной совместимости)
                'delivery_date' => 'nullable|date',
                'delivery_time' => 'nullable|string|max:255',
                'address_id' => 'nullable|exists:user_addresses,id',
                'delivery_cost' => 'nullable|numeric|min:0',
                'assembly_cost' => 'nullable|numeric|min:0',
                'comment' => 'nullable|string|max:1000',

                // Адрес доставки (может быть передан для любого типа доставки)
                'address' => 'nullable|array',
                'address.city' => 'required_with:address|string|max:255',
                'address.street' => 'required_with:address|string|max:255',
                'address.house' => 'required_with:address|string|max:255',
                'address.apartment' => 'nullable|string|max:255',
                'address.entrance' => 'nullable|string|max:255',
            ]);

            // Для delivery shipping_location_id обязателен
            if ($validated['delivery_type'] === 'delivery' && empty($validated['shipping_location_id'])) {
                return response()->json([
                    'message' => 'Для доставки необходимо указать локацию доставки',
                    'errors' => ['shipping_location_id' => ['Поле локация доставки обязательно для типа доставки: delivery']],
                ], 422);
            }

            if ($validated['delivery_type'] === 'pickup' && !empty($validated['shipping_location_id'])) {
                $pickupLocation = ShippingLocation::where('id', $validated['shipping_location_id'])
                    ->where('is_active', true)
                    ->first();

                if ($pickupLocation && !$pickupLocation->getEffectivePickupEnabled()) {
                    return response()->json([
                        'message' => 'Самовывоз недоступен для выбранной локации',
                        'errors' => ['shipping_location_id' => ['Для этой локации самовывоз отключен']],
                    ], 422);
                }
            }

            // Проверяем существование shipping_method_id только если он указан и тип доставки delivery
            if (isset($validated['shipping_method_id']) && $validated['delivery_type'] === 'delivery') {
                $shippingMethodExists = \Vanilo\Shipment\Models\ShippingMethod::where('id', $validated['shipping_method_id'])->exists();
                if (!$shippingMethodExists) {
                    return response()->json([
                        'message' => 'Указанный метод доставки не найден',
                        'errors' => ['shipping_method_id' => ['Метод доставки с ID ' . $validated['shipping_method_id'] . ' не существует']],
                    ], 422);
                }
            }

            $cartItems = Cart::getItems();
            $subtotal = (float) Cart::total();
            $deliveryItems = collect($cartItems)
                ->map(function ($cartItem): ?array {
                    $productId = $cartItem->buyable?->id ?? ($cartItem->product_id ?? null);
                    $quantity = (float) ($cartItem->quantity ?? 0);

                    if (! $productId || $quantity <= 0) {
                        return null;
                    }

                    return [
                        'product_id' => (int) $productId,
                        'quantity' => $quantity,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            // Получаем регион для применения правил корзины
            $region = null;
            if (isset($validated['region_id'])) {
                $region = ShippingLocation::where('id', $validated['region_id'])
                    ->where('is_active', true)
                    ->first();
            }

            // Проверка остатков
            $stockValidation = $this->validateStockAction->execute(isset($validated['shipping_location_id']) ? (int) $validated['shipping_location_id'] : null);
            if (!$stockValidation['valid']) {
                return response()->json([
                    'message' => 'Недостаточно товаров на складе',
                    'errors' => $stockValidation['errors'],
                ], 422);
            }

            // Автоматическая регистрация пользователя, если не авторизован
            $user = $request->user();
            $autoRegistered = false;
            $autoRegisteredPassword = null;

            if (!$user) {
                // Проверяем, существует ли пользователь с таким email
                $existingUser = User::where('email', $validated['contact_email'])->first();

                if (!$existingUser) {
                    // Создаем нового пользователя со случайным паролем
                    $autoRegisteredPassword = Str::random(12);
                    $user = User::create([
                        'name' => $validated['contact_name'],
                        'email' => $validated['contact_email'],
                        'password' => $autoRegisteredPassword,
                        'phone' => $validated['contact_phone'] ?? null,
                    ]);

                    // Автоматически авторизуем пользователя
                    Auth::login($user);
                    $autoRegistered = true;

                    // Отправляем событие автоматической регистрации
                    event(new UserAutoRegistered($user, $autoRegisteredPassword));
                } else {
                    // Р-2: привязываем заказ к владельцу email, но БЕЗ входа в его сессию.
                    $user = $existingUser;
                }
            }

            // Проверка и расчет доставки для delivery
            $deliveryCost = 0;
            $assemblyCost = 0;
            $shippingLocation = null;
            $shippingMethod = null;
            $deliveryHandlingType = null;
            $deliveryDaysMin = null;
            $deliveryDaysMax = null;
            $deliveryBasePrice = null;
            $deliveryFreeThreshold = null;
            $deliveryWarehouseId = null;
            $warehouseDeliveryMethodId = null;

            if ($validated['delivery_type'] === 'delivery') {
                // Получаем локацию доставки
                $shippingLocation = ShippingLocation::findOrFail($validated['shipping_location_id']);

                // Проверяем доступность доставки
                $availability = $this->shippingService->checkDeliveryAvailability(
                    $shippingLocation,
                    $subtotal
                );

                if (!$availability['available']) {
                    return response()->json([
                        'message' => 'Доставка недоступна',
                        'errors' => ['shipping_location_id' => $availability['reasons']],
                    ], 422);
                }

                // Получаем тип обработки доставки если указан
                $deliveryHandlingType = null;
                if (isset($validated['delivery_handling_type_id'])) {
                    $deliveryHandlingType = DeliveryHandlingType::find($validated['delivery_handling_type_id']);

                    // Проверяем, требуется ли этаж для этого типа обработки
                    if ($deliveryHandlingType && $deliveryHandlingType->requires_floor) {
                        // Для типов, требующих этаж, этаж обязателен и не может быть null
                        if (!isset($validated['delivery_floor']) || $validated['delivery_floor'] === null) {
                            return response()->json([
                                'message' => 'Для выбранного типа обработки доставки обязательно требуется указать этаж',
                                'errors' => ['delivery_floor' => ['Поле этаж обязательно для типа обработки: ' . $deliveryHandlingType->name . '. Минимальный этаж: 1']],
                            ], 422);
                        }
                        // Проверяем, что этаж в допустимом диапазоне
                        if (isset($validated['delivery_floor']) && $deliveryHandlingType->max_floor && $validated['delivery_floor'] > $deliveryHandlingType->max_floor) {
                            return response()->json([
                                'message' => 'Этаж превышает максимально допустимый',
                                'errors' => ['delivery_floor' => ["Максимальный этаж для данного типа обработки: {$deliveryHandlingType->max_floor}"]],
                            ], 422);
                        }
                        // Проверяем, что этаж не меньше 1
                        if (isset($validated['delivery_floor']) && $validated['delivery_floor'] < 1) {
                            return response()->json([
                                'message' => 'Этаж должен быть не менее 1',
                                'errors' => ['delivery_floor' => ['Этаж должен быть не менее 1']],
                            ], 422);
                        }
                    }
                }

                $warehouseOption = null;
                $hasConfiguredDeliveryMethods = $this->warehouseDeliveryOptionsService
                    ->hasConfiguredMethodsForLocation($shippingLocation);

                if ($hasConfiguredDeliveryMethods) {
                    $warehouseOptions = $this->warehouseDeliveryOptionsService
                        ->resolveForLocation($shippingLocation, $deliveryItems, $subtotal);

                    if ($warehouseOptions->isEmpty()) {
                        return response()->json([
                            'message' => 'Нет способа доставки, который может обслужить весь состав заказа',
                            'errors' => [
                                'warehouse_delivery_method_id' => [
                                    'Измените состав заказа или выберите другую локацию доставки',
                                ],
                            ],
                        ], 422);
                    }

                    if (isset($validated['warehouse_delivery_method_id'])) {
                        $warehouseOption = $warehouseOptions->firstWhere(
                            'warehouse_delivery_method_id',
                            (int) $validated['warehouse_delivery_method_id']
                        );
                    } elseif (isset($validated['delivery_warehouse_id'], $validated['shipping_method_id'])) {
                        $warehouseOption = $warehouseOptions->first(
                            fn (array $option): bool =>
                                $option['warehouse_id'] === (int) $validated['delivery_warehouse_id']
                                && $option['shipping_method_id'] === (int) $validated['shipping_method_id']
                        );
                    } elseif (isset($validated['delivery_warehouse_id'])) {
                        $warehouseOption = $warehouseOptions->firstWhere(
                            'warehouse_id',
                            (int) $validated['delivery_warehouse_id']
                        );
                    } elseif (isset($validated['shipping_method_id'])) {
                        $warehouseOption = $warehouseOptions->firstWhere(
                            'shipping_method_id',
                            (int) $validated['shipping_method_id']
                        );
                    } else {
                        $warehouseOption = $warehouseOptions->first();
                    }

                    if (! $warehouseOption) {
                        return response()->json([
                            'message' => 'Выбранный вариант доставки недоступен для этого заказа',
                            'errors' => [
                                'warehouse_delivery_method_id' => [
                                    'Выберите один из вариантов доставки, рассчитанных сервером',
                                ],
                            ],
                        ], 422);
                    }

                    $deliveryWarehouseId = (int) $warehouseOption['warehouse_id'];
                    $warehouseDeliveryMethodId = (int) $warehouseOption['warehouse_delivery_method_id'];
                    $shippingMethod = ShippingMethod::findOrFail($warehouseOption['shipping_method_id']);
                    $shippingMethod->load('carrier');
                }

                if (! $warehouseOption && isset($validated['shipping_method_id'])) {
                    $shippingMethod = ShippingMethod::findOrFail($validated['shipping_method_id']);
                    $shippingMethod->load('carrier');

                    $allowedIds = $this->shippingMethodProvider
                        ->getAvailableShippingMethods($shippingLocation)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    if (! in_array((int) $validated['shipping_method_id'], $allowedIds, true)) {
                        Log::warning('order.checkout: shipping_method not allowed for location', [
                            'shipping_method_id' => $validated['shipping_method_id'],
                            'shipping_location_id' => $shippingLocation->id,
                            'allowed_method_ids' => $allowedIds,
                            'user_id' => $request->user()?->id,
                        ]);

                        return response()->json([
                            'message' => 'Выбранный способ доставки недоступен для этой локации',
                            'errors' => [
                                'shipping_method_id' => [
                                    'Укажите один из способов доставки, показанных для выбранной локации',
                                ],
                            ],
                        ], 422);
                    }
                }

                if ($warehouseOption) {
                    $calculation = $this->shippingCostCalculator->calculateForLocation(
                        $shippingLocation,
                        $subtotal,
                        $deliveryHandlingType,
                        $validated['delivery_floor'] ?? null,
                        (bool) ($validated['requires_assembly'] ?? false)
                    );

                    $deliveryBasePrice = (float) $warehouseOption['delivery_base_price'];
                    $deliveryFreeThreshold = $warehouseOption['free_delivery_threshold'];
                    $deliveryCost = (float) $warehouseOption['delivery_price']
                        + (float) ($calculation->handlingPrice ?? 0);
                    $assemblyCost = $calculation->assemblyPrice ?? 0.0;
                    $deliveryDaysMin = $warehouseOption['delivery_days_min'];
                    $deliveryDaysMax = $warehouseOption['delivery_days_max'];
                } elseif ($shippingMethod) {
                    $calculation = $this->shippingCostCalculator->calculateForMethod(
                        $shippingMethod,
                        $shippingLocation,
                        $subtotal,
                        $deliveryHandlingType,
                        $validated['delivery_floor'] ?? null,
                        (bool) ($validated['requires_assembly'] ?? false)
                    );

                    $deliveryCost = $calculation->getDeliveryTotal();
                    $assemblyCost = $calculation->assemblyPrice ?? 0.0;
                    $deliveryDaysMin = $calculation->deliveryDaysMin;
                    $deliveryDaysMax = $calculation->deliveryDaysMax;
                    $deliveryBasePrice = $calculation->basePrice;
                    $deliveryFreeThreshold = $calculation->freeDeliveryThreshold;
                } else {
                    $calculation = $this->shippingCostCalculator->calculateForLocation(
                        $shippingLocation,
                        $subtotal,
                        $deliveryHandlingType,
                        $validated['delivery_floor'] ?? null,
                        (bool) ($validated['requires_assembly'] ?? false)
                    );

                    $deliveryCost = $calculation->getDeliveryTotal();
                    $assemblyCost = $calculation->assemblyPrice ?? 0.0;
                    $deliveryDaysMin = $calculation->deliveryDaysMin;
                    $deliveryDaysMax = $calculation->deliveryDaysMax;
                    $deliveryBasePrice = $calculation->basePrice;
                    $deliveryFreeThreshold = $calculation->freeDeliveryThreshold;
                }

                // Рассчитываем стоимость сборки по "эффективному" флагу:
                // - если requires_assembly передан в запросе → используем его
                // - иначе берём дефолт из локации (наследование)
                $requiresAssemblyEffective = (bool) ($validated['requires_assembly'] ?? $shippingLocation->getEffectiveRequiresAssembly());
                if ($requiresAssemblyEffective) {
                    $assemblyPrice = $shippingLocation->getEffectiveAssemblyPrice();
                    if ($assemblyPrice !== null) {
                        $assemblyCost = (float) $assemblyPrice;
                    }
                }

            } else {
                // Для самовывоза доставка бесплатна
                $deliveryCost = 0;
                $deliveryDaysMin = null;
                $deliveryDaysMax = null;
                $deliveryBasePrice = null;
                $deliveryFreeThreshold = null;

                Log::info('order.checkout: pickup delivery cost forced to zero', [
                    'shipping_location_id' => $validated['shipping_location_id'] ?? null,
                ]);

                // При самовывозе получаем локацию для расчета сборки и обработки (если указана)
                if (isset($validated['shipping_location_id'])) {
                    $shippingLocation = ShippingLocation::find($validated['shipping_location_id']);

                    if ($shippingLocation && !filled($shippingLocation->pickup_notice)) {
                        Log::debug('order.checkout: pickup without location-specific notice, using default UI text', [
                            'shipping_location_id' => $shippingLocation->id,
                        ]);
                    }

                    // Используем интерфейс для расчета сборки и обработки
                    $calculation = $this->shippingCostCalculator->calculateForLocation(
                        $shippingLocation,
                        $subtotal,
                        isset($validated['delivery_handling_type_id']) ? DeliveryHandlingType::find($validated['delivery_handling_type_id']) : null,
                        $validated['delivery_floor'] ?? null,
                        (bool) ($validated['requires_assembly'] ?? false)
                    );

                    // Для pickup обработка добавляется к assembly_cost
                    $assemblyCost = ($calculation->assemblyPrice ?? 0.0) + ($calculation->handlingPrice ?? 0.0);


                    // Проверяем тип обработки для валидации этажа
                    if (isset($validated['delivery_handling_type_id'])) {
                        $deliveryHandlingType = DeliveryHandlingType::find($validated['delivery_handling_type_id']);
                        if ($deliveryHandlingType && $deliveryHandlingType->requires_floor) {
                            // Для типов, требующих этаж, этаж обязателен и не может быть null
                            if (!isset($validated['delivery_floor']) || $validated['delivery_floor'] === null) {
                                return response()->json([
                                    'message' => 'Для выбранного типа обработки доставки обязательно требуется указать этаж',
                                    'errors' => ['delivery_floor' => ['Поле этаж обязательно для типа обработки: ' . $deliveryHandlingType->name . '. Минимальный этаж: 1']],
                                ], 422);
                            }
                            // Проверяем, что этаж в допустимом диапазоне
                            if (isset($validated['delivery_floor']) && $deliveryHandlingType->max_floor && $validated['delivery_floor'] > $deliveryHandlingType->max_floor) {
                                return response()->json([
                                    'message' => 'Этаж превышает максимально допустимый',
                                    'errors' => ['delivery_floor' => ["Максимальный этаж для данного типа обработки: {$deliveryHandlingType->max_floor}"]],
                                ], 422);
                            }
                            // Проверяем, что этаж не меньше 1
                            if (isset($validated['delivery_floor']) && $validated['delivery_floor'] < 1) {
                                return response()->json([
                                    'message' => 'Этаж должен быть не менее 1',
                                    'errors' => ['delivery_floor' => ['Этаж должен быть не менее 1']],
                                ], 422);
                            }
                        }
                    }
                } else {
                    // Без выбранной локации сервер не может рассчитать сборку/обработку.
                    $assemblyCost = 0;
                }

                if (isset($validated['shipping_method_id'])) {
                    // Для pickup проверяем существование, но не требуем его
                    $shippingMethod = ShippingMethod::find($validated['shipping_method_id']);
                    // Если метод не найден, просто игнорируем его для pickup
                }
                if (isset($validated['delivery_handling_type_id']) && !isset($deliveryHandlingType)) {
                    $deliveryHandlingType = DeliveryHandlingType::find($validated['delivery_handling_type_id']);
                }
            }

            $addressId = $validated['address_id'] ?? null;
            $resolvedAddress = $addressId ? Address::find($addressId) : null;
            $addressSnapshot = $this->buildAddressSnapshot(
                $resolvedAddress,
                $validated['address'] ?? null,
                $shippingLocation
            );

            // Стоимость доставки и сборки всегда фиксируется из серверного расчёта.
            // Legacy-поля delivery_cost/assembly_cost принимаются для совместимости, но не являются источником цены.
            $finalDeliveryCost = $validated['delivery_type'] === 'pickup' ? 0.0 : (float) $deliveryCost;
            $finalAssemblyCost = (float) $assemblyCost;

            $paymentMethodModel = PaymentMethod::where('code', $validated['payment_method'])->first();
            if (!$paymentMethodModel) {
                return response()->json(['message' => 'Выбранный метод оплаты недоступен.'], 422);
            }

            // Онлайн-оплата → ожидание оплаты; самовывоз/другие способы → сразу принят
            $isOnlinePayment = in_array((string) $paymentMethodModel->gateway, ['raiffeisen_acquiring', 'raiffeisen_ecom', 'sberbank_acquiring'], true);
            $initialStatus = $isOnlinePayment ? OrderStatus::AWAITING_PAYMENT : OrderStatus::ACCEPTED;

            $gatewayClientConfig = null;

            DB::beginTransaction();
            try {
                $order = Order::create([
                    'user_id' => $user?->id,
                    'status' => $initialStatus->value,
                    'subtotal' => 0, // Будет пересчитано ниже
                    'delivery_cost' => $finalDeliveryCost,
                    'assembly_cost' => $finalAssemblyCost,
                    'payment_method' => $validated['payment_method'],
                    'delivery_type' => $validated['delivery_type'],
                    'delivery_date' => $validated['delivery_date'] ?? null,
                    'delivery_time' => $validated['delivery_time'] ?? null,
                    'contact_name' => $validated['contact_name'],
                    'contact_phone' => $validated['contact_phone'],
                    'contact_email' => $validated['contact_email'],
                    'address_id' => $addressId,
                    'address_snapshot' => $addressSnapshot,
                    'comment' => $validated['comment'] ?? null,
                    // Новые поля для системы доставки (сохраняем для обоих типов, если указаны)
                    'shipping_location_id' => $shippingLocation?->id,
                    'delivery_warehouse_id' => $deliveryWarehouseId,
                    'warehouse_delivery_method_id' => $warehouseDeliveryMethodId,
                    'region_id' => $region?->id, // Сохраняем регион для применения правил корзины
                    'shipping_method_id' => $shippingMethod?->id,
                    'delivery_handling_type_id' => $deliveryHandlingType?->id,
                    'delivery_floor' => $validated['delivery_floor'] ?? null,
                    'requires_assembly' => $validated['requires_assembly'] ?? ($shippingLocation?->getEffectiveRequiresAssembly() ?? false),
                    // Данные о доставке из конфигурации метода доставки
                    'delivery_days_min' => $deliveryDaysMin ?? null,
                    'delivery_days_max' => $deliveryDaysMax ?? null,
                    'delivery_base_price' => $deliveryBasePrice ?? null,
                    'delivery_free_threshold' => $deliveryFreeThreshold ?? null,
                ]);

            // Создаем адрес доставки если передан (для любого типа доставки)
            if (!$addressId && isset($validated['address'])) {
                $addressData = $validated['address'];

                // Если пользователь авторизован, создаем адрес в его профиле
                if ($user) {
                    $address = $request->user()->addresses()
                        ->get()
                        ->first(function ($existing) use ($addressData, $shippingLocation) {
                            return $this->normalizeAddressField($existing->city) === $this->normalizeAddressField($addressData['city'] ?? null)
                                && $this->normalizeAddressField($existing->street) === $this->normalizeAddressField($addressData['street'] ?? null)
                                && $this->normalizeAddressField($existing->house) === $this->normalizeAddressField($addressData['house'] ?? null)
                                && $this->normalizeAddressField($existing->apartment) === $this->normalizeAddressField($addressData['apartment'] ?? null)
                                && $this->normalizeAddressField($existing->entrance) === $this->normalizeAddressField($addressData['entrance'] ?? null)
                                && (int) ($existing->shipping_location_id ?? 0) === (int) ($shippingLocation?->id ?? 0);
                        });

                    if (!$address) {
                        $address = $request->user()->addresses()->create([
                            'title' => $validated['delivery_type'] === 'delivery' ? 'Адрес доставки' : 'Адрес для самовывоза',
                            'city' => $addressData['city'],
                            'street' => $addressData['street'],
                            'house' => $addressData['house'],
                            'apartment' => $addressData['apartment'] ?? null,
                            'entrance' => $addressData['entrance'] ?? null,
                            'is_default' => false,
                            'shipping_location_id' => $shippingLocation?->id,
                        ]);
                    }
                    $addressId = $address->id;
                    $resolvedAddress = $address;
                } else {
                    // Для неавторизованных пользователей создаем адрес без user_id
                    $address = Address::create([
                        'user_id' => null,
                        'title' => $validated['delivery_type'] === 'delivery' ? 'Адрес доставки' : 'Адрес для самовывоза',
                        'city' => $addressData['city'],
                        'street' => $addressData['street'],
                        'house' => $addressData['house'],
                        'apartment' => $addressData['apartment'] ?? null,
                        'entrance' => $addressData['entrance'] ?? null,
                        'is_default' => false,
                        'shipping_location_id' => $shippingLocation?->id,
                    ]);
                    $addressId = $address->id;
                    $resolvedAddress = $address;
                }

                // Обновляем заказ с адресом
                $order->address_id = $addressId;
                $order->address_snapshot = $this->buildAddressSnapshot($resolvedAddress, $validated['address'] ?? null, $shippingLocation);
                $order->save();
            }

            // Рассчитываем subtotal из корзины с учетом региональных правил
            $calculatedSubtotal = 0;
            foreach ($cartItems as $cartItem) {
                // Получаем продукт из buyable или по product_id
                $product = $cartItem->buyable;

                // Если buyable не доступен, получаем продукт по product_id
                if (!$product && isset($cartItem->product_id)) {
                    $product = Product::find($cartItem->product_id);
                }

                // Если продукт все еще не найден, пропускаем этот элемент
                if (!$product) {
                    continue;
                }

                // Определяем, является ли продукт вариацией
                $variant = $product->isVariant() ? $product : null;

                // Получаем родительский продукт, загружая связь при необходимости
                if ($product->isVariant()) {
                    if (!$product->relationLoaded('parentProduct') && $product->parent_product_id) {
                        $product->load('parentProduct');
                    }
                    $parentProduct = $product->parentProduct ?? $product;
                } else {
                    $parentProduct = $product;
                }

                // Применяем региональные правила для цены (только если регион указан)
                if ($region) {
                    $price = $this->regionRuleService->getPriceForRegion($parentProduct, $region, $variant);
                    if ($price === null) {
                        // Если правило не найдено, используем базовую цену
                        $price = $variant ? (float) $variant->price : (float) $product->price ?? (float) $cartItem->price ?? 0;
                    }
                } else {
                    // Если регион не указан, используем базовую цену
                    $price = $variant ? (float) $variant->price : (float) $product->price ?? (float) $cartItem->price ?? 0;
                }

                $quantity = $cartItem->quantity;
                $total = $price * $quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $total,
                ]);

                $calculatedSubtotal += $total;
            }

            // Сохраняем дополнительные услуги
            $additionalServicesTotal = 0;
            if (isset($validated['additional_services']) && is_array($validated['additional_services'])) {
                foreach ($validated['additional_services'] as $serviceData) {
                    $service = AdditionalService::find($serviceData['id']);
                    if ($service && $service->isAvailableForLocation($shippingLocation)) {
                        // Определяем цену услуги
                        $servicePrice = null;
                        if (isset($serviceData['price']) && $serviceData['price'] !== null) {
                            // Если цена передана явно (для custom типа или переопределения)
                            $servicePrice = (float) $serviceData['price'];
                        } else {
                            // Используем цену из услуги или pivot
                            $servicePrice = $service->getPriceForLocation($shippingLocation);
                        }

                        // Сохраняем услугу в заказ только если цена определена (для fixed и from типов)
                        // Для custom типа цена должна быть указана явно
                        if ($servicePrice !== null || $service->price_type === 'custom') {
                            $order->additionalServices()->attach($service->id, [
                                'service_name' => $service->name,
                                'price' => $servicePrice ?? 0,
                                'price_type' => $service->price_type,
                                'icon' => $service->icon,
                            ]);

                            if ($servicePrice !== null) {
                                $additionalServicesTotal += $servicePrice;
                            }
                        }
                    }
                }
            }

            // Обновляем subtotal и пересчитываем total (включая дополнительные услуги)
            $order->subtotal = $calculatedSubtotal;
            $order->total = $calculatedSubtotal + $finalDeliveryCost + $finalAssemblyCost + $additionalServicesTotal;
            $order->save();

            $order->statusHistory()->create([
                'status' => $initialStatus->value,
                'comment' => $initialStatus === OrderStatus::AWAITING_PAYMENT ? 'Заказ создан, ожидание оплаты' : 'Заказ создан, принят к исполнению',
                'user_id' => $user?->id,
            ]);

            // Инициация платежа ДОЛЖНА происходить до коммита/событий:
            // если банк недоступен — не создаём заказ (пользователь нажимает "оплатить" ещё раз).
            if ($paymentMethodModel->gateway && PaymentGateways::getClass($paymentMethodModel->gateway)) {
                $payment = PaymentFactory::createFromPayable($order, $paymentMethodModel);
                $this->gatewayLog->log(
                    $paymentMethodModel->gateway,
                    'payment_initiated',
                    'Создан платёж по заказу ' . $order->number . ', сумма ' . (float) $order->total . ' ₽',
                    [
                        'order_id' => $order->id,
                        'order_number' => $order->number,
                        'amount' => (float) $order->total,
                        'contact_email' => $order->contact_email,
                        'contact_name' => $order->contact_name,
                    ],
                    $order,
                    'payment',
                    'info'
                );

                $gateway = $paymentMethodModel->getGateway();
                if ($gateway instanceof RaiffeisenAcquiringGateway) {
                    $gatewayClientConfig = $gateway->getClientConfig($paymentMethodModel);
                }
                if ($gateway instanceof RaiffeisenEcomGateway) {
                    $gatewayClientConfig = $gateway->getClientConfig($paymentMethodModel, $order);
                }
                if ($gateway instanceof SberbankAcquiringGateway) {
                    $gatewayClientConfig = $gateway->getClientConfig($paymentMethodModel, $order);
                }
            }

            if ($isOnlinePayment && !$gatewayClientConfig) {
                throw new \RuntimeException('payment_init_failed');
            }

            $orderIdForSession = (int) $order->id;
            DB::afterCommit(function () use ($orderIdForSession, $initialStatus) {
                // Событие создания заказа (уменьшение остатков и т.д.) — только после коммита.
                $freshOrder = Order::with(['items.product'])->find($orderIdForSession);
                if ($freshOrder) {
                    event(new OrderCreated($freshOrder));
                }

                if ($initialStatus === OrderStatus::AWAITING_PAYMENT) {
                    $delayMinutes = (int) config('orders.unpaid_auto_cancel_minutes', 10);
                    CancelUnpaidOrderJob::dispatch($orderIdForSession)
                        ->delay(Carbon::now()->addMinutes($delayMinutes));
                }
            });

            DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                if ($e instanceof \RuntimeException && $e->getMessage() === 'payment_init_failed') {
                    return response()->json([
                        'message' => 'Онлайн-оплата временно недоступна. Пожалуйста, нажмите «Оформить заказ» ещё раз через минуту.',
                    ], 503);
                }

                throw $e;
            }

            Cart::clear();

            if (!$user) {
                session(['last_created_order_id' => $order->id]);
            }

            $responseData = [
                'order' => new OrderResource($order->load([
                    'items.product.taxons',
                    'address',
                    'statusHistory.user',
                    'shippingLocation',
                    'deliveryWarehouse',
                    'warehouseDeliveryMethod',
                    'shippingMethod.carrier',
                    'deliveryHandlingType',
                    'additionalServices'
                ])),
                'message' => 'Заказ успешно создан',
            ];
            if ($gatewayClientConfig !== null) {
                $responseData['gateway_client_config'] = $gatewayClientConfig;
            }

            // Добавляем информацию об автоматической регистрации, если она произошла
            if ($autoRegistered) {
                $responseData['user_registered'] = true;
                $responseData['message'] = 'Заказ успешно создан. Для вас автоматически создан аккаунт. Данные для входа отправлены на email.';
            }

            return response()->json($responseData, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Не найдена необходимая запись',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Ошибка при создании заказа: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Ошибка при создании заказа',
                'error' => config('app.debug') ? $e->getMessage() : 'Внутренняя ошибка сервера',
            ], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        // Используем Auth::user() для session-based аутентификации
        $user = \Illuminate\Support\Facades\Auth::user() ?? $request->user();

        // Маршрут защищен auth:sanctum, поэтому пользователь должен быть авторизован
        if (!$user) {
            return response()->json(['message' => 'Необходима авторизация'], 401);
        }

        // Явно загружаем заказ по ID
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        // Проверяем что заказ принадлежит текущему пользователю
        $hasAccess = false;

        if ($order->user_id) {
            // Если у заказа есть user_id, он должен совпадать с текущим пользователем
            $hasAccess = ($order->user_id === $user->id);
        } else {
            // Если заказ был создан без user_id (guest checkout), проверяем по email
            // Это позволяет пользователю видеть свои гостевые заказы после авторизации
            $orderEmail = strtolower(trim($order->contact_email ?? ''));
            $userEmail = strtolower(trim($user->email ?? ''));
            $hasAccess = ($orderEmail === $userEmail && !empty($orderEmail));
        }

        if (!$hasAccess) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $order->load([
            'items.product.taxons',
            'additionalServices',
            'address',
            'statusHistory.user',
            'shippingLocation',
            'deliveryWarehouse',
            'warehouseDeliveryMethod',
            'shippingMethod.carrier',
            'deliveryHandlingType'
        ]);

        $payload = [
            'order' => new OrderResource($order),
        ];

        if ($order->canPayOnline()) {
            $gatewayClientConfig = $this->resolveGatewayClientConfigForOrder($order);
            if ($gatewayClientConfig) {
                $payload['gateway_client_config'] = $gatewayClientConfig;
            }
        }

        return response()->json($payload);
    }

    /**
     * Конфиг для открытия платёжной формы (Сбер / Райффайзен).
     */
    public function paymentConfig(Request $request, $id): JsonResponse
    {
        $user = \Illuminate\Support\Facades\Auth::user() ?? $request->user();
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        $hasAccess = false;
        if ($user) {
            $hasAccess = $order->user_id && $order->user_id === $user->id;
            if (!$hasAccess && $order->contact_email) {
                $hasAccess = strtolower(trim($order->contact_email)) === strtolower(trim($user->email ?? ''));
            }
        } else {
            $hasAccess = (int) session('last_created_order_id', 0) === (int) $order->id;
            if ($hasAccess) {
                session()->forget('last_created_order_id');
            }
        }
        if (!$hasAccess) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $payment = \Vanilo\Payment\Models\PaymentProxy::modelClass()::where('payable_type', 'order')
            ->where('payable_id', $order->id)
            ->orderByDesc('id')
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Платёж не найден'], 404);
        }

        $gatewayClientConfig = $this->resolveGatewayClientConfigForOrder($order);

        if (!$gatewayClientConfig) {
            return response()->json(['message' => 'Онлайн-оплата для этого заказа недоступна'], 404);
        }

        $baseUrl = $this->resolveFrontendBaseUrl();
        return response()->json([
            'amount' => (float) $payment->getAmount(),
            'order_number' => $order->number,
            'success_url' => $baseUrl . '/orders/' . $order->id . '?payment=success',
            'fail_url' => $baseUrl . '/orders/' . $order->id . '?payment=fail',
            'gateway_client_config' => $gatewayClientConfig,
        ]);
    }

    /**
     * Конфиг шлюза для редиректа на оплату (Сбер: payformUrl, Райффайзен: publicId/url).
     */
    protected function resolveGatewayClientConfigForOrder(Order $order): ?array
    {
        $payment = $order->getLatestPayment();
        if (!$payment) {
            return null;
        }

        $method = $payment->getMethod();
        if (!$method) {
            return null;
        }

        $gateway = $method->getGateway();
        if ($gateway instanceof RaiffeisenAcquiringGateway) {
            return $gateway->getClientConfig($method);
        }
        if ($gateway instanceof RaiffeisenEcomGateway) {
            return $gateway->getClientConfig($method, $order);
        }
        if ($gateway instanceof SberbankAcquiringGateway) {
            return $gateway->getClientConfig($method, $order, true);
        }

        return null;
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $user = \Illuminate\Support\Facades\Auth::user() ?? $request->user();

        if (!$user) {
            return response()->json(['message' => 'Необходима авторизация'], 401);
        }

        // Загружаем заказ по ID
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        // Проверяем что заказ принадлежит текущему пользователю
        $hasAccess = false;
        if ($order->user_id) {
            $hasAccess = ($order->user_id === $user->id);
        } else {
            $orderEmail = strtolower(trim($order->contact_email ?? ''));
            $userEmail = strtolower(trim($user->email ?? ''));
            $hasAccess = ($orderEmail === $userEmail && !empty($orderEmail));
        }

        if (!$hasAccess) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        if (!$order->canBeCancelled()) {
            return response()->json([
                'message' => 'Заказ можно отменить только в статусе "Новый"',
            ], 422);
        }

        $order->changeStatus(
            OrderStatus::CANCELLED,
            $request->input('comment', 'Заказ отменен пользователем'),
            $request->user()?->id
        );

        return response()->json([
            'order' => new OrderResource($order->fresh(['items.product', 'address', 'statusHistory'])),
            'message' => 'Заказ отменен',
        ]);
    }

    public function repeat(Request $request, $id): JsonResponse
    {
        $user = \Illuminate\Support\Facades\Auth::user() ?? $request->user();

        if (!$user) {
            return response()->json(['message' => 'Необходима авторизация'], 401);
        }

        // Загружаем заказ по ID с товарами
        $order = Order::with('items.product')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        // Проверяем что заказ принадлежит текущему пользователю
        $hasAccess = false;
        if ($order->user_id) {
            $hasAccess = ($order->user_id === $user->id);
        } else {
            $orderEmail = strtolower(trim($order->contact_email ?? ''));
            $userEmail = strtolower(trim($user->email ?? ''));
            $hasAccess = ($orderEmail === $userEmail && !empty($orderEmail));
        }

        if (!$hasAccess) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        if ($order->items->isEmpty()) {
            return response()->json(['message' => 'В заказе нет товаров для повторения'], 422);
        }

        $addedItems = [];
        $skippedItems = [];
        $errors = [];

        // Добавляем товары из заказа в корзину
        foreach ($order->items as $item) {
            $product = $item->product;

            if (!$product) {
                $skippedItems[] = [
                    'product_id' => $item->product_id,
                    'reason' => 'Товар не найден',
                ];
                continue;
            }

            // Проверяем доступность товара
            try {
                $requestedQuantity = $item->quantity;

                // Получаем текущее количество этого товара в корзине
                $existingItem = Cart::getItems()->first(function ($cartItem) use ($product) {
                    return ($cartItem->product_id ?? $cartItem->buyable->id) === $product->id;
                });
                $currentQuantityInCart = $existingItem ? $existingItem->quantity : 0;

                // Вычисляем доступное количество (максимум 100 шт. на заказ)
                $maxPerOrder = 100;
                $maxAvailable = $maxPerOrder - $currentQuantityInCart;

                // Проверяем остатки, если они есть
                $resolvedStock = $this->stockAvailabilityService->resolveAvailableStock($product, $order->shipping_location_id);
                $availableFromStock = $resolvedStock === null
                    ? null
                    : max(0, $resolvedStock - $currentQuantityInCart);

                // Определяем доступное количество
                $availableQuantity = $maxAvailable;
                if ($availableFromStock !== null) {
                    $availableQuantity = min($availableQuantity, $availableFromStock);
                }

                // Если товар недоступен, пропускаем
                if ($availableQuantity <= 0) {
                    $skippedItems[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'requested_quantity' => $requestedQuantity,
                        'reason' => $resolvedStock === 0
                            ? 'Товар отсутствует на складе'
                            : 'Достигнут лимит количества в корзине',
                    ];
                    continue;
                }

                // Добавляем товар в корзину
                $finalQuantity = min($availableQuantity, $requestedQuantity);

                // Если товар уже есть в корзине, удаляем его перед добавлением с новым количеством
                if ($existingItem) {
                    Cart::removeItem($existingItem);
                    $newQuantity = $currentQuantityInCart + $finalQuantity;
                    Cart::addItem($product, $newQuantity);
                } else {
                    Cart::addItem($product, $finalQuantity);
                }

                $addedItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $finalQuantity,
                    'was_adjusted' => $finalQuantity < $requestedQuantity,
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name ?? 'Неизвестный товар',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Формируем ответ
        $message = 'Товары добавлены в корзину';
        if (!empty($skippedItems)) {
            $skippedCount = count($skippedItems);
            $message .= ". {$skippedCount} " . ($skippedCount === 1 ? 'товар пропущен' : 'товаров пропущено') . ' (недоступны)';
        }

        return response()->json([
            'message' => $message,
            'added_items' => $addedItems,
            'skipped_items' => $skippedItems,
            'errors' => $errors,
            'added_count' => count($addedItems),
            'skipped_count' => count($skippedItems),
        ], 200);
    }
}
