<?php

namespace App\Services\Product;

use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use App\Models\Product\ProductRegionRule;
use App\Models\Shipping\Carrier;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для применения региональных правил к товарам
 */
class ProductRegionRuleService
{
    public const SHIPPING_METHOD_SOURCE_CARRIER = 'carrier';

    public const SHIPPING_METHOD_SOURCE_LOCATION_FALLBACK = 'location_fallback';

    /** @var array<int, array<int, array<int|string, ProductRegionRule>>|null> locId => productId => (variantId|'p' => rule) */
    private ?array $preloadedRules = null;

    /** @var array<int>|null */
    private ?array $preloadedLocationIds = null;

    /**
     * Предзагрузить все правила для локации и её предков (один запрос вместо N на каждый товар).
     * Вызывать перед отдачей списка товаров с region_id (каталог, корзина).
     */
    public function preloadRulesForLocation(?ShippingLocation $location): void
    {
        if (!$location) {
            $this->preloadedRules = null;
            $this->preloadedLocationIds = null;
            return;
        }

        $this->preloadedLocationIds = array_reverse($location->getAncestorsIds());
        $rules = ProductRegionRule::query()
            ->active()
            ->whereIn('shipping_location_id', $this->preloadedLocationIds)
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $byLocProductVariant = [];
        foreach ($rules as $rule) {
            $locId = $rule->shipping_location_id;
            $pid = $rule->product_id;
            $vid = $rule->variant_id ?? 'p';
            if (!isset($byLocProductVariant[$locId][$pid][$vid])) {
                $byLocProductVariant[$locId][$pid][$vid] = $rule;
            }
        }
        $this->preloadedRules = $byLocProductVariant;
    }

