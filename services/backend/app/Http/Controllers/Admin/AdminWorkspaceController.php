<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Product\GetVariationAttributesForProductAction;
use App\Actions\Product\SyncVariantVariationAttributesAction;
use App\Http\Controllers\Controller;
use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Order\Order;
use App\Models\Page\Store;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Manufacturer;
use App\Models\Product\Product;
use App\Models\Product\ProductRegionRule;
use App\Models\Product\Room;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\ShippingLocation;
use App\Services\Catalog\OneCProductSyncService;
use App\Services\Product\ProductAttributeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Vanilo\Shipment\Models\ShippingCategory;
use Vanilo\Taxes\Models\TaxCategory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AdminWorkspaceController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'csrf_token' => csrf_token(),
            'permissions' => [
                'products' => [
                    'view' => $user->can('viewAny products'),
                    'create' => $user->can('create products'),
                    'update' => $user->can('update products'),
                    'delete' => $user->can('delete products'),
                ],
                'attributes' => [
                    'view' => $user->can('viewAny attributes'),
                    'update' => $user->can('update attributes'),
                ],
                'orders' => [
                    'view' => $user->can('viewAny orders'),
                    'update' => $user->can('update orders'),
                ],
                'stores' => [
                    'view' => $user->can('viewAny stores'),
                    'update' => $user->can('update stores'),
                ],
                'shipping_locations' => [
                    'view' => $user->can('viewAny shipping_locations'),
                    'update' => $user->can('update shipping_locations'),
                ],
                'warehouses' => [
                    'view' => $user->can('viewAny warehouses'),
                    'update' => $user->can('update warehouses'),
                ],
            ],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'state' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', Rule::in([
                'updated_desc',
                'updated_asc',
                'name_asc',
                'name_desc',
                'price_asc',
                'price_desc',
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Product::query()
            ->whereNull('parent_product_id')
            ->with(['taxons'])
            ->withCount('variants');

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('gtin', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['category_id'])) {
            $categoryIds = Category::getAllDescendantIdsFor((int) $validated['category_id']);

            $query->whereHas(
                'taxons',
                fn ($builder) => $builder->whereIn('taxons.id', $categoryIds)
            );
        }

        if (! empty($validated['room_id'])) {
            $roomIds = Room::getAllDescendantIdsFor((int) $validated['room_id']);

            $categoryIds = Room::query()
                ->whereIn('id', $roomIds)
                ->with('productCategories:id')
                ->get()
                ->flatMap(fn (Room $room) => $room->productCategories->pluck('id'))
                ->unique()
                ->flatMap(fn ($categoryId) => Category::getAllDescendantIdsFor((int) $categoryId))
                ->unique()
                ->values();

            if ($categoryIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas(
                    'taxons',
                    fn ($builder) => $builder->whereIn('taxons.id', $categoryIds->all())
                );
            }
        }

        if (! empty($validated['state'])) {
            $query->where('state', $validated['state']);
        }

        match ($validated['sort'] ?? 'updated_desc') {
            'updated_asc' => $query->orderBy('updated_at'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default => $query->orderByDesc('updated_at'),
        };

        $products = $query->paginate($validated['per_page'] ?? 30);

        return response()->json([
            'data' => collect($products->items())
                ->map(fn (Product $product) => $this->productSummary($product))
                ->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function product(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        $product->load([
            'taxons',
            'attributes.values',
            'variants',
            'warehouseStocks.warehouse',
            'variationAttributeSelection',
            'media',
            'relatedProducts.media',
            'bundleProducts.media',
            'regionRules.region',
        ]);

        return response()->json([
            'product' => $this->productDetails($product),
        ]);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)],
            'sku' => ['nullable', 'string', 'max:255'],
            'gtin' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'state' => ['required', Rule::in(['draft', 'active', 'inactive'])],
            'priority' => ['required', 'integer'],
            'price' => ['required', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'backorder' => ['required', 'boolean'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'tax_category_id' => ['nullable', 'integer'],
            'shipping_category_id' => ['nullable', 'integer'],
            'manufacturer_id' => ['nullable', 'integer'],
            'warehouse_stocks' => ['array'],
            'warehouse_stocks.*.warehouse_id' => ['required', 'integer'],
            'warehouse_stocks.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $categoryIds = Category::query()
            ->whereIn('id', $validated['category_ids'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($categoryIds) !== count(array_unique($validated['category_ids'] ?? []))) {
            throw ValidationException::withMessages([
                'category_ids' => 'Одна или несколько выбранных категорий не найдены.',
            ]);
        }

        if (! empty($validated['tax_category_id'])
            && ! TaxCategory::query()->whereKey($validated['tax_category_id'])->exists()) {
            throw ValidationException::withMessages([
                'tax_category_id' => 'Категория налога не найдена.',
            ]);
        }

        if (! empty($validated['shipping_category_id'])
            && ! ShippingCategory::query()->whereKey($validated['shipping_category_id'])->exists()) {
            throw ValidationException::withMessages([
                'shipping_category_id' => 'Категория доставки не найдена.',
            ]);
        }

        if (! empty($validated['manufacturer_id'])
            && ! Manufacturer::query()->whereKey($validated['manufacturer_id'])->exists()) {
            throw ValidationException::withMessages([
                'manufacturer_id' => 'Производитель не найден.',
            ]);
        }

        DB::transaction(function () use ($product, $validated, $categoryIds): void {
            $product->name = $validated['name'];
            $product->slug = filled($validated['slug'] ?? null) ? $validated['slug'] : null;
            $product->sku = $validated['sku'] ?? '';
            $product->gtin = $validated['gtin'] ?? null;
            $product->description = $validated['description'] ?? null;
            $product->state = $validated['state'];
            $product->priority = $validated['priority'];
            $product->price = $validated['price'];
            $product->original_price = $validated['original_price'] ?? null;
            $product->backorder = (bool) $validated['backorder'];
            $product->length = $validated['length'] ?? null;
            $product->width = $validated['width'] ?? null;
            $product->height = $validated['height'] ?? null;
            $product->weight = $validated['weight'] ?? null;
            $product->tax_category_id = $validated['tax_category_id'] ?? null;
            $product->shipping_category_id = $validated['shipping_category_id'] ?? null;
            $product->manufacturer_id = $validated['manufacturer_id'] ?? null;

            $stockSettings = ProductStockSettings::getInstance();
            if (! $stockSettings->warehouse_accounting_enabled && ! $product->isVariable()) {
                $product->stock = $validated['stock'] ?? 0;
            }

            $product->save();
            $product->taxons()->sync($categoryIds);

            if ($stockSettings->warehouse_accounting_enabled && ! $product->isVariable()) {
                $rows = collect($validated['warehouse_stocks'] ?? [])
                    ->map(fn (array $row) => [
                        'warehouse_id' => (int) $row['warehouse_id'],
                        'quantity' => (float) $row['quantity'],
                    ])
                    ->unique('warehouse_id')
                    ->values();

                $validWarehouseIds = Warehouse::query()
                    ->whereIn('id', $rows->pluck('warehouse_id'))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);

                if ($validWarehouseIds->count() !== $rows->count()) {
                    throw ValidationException::withMessages([
                        'warehouse_stocks' => 'Один или несколько складов не найдены.',
                    ]);
                }

                $product->warehouseStocks()
                    ->whereNotIn('warehouse_id', $validWarehouseIds->all())
                    ->delete();

                foreach ($rows as $row) {
                    ProductWarehouseStock::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'warehouse_id' => $row['warehouse_id'],
                        ],
                        ['quantity' => $row['quantity']],
                    );
                }
            }
        });

        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Товар сохранён',
            'product' => $this->productDetails($product),
        ]);
    }

    public function productEditorOptions(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        $stockSettings = ProductStockSettings::getInstance();

        $attributes = Attribute::query()
            ->with('orderedValues')
            ->when(
                $product->isVariable(),
                fn ($query) => $query->where('is_use_in_variations', false)
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'attributes' => $attributes->map(fn (Attribute $attribute) => [
                'id' => $attribute->id,
                'name' => $this->scalarString($attribute->name),
                'slug' => $this->scalarString($attribute->slug),
                'type' => $this->scalarString($attribute->type),
                'is_required' => (bool) $attribute->is_required,
                'is_filterable' => (bool) $attribute->is_filterable,
                'is_multiple' => (bool) $attribute->is_multiple,
                'is_use_in_variations' => (bool) $attribute->is_use_in_variations,
                'allow_custom_value' => (bool) $attribute->allow_custom_value,
                'values' => $attribute->orderedValues->map(fn ($value) => [
                    'id' => (int) $value->id,
                    'value' => $this->scalarString($value->value),
                    'slug' => $this->scalarString($value->slug),
                    'color_code' => $this->nullableScalarString($value->color_code),
                ])->values(),
            ])->values(),
            'variation_attributes' => app(GetVariationAttributesForProductAction::class)
                ->execute($product)
                ->map(fn (Attribute $attribute) => [
                    'id' => (int) $attribute->id,
                    'name' => $this->scalarString($attribute->name),
                    'slug' => $this->scalarString($attribute->slug),
                    'type' => $this->scalarString($attribute->type),
                    'is_required' => (bool) $attribute->is_required,
                    'is_filterable' => (bool) $attribute->is_filterable,
                    'is_multiple' => (bool) $attribute->is_multiple,
                    'is_use_in_variations' => (bool) $attribute->is_use_in_variations,
                    'allow_custom_value' => (bool) $attribute->allow_custom_value,
                    'values' => $attribute->orderedValues->map(fn ($value) => [
                        'id' => (int) $value->id,
                        'value' => $this->scalarString($value->value),
                        'slug' => $this->scalarString($value->slug),
                        'color_code' => $this->nullableScalarString($value->color_code),
                    ])->values(),
                ])->values(),
            'shipping_locations' => ShippingLocation::query()
                ->where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(fn (ShippingLocation $location) => [
                    'id' => (int) $location->id,
                    'name' => $this->scalarString($location->name),
                    'path' => $this->scalarString($location->getFullPathAttribute()),
                    'type' => $this->scalarString($location->type),
                ])->values(),
            'manufacturers' => Manufacturer::query()
                ->orderBy('name')
                ->get(['id', 'name', 'external_id'])
                ->map(fn (Manufacturer $manufacturer) => [
                    'id' => (int) $manufacturer->id,
                    'name' => $this->scalarString($manufacturer->name),
                    'external_id' => $this->nullableScalarString($manufacturer->external_id),
                ])->values(),
            'tax_categories' => TaxCategory::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'name' => $this->scalarString($item->name),
                ])->values(),
            'shipping_categories' => ShippingCategory::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'name' => $this->scalarString($item->name),
                ])->values(),
            'warehouses' => Warehouse::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'external_id'])
                ->map(fn (Warehouse $warehouse) => [
                    'id' => (int) $warehouse->id,
                    'name' => $this->scalarString($warehouse->name),
                    'external_id' => $this->nullableScalarString($warehouse->external_id),
                ])->values(),
            'stock_settings' => [
                'warehouse_accounting_enabled' => (bool) $stockSettings->warehouse_accounting_enabled,
                'fallback_to_first_warehouse' => (bool) $stockSettings->fallback_to_first_warehouse,
            ],
        ]);
    }

    public function syncProductFromOneC(
        Product $product,
        OneCProductSyncService $syncService
    ): JsonResponse {
        Gate::authorize('update', $product);

        if (! filled($product->external_id)) {
            throw ValidationException::withMessages([
                'external_id' => 'У товара не указан внешний ID 1С.',
            ]);
        }

        $synced = $syncService->syncProductByExternalId((string) $product->external_id);

        if (! $synced) {
            throw ValidationException::withMessages([
                'external_id' => 'Товар не найден в источнике 1С или синхронизация не вернула данные.',
            ]);
        }

        $this->reloadProductRelations($product->refresh());

        return response()->json([
            'message' => 'Товар синхронизирован с 1С',
            'product' => $this->productDetails($product),
        ]);
    }

    public function updateProductAttributes(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'rows' => ['array'],
            'rows.*.attribute_id' => ['required', 'integer'],
            'rows.*.attribute_value_id' => ['nullable'],
            'rows.*.attribute_value_id.*' => ['integer'],
            'rows.*.custom_value' => ['nullable', 'string', 'max:1000'],
        ]);

        $rows = collect($validated['rows'] ?? [])
            ->map(function (array $row): array {
                $valueIds = $row['attribute_value_id'] ?? [];
                if (! is_array($valueIds)) {
                    $valueIds = $valueIds === null || $valueIds === '' ? [] : [$valueIds];
                }

                return [
                    'attribute_id' => (int) $row['attribute_id'],
                    'attribute_value_id' => array_values(array_map('intval', $valueIds)),
                    'custom_value' => trim((string) ($row['custom_value'] ?? '')),
                ];
            })
            ->values()
            ->all();

        if ($product->isVariable()) {
            $variationAttributeIds = Attribute::query()
                ->whereIn('id', collect($rows)->pluck('attribute_id'))
                ->where('is_use_in_variations', true)
                ->pluck('id');

            if ($variationAttributeIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'rows' => 'Характеристики вариаций изменяются во вкладке «Вариации».',
                ]);
            }
        }

        app(ProductAttributeSyncService::class)->sync($product, $rows);
        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Характеристики сохранены',
            'product' => $this->productDetails($product),
        ]);
    }

    public function attachRelatedProduct(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'related_product_id' => ['required', 'integer'],
        ]);

        $related = Product::query()
            ->whereNull('parent_product_id')
            ->whereKey($validated['related_product_id'])
            ->firstOrFail();

        Gate::authorize('update', $related);

        if ($product->is($related)) {
            throw ValidationException::withMessages([
                'related_product_id' => 'Нельзя добавить товар в сопутствующие к самому себе.',
            ]);
        }

        $product->attachRelatedProduct($related);
        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Сопутствующий товар добавлен',
            'product' => $this->productDetails($product),
        ]);
    }

    public function detachRelatedProduct(Product $product, Product $related): JsonResponse
    {
        Gate::authorize('update', $product);
        Gate::authorize('update', $related);

        $product->detachRelatedProduct($related);
        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Сопутствующий товар удалён',
            'product' => $this->productDetails($product),
        ]);
    }

    public function attachBundleProducts(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
        ]);

        $productIds = Product::query()
            ->whereNull('parent_product_id')
            ->where('id', '!=', $product->id)
            ->whereIn('id', $validated['product_ids'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($productIds) !== count(array_unique($validated['product_ids']))) {
            throw ValidationException::withMessages([
                'product_ids' => 'Один или несколько товаров для комплекта не найдены.',
            ]);
        }

        $product->attachBundleProducts($productIds);
        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Товары добавлены в комплект',
            'product' => $this->productDetails($product),
        ]);
    }

    public function updateBundleProduct(
        Request $request,
        Product $product,
        Product $bundle
    ): JsonResponse {
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'sort_order' => ['required', 'integer'],
        ]);

        $exists = $product->bundleProducts()
            ->where('products.id', $bundle->id)
            ->exists();

        abort_unless($exists, 404);

        $product->bundleProducts()->updateExistingPivot(
            $bundle->id,
            ['sort_order' => (int) $validated['sort_order']]
        );
        $product->flushCache();
        Product::flushAllProductCaches();

        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Порядок товара в комплекте сохранён',
            'product' => $this->productDetails($product),
        ]);
    }

    public function detachBundleProduct(Product $product, Product $bundle): JsonResponse
    {
        Gate::authorize('update', $product);

        $product->detachBundleProduct($bundle);
        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Товар удалён из комплекта',
            'product' => $this->productDetails($product),
        ]);
    }

    public function createProductRegionRule(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $validated = $this->validateRegionRuleRequest($request);
        $variantId = $this->resolveRegionRuleVariantId(
            $product,
            $validated['variant_id'] ?? null
        );

        ProductRegionRule::query()->create(
            $this->regionRulePayload($product, $validated, $variantId)
        );

        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Региональное правило добавлено',
            'product' => $this->productDetails($product),
        ]);
    }

    public function updateProductRegionRule(
        Request $request,
        Product $product,
        ProductRegionRule $rule
    ): JsonResponse {
        Gate::authorize('update', $product);
        $this->ensureRegionRuleBelongsToProduct($product, $rule);

        $validated = $this->validateRegionRuleRequest($request);
        $variantId = $this->resolveRegionRuleVariantId(
            $product,
            $validated['variant_id'] ?? null
        );

        $rule->fill(
            $this->regionRulePayload($product, $validated, $variantId)
        )->save();

        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Региональное правило сохранено',
            'product' => $this->productDetails($product),
        ]);
    }

    public function deleteProductRegionRule(
        Product $product,
        ProductRegionRule $rule
    ): JsonResponse {
        Gate::authorize('update', $product);
        $this->ensureRegionRuleBelongsToProduct($product, $rule);

        $rule->delete();
        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Региональное правило удалено',
            'product' => $this->productDetails($product),
        ]);
    }

    public function createProductVariant(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);
        Gate::authorize('create', Product::class);

        if ($product->isVariant()) {
            throw ValidationException::withMessages([
                'product' => 'Нельзя создавать торговые предложения у торгового предложения.',
            ]);
        }

        $validated = $this->validateVariantRequest($request);
        $variationData = $this->normalizeVariantAttributeData(
            $product,
            $validated['attributes'] ?? []
        );

        DB::transaction(function () use ($product, $validated, $variationData): void {
            $variant = Product::query()->create([
                'parent_product_id' => $product->id,
                'is_variable' => false,
                'name' => $validated['name'],
                'sku' => $validated['sku'],
                'price' => $validated['price'],
                'original_price' => $validated['original_price'] ?? null,
                'stock' => ProductStockSettings::getInstance()->warehouse_accounting_enabled
                    ? 0
                    : ($validated['stock'] ?? 0),
                'backorder' => (bool) $validated['backorder'],
                'state' => $validated['state'],
                'external_id' => $validated['external_id'] ?? null,
                'description' => $product->description,
                'excerpt' => $product->excerpt,
                'length' => $product->length,
                'width' => $product->width,
                'height' => $product->height,
                'weight' => $product->weight,
            ]);

            app(SyncVariantVariationAttributesAction::class)
                ->execute($variant, $variationData, $product);

            $this->syncWarehouseStocks(
                $variant,
                $validated['warehouse_stocks'] ?? []
            );

            if (! $product->isVariable()) {
                $product->forceFill(['is_variable' => true])->save();
            }
        });

        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Торговое предложение создано',
            'product' => $this->productDetails($product),
        ]);
    }

    public function updateProductVariant(
        Request $request,
        Product $product,
        Product $variant
    ): JsonResponse {
        Gate::authorize('update', $product);
        Gate::authorize('update', $variant);
        $this->ensureVariantBelongsToProduct($product, $variant);

        $validated = $this->validateVariantRequest($request, $variant);
        $variationData = $this->normalizeVariantAttributeData(
            $product,
            $validated['attributes'] ?? []
        );

        DB::transaction(function () use ($product, $variant, $validated, $variationData): void {
            $variant->name = $validated['name'];
            $variant->sku = $validated['sku'];
            $variant->price = $validated['price'];
            $variant->original_price = $validated['original_price'] ?? null;
            $variant->backorder = (bool) $validated['backorder'];
            $variant->state = $validated['state'];
            $variant->external_id = $validated['external_id'] ?? null;

            if (! ProductStockSettings::getInstance()->warehouse_accounting_enabled) {
                $variant->stock = $validated['stock'] ?? 0;
            }

            $variant->save();

            app(SyncVariantVariationAttributesAction::class)
                ->execute($variant, $variationData, $product);

            $this->syncWarehouseStocks(
                $variant,
                $validated['warehouse_stocks'] ?? []
            );
        });

        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Торговое предложение сохранено',
            'product' => $this->productDetails($product),
        ]);
    }

    public function deleteProductVariant(Product $product, Product $variant): JsonResponse
    {
        Gate::authorize('update', $product);
        Gate::authorize('delete', $variant);
        $this->ensureVariantBelongsToProduct($product, $variant);

        DB::transaction(function () use ($product, $variant): void {
            $variant->delete();

            if (! $product->variants()->exists()) {
                $product->forceFill(['is_variable' => false])->save();
            }
        });

        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Торговое предложение удалено',
            'product' => $this->productDetails($product),
        ]);
    }

    public function uploadProductVariantMedia(
        Request $request,
        Product $product,
        Product $variant
    ): JsonResponse {
        Gate::authorize('update', $product);
        Gate::authorize('update', $variant);
        $this->ensureVariantBelongsToProduct($product, $variant);

        $validated = $request->validate([
            'collection' => ['required', Rule::in(['images', 'gallery'])],
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $collection = $validated['collection'];

        if ($collection === 'gallery' && $variant->getMedia('gallery')->count() >= 10) {
            throw ValidationException::withMessages([
                'file' => 'В галерее уже 10 изображений. Удалите одно из них перед загрузкой нового.',
            ]);
        }

        if ($collection === 'images') {
            $variant->clearMediaCollection('images');
        }

        $variant
            ->addMediaFromRequest('file')
            ->toMediaCollection($collection);

        $this->reloadProductRelations($product);

        return response()->json([
            'message' => $collection === 'images'
                ? 'Главное изображение вариации обновлено'
                : 'Изображение добавлено в галерею вариации',
            'product' => $this->productDetails($product),
        ]);
    }

    public function deleteProductVariantMedia(
        Product $product,
        Product $variant,
        int $media
    ): JsonResponse {
        Gate::authorize('update', $product);
        Gate::authorize('update', $variant);
        $this->ensureVariantBelongsToProduct($product, $variant);

        $item = Media::query()
            ->whereKey($media)
            ->where('model_type', $variant->getMorphClass())
            ->where('model_id', $variant->id)
            ->whereIn('collection_name', ['images', 'gallery'])
            ->firstOrFail();

        $item->delete();
        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Изображение вариации удалено',
            'product' => $this->productDetails($product),
        ]);
    }

    public function uploadProductMedia(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'collection' => ['required', Rule::in(['images', 'gallery'])],
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $collection = $validated['collection'];

        if ($collection === 'gallery' && $product->getMedia('gallery')->count() >= 10) {
            throw ValidationException::withMessages([
                'file' => 'В галерее уже 10 изображений. Удалите одно из них перед загрузкой нового.',
            ]);
        }

        if ($collection === 'images') {
            $product->clearMediaCollection('images');
        }

        $product
            ->addMediaFromRequest('file')
            ->toMediaCollection($collection);

        $this->reloadProductRelations($product);

        return response()->json([
            'message' => $collection === 'images'
                ? 'Главное изображение обновлено'
                : 'Изображение добавлено в галерею',
            'product' => $this->productDetails($product),
        ]);
    }

    public function deleteProductMedia(Product $product, int $media): JsonResponse
    {
        Gate::authorize('update', $product);

        $item = Media::query()
            ->whereKey($media)
            ->where('model_type', $product->getMorphClass())
            ->where('model_id', $product->id)
            ->whereIn('collection_name', ['images', 'gallery'])
            ->firstOrFail();

        $item->delete();
        $this->reloadProductRelations($product);

        return response()->json([
            'message' => 'Изображение удалено',
            'product' => $this->productDetails($product),
        ]);
    }

    public function attributes(): JsonResponse
    {
        Gate::authorize('viewAny', Attribute::class);

        $attributes = Attribute::query()
            ->with(['orderedValues'])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $attributes->map(fn (Attribute $attribute) => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'slug' => $attribute->slug,
                'type' => $attribute->type,
                'is_filterable' => (bool) $attribute->is_filterable,
                'is_required' => (bool) $attribute->is_required,
                'is_use_in_variations' => (bool) $attribute->is_use_in_variations,
                'allow_custom_value' => (bool) $attribute->allow_custom_value,
                'is_multiple' => (bool) $attribute->is_multiple,
                'sort_order' => (int) $attribute->sort_order,
                'products_count' => (int) $attribute->products_count,
                'values' => $attribute->orderedValues->map(fn ($value) => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'slug' => $value->slug,
                    'sort_order' => (int) $value->sort_order,
                ])->values(),
            ])->values(),
        ]);
    }

    public function updateAttribute(Request $request, Attribute $attribute): JsonResponse
    {
        Gate::authorize('update', $attribute);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('product_attributes', 'slug')->ignore($attribute->id)],
            'type' => ['required', Rule::in(['select', 'color', 'string', 'text', 'number', 'number_input'])],
            'is_filterable' => ['required', 'boolean'],
            'is_required' => ['required', 'boolean'],
            'is_use_in_variations' => ['required', 'boolean'],
            'allow_custom_value' => ['required', 'boolean'],
            'is_multiple' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $attribute->fill($validated);
        $attribute->save();
        $attribute->load('orderedValues')->loadCount('products');

        return response()->json([
            'message' => 'Характеристика сохранена',
            'attribute' => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'slug' => $attribute->slug,
                'type' => $attribute->type,
                'is_filterable' => (bool) $attribute->is_filterable,
                'is_required' => (bool) $attribute->is_required,
                'is_use_in_variations' => (bool) $attribute->is_use_in_variations,
                'allow_custom_value' => (bool) $attribute->allow_custom_value,
                'is_multiple' => (bool) $attribute->is_multiple,
                'sort_order' => (int) $attribute->sort_order,
                'products_count' => (int) $attribute->products_count,
                'values' => $attribute->orderedValues->map(fn ($value) => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'slug' => $value->slug,
                    'sort_order' => (int) $value->sort_order,
                ])->values(),
            ],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Order::query()
            ->with(['items.product', 'shippingLocation', 'shippingMethod'])
            ->orderByDesc('created_at');

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(30);

        return response()->json([
            'data' => collect($orders->items())->map(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'status_label' => $order->getStatusLabel(),
                'total' => (float) $order->total,
                'contact_name' => $order->contact_name,
                'contact_phone' => $order->contact_phone,
                'contact_email' => $order->contact_email,
                'created_at' => $order->created_at?->toIso8601String(),
                'shipping_location' => $order->shippingLocation ? [
                    'id' => $order->shippingLocation->id,
                    'name' => $order->shippingLocation->name,
                ] : null,
                'shipping_method' => $order->shippingMethod ? [
                    'id' => $order->shippingMethod->id,
                    'name' => $order->shippingMethod->name,
                ] : null,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name ?? $item->name ?? 'Товар',
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                ])->values(),
            ])->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function stores(): JsonResponse
    {
        Gate::authorize('viewAny', Store::class);

        return response()->json([
            'data' => Store::query()
                ->with('region')
                ->orderBy('priority')
                ->orderBy('name')
                ->get()
                ->map(fn (Store $store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'address' => $store->address,
                    'city' => $store->city,
                    'phone' => $store->phone,
                    'hours' => $store->hours,
                    'coordinates' => $store->coordinates,
                    'is_active' => (bool) $store->is_active,
                    'priority' => (int) $store->priority,
                    'region' => $store->region ? [
                        'id' => $store->region->id,
                        'name' => $store->region->name,
                    ] : null,
                ])
                ->values(),
        ]);
    }

    public function warehouses(): JsonResponse
    {
        Gate::authorize('viewAny', Warehouse::class);

        return response()->json([
            'data' => Warehouse::query()
                ->with(['shippingLocations:id,name'])
                ->withCount(['productStocks', 'shippingLocations'])
                ->orderBy('name')
                ->get()
                ->map(fn (Warehouse $warehouse) => [
                    'id' => $warehouse->id,
                    'external_id' => $warehouse->external_id,
                    'name' => $warehouse->name,
                    'is_active' => (bool) $warehouse->is_active,
                    'product_stocks_count' => (int) $warehouse->product_stocks_count,
                    'shipping_locations_count' => (int) $warehouse->shipping_locations_count,
                    'shipping_locations' => $warehouse->shippingLocations
                        ->map(fn ($location) => [
                            'id' => $location->id,
                            'name' => $location->name,
                        ])
                        ->values(),
                ])
                ->values(),
        ]);
    }

    public function locations(): JsonResponse
    {
        Gate::authorize('viewAny', ShippingLocation::class);

        return response()->json([
            'data' => ShippingLocation::query()
                ->with('parent')
                ->orderBy('type')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (ShippingLocation $location) => [
                    'id' => $location->id,
                    'parent_id' => $location->parent_id,
                    'name' => $location->name,
                    'slug' => $location->slug,
                    'code' => $location->code,
                    'type' => $location->type,
                    'location_type' => $location->location_type,
                    'is_active' => (bool) $location->is_active,
                    'delivery_price' => $location->delivery_price !== null
                        ? (float) $location->delivery_price
                        : null,
                    'free_delivery_threshold' => $location->free_delivery_threshold !== null
                        ? (float) $location->free_delivery_threshold
                        : null,
                    'delivery_days_min' => $location->delivery_days_min,
                    'delivery_days_max' => $location->delivery_days_max,
                    'parent' => $location->parent ? [
                        'id' => $location->parent->id,
                        'name' => $location->parent->name,
                    ] : null,
                ])
                ->values(),
        ]);
    }

    private function productSummary(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $this->scalarString($product->name),
            'slug' => $this->scalarString($product->slug),
            'sku' => $this->scalarString($product->sku),
            'gtin' => $this->nullableScalarString($product->gtin),
            'state' => $this->scalarString($product->getRawOriginal('state') ?? $product->state),
            'price' => (float) $product->price,
            'original_price' => $product->original_price !== null
                ? (float) $product->original_price
                : null,
            'stock' => (int) ($product->stock ?? 0),
            'variants_count' => $product->relationLoaded('variants')
                ? $product->variants->count()
                : (int) ($product->variants_count ?? $product->variants()->count()),
            'categories' => $product->taxons->map(fn ($category) => [
                'id' => $category->id,
                'name' => $this->scalarString($category->name),
                'slug' => $this->scalarString($category->slug),
            ])->values(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    private function productDetails(Product $product): array
    {
        $stockSettings = ProductStockSettings::getInstance();

        return array_merge($this->productSummary($product), [
            'slug' => $this->scalarString($product->slug),
            'description' => $this->nullableScalarString($product->description),
            'priority' => (int) ($product->priority ?? 0),
            'is_variable' => $product->isVariable(),
            'category_ids' => $product->taxons->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'stock' => (float) ($product->getRawOriginal('stock') ?? $product->stock ?? 0),
            'backorder' => (bool) ($product->getRawOriginal('backorder') ?? $product->backorder ?? false),
            'units_sold' => (int) ($product->units_sold ?? 0),
            'length' => $product->length !== null ? (float) $product->length : null,
            'width' => $product->width !== null ? (float) $product->width : null,
            'height' => $product->height !== null ? (float) $product->height : null,
            'weight' => $product->weight !== null ? (float) $product->weight : null,
            'tax_category_id' => $product->tax_category_id !== null ? (int) $product->tax_category_id : null,
            'shipping_category_id' => $product->shipping_category_id !== null ? (int) $product->shipping_category_id : null,
            'external_id' => $this->nullableScalarString($product->external_id),
            'warehouse_accounting_enabled' => (bool) $stockSettings->warehouse_accounting_enabled,
            'media' => $product->media
                ->whereIn('collection_name', ['images', 'gallery'])
                ->sortBy(fn (Media $media) => [
                    $media->collection_name === 'images' ? 0 : 1,
                    $media->order_column ?? PHP_INT_MAX,
                    $media->id,
                ])
                ->map(fn (Media $media) => [
                    'id' => (int) $media->id,
                    'collection' => $this->scalarString($media->collection_name),
                    'name' => $this->scalarString($media->name),
                    'file_name' => $this->scalarString($media->file_name),
                    'url' => $media->getUrl(),
                    'thumb_url' => $media->hasGeneratedConversion('thumb')
                        ? $media->getUrl('thumb')
                        : $media->getUrl(),
                    'order' => (int) ($media->order_column ?? 0),
                ])
                ->values(),
            'related_products' => $product->relatedProducts
                ->map(fn (Product $related) => $this->linkedProductPayload($related))
                ->values(),
            'bundle_products' => $product->bundleProducts
                ->map(function (Product $bundle): array {
                    $payload = $this->linkedProductPayload($bundle);
                    $payload['sort_order'] = (int) ($bundle->pivot?->sort_order ?? 0);

                    return $payload;
                })
                ->values(),
            'region_rules' => ProductRegionRule::query()
                ->with(['region', 'variant'])
                ->where('product_id', $product->id)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get()
                ->map(fn (ProductRegionRule $rule) => [
                    'id' => (int) $rule->id,
                    'variant_id' => $rule->variant_id !== null ? (int) $rule->variant_id : null,
                    'variant_name' => $this->nullableScalarString($rule->variant?->name),
                    'shipping_location_id' => (int) $rule->shipping_location_id,
                    'location_name' => $this->nullableScalarString($rule->region?->name),
                    'location_path' => $rule->region
                        ? $this->scalarString($rule->region->getFullPathAttribute())
                        : null,
                    'price_override' => $rule->price_override !== null
                        ? (float) $rule->price_override
                        : null,
                    'price_modifier_type' => $this->nullableScalarString($rule->price_modifier_type),
                    'price_modifier_value' => $rule->price_modifier_value !== null
                        ? (float) $rule->price_modifier_value
                        : null,
                    'is_hidden' => (bool) $rule->is_hidden,
                    'delivery_days_override' => $rule->delivery_days_override !== null
                        ? (int) $rule->delivery_days_override
                        : null,
                    'priority' => (int) $rule->priority,
                    'is_active' => (bool) $rule->is_active,
                ])
                ->values(),
            'attribute_rows' => $this->regularProductAttributeRows($product),
            'attributes' => $this->productAttributeGroups($product),
            'variation_attribute_ids' => $product->variationAttributeSelection
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values(),
            'variants' => $product->variants
                ->map(fn (Product $variant) => $this->variantPayload($variant))
                ->values(),
            'warehouse_stocks' => $product->warehouseStocks->map(fn ($stock) => [
                'warehouse_id' => (int) $stock->warehouse_id,
                'warehouse_name' => $this->nullableScalarString($stock->warehouse?->name),
                'quantity' => (float) $stock->quantity,
            ])->values(),
        ]);
    }

    private function reloadProductRelations(Product $product): void
    {
        $product->refresh()->load([
            'taxons',
            'attributes.values',
            'variants',
            'warehouseStocks.warehouse',
            'variationAttributeSelection',
            'media',
            'relatedProducts.media',
            'bundleProducts.media',
            'regionRules.region',
        ]);
    }

    private function regularProductAttributeRows(Product $product): array
    {
        return DB::table('product_product_attributes')
            ->where('product_id', $product->id)
            ->orderBy('attribute_id')
            ->get()
            ->groupBy('attribute_id')
            ->map(function ($rows, $attributeId): array {
                $valueIds = $rows
                    ->pluck('attribute_value_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $customValue = $rows
                    ->pluck('custom_value')
                    ->first(fn ($value) => $value !== null && trim((string) $value) !== '');

                return [
                    'attribute_id' => (int) $attributeId,
                    'attribute_value_id' => $valueIds,
                    'custom_value' => $customValue !== null ? (string) $customValue : '',
                ];
            })
            ->values()
            ->all();
    }

    private function linkedProductPayload(Product $product): array
    {
        $product->loadMissing('media');

        $mainImage = $product->getFirstMediaUrl('images', 'thumb')
            ?: $product->getFirstMediaUrl('images')
            ?: null;

        return [
            'id' => (int) $product->id,
            'name' => $this->scalarString($product->name),
            'sku' => $this->scalarString($product->sku),
            'price' => (float) $product->price,
            'state' => $this->scalarString(
                $product->getRawOriginal('state') ?? $product->state
            ),
            'image_url' => $mainImage,
        ];
    }

    private function validateRegionRuleRequest(Request $request): array
    {
        return $request->validate([
            'variant_id' => ['nullable', 'integer'],
            'shipping_location_id' => ['required', 'integer'],
            'price_override' => ['nullable', 'numeric'],
            'price_modifier_type' => ['nullable', Rule::in(['fixed', 'percent', 'multiply'])],
            'price_modifier_value' => ['nullable', 'numeric'],
            'is_hidden' => ['required', 'boolean'],
            'delivery_days_override' => ['nullable', 'integer', 'min:1'],
            'priority' => ['required', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function resolveRegionRuleVariantId(
        Product $product,
        mixed $variantId
    ): ?int {
        if ($variantId === null || $variantId === '') {
            return null;
        }

        $variant = $product->variants()
            ->whereKey((int) $variantId)
            ->first();

        if (! $variant) {
            throw ValidationException::withMessages([
                'variant_id' => 'Выбранная вариация не принадлежит этому товару.',
            ]);
        }

        return (int) $variant->id;
    }

    private function regionRulePayload(
        Product $product,
        array $validated,
        ?int $variantId
    ): array {
        $locationExists = ShippingLocation::query()
            ->where('is_active', true)
            ->whereKey($validated['shipping_location_id'])
            ->exists();

        if (! $locationExists) {
            throw ValidationException::withMessages([
                'shipping_location_id' => 'Локация доставки не найдена или отключена.',
            ]);
        }

        $modifierType = $validated['price_modifier_type'] ?? null;

        return [
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'shipping_location_id' => (int) $validated['shipping_location_id'],
            'price_override' => $validated['price_override'] ?? null,
            'price_modifier_type' => $modifierType,
            'price_modifier_value' => $modifierType
                ? ($validated['price_modifier_value'] ?? null)
                : null,
            'is_hidden' => (bool) $validated['is_hidden'],
            'delivery_days_override' => $validated['delivery_days_override'] ?? null,
            'priority' => (int) $validated['priority'],
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    private function ensureRegionRuleBelongsToProduct(
        Product $product,
        ProductRegionRule $rule
    ): void {
        abort_unless(
            (int) $rule->product_id === (int) $product->id,
            404
        );
    }

    private function validateVariantRequest(
        Request $request,
        ?Product $variant = null
    ): array {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($variant?->id),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'backorder' => ['required', 'boolean'],
            'state' => ['required', Rule::in(['active', 'draft', 'inactive'])],
            'external_id' => ['nullable', 'string', 'max:255'],
            'warehouse_stocks' => ['array'],
            'warehouse_stocks.*.warehouse_id' => ['required', 'integer'],
            'warehouse_stocks.*.quantity' => ['required', 'numeric', 'min:0'],
            'attributes' => ['array'],
            'attributes.*.attribute_id' => ['required', 'integer'],
            'attributes.*.attribute_value_id' => ['array'],
            'attributes.*.attribute_value_id.*' => ['integer'],
            'attributes.*.custom_value' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function normalizeVariantAttributeData(
        Product $parent,
        array $rows
    ): array {
        $attributes = app(GetVariationAttributesForProductAction::class)
            ->execute($parent)
            ->keyBy('id');

        $rowsByAttribute = collect($rows)->keyBy(
            fn (array $row) => (int) ($row['attribute_id'] ?? 0)
        );

        $data = [];

        foreach ($attributes as $attributeId => $attribute) {
            $row = $rowsByAttribute->get((int) $attributeId, []);
            $valueIds = collect($row['attribute_value_id'] ?? [])
                ->filter(fn ($value) => is_numeric($value))
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values();

            $validValueIds = AttributeValue::query()
                ->where('attribute_id', $attribute->id)
                ->whereIn('id', $valueIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($validValueIds->count() !== $valueIds->count()) {
                throw ValidationException::withMessages([
                    'attributes' => "Для характеристики «{$attribute->name}» выбрано недопустимое значение.",
                ]);
            }

            if (! $attribute->is_multiple && $validValueIds->count() > 1) {
                throw ValidationException::withMessages([
                    'attributes' => "Характеристика «{$attribute->name}» допускает только одно значение.",
                ]);
            }

            $customValue = trim((string) ($row['custom_value'] ?? ''));
            if ($customValue !== '' && ! $attribute->allow_custom_value) {
                throw ValidationException::withMessages([
                    'attributes' => "Характеристика «{$attribute->name}» не допускает ручное значение.",
                ]);
            }

            $required = $attribute->is_required
                || $attribute->slug === Attribute::SLUG_VARIANT;

            if ($required && $validValueIds->isEmpty() && $customValue === '') {
                throw ValidationException::withMessages([
                    'attributes' => "Заполните характеристику «{$attribute->name}».",
                ]);
            }

            if ($validValueIds->isNotEmpty()) {
                $data['variation_attr_' . $attribute->id] = $attribute->is_multiple
                    ? $validValueIds->all()
                    : $validValueIds->first();
            }

            if ($customValue !== '') {
                $data['variation_custom_' . $attribute->id] = $customValue;
            }
        }

        return $data;
    }

    private function syncWarehouseStocks(Product $product, array $rows): void
    {
        if (! ProductStockSettings::getInstance()->warehouse_accounting_enabled) {
            return;
        }

        $normalized = collect($rows)
            ->map(fn (array $row) => [
                'warehouse_id' => (int) ($row['warehouse_id'] ?? 0),
                'quantity' => (float) ($row['quantity'] ?? 0),
            ])
            ->filter(fn (array $row) => $row['warehouse_id'] > 0)
            ->unique('warehouse_id')
            ->values();

        $warehouseIds = Warehouse::query()
            ->whereIn('id', $normalized->pluck('warehouse_id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($warehouseIds->count() !== $normalized->count()) {
            throw ValidationException::withMessages([
                'warehouse_stocks' => 'Один или несколько складов не найдены.',
            ]);
        }

        $product->warehouseStocks()
            ->whereNotIn('warehouse_id', $warehouseIds->all())
            ->delete();

        foreach ($normalized as $row) {
            ProductWarehouseStock::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'warehouse_id' => $row['warehouse_id'],
                ],
                ['quantity' => $row['quantity']],
            );
        }
    }

    private function ensureVariantBelongsToProduct(
        Product $product,
        Product $variant
    ): void {
        abort_unless(
            (int) $variant->parent_product_id === (int) $product->id,
            404
        );
    }

    private function variantPayload(Product $variant): array
    {
        $variant->loadMissing(['warehouseStocks.warehouse', 'media']);

        $attributeRows = DB::table('product_variant_attributes')
            ->where('product_id', $variant->id)
            ->get()
            ->groupBy('attribute_id')
            ->map(function ($rows, $attributeId): array {
                return [
                    'attribute_id' => (int) $attributeId,
                    'attribute_value_id' => $rows
                        ->pluck('attribute_value_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all(),
                    'custom_value' => (string) (
                        $rows
                            ->pluck('custom_value')
                            ->first(fn ($value) => filled($value))
                        ?? ''
                    ),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => (int) $variant->id,
            'name' => $this->scalarString($variant->name),
            'sku' => $this->scalarString($variant->sku),
            'state' => $this->scalarString(
                $variant->getRawOriginal('state') ?? $variant->state
            ),
            'price' => (float) $variant->price,
            'original_price' => $variant->original_price !== null
                ? (float) $variant->original_price
                : null,
            'stock' => (float) ($variant->getRawOriginal('stock') ?? $variant->stock ?? 0),
            'backorder' => (bool) ($variant->getRawOriginal('backorder') ?? $variant->backorder ?? false),
            'external_id' => $this->nullableScalarString($variant->external_id),
            'media' => $variant->media
                ->whereIn('collection_name', ['images', 'gallery'])
                ->sortBy(fn (Media $media) => [
                    $media->collection_name === 'images' ? 0 : 1,
                    $media->order_column ?? PHP_INT_MAX,
                    $media->id,
                ])
                ->map(fn (Media $media) => [
                    'id' => (int) $media->id,
                    'collection' => $this->scalarString($media->collection_name),
                    'name' => $this->scalarString($media->name),
                    'file_name' => $this->scalarString($media->file_name),
                    'url' => $media->getUrl(),
                    'thumb_url' => $media->hasGeneratedConversion('thumb')
                        ? $media->getUrl('thumb')
                        : $media->getUrl(),
                    'order' => (int) ($media->order_column ?? 0),
                ])
                ->values(),
            'attributes' => $attributeRows,
            'warehouse_stocks' => $variant->warehouseStocks->map(fn ($stock) => [
                'warehouse_id' => (int) $stock->warehouse_id,
                'warehouse_name' => $this->nullableScalarString($stock->warehouse?->name),
                'quantity' => (float) $stock->quantity,
            ])->values(),
        ];
    }

    private function scalarString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return '';
    }

    private function nullableScalarString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->scalarString($value);
    }

    private function productAttributeGroups(Product $product): array
    {
        $regularRows = DB::table('product_product_attributes as ppa')
            ->join('product_attributes as pa', 'pa.id', '=', 'ppa.attribute_id')
            ->leftJoin('product_attribute_values as pav', 'pav.id', '=', 'ppa.attribute_value_id')
            ->where('ppa.product_id', $product->id)
            ->orderBy('pa.sort_order')
            ->orderBy('pa.name')
            ->select([
                'pa.id',
                'pa.name',
                'pa.slug',
                'pa.type',
                'pa.is_multiple',
                'pa.is_use_in_variations',
                'ppa.attribute_value_id',
                'ppa.custom_value',
                'pav.value',
                'pav.color_code',
            ])
            ->get()
            ->groupBy('id')
            ->map(fn ($rows) => $this->attributeGroupPayload($rows, 'product'))
            ->values();

        if (! $product->isVariable()) {
            return $regularRows->all();
        }

        $variantRows = DB::table('product_variant_attributes as pva')
            ->join('products as variants', 'variants.id', '=', 'pva.product_id')
            ->join('product_attributes as pa', 'pa.id', '=', 'pva.attribute_id')
            ->leftJoin('product_attribute_values as pav', 'pav.id', '=', 'pva.attribute_value_id')
            ->where('variants.parent_product_id', $product->id)
            ->orderBy('pa.sort_order')
            ->orderBy('pa.name')
            ->select([
                'pa.id',
                'pa.name',
                'pa.slug',
                'pa.type',
                'pa.is_multiple',
                'pa.is_use_in_variations',
                'pva.attribute_value_id',
                'pva.custom_value',
                'pav.value',
                'pav.color_code',
                'variants.id as variant_id',
                'variants.sku as variant_sku',
            ])
            ->get()
            ->groupBy('id')
            ->map(fn ($rows) => $this->attributeGroupPayload($rows, 'variants'))
            ->values();

        return $regularRows
            ->concat($variantRows)
            ->values()
            ->all();
    }

    private function attributeGroupPayload($rows, string $source): array
    {
        $first = $rows->first();

        $values = $rows
            ->map(function ($row) {
                $label = $row->custom_value !== null && $row->custom_value !== ''
                    ? (string) $row->custom_value
                    : (string) ($row->value ?? '');

                if ($label === '') {
                    return null;
                }

                return [
                    'value_id' => $row->attribute_value_id !== null
                        ? (int) $row->attribute_value_id
                        : null,
                    'value' => $label,
                    'color_code' => $row->color_code ?? null,
                ];
            })
            ->filter()
            ->unique(fn (array $value) => ($value['value_id'] ?? 'custom') . '|' . $value['value'])
            ->values()
            ->all();

        return [
            'id' => (int) $first->id,
            'name' => $this->scalarString($first->name),
            'slug' => $this->scalarString($first->slug),
            'type' => $this->scalarString($first->type),
            'source' => $source,
            'is_multiple' => (bool) $first->is_multiple,
            'is_use_in_variations' => (bool) $first->is_use_in_variations,
            'values' => $values,
        ];
    }

}
