<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Product\Category;
use App\Models\Product\Room;
use App\Models\Product\RoomFilters;
use App\Models\Product\Product;
use App\Models\Product\ProductCollection;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Manufacturer;
use App\Models\Shipping\ShippingLocation;
use App\Services\Product\ProductRegionRuleService;
use App\Services\Product\ProductCanonicalAttributeQueryService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @OA\Tag(
 *     name="Products",
 *     description="Работа с товарами и каталогом"
 * )
 */
class ProductController extends Controller
{
    public function __construct(
        protected ProductRegionRuleService $regionRuleService
    ) {
    }

    /**
     * Получить локацию доставки из запроса
     * Возвращает null если локация не передана или не найдена
     *
     * ВАЖНО: Правила применяются к локации доставки и всем её дочерним локациям (наследование)
     */
    protected function getRegionFromRequest(Request $request): ?ShippingLocation
    {
        $shippingLocationId = $request->input('shipping_location_id');
        $legacyRegionId = $request->input('region_id');
        $locationId = $shippingLocationId ?? $legacyRegionId;

        if (!$locationId) {
            $request->attributes->set('warehouse_destination', app(\App\Services\Shipping\CatalogDestination::class)->resolve($request, null));
            $request->merge(['_region' => null, '_region_id' => null]);
            return null;
        }

        Log::debug('Resolving product location from request', [
            'shipping_location_id' => $shippingLocationId,
            'region_id' => $legacyRegionId,
            'resolved_location_id' => $locationId,
            'source' => $shippingLocationId ? 'shipping_location_id' : 'region_id',
            'path' => $request->path(),
        ]);

        if ($shippingLocationId === null && $legacyRegionId !== null) {
            Log::info('Using legacy region_id for product location resolution', [
                'region_id' => $legacyRegionId,
                'path' => $request->path(),
            ]);
        }

        // Ищем локацию доставки (может быть любого типа: федеральный округ, регион, город)
        $location = ShippingLocation::where('id', $locationId)
            ->where('is_active', true)
            ->first();

        if (!$location) {
            Log::warning('Invalid or inactive location provided for product request', [
                'resolved_location_id' => $locationId,
                'shipping_location_id' => $shippingLocationId,
                'region_id' => $legacyRegionId,
                'path' => $request->path(),
            ]);
            throw \Illuminate\Validation\ValidationException::withMessages(['shipping_location_id' => 'Локация не найдена или отключена.']);
        }

        $request->attributes->set('warehouse_destination', app(\App\Services\Shipping\CatalogDestination::class)->resolve($request, $location));
        return $location;
    }