    /**
     * Получить цену товара с учетом правил локации доставки
     * 
     * Логика применения правил:
     * 1. Ищем правила для текущей локации и всех её родителей (от специфичного к общему)
     * 2. Сначала ищутся правила для конкретной вариации + локация
     * 3. Если не найдено, ищутся правила для товара + локация
     * 4. Если не найдено, используется базовая цена товара
     * 5. При конфликтах используется правило с большим приоритетом
     * 
     * @param Product $product Товар
     * @param ShippingLocation|null $location Локация доставки (null = без локации)
     * @param Product|null $variant Вариация товара (если есть)
     * @return float Цена с учетом правил
     */
    public function getPriceForRegion(Product $product, ?ShippingLocation $location, ?Product $variant = null): float
    {
        $basePrice = $variant ? (float) $variant->price : (float) $product->price;
        if (!$location) {
            return $basePrice;
        }

        $locationIds = $this->preloadedLocationIds ?? array_reverse($location->getAncestorsIds());

        if ($this->preloadedRules !== null) {
            foreach ($locationIds as $locId) {
                $byProduct = $this->preloadedRules[$locId][$product->id] ?? null;
                if (!$byProduct) {
                    continue;
                }
                $rule = $variant ? ($byProduct[$variant->id] ?? $byProduct['p'] ?? null) : ($byProduct['p'] ?? null);
                if ($rule) {
                    return $rule->applyToPrice($basePrice);
                }
            }
            return $basePrice;
        }

        foreach ($locationIds as $locId) {
            $query = ProductRegionRule::query()
                ->active()
                ->where('shipping_location_id', $locId);

            if ($variant) {
                $query->where(function ($q) use ($variant, $product) {
                    $q->where('variant_id', $variant->id)
                      ->orWhere(function ($subQ) use ($product) {
                          $subQ->where('product_id', $product->id)
                               ->whereNull('variant_id');
                      });
                });
            } else {
                $query->where('product_id', $product->id)
                      ->whereNull('variant_id');
            }

            $rule = $query->orderBy('priority', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($rule) {
                return $rule->applyToPrice($basePrice);
            }
        }

        return $basePrice;
    }

    /**
     * Проверить видимость товара в локации доставки
     * 
     * Логика: по умолчанию все товары показываются.
     * Правило создается только для скрытия товара в локации доставки.
     * 
     * ВАЖНО: 
     * - Если скрыта любая вариация товара, то весь родительский товар скрывается.
     * - Правила применяются с учетом наследования: если у родительской локации is_hidden = true,
     *   то товар скрыт для всех дочерних локаций, пока не переопределено правило для дочерней локации.
     * - Правила проверяются от текущей локации к корню (от специфичного к общему).
     * - Если для текущей локации есть правило, оно имеет приоритет над правилами родителей.
     * 
     * @param Product $product Товар
     * @param ShippingLocation|null $location Локация доставки (null = видим везде)
     * @param Product|null $variant Вариация товара (если есть)
     * @return bool Видим ли товар в локации (true = видим, false = скрыт)
     */
    public function isVisibleInRegion(Product $product, ?ShippingLocation $location, ?Product $variant = null): bool
    {
        if (!$location) {
            return true;
        }

        $locationIds = $this->preloadedLocationIds ?? array_reverse($location->getAncestorsIds());

        if ($this->preloadedRules !== null) {
            foreach ($locationIds as $locId) {
                $byProduct = $this->preloadedRules[$locId][$product->id] ?? null;
                if (!$byProduct) {
                    continue;
                }
                if ($variant) {
                    $rule = $byProduct[$variant->id] ?? $byProduct['p'] ?? null;
                    if ($rule) {
                        return !($rule->is_hidden ?? false);
                    }
                    continue;
                }
                foreach ($byProduct as $rule) {
                    if ($rule->is_hidden ?? false) {
                        return false;
                    }
                }
                return true;
            }
            return true;
        }

        $locationIds = array_reverse($location->getAncestorsIds());

        if ($variant) {
            foreach ($locationIds as $locId) {
                $rules = ProductRegionRule::query()
                    ->active()
                    ->where('shipping_location_id', $locId)
                    ->where(function ($query) use ($variant, $product) {
                        $query->where('variant_id', $variant->id)
                            ->orWhere(fn ($q) => $q->where('product_id', $product->id)->whereNull('variant_id'));
                    })
                    ->orderBy('priority', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();

                if ($rules->isNotEmpty()) {
                    return !($rules->first()->is_hidden ?? false);
                }
            }
            return true;
        }

        $variantIds = $product->relationLoaded('variants')
            ? $product->variants->pluck('id')->all()
            : Product::where('parent_product_id', $product->id)->pluck('id')->all();

        foreach ($locationIds as $locId) {
            $rules = ProductRegionRule::query()
                ->active()
                ->where('shipping_location_id', $locId)
                ->where(function ($query) use ($product, $variantIds) {
                    $query->where(fn ($q) => $q->where('product_id', $product->id)->whereNull('variant_id'));
                    if ($variantIds !== []) {
                        $query->orWhere(fn ($q) => $q->whereIn('variant_id', $variantIds));
                    }
                })
                ->orderBy('priority', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            if ($rules->isNotEmpty()) {
                foreach ($rules as $rule) {
                    if ($rule->is_hidden ?? false) {
                        return false;
                    }
                }
                return true;
            }
        }

        return true;
    }

    /**
     * Получить срок доставки для товара в локации доставки
     * 
     * @param Product $product Товар
     * @param ShippingLocation|null $location Локация доставки (null = без локации)
     * @param Product|null $variant Вариация товара (если есть)
     * @return int|null Срок доставки в днях (null если не переопределен)
     */
    public function getDeliveryDaysForRegion(Product $product, ?ShippingLocation $location, ?Product $variant = null): ?int
    {
        if (!$location) {
            return null;
        }

        $locationIds = $this->preloadedLocationIds ?? array_reverse($location->getAncestorsIds());

        if ($this->preloadedRules !== null) {
            foreach ($locationIds as $locId) {
                $byProduct = $this->preloadedRules[$locId][$product->id] ?? null;
                if (!$byProduct) {
                    continue;
                }
                $rule = $variant ? ($byProduct[$variant->id] ?? $byProduct['p'] ?? null) : ($byProduct['p'] ?? null);
                if ($rule && $rule->delivery_days_override !== null) {
                    return $rule->delivery_days_override;
                }
            }
            return null;
        }

        foreach ($locationIds as $locId) {
            $query = ProductRegionRule::query()
                ->active()
                ->where('shipping_location_id', $locId)
                ->whereNotNull('delivery_days_override');

            if ($variant) {
                $query->where(fn ($q) => $q->where('variant_id', $variant->id)
                    ->orWhere(fn ($sq) => $sq->where('product_id', $product->id)->whereNull('variant_id')));
            } else {
                $query->where('product_id', $product->id)->whereNull('variant_id');
            }

            $rule = $query->orderBy('priority', 'desc')->orderBy('id', 'desc')->first();
            if ($rule) {
                return $rule->delivery_days_override;
            }
        }

        return null;
    }

    /**
     * Получить доступные методы доставки для локации доставки
     * Берет carriers из локации (с учетом иерархии) и создает ShippingMethod на их основе
     * 
     * @param ShippingLocation|null $location Локация доставки (null = все методы)
     * @return Collection Коллекция методов доставки
     */
    public function getAvailableShippingMethods(?ShippingLocation $location): Collection
    {
        // Если локация не указана, возвращаем все активные методы
        if (!$location) {
            $methods = \Vanilo\Shipment\Models\ShippingMethod::where('is_active', true)
                ->with('carrier')
                ->get();

            Log::debug('shipping.methods: no location, returning all active methods', [
                'count' => $methods->count(),
            ]);

            return $methods;
        }

        // Получаем carriers для локации с учетом иерархии
        $carriers = $location->getEffectiveCarriers();

        if ($carriers->isEmpty()) {
            Log::info('shipping.methods: no carriers for location, using location tariff fallback', [
                'location_id' => $location->id,
                'source' => self::SHIPPING_METHOD_SOURCE_LOCATION_FALLBACK,
            ]);

            return $this->buildLocationFallbackShippingMethods($location);
        }

        Log::debug('shipping.methods: building methods from location carriers', [
            'location_id' => $location->id,
            'source' => self::SHIPPING_METHOD_SOURCE_CARRIER,
            'carrier_count' => $carriers->count(),
        ]);

        // Для каждого carrier создаем или получаем ShippingMethod
        $shippingMethods = collect();

        foreach ($carriers as $carrier) {
            // Получаем настройки из pivot для текущей локации или родителя
            $pivot = null;
            // Важно: сначала текущая локация, затем родители (более специфичное правило важнее).
            $locationIds = $location->getAncestorsIds();

            foreach ($locationIds as $locId) {
                $tempLocation = ShippingLocation::find($locId);
                if ($tempLocation) {
                    $pivot = $tempLocation->carriers()
                        ->where('carriers.id', $carrier->id)
                        ->wherePivot('is_active', true)
                        ->first()?->pivot;

                    if ($pivot) {
                        break;
                    }
                }
            }

            // Цены/сроки только из pivot привязки службы к локации (без подмешивания тарифа локации)
            $basePrice = $pivot !== null && $pivot->base_price !== null
                ? (float) $pivot->base_price
                : 0.0;
            $freeThreshold = $pivot?->free_delivery_threshold;
            $deliveryDaysMin = $pivot?->delivery_days_min ?? 1;
            $deliveryDaysMax = $pivot?->delivery_days_max ?? 3;
            $sortOrder = $pivot?->sort_order ?? 0;

            // Создаем уникальное имя для метода доставки
            $methodName = "{$carrier->name} - {$location->name}";

            // Создаем или получаем shipping method
            $shippingMethod = \Vanilo\Shipment\Models\ShippingMethod::firstOrCreate(
                [
                    'name' => $methodName,
                    'carrier_id' => $carrier->id,
                ],
                [
                    'name' => $methodName,
                    'carrier_id' => $carrier->id,
                    'zone_id' => null,
                    'configuration' => [
                        'base_price' => $basePrice,
                        'free_delivery_threshold' => $freeThreshold,
                        'delivery_days_min' => $deliveryDaysMin,
                        'delivery_days_max' => $deliveryDaysMax,
                        'location_id' => $location->id,
                        'location_type' => $location->type,
                        'sort_order' => $sortOrder,
                        'source' => self::SHIPPING_METHOD_SOURCE_CARRIER,
                    ],
                    'is_active' => true,
                ]
            );

            // Обновляем конфигурацию, если метод уже существовал
            if ($shippingMethod->wasRecentlyCreated === false) {
                $config = $shippingMethod->configuration ?? [];
                $config['base_price'] = $basePrice;
                $config['free_delivery_threshold'] = $freeThreshold;
                $config['delivery_days_min'] = $deliveryDaysMin;
                $config['delivery_days_max'] = $deliveryDaysMax;
                $config['location_id'] = $location->id;
                $config['location_type'] = $location->type;
                $config['sort_order'] = $sortOrder;
                $config['source'] = self::SHIPPING_METHOD_SOURCE_CARRIER;

                $shippingMethod->configuration = $config;
                $shippingMethod->save();
            }

            // Загружаем carrier, если не загружен
            if (!$shippingMethod->relationLoaded('carrier')) {
                $shippingMethod->load('carrier');
            }

            $shippingMethods->push($shippingMethod);
        }

        // Сортируем по sort_order из pivot или конфигурации
        $sorted = $shippingMethods->sortBy(function ($method) {
            return $method->configuration['sort_order'] ?? 999;
        })->values();

        Log::info('shipping.methods: resolved carrier-based methods for location', [
            'location_id' => $location->id,
            'method_count' => $sorted->count(),
            'source' => self::SHIPPING_METHOD_SOURCE_CARRIER,
        ]);

        return $sorted;
    }

    /**
     * Один метод доставки по полям локации (effective), если нет привязанных служб.
     *
     * @return Collection<int, \Vanilo\Shipment\Models\ShippingMethod>
     */
    private function buildLocationFallbackShippingMethods(ShippingLocation $location): Collection
    {
        $fallbackCarrier = Carrier::firstOrCreate(
            ['name' => 'Тариф локации (системный)'],
            [
                'is_active' => true,
                'configuration' => ['type' => 'location_tariff'],
            ]
        );

        $basePrice = (float) ($location->getEffectiveDeliveryPrice() ?? 0);
        $freeThreshold = $location->getEffectiveFreeDeliveryThreshold();
        $effectiveDays = $location->getEffectiveDeliveryDays();
        $deliveryDaysMin = $effectiveDays['min'] ?? 1;
        $deliveryDaysMax = $effectiveDays['max'] ?? 3;

        $methodName = 'Доставка — '.$location->name.' #'.$location->id;

        $shippingMethod = \Vanilo\Shipment\Models\ShippingMethod::firstOrCreate(
            [
                'name' => $methodName,
                'carrier_id' => $fallbackCarrier->id,
            ],
            [
                'name' => $methodName,
                'carrier_id' => $fallbackCarrier->id,
                'zone_id' => null,
                'configuration' => [
                    'base_price' => $basePrice,
                    'free_delivery_threshold' => $freeThreshold,
                    'delivery_days_min' => $deliveryDaysMin,
                    'delivery_days_max' => $deliveryDaysMax,
                    'location_id' => $location->id,
                    'location_type' => $location->type,
                    'sort_order' => 0,
                    'source' => self::SHIPPING_METHOD_SOURCE_LOCATION_FALLBACK,
                ],
                'is_active' => true,
            ]
        );

        $config = $shippingMethod->configuration ?? [];
        $config['base_price'] = $basePrice;
        $config['free_delivery_threshold'] = $freeThreshold;
        $config['delivery_days_min'] = $deliveryDaysMin;
        $config['delivery_days_max'] = $deliveryDaysMax;
        $config['location_id'] = $location->id;
        $config['location_type'] = $location->type;
        $config['sort_order'] = 0;
        $config['source'] = self::SHIPPING_METHOD_SOURCE_LOCATION_FALLBACK;
        $shippingMethod->configuration = $config;
        $shippingMethod->save();

        if (!$shippingMethod->relationLoaded('carrier')) {
            $shippingMethod->load('carrier');
        }

        if ($basePrice <= 0 && $freeThreshold === null) {
            Log::warning('shipping.methods: location fallback has no tariff data', [
                'location_id' => $location->id,
            ]);
        }

        return collect([$shippingMethod]);
    }

    /**
     * Получить доступные методы оплаты для локации доставки
     * Берет методы оплаты из связи с локацией (с учетом иерархии)
     * 
     * @param ShippingLocation|null $location Локация доставки (null = все методы)
     * @return Collection Коллекция методов оплаты
     */
    public function getAvailablePaymentMethods(?ShippingLocation $location): Collection
    {
        // Если локация не указана, возвращаем все активные методы
        if (!$location) {
            return PaymentMethod::active()->ordered()->get();
        }

        // Получаем методы оплаты для локации с учетом иерархии
        $paymentMethods = $location->getEffectivePaymentMethods();

        if ($paymentMethods->isEmpty()) {
            // Если методов оплаты нет, возвращаем все активные методы (fallback)
            return PaymentMethod::active()->ordered()->get();
        }

        // Сортируем по sort_order из pivot
        return $paymentMethods->sortBy(function ($method) use ($location) {
            // Сначала ищем sort_order в текущей локации, затем у предков.
            $locationIds = $location->getAncestorsIds();
            
            foreach ($locationIds as $locId) {
                $tempLocation = ShippingLocation::find($locId);
                if ($tempLocation) {
                    $pivot = $tempLocation->paymentMethods()
                        ->where('payment_methods.id', $method->id)
                        ->wherePivot('is_active', true)
                        ->first()?->pivot;
                    
                    if ($pivot) {
                        return $pivot->sort_order ?? 999;
                    }
                }
            }
            
            return $method->sort_order ?? 999;
        })->values();
    }
}
