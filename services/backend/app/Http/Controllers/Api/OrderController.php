<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Rules\PhoneNumberRule;
use App\Support\PhoneNumber;
use App\Models\Address\Address;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Order\OrderStatus;
use App\Models\Product\Product;
use App\Models\Payment\PaymentMethod;
use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use App\Services\Product\ProductRegionRuleService;
use App\Services\Payment\Contracts\PaymentMethodAvailabilityInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Events\OrderCreated;
use App\Events\UserAutoRegistered;
use App\Jobs\CancelUnpaidOrderJob;
use App\Models\User;
use App\Models\Settings\PublicAuthSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Vanilo\Cart\Facades\Cart;
use Vanilo\Payment\Factories\PaymentFactory;
use Vanilo\Payment\PaymentGateways;
use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Services\Inventory\StockAvailabilityService;
use App\Payment\Gateways\RaiffeisenAcquiringGateway;
use App\Payment\Gateways\RaiffeisenEcomGateway;
use App\Payment\Gateways\SberbankAcquiringGateway;

class OrderController extends Controller
{
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
        protected ProductRegionRuleService $regionRuleService,
        protected PaymentMethodAvailabilityInterface $paymentMethodAvailability,
        protected GatewayLoggerInterface $gatewayLog,
        protected StockAvailabilityService $stockAvailabilityService
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
            $directCheckout = $request->exists('items');
            if ($directCheckout) {
                $request->validate([
                    'items' => 'required|array|min:1|max:30',
                    'bundle_id' => 'sometimes|integer',
                    'primary_sku_id' => 'nullable|integer',
                    'bundle_quantity' => 'required_with:bundle_id|integer|min:1|max:1000',
                    'items.*.product_id' => 'required|integer',
                    'items.*.variant_id' => 'nullable|integer',
                    'items.*.quantity' => 'required|integer|min:1|max:1000',
                    'accept_offer' => 'required|accepted',
                    'accept_personal_data' => 'required|accepted',
                ]);
            }
            if ($directCheckout && ! $request->filled('bundle_id') && count($request->input('items', [])) !== 1) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Отдельно можно оформить только один товар.']);
            }
            if (! $directCheckout && Cart::isEmpty()) {
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

            // Тем же нормализатором, что и телефон пользователя — чтобы 1С сопоставлял заказ с юзером по единому формату.
            $request->merge(['contact_phone' => PhoneNumber::normalize($request->input('contact_phone'))]);

            $validated = $request->validate([
                'contact_name' => 'required|string|max:255',
                'expected_subtotal' => 'nullable|numeric|min:0',
                'contact_phone' => ['required', new PhoneNumberRule],
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
                'shipping_location_id' => 'prohibited',
                'region_id' => 'nullable|exists:shipping_locations,id',
                'shipping_method_id' => 'prohibited',
                'delivery_handling_type_id' => 'nullable|exists:delivery_handling_types,id',
                'delivery_floor' => 'nullable|integer|min:1|max:20',
                'requires_assembly' => 'nullable|boolean',
                'additional_services' => 'nullable|array',
                'additional_services.*.id' => 'required|exists:additional_services,id',
                'additional_services.*.price' => 'prohibited',

                // Старые поля (для обратной совместимости)
                'delivery_date' => 'nullable|date',
                'delivery_time' => 'nullable|string|max:255',
                'address_id' => 'prohibited_if:delivery_type,pickup|nullable|exists:user_addresses,id',
                'directory_external_id' => 'prohibited_if:delivery_type,pickup|nullable|string|max:128',
                'warehouse_id' => 'required_if:delivery_type,pickup|nullable|integer|exists:warehouses,id',
                'store_id' => 'nullable|integer|exists:stores,id',
                'delivery_details' => 'prohibited_if:delivery_type,pickup|nullable|array',
                'delivery_details.apartment' => 'nullable|string|max:100',
                'delivery_details.room' => 'nullable|string|max:100',
                'delivery_details.entrance' => 'nullable|string|max:100',
                'delivery_details.floor' => 'nullable|string|max:100',
                'delivery_details.intercom' => 'nullable|string|max:100',
                'delivery_cost' => 'prohibited',
                'assembly_cost' => 'prohibited',
                'comment' => 'nullable|string|max:1000',

                // Адрес доставки (может быть передан для любого типа доставки)
                'address' => 'prohibited',
                'address.city' => 'required_with:address|string|max:255',
                'address.street' => 'required_with:address|string|max:255',
                'address.house' => 'required_with:address|string|max:255',
                'address.apartment' => 'nullable|string|max:255',
                'address.entrance' => 'nullable|string|max:255',
            ]);

            $savedDestination = null;
            if (!empty($validated['address_id'])) {
                if (!$request->user()) {
                    return response()->json(['message' => 'Для использования адреса профиля войдите в аккаунт.'], 401);
                }
                $savedDestination = Address::where('user_id', $request->user()->id)->findOrFail($validated['address_id']);
            }
            if ($savedDestination && filled($validated['directory_external_id'] ?? null)
                && $savedDestination->directory_external_id !== $validated['directory_external_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['address_id' => 'Адрес профиля не совпадает с выбранным зданием.']);
            }
            $directoryId = $savedDestination ? $savedDestination->directory_external_id : ($validated['directory_external_id'] ?? null);
            $directoryDelivery = $validated['delivery_type'] === 'delivery' && filled($directoryId);
            $warehousePickup = $validated['delivery_type'] === 'pickup' && ! empty($validated['warehouse_id']);
            $warehouseQuote = null;
            if ($directoryDelivery && !empty($validated['address'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['delivery' => 'Для тарифа склада адрес задаётся справочником.']);
            }

            if ($validated['delivery_type'] === 'delivery' && ! $directoryDelivery) {
                throw \Illuminate\Validation\ValidationException::withMessages(['directory_external_id' => 'Выберите здание из адресного справочника для расчёта доставки со склада.']);
            }
            if ($validated['delivery_type'] === 'delivery' && ! empty($validated['store_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['store_id' => 'Магазин выбирается только для самовывоза.']);
            }

            $bundleComposition = null;
            if ($directCheckout && $request->filled('bundle_id')) {
                $bundleComposition = app(\App\Services\Product\SellableBundle::class)->composition(
                    (int) $request->input('bundle_id'),
                    $request->filled('primary_sku_id') ? (int) $request->input('primary_sku_id') : null,
                    (int) $request->input('bundle_quantity')
                );
                app(\App\Services\Product\SellableBundle::class)->assertMatches($bundleComposition, $request->input('items'));
            }
            $cartItems = $directCheckout
                ? app(\App\Services\Order\DirectCheckout::class)->items($request->input('items'))
                : app(\App\Services\Product\CartFulfillment::class)->expand(Cart::getItems());
            if ($warehousePickup) {
                if (! empty($validated['additional_services']) || ! empty($validated['requires_assembly']) || ! empty($validated['delivery_handling_type_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['services' => 'Услуги доставки недоступны в расчёте самовывоза.']);
                }
                $warehouseQuote = ['selected' => app(\App\Services\Shipping\WarehousePickup::class)->select((int) $validated['warehouse_id'],
                    isset($validated['store_id']) ? (int) $validated['store_id'] : null, app(\App\Services\Shipping\WarehouseDeliveryQuote::class)->cartLines($cartItems))];
            }
            if ($bundleComposition === null) {
                foreach ($cartItems as $line) {
                    $sku = $line->buyable ?? Product::findOrFail($line->product_id);
                    if ($sku->is_sellable_bundle || $sku->parentProduct?->is_sellable_bundle) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Комплект необходимо оформить целиком.']);
                    }
                }
            }
            $subtotal = 0;

            // Получаем регион для применения правил корзины
            $region = null;
            if (isset($validated['region_id'])) {
                $region = ShippingLocation::where('id', $validated['region_id'])
                    ->where('is_active', true)
                    ->first();
            }

            // Проверка остатков
            $subtotal = 0;
            $pricedItems = app(\App\Services\Product\CartFulfillment::class)->pricedItems($cartItems, $region);
            if ($bundleComposition !== null) {
                $pricedItems = app(\App\Services\Product\BundlePricing::class)->apply($bundleComposition, $pricedItems);
            }
            foreach ($pricedItems as $pricingLine) {
                $subtotal += $pricingLine['price'] * $pricingLine['quantity'];
                if ($pricingLine['expired_offer'] && ! array_key_exists('expected_subtotal', $validated)) {
                    return response()->json(['message' => 'Цена по ссылке больше недоступна. Подтвердите актуальную стоимость.', 'code' => 'price_changed'], 409);
                }
            }
            if (isset($validated['expected_subtotal']) && abs((float) $validated['expected_subtotal'] - $subtotal) >= 0.005) {
                return response()->json(['message' => 'Стоимость товаров изменилась. Обновите расчёт и подтвердите цену.', 'subtotal' => round($subtotal, 2)], 409);
            }
            if ($directoryDelivery) {
                $quotes = app(\App\Services\Shipping\WarehouseDeliveryQuote::class);
                try {
                    $warehouseQuote = $quotes->calculate($directoryId, $quotes->cartLines($cartItems), $region, array_merge($validated, [
                        '_cart_subtotal' => $subtotal,
                        'allow_multi_warehouse' => true,
                        '_priced_unit_prices' => app(\App\Services\Product\BundlePricing::class)->unitPrices($pricedItems),
                    ]));
                } catch (\RuntimeException $error) {
                    report($error);
                    return response()->json(['message' => 'Не удалось проверить адрес и тариф доставки. Повторите позже.'], 503);
                }
                if (!$warehouseQuote['available']) {
                    return response()->json(['message' => $warehouseQuote['reason'], 'available' => false], 422);
                }
            }
            // Автоматическая регистрация пользователя, если не авторизован
            $user = $request->user();
            // Capture checkout identity before optional automatic registration.
            $guestCheckout = $user === null;
            $autoRegistered = false;
            $autoRegisteredPassword = null;

            if (!$user) {
                $publicAccess = PublicAuthSettings::current();
                // Проверяем, существует ли пользователь с таким email
                $existingUser = User::where('email', $validated['contact_email'])->first();

                if (!$existingUser && $publicAccess->registration_enabled) {
                    // Телефон уникален: если он уже за другим аккаунтом (иной email) — новому юзеру
                    // не присваиваем (иначе коллизия), но в заказе contact_phone сохраняется и уходит в 1С.
                    $phone = $validated['contact_phone'] ?? null;
                    if ($phone !== null && User::where('phone', $phone)->exists()) {
                        $phone = null;
                    }

                    // Создаем нового пользователя со случайным паролем
                    $autoRegisteredPassword = Str::random(12);
                    $user = User::create([
                        'name' => $validated['contact_name'],
                        'email' => $validated['contact_email'],
                        'password' => $autoRegisteredPassword,
                        'phone' => $phone,
                    ]);

                    // Автоматически авторизуем пользователя
                    if ($publicAccess->login_enabled) {
                        Auth::guard('web')->login($user);
                    }
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

            if ($directoryDelivery || $warehousePickup) {
                if ($directoryDelivery && isset($validated['delivery_handling_type_id'])) {
                    $deliveryHandlingType = DeliveryHandlingType::find($validated['delivery_handling_type_id']);
                }
                $selected = $warehouseQuote['selected'];
                $deliveryCost = $selected['delivery_cost'];
                $assemblyCost = $selected['assembly_cost'];
                $deliveryDaysMin = $selected['delivery_days_min'];
                $deliveryDaysMax = $selected['delivery_days_max'];
                $deliveryBasePrice = $selected['base_price'];
                $deliveryFreeThreshold = $selected['free_delivery_threshold'] ?? null;
            }

            $addressId = $validated['address_id'] ?? null;
            $addressSnapshot = null;
            if ($directoryDelivery) {
                $addressSnapshot = $savedDestination?->full_address ?? $warehouseQuote['address']['address_snapshot']['label'];
                $warehouseQuote['selected']['address_directory'] = app(\App\Services\Address\FulfillmentAddress::class)->snapshot(
                    $warehouseQuote['address']['address_snapshot'],
                    $savedDestination ? $savedDestination->only(['apartment', 'room', 'entrance', 'floor', 'intercom', 'comment']) : ($validated['delivery_details'] ?? [])
                );
            } elseif ($warehousePickup) {
                $addressSnapshot = $warehouseQuote['selected']['address_directory']['label'] ?? $warehouseQuote['selected']['address'] ?? null;
            }

            $finalDeliveryCost = $deliveryCost;
            $finalAssemblyCost = $assemblyCost;

            $paymentMethodModel = PaymentMethod::where('code', $validated['payment_method'])->first();
            if (!$paymentMethodModel) {
                return response()->json(['message' => 'Выбранный метод оплаты недоступен.'], 422);
            }

            // Онлайн-оплата → ожидание оплаты; самовывоз/другие способы → сразу принят
            $isOnlinePayment = in_array((string) $paymentMethodModel->gateway, ['raiffeisen_acquiring', 'raiffeisen_ecom', 'sberbank_acquiring'], true);
            $initialStatus = OrderStatus::MANAGER_PROCESSING;

            $gatewayClientConfig = null;
            $guestToken = bin2hex(random_bytes(32));

            DB::beginTransaction();
            try {
                if ($bundleComposition !== null) {
                    $lockedComposition = app(\App\Services\Product\SellableBundle::class)->composition(
                        $bundleComposition['bundle_id'], $bundleComposition['primary_sku_id'], $bundleComposition['bundle_quantity']
                    );
                    app(\App\Services\Product\SellableBundle::class)->assertMatches($lockedComposition, $bundleComposition['items']);
                    $lockedPrices = app(\App\Services\Product\BundlePricing::class)->apply($lockedComposition,
                        app(\App\Services\Pricing\PrivateOfferAccess::class)->pricedItems($cartItems, $region));
                    $priceIdentity = fn (array $lines): array => array_map(fn ($line) => [
                        $line['product_id'], $line['quantity'], $line['price'], $line['pricing_snapshot']['bundle_pricing'] ?? null,
                    ], $lines);
                    if ($priceIdentity($lockedPrices) !== $priceIdentity($pricedItems)) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['expected_subtotal' => 'Цена комплекта изменилась. Пересчитайте заказ.']);
                    }
                }
                if (! $directCheckout) {
                    $fresh = app(\App\Services\Product\CartFulfillment::class)->pricedItems(Cart::model()->items()->lockForUpdate()->get(), $region);
                    $identity = fn (array $lines) => array_map(fn ($line) => [
                        (int) $line['product_id'], (int) $line['quantity'], round((float) $line['price'], 2),
                        $line['pricing_snapshot']['bundle'] ?? null,
                    ], $lines);
                    if ($identity($fresh) !== $identity($pricedItems)) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'Состав или цена корзины изменились. Пересчитайте заказ.']);
                    }
                }
                $bundleSnapshot = $bundleComposition === null ? app(\App\Services\Product\CartFulfillment::class)->snapshot($cartItems, $pricedItems) : [
                    'bundle_id' => $bundleComposition['bundle_id'],
                    'primary_sku_id' => $bundleComposition['primary_sku_id'],
                    'bundle_quantity' => $bundleComposition['bundle_quantity'],
                    'components' => collect($bundleComposition['components'])->map(function (array $component) use ($pricedItems, $bundleComposition): array {
                        $priced = collect($pricedItems)->where('product_id', $component['product_id']);
                        $quantity = $priced->sum('quantity');
                        $total = round($priced->sum(fn ($row) => $row['price'] * $row['quantity']), 2);
                        return [...$component, 'unit_price' => $total / $quantity, 'quantity' => $quantity, 'total' => $total,
                            'price_allocations' => $priced->map(fn ($row) => ['quantity' => $row['quantity'], 'unit_price' => $row['price']])->values()->all()];
                    })->all(),
                ];
                $order = Order::create([
                    'user_id' => $user?->id,
                    'bundle_snapshot' => $bundleSnapshot,
                    'guest_checkout' => $guestCheckout,
                    'status' => $initialStatus->value,
                    'cooling_until' => now()->addMinutes(60),
                    'integration_state' => 'cooling',
                    'guest_token_hash' => hash('sha256', $guestToken),
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
                    'fulfillment_snapshot' => $directoryDelivery || $warehousePickup ? $warehouseQuote['selected'] : null,
                    'comment' => $validated['comment'] ?? null,
                    // Новые поля для системы доставки (сохраняем для обоих типов, если указаны)
                    'shipping_location_id' => $shippingLocation?->id,
                    'region_id' => $region?->id, // Сохраняем регион для применения правил корзины
                    'shipping_method_id' => $shippingMethod?->id,
                    'delivery_handling_type_id' => $deliveryHandlingType?->id,
                    'delivery_floor' => $validated['delivery_floor'] ?? null,
                    'requires_assembly' => $directoryDelivery ? $warehouseQuote['selected']['requires_assembly'] : false,
                    // Данные о доставке из конфигурации метода доставки
                    'delivery_days_min' => $deliveryDaysMin ?? null,
                    'delivery_days_max' => $deliveryDaysMax ?? null,
                    'delivery_base_price' => $deliveryBasePrice ?? null,
                    'delivery_free_threshold' => $deliveryFreeThreshold ?? null,
                ]);

            if ($directoryDelivery && !$addressId) {
                $address = Address::create(array_merge($warehouseQuote['address'], $validated['delivery_details'] ?? [], [
                    'user_id' => $user?->id, 'title' => 'Адрес доставки', 'is_default' => false,
                ]));
                $order->update(['address_id' => $address->id, 'address_snapshot' => $address->full_address]);
            }
            if ($directoryDelivery || $warehousePickup) {
                app(\App\Services\Shipping\WarehouseOrderStock::class)->reserve($order);
            }

            $calculatedSubtotal = 0;
            foreach ($pricedItems as $line) {
                $product = Product::with('parentProduct')->findOrFail($line['product_id']);
                $price = $line['price'];
                $lineTotal = $price * $line['quantity'];
                OrderItem::create([
                    'order_id' => $order->id, 'product_id' => $product->id,
                    'quantity' => $line['quantity'], 'price' => $price, 'total' => $lineTotal,
                    'pricing_snapshot' => $bundleComposition !== null
                        ? [...$line['pricing_snapshot'], 'bundle' => [
                            'bundle_id' => $bundleComposition['bundle_id'],
                            'primary_sku_id' => $bundleComposition['primary_sku_id'],
                            'bundle_quantity' => $bundleComposition['bundle_quantity'],
                        ]] : $line['pricing_snapshot'],
                ]);
                $calculatedSubtotal += $lineTotal;
            }

            $additionalServicesTotal = 0;
            if ($directoryDelivery) {
                foreach ($warehouseQuote['selected']['additional_services'] as $service) {
                    $order->additionalServices()->attach($service['id'], ['service_name' => $service['name'],
                        'price' => $service['price'], 'price_type' => $service['price_type'], 'icon' => $service['icon']]);
                    $additionalServicesTotal += $service['price'];
                }
            }

            // Обновляем subtotal и пересчитываем total (включая дополнительные услуги)
            $order->subtotal = $calculatedSubtotal;
            $order->total = $calculatedSubtotal + $finalDeliveryCost + $finalAssemblyCost + $additionalServicesTotal;
            $order->save();
            $order->sealCheckout();

            $order->statusHistory()->create([
                'status' => $initialStatus->value,
                'comment' => 'Заказ создан. Период охлаждения 60 минут.',
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

            if (! $directCheckout) {
                Cart::clear();
            }

            if (! Auth::guard('web')->check() && ! $request->user()) {
                session(['last_created_order_id' => $order->id]);
            }

            $responseData = [
                'guest_order_token' => $guestToken,
                'order' => new OrderResource($order->load([
                    'items.product.taxons',
                    'address',
                    'statusHistory.user',
                    'shippingLocation',
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
                $responseData['user_authenticated'] = $publicAccess->login_enabled;
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
        $user = Auth::guard('sanctum')->user() ?? Auth::user() ?? $request->user();
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
        }
          $guestToken = $request->header('X-Order-Token', '');
          if (! $hasAccess) {
              $hasAccess = app(\App\Services\Order\GuestOrderAccess::class)->valid($order, $guestToken);
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

        if (! $order->canPayOnline()) {
            return response()->json(['message' => 'Этот заказ больше не ожидает онлайн-оплату.'], 422);
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

        $request->validate(['comment' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($order, $request): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! $locked->canBeCancelled()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['order' => 'Статус заказа изменился. Обновите страницу.']);
            }
            $locked->changeStatus(OrderStatus::CANCELLED,
                $request->input('comment') ?? 'Заказ отменен пользователем', $request->user()?->id);
        });

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
                $resolvedStock = $this->stockAvailabilityService->resolveAvailableStock($product);
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
