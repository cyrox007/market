<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Warehouse;
use App\Models\Order\Order;
use App\Models\Page\Store;
use App\Models\Product\Attribute;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Models\Product\Room;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
                    'update' => $user->can('update products'),
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Product::query()
            ->whereNull('parent_product_id')
            ->with(['taxons'])
            ->withCount('variants')
            ->orderByDesc('updated_at');

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
            'sku' => ['required', 'string', 'max:255'],
            'gtin' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'state' => ['required', Rule::in(['draft', 'active', 'inactive'])],
            'priority' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0', 'gt:price'],
        ]);

        $product->name = $validated['name'];
        $product->sku = $validated['sku'];
        $product->gtin = $validated['gtin'] ?? null;
        $product->description = $validated['description'] ?? null;
        $product->state = $validated['state'];
        $product->priority = $validated['priority'];
        $product->price = $validated['price'];
        $product->original_price = $validated['original_price'] ?? null;
        $product->save();

        $product->refresh()->load([
            'taxons',
            'attributes.values',
            'variants',
            'warehouseStocks.warehouse',
            'variationAttributeSelection',
        ]);

        return response()->json([
            'message' => 'Товар сохранён',
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
        ];
    }

    private function productDetails(Product $product): array
    {
        return array_merge($this->productSummary($product), [
            'description' => $this->nullableScalarString($product->description),
            'priority' => (int) ($product->priority ?? 0),
            'is_variable' => $product->isVariable(),
            'attributes' => $product->attributes
                ->map(function (Attribute $attribute) {
                    $pivot = $attribute->pivot;
                    $value = $attribute->values
                        ->firstWhere('id', $pivot?->attribute_value_id);

                    return [
                        'id' => $attribute->id,
                        'name' => $this->scalarString($attribute->name),
                        'slug' => $this->scalarString($attribute->slug),
                        'value_id' => $value?->id,
                        'value' => $this->nullableScalarString($value?->value),
                        'custom_value' => $this->nullableScalarString($pivot?->custom_value),
                        'is_multiple' => (bool) $attribute->is_multiple,
                        'is_use_in_variations' => (bool) $attribute->is_use_in_variations,
                    ];
                })
                ->values(),
            'variation_attribute_ids' => $product->variationAttributeSelection
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values(),
            'variants' => $product->variants->map(fn (Product $variant) => [
                'id' => $variant->id,
                'name' => $this->scalarString($variant->name),
                'sku' => $this->scalarString($variant->sku),
                'state' => $this->scalarString($variant->getRawOriginal('state') ?? $variant->state),
                'price' => (float) $variant->price,
                'stock' => (int) ($variant->stock ?? 0),
            ])->values(),
            'warehouse_stocks' => $product->warehouseStocks->map(fn ($stock) => [
                'warehouse_id' => $stock->warehouse_id,
                'warehouse_name' => $this->nullableScalarString($stock->warehouse?->name),
                'quantity' => (float) $stock->quantity,
            ])->values(),
        ]);
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
}
