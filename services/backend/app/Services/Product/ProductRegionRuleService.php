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

        $this->preloadedLocationIds = $location->getAncestorsIds();
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
        $sku = $variant ?? $product;
        $engine = app(\App\Services\Pricing\PricingEngine::class);
        if ($engine->eligible($location, $engine->customer())->isEmpty()) {
            return $this->getLegacyPriceForRegion($product, $location, $variant);
        }
        return $engine->price($sku, $location);
    }

    public function getLegacyPriceForRegion(Product $product, ?ShippingLocation $location, ?Product $variant = null): float
    {
        $basePrice = $variant ? (float) $variant->price : (float) $product->price;
        if (!$location) {
            return $basePrice;
        }

        $locationIds = $this->preloadedLocationIds ?? $location->getAncestorsIds();

        if ($this->preloadedRules !== null) {
            foreach ($locationIds as $locId) {
                $byProduct = $this->preloadedRules[$locId][$product->id] ?? null;
                if (!$byProduct) {
                    continue;
                }
                $rule = $variant ? ($byProduct[$variant->id] ?? $byProduct['p'] ?? null) : ($byProduct['p'] ?? null);
                if ($rule) {
                    return round($rule->applyToPrice($basePrice), 2);
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

            $rule = $query->orderByRaw('CASE WHEN variant_id IS NULL THEN 1 ELSE 0 END')->orderBy('priority', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($rule) {
                return round($rule->applyToPrice($basePrice), 2);
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

        $locationIds = $this->preloadedLocationIds ?? $location->getAncestorsIds();

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

        $locationIds = $location->getAncestorsIds();

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
        $destination = request()->attributes->get('warehouse_destination');
        if (! $destination || ! $destination['specified']) {
            return null;
        }
        $sources = app(\App\Services\Shipping\FulfillmentSourceResolver::class)->resolve($variant ?? $product, $destination);
        return $sources->map(fn ($source): int => $source['processing_days_min'] + $source['delivery_days_min'])->min();

    }

    /**
     * Получить доступные методы доставки для локации доставки
     * Берет carriers из локации (с учетом иерархии) и создает ShippingMethod на их основе
     * 
     * @param ShippingLocation|null $location Локация доставки (null = все методы)
     * @return Collection Коллекция методов доставки
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
