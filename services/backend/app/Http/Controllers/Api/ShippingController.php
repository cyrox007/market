<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use App\Services\Shipping\Contracts\ShippingCostCalculatorInterface;
use App\Services\Shipping\Contracts\ShippingMethodProviderInterface;
use App\Services\Shipping\Contracts\DeliveryHandlingProviderInterface;
use App\Services\Shipping\WarehouseDeliveryOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Shipping\Carrier;
use Illuminate\Support\Facades\Log;
use Vanilo\Shipment\Models\ShippingMethod;

class ShippingController extends Controller
{
    public function __construct(
        private ShippingCostCalculatorInterface $shippingCostCalculator,
        private ShippingMethodProviderInterface $shippingMethodProvider,
        private DeliveryHandlingProviderInterface $deliveryHandlingProvider,
        private WarehouseDeliveryOptionsService $warehouseDeliveryOptionsService
    ) {
    }

    /**
     * Получить список локаций по типу (federal_district, region, locality)
     */
    public function getLocations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:federal_district,region,locality',
            'parent_id' => 'nullable|exists:shipping_locations,id',
            'search' => 'nullable|string|max:255',
        ]);

        $query = ShippingLocation::where('is_active', true);

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['parent_id'])) {
            $query->where('parent_id', $validated['parent_id']);
        }

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $locations = $query->orderBy('sort_order')
            ->limit(100)
            ->get(['id', 'parent_id', 'name', 'slug', 'code', 'type', 'location_type', 'postal_code', 'pickup_enabled', 'pickup_notice']);

        $locations->each(function (ShippingLocation $location): void {
            $location->setAttribute('effective_pickup_enabled', $location->getEffectivePickupEnabled());
        });

        return response()->json([
            'data' => $locations,
        ]);
    }

    /**
     * Получить иерархию локаций (дерево)
     */
    public function getLocationTree(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:shipping_locations,id',
            'max_depth' => 'nullable|integer|min:1|max:3',
        ]);

        $maxDepth = $validated['max_depth'] ?? 3;
        $parentId = $validated['parent_id'] ?? null;

        $query = ShippingLocation::where('is_active', true)
            ->whereNull('parent_id');

        if ($parentId) {
            $query = ShippingLocation::where('id', $parentId);
        }

        $locations = $query->with([
            'activeChildren' => function ($query) use ($maxDepth) {
                if ($maxDepth > 1) {
                    $query->with([
                        'activeChildren' => function ($query) use ($maxDepth) {
                            if ($maxDepth > 2) {
                                $query->with('activeChildren');
                            }
                        }
                    ]);
                }
            }
        ])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $locations,
        ]);
    }

    /**
     * Получить типы обработки доставки для локации (с учетом иерархии)
     */
    public function getDeliveryHandlingTypes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'nullable|exists:shipping_locations,id', // Локация доставки для фильтрации и расчета цен
        ]);

        $deliveryLocation = null;
        if (isset($validated['location_id'])) {
            $deliveryLocation = ShippingLocation::where('id', $validated['location_id'])
                ->where('is_active', true)
                ->first();
        }

        // Если локация указана, получаем типы обработки с учетом иерархии через интерфейс
        if ($deliveryLocation) {
            $types = $this->deliveryHandlingProvider->getAvailableHandlingTypes($deliveryLocation);
        } else {
            // Иначе возвращаем все активные типы
            $types = DeliveryHandlingType::where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        // Добавляем информацию о ценах для каждого типа, если указана локация доставки
        $typesWithPrices = $types->map(function ($type) use ($deliveryLocation) {
            $typeData = [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'code' => $type->code,
                'description' => $type->description,
                'requires_floor' => $type->requires_floor,
                'max_floor' => $type->max_floor,
                'requires_elevator' => $type->requires_elevator,
            ];

            // Если указана локация доставки, добавляем информацию о ценах через интерфейс
            if ($deliveryLocation) {
                $basePrice = $this->deliveryHandlingProvider->getHandlingPrice($deliveryLocation, $type);
                $typeData['base_price'] = $basePrice;
                
                // Если требуется этаж, добавляем примерную цену для разных этажей
                if ($type->requires_floor && $basePrice !== null) {
                    $typeData['example_prices'] = [];
                    for ($floor = 1; $floor <= min(5, $type->max_floor ?? 5); $floor++) {
                        $floorPrice = $this->deliveryHandlingProvider->getHandlingPrice($deliveryLocation, $type, $floor);
                        if ($floorPrice !== null) {
                            $typeData['example_prices'][$floor] = $floorPrice;
                        }
                    }
                }
            }

            return $typeData;
        });

        return response()->json([
            'data' => $typesWithPrices,
        ]);
    }

    /**
     * Рассчитать стоимость доставки
     */
    public function calculateShipping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:shipping_locations,id',
            'order_amount' => 'nullable|numeric|min:0',
            'delivery_handling_type_id' => 'nullable|exists:delivery_handling_types,id',
            'floor' => 'nullable|integer|min:1|max:20',
            'requires_assembly' => 'nullable|boolean',
            'order_weight' => 'nullable|numeric|min:0',
            'order_volume' => 'nullable|numeric|min:0',
            'region_id' => 'nullable|exists:shipping_locations,id', // Локация для применения региональных правил
        ]);

        $location = ShippingLocation::findOrFail($validated['location_id']);

        // Проверяем доступность доставки
        $availability = $this->shippingCostCalculator->checkDeliveryAvailability(
            $location,
            $validated['order_amount'] ?? 0,
            $validated['order_weight'] ?? null,
            $validated['order_volume'] ?? null
        );

        if (!$availability['available']) {
            return response()->json([
                'available' => false,
                'reasons' => $availability['reasons'],
            ], 422);
        }

        // Получаем тип обработки доставки если указан
        $handlingType = null;
        if (isset($validated['delivery_handling_type_id'])) {
            $handlingType = DeliveryHandlingType::find($validated['delivery_handling_type_id']);
            if ($handlingType && $handlingType->requires_floor) {
                // Для типов, требующих этаж, этаж обязателен и не может быть null
                if (!isset($validated['floor']) || $validated['floor'] === null) {
                    return response()->json([
                        'available' => false,
                        'reasons' => ['Для выбранного типа обработки обязательно требуется указать этаж'],
                    ], 422);
                }
                // Проверяем, что этаж в допустимом диапазоне
                if (isset($validated['floor']) && $handlingType->max_floor && $validated['floor'] > $handlingType->max_floor) {
                    return response()->json([
                        'available' => false,
                        'reasons' => ["Максимальный этаж для данного типа обработки: {$handlingType->max_floor}"],
                    ], 422);
                }
            }
        }

        // Рассчитываем стоимость через интерфейс
        $calculation = $this->shippingCostCalculator->calculateForLocation(
            $location,
            $validated['order_amount'] ?? 0,
            $handlingType,
            $validated['floor'] ?? null,
            (bool) ($validated['requires_assembly'] ?? false)
        );

        // Получаем доступные типы обработки доставки для данной локации
        $availableHandlingTypes = $location->activeDeliveryHandlingTypes()
            ->select('delivery_handling_types.id', 'delivery_handling_types.name', 'delivery_handling_types.slug', 'delivery_handling_types.code', 'delivery_handling_types.description', 'delivery_handling_types.requires_floor', 'delivery_handling_types.max_floor', 'delivery_handling_types.requires_elevator')
            ->get();

        return response()->json([
            'available' => true,
            'calculation' => $calculation->toArray(),
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type,
                'full_path' => $location->full_path,
            ],
            'available_delivery_handling_types' => $availableHandlingTypes,
        ]);
    }

    /**
     * Получить информацию о локации
     */
    public function getLocationInfo(int $locationId): JsonResponse
    {
        $location = ShippingLocation::with(['parent', 'activeDeliveryHandlingTypes', 'shippingMethod', 'activeCarriers'])
            ->findOrFail($locationId);

        // Получаем доступные shipping methods через интерфейс
        $shippingMethods = $this->shippingMethodProvider->getAvailableShippingMethods($location);

        return response()->json([
            'data' => [
                'id' => $location->id,
                'name' => $location->name,
                'slug' => $location->slug,
                'type' => $location->type,
                'location_type' => $location->location_type,
                'postal_code' => $location->postal_code,
                'pickup_enabled' => $location->pickup_enabled,
                'effective_pickup_enabled' => $location->getEffectivePickupEnabled(),
                'pickup_notice' => $location->pickup_notice,
                'full_path' => $location->full_path,
                'parent' => $location->parent ? [
                    'id' => $location->parent->id,
                    'name' => $location->parent->name,
                    'type' => $location->parent->type,
                ] : null,
                'effective_delivery_price' => $location->getEffectiveDeliveryPrice(),
                'effective_free_delivery_threshold' => $location->getEffectiveFreeDeliveryThreshold(),
                'effective_delivery_days' => $location->getEffectiveDeliveryDays(),
                'effective_assembly_price' => $location->getEffectiveAssemblyPrice(),
                'effective_assembly_days' => $location->getEffectiveAssemblyDays(),
                'effective_requires_assembly' => $location->getEffectiveRequiresAssembly(),
                'delivery_handling_types' => $location->activeDeliveryHandlingTypes,
                'shipping_method' => $location->getEffectiveShippingMethod(),
                'carriers' => $location->activeCarriers->map(function ($carrier) {
                    return [
                        'id' => $carrier->id,
                        'name' => $carrier->name,
                        'pivot' => [
                            'base_price' => $carrier->pivot->base_price,
                            'free_delivery_threshold' => $carrier->pivot->free_delivery_threshold,
                            'delivery_days_min' => $carrier->pivot->delivery_days_min,
                            'delivery_days_max' => $carrier->pivot->delivery_days_max,
                        ],
                    ];
                }),
                'shipping_methods' => $shippingMethods,
            ],
        ]);
    }

    /**
     * Получить список carriers
     */
    public function getCarriers(): JsonResponse
    {
        $carriers = Carrier::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'configuration']);

        return response()->json([
            'data' => $carriers,
        ]);
    }

    /**
     * Получить доступные shipping methods для локации
     */
    public function getShippingMethods(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:shipping_locations,id',
            'order_amount' => 'nullable|numeric|min:0',
        ]);

        $location = ShippingLocation::findOrFail($validated['location_id']);
        $orderAmount = $validated['order_amount'] ?? 0.0;

        // Получаем методы доставки через интерфейс
        $shippingMethods = $this->shippingMethodProvider->getAvailableShippingMethods($location);

        // Преобразуем в нужный формат с полной информацией через интерфейс
        $methodsWithPrice = $shippingMethods
            ->filter(function ($method) {
                // Фильтруем только объекты ShippingMethod
                return $method instanceof \Vanilo\Shipment\Models\ShippingMethod;
            })
            ->map(function ($method) use ($location, $orderAmount) {
                return $this->shippingMethodProvider->getMethodInfo($method, $location, $orderAmount);
            })
            ->values()
            ->toArray();

        $shippingResolution = $methodsWithPrice[0]['source'] ?? null;
        Log::debug('ShippingController::getShippingMethods response', [
            'location_id' => $location->id,
            'method_count' => count($methodsWithPrice),
            'shipping_resolution' => $shippingResolution,
            'methods' => array_map(static fn (array $m) => [
                'id' => $m['id'] ?? null,
                'priority' => $m['priority'] ?? null,
                'source' => $m['source'] ?? null,
            ], $methodsWithPrice),
        ]);
        Log::info('ShippingController::getShippingMethods', [
            'location_id' => $location->id,
            'shipping_resolution' => $shippingResolution,
            'count' => count($methodsWithPrice),
        ]);

        return response()->json([
            'data' => $methodsWithPrice,
            'shipping_resolution' => $shippingResolution,
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type,
            ],
        ]);
    }

    /**
     * Получить доступные варианты доставки для состава заказа.
     *
     * Каждый вариант однозначно задаёт склад, способ доставки,
     * перевозчика, серверную стоимость и срок.
     */
    public function getWarehouseDeliveryOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:shipping_locations,id',
            'order_amount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|integer|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.001',
        ]);

        $location = ShippingLocation::findOrFail($validated['location_id']);
        $options = $this->warehouseDeliveryOptionsService->resolveForLocation(
            $location,
            $validated['items'] ?? [],
            (float) ($validated['order_amount'] ?? 0)
        );

        return response()->json([
            'data' => $options->all(),
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type,
            ],
        ]);
    }

    /**
     * Получить дополнительные услуги для локации
     */
    public function getAdditionalServices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:shipping_locations,id',
        ]);

        $location = ShippingLocation::findOrFail($validated['location_id']);

        // Получаем услуги с учетом иерархии
        $services = $location->getAvailableAdditionalServices();

        return response()->json([
            'data' => $services->map(function ($service) use ($location) {
                $price = $service->effective_price ?? $service->base_price;
                
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'code' => $service->code,
                    'description' => $service->description,
                    'icon' => $service->icon,
                    'price_type' => $service->price_type,
                    'price' => $price !== null ? (float) $price : null,
                    'base_price' => $service->base_price !== null ? (float) $service->base_price : null,
                ];
            }),
        ]);
    }

    /**
     * Рассчитать стоимость доставки для конкретного shipping method
     */
    public function calculateShippingMethod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_method_id' => 'required|exists:shipping_methods,id',
            'location_id' => 'required|exists:shipping_locations,id',
            'order_amount' => 'nullable|numeric|min:0',
            'delivery_handling_type_id' => 'nullable|exists:delivery_handling_types,id',
            'floor' => 'nullable|integer|min:1|max:20',
            'requires_assembly' => 'nullable|boolean',
        ]);

        $shippingMethod = ShippingMethod::findOrFail($validated['shipping_method_id']);
        $location = ShippingLocation::findOrFail($validated['location_id']);
        $orderAmount = $validated['order_amount'] ?? 0.0;

        // Получаем тип обработки доставки если указан
        $handlingType = null;
        if (isset($validated['delivery_handling_type_id'])) {
            $handlingType = DeliveryHandlingType::find($validated['delivery_handling_type_id']);
            if ($handlingType && $handlingType->requires_floor && !isset($validated['floor'])) {
                return response()->json([
                    'message' => 'Для выбранного типа обработки доставки требуется указать этаж',
                    'errors' => ['floor' => ['Поле этаж обязательно для типа обработки: ' . $handlingType->name]],
                ], 422);
            }
        }

        // Используем интерфейс для расчета стоимости доставки
        $calculation = $this->shippingCostCalculator->calculateForMethod(
            $shippingMethod,
            $location,
            $orderAmount,
            $handlingType,
            $validated['floor'] ?? null,
            (bool) ($validated['requires_assembly'] ?? false)
        );

        // Загружаем carrier для метода доставки
        if (!$shippingMethod->relationLoaded('carrier')) {
            $shippingMethod->load('carrier');
        }

        return response()->json([
            'shipping_method' => [
                'id' => $shippingMethod->id,
                'name' => $shippingMethod->name,
                'carrier' => $shippingMethod->carrier ? [
                    'id' => $shippingMethod->carrier->id,
                    'name' => $shippingMethod->carrier->name,
                ] : null,
            ],
            'calculation' => $calculation->toArray(),
            'delivery_days' => $calculation->deliveryDays,
            'assembly_days' => $calculation->assemblyDays,
        ]);
    }
}