    /**
     * Запрос «горячий» (первая страница без фильтров) — дольше кэшируем и прогреваем.
     */
    protected function isHotIndexRequest(Request $request): bool
    {
        if ($request->input('page', 1) != 1) {
            return false;
        }
        $perPage = (int) $request->get('per_page', 20);
        if ($perPage < 1 || $perPage > 20) {
            return false;
        }
        if ($request->filled('category_id') || $request->filled('category_slug')) {
            return false;
        }
        if ($request->filled('room_slug') || $request->filled('room_id')) {
            return false;
        }
        if ($request->filled('search') || $request->filled('price_min') || $request->filled('price_max')) {
            return false;
        }
        if ($request->filled('region_id') || $request->filled('shipping_location_id')) {
            return false;
        }
        $colors = $request->input('colors', []);
        $sizes = $request->input('sizes', []);
        if (!empty($colors) || !empty($sizes)) {
            return false;
        }
        if ($request->filled('manufacturer_id') || $request->filled('manufacturer_slug')) {
            return false;
        }
        $attrs = $this->parseAttributesFromRequest($request);
        return empty($attrs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/count",
     *     operationId="countProducts",
     *     summary="Количество товаров по фильтрам каталога",
     *     tags={"Products"},
     *     @OA\Response(response=200, description="Количество уникальных карточек",
     *         @OA\JsonContent(type="object", @OA\Property(property="total", type="integer")))
     * )
     * Full parameter contract is maintained in public/api-docs/openapi.json.
     */
    public function count(Request $request): JsonResponse
    {
        return $this->index($request, countOnly: true);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products",
     *     operationId="getProducts",
     *     summary="Получить список товаров",
     *     description="Возвращает список товаров с пагинацией, фильтрацией и сортировкой",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Номер страницы",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Количество товаров на странице (максимум 100)",
     *         required=false,
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="ID категории",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="category_slug",
     *         in="query",
     *         description="Slug категории",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="price_min",
     *         in="query",
     *         description="Минимальная цена",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="price_max",
     *         in="query",
     *         description="Максимальная цена",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Поисковый запрос",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="region_id",
     *         in="query",
     *         description="ID региона доставки",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Поле для сортировки (created_at, price, name, units_sold, rating)",
     *         required=false,
     *         @OA\Schema(type="string", example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Направление сортировки (asc, desc)",
     *         required=false,
     *         @OA\Schema(type="string", example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список товаров",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request, bool $countOnly = false): AnonymousResourceCollection|JsonResponse
    {
        $request->validate([
            'on_sale' => 'sometimes|boolean',
            'price_min' => 'sometimes|numeric|min:0|max:100000000000',
            'price_max' => 'sometimes|numeric|min:0|max:100000000000',
            'sort_by' => 'sometimes|in:created_at,price,name,units_sold,rating,distance',
            'sort_order' => 'sometimes|in:asc,desc',
        ]);
        $dimensionRules = [];
        foreach (\App\Services\Product\CatalogFilterService::DIMENSIONS as $dimension => $slug) {
            $dimensionRules[$dimension.'_min'] = ['sometimes', 'numeric', 'min:0', 'max:1000000'];
            $dimensionRules[$dimension.'_max'] = ['sometimes', 'numeric', 'min:0', 'max:1000000'];
            if ($request->has($dimension.'_min') && $request->has($dimension.'_max')) {
                $dimensionRules[$dimension.'_max'][] = 'gte:'.$dimension.'_min';
            }
        }
        $request->validate($dimensionRules);
        $categorySlugs = $request->input('category_slugs', []);
        if (is_string($categorySlugs)) {
            $categorySlugs = explode(',', $categorySlugs);
        }
        validator(['category_slugs' => $categorySlugs], [
            'category_slugs' => 'array|max:100', 'category_slugs.*' => 'string|max:255',
        ])->validate();
        $categorySlugs = array_values(array_unique(array_filter(array_map('trim', $categorySlugs))));
        sort($categorySlugs);
        // Комната (вторая таксономия): сводим к продуктовым категориям + базовый фильтр из комнаты.
        $roomSlug = $request->get('room_slug') ?? $request->get('room_id');
        $roomCategoryIds = null;
        $roomEffectiveFilters = null;
        if ($roomSlug) {
            $room = Room::where('slug', $roomSlug)->orWhere('id', $roomSlug)->first();
            if ($room) {
                // Ограничения комнаты (с наследованием) применим ниже — сузив ими фильтр пользователя.
                $roomEffectiveFilters = $room->effectiveFilters();
                // productCategories самой комнаты И всех подкомнат (родитель = товары подкомнат).
                $roomIds = Room::getAllDescendantIdsFor($room->id);
                $rooms = Room::whereIn('id', $roomIds)->with('productCategories')->get();
                $ids = [];
                foreach ($rooms as $r) {
                    foreach ($r->productCategories as $cat) {
                        $ids = array_merge($ids, Category::getAllDescendantIdsFor($cat->id));
                    }
                }
                $roomCategoryIds = array_values(array_unique($ids));
            } else {
                $roomCategoryIds = []; // комната не найдена — пустой список
            }
        }

        // Создаем детальный ключ кэша с учетом всех параметров запроса
        $perPage = max(1, min((int) $request->get('per_page', 20), 100));
        $page = max(1, (int) $request->input('page', 1));
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $categoryId = $request->get('category_id');
        $categorySlug = $request->get('category_slug');
        $priceMin = $request->get('price_min');
        $priceMax = $request->get('price_max');
        $search = $request->get('search');
        $colors = $request->input('colors', []);
        $sizes = $request->input('sizes', []);
        $attributes = $this->parseAttributesFromRequest($request);
        $manufacturerId = $request->get('manufacturer_id');
        $manufacturerSlug = $request->get('manufacturer_slug');
        // Нормализуем массивы для ключа кэша. Пустые фильтры = не применяем, показываем все товары.
        if (is_string($colors)) {
            $colors = array_filter(array_map('trim', explode(',', $colors)));
        }
        if (is_string($sizes)) {
            $sizes = array_filter(array_map('trim', explode(',', $sizes)));
        }
        if (!is_array($colors)) {
            $colors = [];
        }
        if (!is_array($sizes)) {
            $sizes = [];
        }
        // Пустые значения и null — считаем «фильтр не задан»
        $colors = array_values(array_filter(array_map('trim', $colors)));
        $sizes = array_values(array_filter(array_map('trim', $sizes)));

        $catalogInput = ['price_min' => $priceMin, 'price_max' => $priceMax,
            'colors' => $colors, 'sizes' => $sizes, 'attributes' => $attributes,
            'on_sale' => $request->boolean('on_sale')];
        foreach (\App\Services\Product\CatalogFilterService::DIMENSIONS as $dimension => $slug) {
            foreach (['min', 'max'] as $bound) {
                $catalogInput[$dimension.'_'.$bound] = $request->input($dimension.'_'.$bound);
            }
        }

        // Visibility can change the result set: include location and all new filters in the cache key.
        $cacheKeyPayload = sprintf(
            'index:page:%d:per_page:%d:sort_by:%s:sort_order:%s:category_id:%s:category_slug:%s:price_min:%s:price_max:%s:search:%s:colors:%s:sizes:%s:attributes:%s:manufacturer:%s',
            $page,
            $perPage,
            $sortBy ?: 'null',
            $sortOrder,
            $categoryId ?: 'null',
            $categorySlug ?: 'null',
            $priceMin ?: 'null',
            $priceMax ?: 'null',
            $search ?: 'null',
            implode(',', $colors),
            implode(',', $sizes),
            json_encode($attributes),
            $manufacturerId ?: $manufacturerSlug ?: 'null'
        );
        $cacheKey = 'idx:v3:' . md5($cacheKeyPayload . '|room:' . ($roomSlug ?: 'null')
            .'|room_constraints:'.json_encode([$roomEffectiveFilters, $roomCategoryIds])
            .'|dimension_filters:'.json_encode($catalogInput).'|categories:'.json_encode($categorySlugs).'|sale:'.(int) $request->boolean('on_sale')
            .'|revision:'.Cache::get('catalog_filters_revision', '0')
            .'|location:'.($request->input('shipping_location_id') ?? $request->input('region_id') ?? 'none'));

        // ВАЖНО: Получаем регион из запроса ДО построения запроса
        // Передаём объект в request, чтобы ProductResource не вызывал find() на каждый товар (N+1)
        $location = $this->getRegionFromRequest($request);
        $destination = app(\App\Services\Shipping\CatalogDestination::class)->resolve($request, $location);
        $request->attributes->set('warehouse_destination', $destination);
        $cacheKey = 'idx:v5:'.sha1($cacheKey.json_encode($destination).Cache::get('fulfillment_revision', '0').app(\App\Services\Pricing\PricingEngine::class)->cacheContext($location));
        if ($location) {
            $request->merge(['_region_id' => $location->id, '_region' => $location]);
            if (! $countOnly) {
                $this->regionRuleService->preloadRulesForLocation($location);
            }
        }

        $query = Product::query()->active()->with(['taxons', 'variants', 'manufacturer']);
        if ($location) {
            $query = $this->applyRegionVisibilityFilter($query, $location);
        }

        $categoryResolved = null;
        if ($categoryId) {
            $query->inCategory($categoryId);
            $categoryResolved = Category::find($categoryId);
        }

        if ($categorySlug) {
            $categoryResolved = Category::where('slug', $categorySlug)->first();
            if ($categoryResolved) {
                $query->inCategory($categoryResolved->id);
            } else {
                // Категория не найдена — не отдаём все товары, а пустой список
                $query->whereRaw('1 = 0');
            }
        }

        if ($categorySlugs) {
            $categories = Category::whereIn('slug', $categorySlugs)->get();
            if ($categories->count() !== count($categorySlugs)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['category_slugs' => 'Одна из выбранных категорий не найдена.']);
            }
            $ids = $categories->flatMap(fn ($category) => Category::getAllDescendantIdsFor($category->id))->unique()->values()->all();
            $query->whereHas('taxons', fn ($taxons) => $taxons->whereIn('taxons.id', $ids));
        }

        // Товары комнаты = объединение товаров её продуктовых категорий (с потомками).
        if ($roomCategoryIds !== null) {
            if (! empty($roomCategoryIds)) {
                $query->whereHas('taxons', fn ($q) => $q->whereIn('taxons.id', $roomCategoryIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($search) {
            $query->search($search);
        }

        // Фильтр по производителю
        if ($manufacturerId) {
            $query->where('manufacturer_id', (int) $manufacturerId);
        } else if ($manufacturerSlug) {
            $query->whereHas('manufacturer', function ($q) use ($manufacturerSlug) {
                $q->where('slug', $manufacturerSlug);
            });
        }

        $baseQueryForFilters = (clone $query)->whereNull('parent_product_id');
        $catalogFilters = app(\App\Services\Product\CatalogFilterService::class)->forLocation($location);
        $query->whereNull('parent_product_id');
        $catalogFilters->apply($query, $catalogFilters->filters($catalogInput, $roomEffectiveFilters ?? []));
        if ($countOnly) {
            return response()->json(['total' => (clone $query)->reorder()->count('products.id')]);
        }

        if ($sortBy === 'distance') {
            if (! $destination['specified']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['directory_external_id' => 'Для сортировки по расстоянию выберите местоположение.']);
            }
            app(\App\Services\Inventory\CatalogWarehouseDistance::class)->order($query,
                $catalogFilters->skus($baseQueryForFilters, $catalogFilters->filters($catalogInput, $roomEffectiveFilters ?? [])), $destination);
        } elseif ($sortBy === 'price') {
            $prices = $catalogFilters->skus($baseQueryForFilters, $catalogFilters->filters($catalogInput, $roomEffectiveFilters ?? []))
                ->selectRaw('COALESCE(products.parent_product_id, products.id) AS root_id, MIN(catalog_prices.effective_price) AS effective_price')
                ->groupByRaw('COALESCE(products.parent_product_id, products.id)');
            $query->leftJoinSub($prices, 'root_prices', 'root_prices.root_id', '=', 'products.id')
                ->select('products.*')->orderByRaw('root_prices.effective_price IS NULL')->orderBy('root_prices.effective_price', $sortOrder)->orderBy('products.id');
        } else {
            $this->applyCatalogSort($query, $sortBy, $sortOrder);
        }

        $categoryId = $categoryResolved?->id ?? $categoryId;
        $indexTtl = $this->isHotIndexRequest($request) ? (int) config('cache_segments.hot_ttl', 86400) : (int) config('cache_segments.cold_ttl', 36000);

        $emptyFiltersResponse = function () use ($request, $perPage, $page) {
            $location = $this->getRegionFromRequest($request);
            if ($location) {
                $request->merge(['_region_id' => $location->id, '_region' => $location]);
            }
            $request->merge(['_list_view' => true]);
            $emptyPaginator = new LengthAwarePaginator([], 0, $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
            $emptyFilters = [
                'price' => ['min' => 0, 'max' => 0],
                'colors' => [],
                'sizes' => [],
                'attributes' => [],
                'manufacturers' => [],
                'has_on_sale' => false,
                'has_sale' => false,
                'facet_mode' => 'excluding_self',
                'dimensions' => array_fill_keys(['width', 'depth', 'height'], ['min' => null, 'max' => null, 'unit' => 'mm']),
            ];
            return ProductResource::collection($emptyPaginator)->additional([
                'meta' => ['filters' => $emptyFilters],
            ]);
        };

        // Пустая категория: по кэшу или один exists() — сразу отдаём пустой список без загрузки карточек и построения фасетов
        if ($categoryId) {
            $emptyCategoryKey = 'empty_category:v2:'.sha1($baseQueryForFilters->toSql().json_encode($baseQueryForFilters->getBindings()).Cache::get('catalog_filters_revision', '0'));
            if (Cache::get($emptyCategoryKey)) {
                return $emptyFiltersResponse();
            }
            if ((clone $baseQueryForFilters)->limit(1)->exists() === false) {
                Cache::put($emptyCategoryKey, true, 300); // 5 мин; сбрасывается при сохранении товара/категории
                return $emptyFiltersResponse();
            }
        }

        if ($categoryId) {
            $products = Product::cachedWithTags(
                ["category_{$categoryId}_products", "product_index_cache"],
                $cacheKey,
                function () use ($query, $perPage, $request) {
                    return $this->fetchProductsForCache($query, $perPage, $request, true);
                },
                $indexTtl
            );
        } else {
            $products = Product::cachedWithTags(
                ["product_index_cache"],
                $cacheKey,
                function () use ($query, $perPage, $request) {
                    return $this->fetchProductsForCache($query, $perPage, $request, true);
                },
                $indexTtl
            );
        }

        // Передаём локацию в request для ProductResource (избегаем N+1)
        $location = $this->getRegionFromRequest($request);
        if ($location) {
            $request->merge(['_region_id' => $location->id, '_region' => $location]);
        }

        // Мета-данные фильтров: кэшируем по категории (одни и те же для всех запросов категории), TTL 5 мин.
        // Если категория не задана, фильтрацию по атрибутам/цветам/размерам не строим — это сильно нагружает БД
        // и в реальном фронте каталог всегда фильтруется по категории.
        $filtersMeta = null;
        if ($categoryId || $categorySlugs || $roomSlug) {
            $filtersMetaCacheKey = 'product_filters_meta:v3:'.sha1($baseQueryForFilters->toSql()
                .json_encode([$baseQueryForFilters->getBindings(), $catalogInput, $roomEffectiveFilters, $location?->id])
                .Cache::get('catalog_filters_revision', '0').Cache::get('fulfillment_revision', '0').app(\App\Services\Pricing\PricingEngine::class)->cacheContext($location));
            $filtersMeta = Cache::remember($filtersMetaCacheKey, 300,
                fn () => $catalogFilters->meta($baseQueryForFilters, $catalogInput, $roomEffectiveFilters ?? []));
        }

        if ($location) {
            $this->regionRuleService->preloadRulesForLocation($location);
        }

        // Облегчённый ответ для списка: без specifications и seo — меньше payload и быстрее сериализация
        $request->merge(['_list_view' => true]);
        $request->attributes->set('matching_sku_ids', $catalogFilters->skus((clone $baseQueryForFilters)->whereIn('products.id', $products->pluck('id')),
            $catalogFilters->filters($catalogInput, $roomEffectiveFilters ?? []))->pluck('products.id')->all());
        app(\App\Services\Pricing\PricingEngine::class)->preload($request->attributes->get('matching_sku_ids'), $location);

        return ProductResource::collection($products)->additional([
            'meta' => [
                'filters' => $filtersMeta,
            ],
        ]);
    }

    private function applyCatalogSort(\Illuminate\Database\Eloquent\Builder $query, string $sortBy, string $sortOrder): void
    {
        if ($sortBy === 'rating') {
            $query->addSelect('products.*')->selectSub(
                DB::table('reviews')->selectRaw('COALESCE(ROUND(AVG(rating), 1), 0)')
                    ->whereColumn('reviews.product_id', 'products.id')->where('is_approved', true),
                'rating',
            )->orderBy('rating', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }
        $query->orderBy('products.id', 'asc');
    }

    /**
     * Получить товары для кэширования (вынесено в отдельный метод для переиспользования).
     * Для списка каталога ($listView = true) не грузим attributeValues — меньше БД и payload.
     */
    protected function fetchProductsForCache(\Illuminate\Database\Eloquent\Builder $query, int $perPage, Request $request, bool $listView = false)
    {
        $with = [
            'taxons',
            'variants' => fn ($q) => $q->active()->orderBy('id'),
            'manufacturer',
            'media',
        ];
        if (!$listView) {
            $with[] = 'attributeValues.attribute';
        }
        $products = $query->with($with)->paginate($perPage);

        // Не фильтруем по видимости в регионе здесь: кэш общий для всех регионов.
        // is_visible_in_region и цены подставляются в ProductResource при отдаче по request._region.
        return $products;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/{slug}",
     *     operationId="getProductDetails",
     *     summary="Получить детальную информацию о товаре",
     *     description="Возвращает товар с вариациями, характеристиками, блоками фич и доставки.
     *         При передаче color и size для вариативного товара пытается вернуть конкретную вариацию.",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Slug или ID товара",
     *         required=true,
     *         @OA\Schema(type="string", example="divan-uglovoi-modul-s-iashhikom")
     *     ),
     *     @OA\Parameter(
     *         name="color",
     *         in="query",
     *         description="Цвет вариации (название или slug, например Синий или sinii)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="Коммерческий размер вариации: значение или slug характеристики size (не физические габариты товара)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="region_id",
     *         in="query",
     *         description="ID региона доставки для применения региональных правил цен и наличия",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Детальная информация о товаре",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="product", type="object"),
     *             @OA\Property(
     *                 property="variant_info",
     *                 type="object",
     *                 nullable=true,
     *                 description="Доступные размеры для цвета и цвета для размера у вариативных товаров"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Товар не найден или недоступен в регионе"
     *     )
     * )
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        // Получаем регион из запроса
        $region = $this->getRegionFromRequest($request);

        // ВАЖНО: Устанавливаем _region_id в request ДО загрузки продукта,
        // чтобы ProductDetailResource мог получить регион для применения правил
        if ($region) {
            $request->merge(['_region_id' => $region->id]);
            $this->regionRuleService->preloadRulesForLocation($region);
        }

        // Параметры вариации:
        // - attributes[color]=slug1&attributes[size]=slug2
        // - attr_color=...&attr_size=...
        // - color=...&size=... (legacy / simple)
        $attributesParam = $request->get('attributes', []);
        if (!is_array($attributesParam)) {
            $attributesParam = [];
        }
        $attributesParam = array_filter($attributesParam);

        // Если attributes[] не передали — соберем из остальных поддерживаемых форматов.
        if ($attributesParam === []) {
            $attributesParam = $this->parseAttributesFromRequest($request);

            $color = trim((string) $request->query('color', ''));
            if ($color !== '') {
                $attributesParam['color'] = [$color];
            }
            $size = trim((string) $request->query('size', ''));
            if ($size !== '') {
                $attributesParam['size'] = [$size];
            }
        }

        $cacheKey = "show_{$slug}" . (empty($attributesParam) ? '' : '_' . json_encode($attributesParam)) . ($region ? "_region_{$region->id}" : '');
        $productId = $this->resolveProductShowCacheId($slug);
        $loadProductCallback = function () use ($slug, $attributesParam) {
            return $this->loadProductForShow($slug, $attributesParam);
        };
        $supportsTags = in_array(config('cache.default'), ['redis', 'memcached'], true);
        if ($productId !== null && $supportsTags) {
            $product = \Illuminate\Support\Facades\Cache::tags(['product_show_' . $productId])->remember(
                Product::cacheKey($cacheKey),
                36000,
                $loadProductCallback
            );
        } else {
            $product = $loadProductCallback();
        }

        if ($region) {
            $variant = $product->isVariant() ? $product : null;
            $parentProduct = $product->isVariant() ? $product->parentProduct : $product;
            if (!$this->regionRuleService->isVisibleInRegion($parentProduct, $region, $variant)) {
                return response()->json([
                    'message' => 'Товар недоступен в выбранном регионе',
                ], 404);
            }
        }

        $parentForLists = $this->resolveParentProductForLists($product);
        $request->merge(['_list_view' => true]);
        $bundleItems = $this->fetchBundleProducts($parentForLists);
        $relatedItems = $this->fetchRelatedProducts($parentForLists);

        return response()->json([
            'product' => new ProductDetailResource($product),
            'variant_info' => null,
            'bundle' => [
                'data' => ProductResource::collection($bundleItems)->resolve($request),
            ],
            'related' => [
                'data' => ProductResource::collection($relatedItems)->resolve($request),
            ],
        ]);
    }

    /**
     * ID родителя для тегированного кэша карточки (родитель или parent вариации по slug).
     */
    protected function resolveProductShowCacheId(string $slug): ?int
    {
        $parentId = Product::query()
            ->where('slug', $slug)
            ->whereNull('parent_product_id')
            ->value('id');

        if ($parentId !== null) {
            return (int) $parentId;
        }

        if (\is_numeric($slug)) {
            $numericId = (int) $slug;
            $exists = Product::query()
                ->where('id', $numericId)
                ->whereNull('parent_product_id')
                ->exists();

            return $exists ? $numericId : null;
        }

        $variantParentId = Product::query()
            ->where('slug', $slug)
            ->whereNotNull('parent_product_id')
            ->value('parent_product_id');

        return $variantParentId !== null ? (int) $variantParentId : null;
    }

    /**
     * Загрузить карточку: родитель по slug, вариация по attributes или прямой URL вариации (/product/{variant-slug}).
     */
    protected function loadProductForShow(string $slug, array $attributesParam): Product
    {
        $parentEagerLoad = [
            'taxons',
            'taxons.parent',
            'variants' => function ($query) {
                $query->active()->with(['attributes']);
            },
        ];

        $product = Product::query()
            ->where(function ($query) use ($slug) {
                $query->where('slug', $slug)
                    ->orWhere('id', $slug);
            })
            ->whereNull('parent_product_id')
            ->with($parentEagerLoad)
            ->active()
            ->first();

        if ($product !== null) {
            if ($product->isVariable() && $attributesParam !== []) {
                $variationSelection = [];
                foreach ($attributesParam as $attributeSlug => $rawValue) {
                    $candidates = is_array($rawValue) ? $rawValue : [$rawValue];
                    foreach ($candidates as $candidate) {
                        $candidate = trim((string) $candidate);
                        if ($candidate !== '') {
                            $variationSelection[$attributeSlug] = $candidate;
                            break;
                        }
                    }
                }

                $variant = $product->getVariantByVariationAttributes($variationSelection);
                if ($variant) {
                    return $this->loadVariantForShow($variant);
                }
            }

            return $product;
        }

        $variant = Product::query()
            ->where('slug', $slug)
            ->whereNotNull('parent_product_id')
            ->active()
            ->first();

        if ($variant === null) {
            abort(404, 'Товар не найден');
        }

        return $this->loadVariantForShow($variant);
    }

    protected function loadVariantForShow(Product $variant): Product
    {
        $loadedVariant = $variant->load([
            'taxons',
            'taxons.parent',
            'variantAttributeValues.attribute',
            'parentProduct' => function ($query) {
                $query->active()->with([
                    'taxons',
                    'taxons.parent',
                    'variants' => function ($vQuery) {
                        $vQuery->active()->with(['attributes']);
                    },
                ]);
            },
        ]);

        if (! $loadedVariant->parentProduct || ! $this->productHasActiveState($loadedVariant->parentProduct)) {
            abort(404, 'Товар не найден');
        }

        return $loadedVariant;
    }

    protected function productHasActiveState(Product $product): bool
    {
        $state = $product->state;

        if ($state instanceof \UnitEnum) {
            return $state->value() === Product::ACTIVE;
        }

        return (string) $state === Product::ACTIVE;
    }

    protected function resolveParentProductForLists(Product $product): Product
    {
        if (!$product->isVariant()) {
            return $product;
        }

        if (!$product->relationLoaded('parentProduct')) {
            $product->load([
                'parentProduct' => fn ($query) => $query->active(),
            ]);
        }

        return $product->parentProduct ?? $product;
    }

    /**
     * @return array<string, mixed>
     */
    protected function bundleListEagerLoad(): array
    {
        return [
            'taxons',
            'variants' => fn ($q) => $q->active()->orderBy('id'),
            'media',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function relatedListEagerLoad(): array
    {
        return [
            'taxons',
            'variants' => fn ($q) => $q->active()->orderBy('id'),
            'attributeValues.attribute',
            'manufacturer',
            'media',
        ];
    }

    protected function fetchBundleProducts(Product $parentProduct): \Illuminate\Support\Collection
    {
        $id = (int) $parentProduct->id;
        $hotTtl = (int) config('cache_segments.hot_ttl', 86400);
        $eager = $this->bundleListEagerLoad();

        return Product::cached("bundle_{$id}", function () use ($parentProduct, $eager) {
            return $parentProduct->bundleProducts()
                ->whereNull('parent_product_id')
                ->active()
                ->with($eager)
                ->get();
        }, $hotTtl);
    }

    protected function fetchRelatedProducts(Product $parentProduct): \Illuminate\Support\Collection
    {
        $id = (int) $parentProduct->id;

        if (!$parentProduct->relationLoaded('taxons')) {
            $parentProduct->load('taxons');
        }
        if (!$parentProduct->relationLoaded('relatedProducts')) {
            $parentProduct->load('relatedProducts');
        }

        $eager = $this->relatedListEagerLoad();

        return Product::cached("related_{$id}", function () use ($parentProduct, $eager) {
            $manualRelated = $parentProduct->relatedProducts()
                ->whereNull('parent_product_id')
                ->active()
                ->with($eager)
                ->limit(4)
                ->get();

            if ($manualRelated->isNotEmpty()) {
                return $manualRelated;
            }

            $categoryIds = $parentProduct->taxons->pluck('id');
            if ($categoryIds->isNotEmpty()) {
                $categoryRelated = Product::where('id', '!=', $parentProduct->id)
                    ->whereNull('parent_product_id')
                    ->whereHas('taxons', fn ($query) => $query->whereIn('taxons.id', $categoryIds))
                    ->active()
                    ->with($eager)
                    ->limit(4)
                    ->get();

                if ($categoryRelated->isNotEmpty()) {
                    return $categoryRelated;
                }
            }

            return Product::where('id', '!=', $parentProduct->id)
                ->whereNull('parent_product_id')
                ->active()
                ->with($eager)
                ->limit(4)
                ->get();
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/featured",
     *     operationId="getFeaturedProducts",
     *     summary="Получить популярные товары",
     *     description="Возвращает до 12 товаров, отсортированных по количеству продаж (units_sold).",
     *     tags={"Products"},
     *     @OA\Response(
     *         response=200,
     *         description="Список популярных товаров",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function featured(Request $request): AnonymousResourceCollection|JsonResponse
    {
        // ВАЖНО: Получаем регион из запроса для применения правил корзины
        $location = $this->getRegionFromRequest($request);
        if ($location) {
            $request->merge(['_region_id' => $location->id]);
        }

        $products = $this->resolveHomeBlockProducts('featured', function () {
            return Product::with([
                'taxons',
                'variants' => fn ($q) => $q->active()->orderBy('id'),
                'attributeValues.attribute',
                'manufacturer',
                'media',
            ])
                ->whereNull('parent_product_id')
                ->active()
                ->featured()
                ->limit(12)
                ->get();
        });

        return ProductResource::collection($products);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/new",
     *     operationId="getNewProducts",
     *     summary="Получить новые товары",
     *     description="Возвращает до 12 новых товаров. Если существует активная подборка с slug=new, используются товары из неё, иначе — товары по дате создания.",
     *     tags={"Products"},
     *     @OA\Response(
     *         response=200,
     *         description="Список новых товаров",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function new(Request $request): AnonymousResourceCollection|JsonResponse
    {
        // ВАЖНО: Получаем регион из запроса для применения правил корзины
        $location = $this->getRegionFromRequest($request);
        if ($location) {
            $request->merge(['_region_id' => $location->id]);
        }

        $products = $this->resolveHomeBlockProducts('new', function () {
            return Product::with([
                'taxons',
                'variants' => fn ($q) => $q->active()->orderBy('id'),
                'attributeValues.attribute',
                'manufacturer',
                'media',
            ])
                ->whereNull('parent_product_id')
                ->active()
                ->new()
                ->limit(12)
                ->get();
        });

        return ProductResource::collection($products);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/sale",
     *     operationId="getSaleProducts",
     *     summary="Получить товары со скидками",
     *     description="Возвращает до 12 товаров, у которых цена меньше original_price.
     *         Если существует активная подборка с slug=sale, используются товары из неё.",
     *     tags={"Products"},
     *     @OA\Response(
     *         response=200,
     *         description="Список товаров со скидкой",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function sale(Request $request): AnonymousResourceCollection|JsonResponse
    {
        // ВАЖНО: Получаем регион из запроса для применения правил корзины
        $location = $this->getRegionFromRequest($request);
        if ($location) {
            $request->merge(['_region_id' => $location->id]);
        }

        $products = $this->resolveHomeBlockProducts('sale', function () use ($location) {
            $roots = Product::with([
                'taxons',
                'variants' => fn ($q) => $q->active()->orderBy('id'),
                'attributeValues.attribute',
                'manufacturer',
                'media',
            ])
                ->whereNull('parent_product_id')
                ->active();
            app(\App\Services\Product\CatalogFilterService::class)->forLocation($location)->apply($roots, ['on_sale' => true]);
            return $roots->limit(12)->get();
        });

        return ProductResource::collection($products);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/collections/{slug}",
     *     operationId="getProductCollection",
     *     summary="Получить товары из подборки",
     *     description="Возвращает товары из указанной подборки по её slug.",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Slug подборки товаров",
     *         required=true,
     *         @OA\Schema(type="string", example="new")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Товары в подборке",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Подборка не найдена"
     *     )
     * )
     */
    public function collection(Request $request, string $slug): AnonymousResourceCollection|JsonResponse
    {
        $location = $this->getRegionFromRequest($request);
        if ($location) {
            $request->merge(['_region_id' => $location->id]);
            $this->regionRuleService->preloadRulesForLocation($location);
        }

        $collection = ProductCollection::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$collection) {
            return response()->json([
                'message' => 'Подборка не найдена',
            ], 404);
        }

        $hotTtl = (int) config('cache_segments.hot_ttl', 86400);
        $products = Product::cached('collection_slug_' . $slug, function () use ($collection) {
            return $collection->getProductsForApi();
        }, $hotTtl);

        return ProductResource::collection($products)->additional([
            'collection' => [
                'slug' => $collection->slug,
                'name' => $collection->name,
            ],
        ]);
    }

    /**
     * Блоки главной: активная подборка по slug или fallback-скоуп, с кэшем и инвалидацией из админки.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function resolveHomeBlockProducts(string $slug, callable $scopeFallback): \Illuminate\Support\Collection
    {
        $hotTtl = (int) config('cache_segments.hot_ttl', 86400);
        $collection = ProductCollection::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($collection) {
            return Product::cached('home_collection_' . $slug, function () use ($collection) {
                return $collection->getProductsForApi();
            }, $hotTtl);
        }

        return Product::cached($slug, $scopeFallback, $hotTtl);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/{id}/related",
     *     operationId="getRelatedProducts",
     *     summary="Получить сопутствующие товары",
     *     description="Возвращает до 4 сопутствующих товаров для указанного товара.
     *         Приоритет: 1) явно привязанные сопутствующие товары,
     *         2) товары из тех же категорий, 3) fallback — первые активные товары каталога.",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID товара",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список сопутствующих товаров",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Товар не найден"
     *     )
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/v1/products/{id}/bundle",
     *     operationId="getProductBundle",
     *     summary="Товары набора / комплекта",
     *     description="Возвращает товары, привязанные к карточке как «Входит в набор / комплект» (без лимита, порядок sort_order).",
     *     tags={"Products"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Список товаров набора"),
     *     @OA\Response(response=404, description="Товар не найден")
     * )
     */
    public function bundle(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $location = $this->getRegionFromRequest($request);
        if ($location) {
            $request->merge(['_region_id' => $location->id]);
            $this->regionRuleService->preloadRulesForLocation($location);
        }

        $product = Product::query()
            ->whereNull('parent_product_id')
            ->active()
            ->findOrFail($id);

        $request->merge(['_list_view' => true]);

        $items = $this->fetchBundleProducts($product);

        \Illuminate\Support\Facades\Log::debug('[ProductController.bundle]', [
            'product_id' => $id,
            'count' => $items->count(),
            'region_id' => $request->get('_region_id'),
        ]);

        if ($items->isEmpty()) {
            \Illuminate\Support\Facades\Log::info('[ProductController.bundle] empty bundle', ['product_id' => $id]);
        }

        return ProductResource::collection($items);
    }

    public function related(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $location = $this->getRegionFromRequest($request);
        if ($location) {
            $request->merge(['_region_id' => $location->id]);
            $this->regionRuleService->preloadRulesForLocation($location);
        }

        $product = Product::with(['taxons', 'relatedProducts', 'manufacturer'])->findOrFail($id);

        $request->merge(['_list_view' => true]);

        $related = $this->fetchRelatedProducts($product);

        return ProductResource::collection($related);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/search",
     *     operationId="searchProducts",
     *     summary="Поиск товаров",
     *     description="Полнотекстовый поиск по товарам с пагинацией и сортировкой.",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Поисковый запрос (минимум 2 символа)",
     *         required=true,
     *         @OA\Schema(type="string", example="диван")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Номер страницы",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Количество товаров на странице",
     *         required=false,
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Поле для сортировки (created_at, price, name, units_sold, rating)",
     *         required=false,
     *         @OA\Schema(type="string", example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Направление сортировки (asc, desc)",
     *         required=false,
     *         @OA\Schema(type="string", example="desc")
     *     ),
     *     @OA\Parameter(
     *         name="region_id",
     *         in="query",
     *         description="ID региона доставки для применения региональных правил",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Результаты поиска товаров",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации (например, слишком короткий запрос)"
     *     )
     * )
     */
    public function search(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'sort_by' => 'sometimes|in:created_at,price,name,units_sold,rating',
            'sort_order' => 'sometimes|in:asc,desc',
        ]);

        // Создаем детальный ключ кэша для поиска
        $perPage = min($request->get('per_page', 20), 100);
        $page = max(1, (int) $request->input('page', 1));
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $searchQuery = $request->q;

        // The resolved location and catalog revision are appended below before reading the cache.
        $cacheKey = sprintf(
            'search:q:%s:page:%d:per_page:%d:sort_by:%s:sort_order:%s',
            md5($searchQuery),
            $page,
            $perPage,
            $sortBy ?: 'null',
            $sortOrder
        );

        // Регион из запроса подставляется в ProductResource при отдаче
        $location = $this->getRegionFromRequest($request);
        if ($location) {
            $request->merge(['_region_id' => $location->id, '_region' => $location]);
        }

        $query = Product::query()->active()->with(['taxons', 'variants', 'manufacturer']);
        if ($location) {
            $query = $this->applyRegionVisibilityFilter($query, $location);
        }
        $query->whereNull('parent_product_id');
        $query->search($searchQuery);

        if ($sortBy === 'price') {
            $prices = app(\App\Services\Product\CatalogFilterService::class)->forLocation($location)->skus(clone $query, [])
                ->selectRaw('COALESCE(products.parent_product_id, products.id) AS root_id, MIN(catalog_prices.effective_price) AS effective_price')
                ->groupByRaw('COALESCE(products.parent_product_id, products.id)');
            $query->leftJoinSub($prices, 'search_prices', 'search_prices.root_id', '=', 'products.id')->select('products.*')
                ->orderByRaw('search_prices.effective_price IS NULL')->orderBy('search_prices.effective_price', $sortOrder)->orderBy('products.id');
        } else {
            $this->applyCatalogSort($query, $sortBy, $sortOrder);
        }
        $cacheKey .= ':v3:'.Cache::get('catalog_filters_revision', '0').':location:'.($location?->id ?? 'none').':pricing:'.app(\App\Services\Pricing\PricingEngine::class)->cacheContext($location);

        // Используем кэш для поиска с тегами (без region_id; облегчённый список как в index)
        $products = Product::cachedWithTags(
            ["product_search_cache", "product_index_cache"],
            $cacheKey,
            function () use ($query, $perPage, $request) {
                return $this->fetchProductsForCache($query, $perPage, $request, true);
            },
            36000
        );

        $request->merge(['_list_view' => true]);
        return ProductResource::collection($products);
    }

    /**
     * Применяет server-side фильтрацию по региональной видимости.
     * Исключает товары, которые скрыты правилами текущей локации или её предков.
     */
    private function applyRegionVisibilityFilter(\Illuminate\Database\Eloquent\Builder $query, ShippingLocation $location)
    {
        $locationIds = array_reverse($location->getAncestorsIds());

        $query->whereNotExists(function ($sub) use ($locationIds) {
            $sub->select(DB::raw(1))
                ->from('product_region_rules as prr')
                ->whereIn('prr.shipping_location_id', $locationIds)
                ->where('prr.is_active', true)
                ->where('prr.is_hidden', true)
                ->where(function ($ruleScope) {
                    $ruleScope->where(function ($directProductRule) {
                        $directProductRule->whereColumn('prr.product_id', 'products.id')
                            ->whereNull('prr.variant_id');
                    })->orWhereExists(function ($variantRule) {
                        $variantRule->select(DB::raw(1))
                            ->from('products as product_variants')
                            ->whereColumn('product_variants.parent_product_id', 'products.id')
                            ->whereColumn('prr.variant_id', 'product_variants.id');
                    });
                });
        });

        Log::debug('Applied region visibility filter for products listing', [
            'location_id' => $location->id,
            'location_ids_scope' => $locationIds,
        ]);

        return $query;
    }

    /**
     * Собрать attributes из запроса: поддержка attributes[slug][]=val1&attributes[slug][]=val2 и attr_slug=val1,val2.
     * Пустой результат = фильтр не задан, показываем все товары.
     *
     * @return array<string, array<int, string>>
     */
    private function parseAttributesFromRequest(Request $request): array
    {
        $attributes = $request->input('attributes', []);
        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true) ?: [];
        }
        if (!is_array($attributes)) {
            $attributes = [];
        }

        $allParams = $request->all();
        foreach ($allParams as $key => $value) {
            if (strpos($key, 'attr_') === 0 && strlen($key) > 5) {
                $attrSlug = substr($key, 5);
                $vals = is_array($value) ? $value : explode(',', (string) $value);
                $attributes[$attrSlug] = array_values(array_filter(array_map('trim', $vals)));
            }
            if (strpos($key, 'attributes[') === 0 && preg_match('/^attributes\[([^\]]+)\]/', $key, $matches)) {
                $attrSlug = $matches[1];
                if (!isset($attributes[$attrSlug])) {
                    $attributes[$attrSlug] = [];
                }
                $add = is_array($value) ? $value : [$value];
                foreach ($add as $v) {
                    $v = is_string($v) ? trim($v) : '';
                    if ($v !== '') {
                        $attributes[$attrSlug][] = $v;
                    }
                }
            }
        }

        $out = [];
        foreach ($attributes as $slug => $vals) {
            $vals = is_array($vals) ? $vals : [$vals];
            $vals = array_values(array_filter(array_map('trim', $vals)));
            if (count($vals) > 0) {
                $out[$slug] = $vals;
            }
        }
        return $out;
    }

}
