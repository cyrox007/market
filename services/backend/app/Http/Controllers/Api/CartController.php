<?php

namespace App\Http\Controllers\Api;

use App\Actions\Cart\AddToCartAction;
use App\Actions\Cart\Exceptions\CartItemNotFoundException;
use App\Actions\Cart\Exceptions\CartProductUnavailableException;
use App\Actions\Cart\UpdateCartItemQuantityAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemResource;
use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Services\Product\ProductRegionRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Vanilo\Cart\Facades\Cart;

/**
 * @OA\Tag(
 *     name="Cart",
 *     description="Корзина покупок"
 * )
 */
class CartController extends Controller
{
    public function __construct(
        protected ProductRegionRuleService $regionRuleService,
        protected AddToCartAction $addToCart,
        protected UpdateCartItemQuantityAction $updateCartItemQuantity,
    ) {
    }

    /**
     * Получить локацию доставки из запроса для применения региональных правил
     */
    protected function getRegionFromRequest(Request $request): ?ShippingLocation
    {
        $shippingLocationId = $request->input('shipping_location_id');
        $legacyRegionId = $request->input('region_id');
        $locationId = $shippingLocationId ?? $legacyRegionId;

        if (!$locationId) {
            return null;
        }

        Log::debug('Resolving cart location from request', [
            'shipping_location_id' => $shippingLocationId,
            'region_id' => $legacyRegionId,
            'resolved_location_id' => $locationId,
            'source' => $shippingLocationId ? 'shipping_location_id' : 'region_id',
            'path' => $request->path(),
        ]);

        if ($shippingLocationId === null && $legacyRegionId !== null) {
            Log::info('Using legacy region_id for cart location resolution', [
                'region_id' => $legacyRegionId,
                'path' => $request->path(),
            ]);
        }

        // Ищем локацию доставки (может быть любого типа)
        $location = ShippingLocation::where('id', $locationId)
            ->where('is_active', true)
            ->first();

        if (!$location) {
            Log::warning('Invalid or inactive location provided for cart request', [
                'resolved_location_id' => $locationId,
                'shipping_location_id' => $shippingLocationId,
                'region_id' => $legacyRegionId,
                'path' => $request->path(),
            ]);
            return null;
        }

        return $location;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cart",
     *     operationId="getCart",
     *     summary="Получить содержимое корзины",
     *     description="Возвращает все товары в корзине с ценами и количеством",
     *     tags={"Cart"},
     *     security={{"session": {}}},
     *     @OA\Parameter(
     *         name="region_id",
     *         in="query",
     *         description="ID региона доставки для расчета цен",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Содержимое корзины",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="subtotal", type="number", format="float", example=15000.00),
     *             @OA\Property(property="item_count", type="integer", example=3),
     *             @OA\Property(property="is_empty", type="boolean", example=false)
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $items = Cart::getItems();

        if ($items->isNotEmpty()) {
            // ВАЖНО: Загружаем продукт и его родительский товар (если это вариация)
            // Это необходимо для правильного применения правил корзины в CartItemResource
            $items->load([
                'product' => function ($query) {
                    $query->with('parentProduct');
                }
            ]);
        }

        // ВАЖНО: Сортируем по дате добавления (новые сверху)
        // Vanilo Cart хранит элементы в порядке добавления, но для надежности сортируем по ID (больше ID = новее)
        // Если у элементов есть created_at, используем его, иначе сортируем по ID в обратном порядке
        $sortedItems = $items->sortByDesc(function ($item) {
            if (isset($item->created_at)) {
                return $item->created_at;
            }
            return $item->id ?? 0;
        })->values();

        $region = $this->getRegionFromRequest($request);

        if ($region) {
            $request->merge(['_region_id' => $region->id, '_region' => $region]);
            $this->regionRuleService->preloadRulesForLocation($region);
        }

        $itemsPayload = CartItemResource::collection($sortedItems)->toArray($request);
        $subtotal = collect($itemsPayload)->sum(function ($item) {
            return $item['total'] ?? 0;
        });

        $itemCount = Cart::itemCount();

        return response()->json([
            'items' => $itemsPayload,
            'subtotal' => (float) $subtotal,
            'item_count' => $itemCount,
            'is_empty' => Cart::isEmpty(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cart",
     *     operationId="addToCart",
     *     summary="Добавить товар в корзину",
     *     description="Добавляет товар в корзину. Поддерживает вариации товаров (цвет, размер). Автоматически корректирует количество с учетом остатков.",
     *     tags={"Cart"},
     *     security={{"session": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id"},
     *             @OA\Property(property="product_id", type="integer", example=1, description="ID товара"),
     *             @OA\Property(property="quantity", type="integer", example=1, minimum=1, description="Количество (по умолчанию 1)"),
     *             @OA\Property(property="color_slug", type="string", nullable=true, example="red", description="Slug цвета (для вариативных товаров)"),
     *             @OA\Property(property="size_slug", type="string", nullable=true, example="large", description="Slug размера (для вариативных товаров)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Товар добавлен в корзину",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Товар добавлен в корзину"),
     *             @OA\Property(property="item", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации или недостаточно товара на складе"
     *     )
     * )
     */
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'variation_attributes' => 'sometimes|array',
            'variation_attributes.*.attribute_slug' => 'required_with:variation_attributes|string',
            'variation_attributes.*.value_slug' => 'required_with:variation_attributes|string',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if ($product->isVariant()) {
            // Уже вариация — используем как есть
        } elseif ($product->isVariable()) {
            $variationAttributes = $validated['variation_attributes'] ?? [];
            if (empty($variationAttributes)) {
                return response()->json([
                    'message' => 'Для вариативного товара необходимо указать variation_attributes',
                    'product_name' => $product->name,
                ], 422);
            }
            $attrs = [];
            foreach ($variationAttributes as $va) {
                $attrs[$va['attribute_slug']] = $va['value_slug'];
            }
            $variant = $product->getVariantByVariationAttributes($attrs);
            if (!$variant) {
                return response()->json([
                    'message' => 'Вариация товара не найдена. Проверьте выбранные параметры.',
                    'product_name' => $product->name,
                    'variation_attributes' => $attrs,
                ], 404);
            }
            $product = $variant;
        }

        try {
            $result = $this->addToCart->execute(
                $product,
                $validated['quantity'] ?? 1,
                $this->resolveShippingLocationId($request),
            );
        } catch (CartProductUnavailableException $e) {
            return $this->unavailableResponse($product, $e);
        }

        $message = $result->previous > 0
            ? "Количество обновлено. Добавлено {$result->applied} шт. (всего {$result->item->quantity} шт.)"
            : 'Товар добавлен в корзину';

        return response()->json([
            'item' => new CartItemResource($result->item),
            'message' => $message . $result->reasonMessage(),
            'requested_quantity' => $result->requested,
            'added_quantity' => $result->applied,
            'was_adjusted' => $result->wasAdjusted(),
            'limit_reason' => $result->reason?->value,
        ], 201);
    }

    /**
     * Update cart item quantity and/or variation.
     * Поддерживает изменение вариаций: если товар вариативный, можно передать color и size
     *
     * Примеры работы с Cart::getItems() (это Laravel Collection):
     *
     * 1. ПОИСК ЭЛЕМЕНТА:
     *    $item = Cart::getItems()->first(fn($item) => $item->id === $itemId);
     *    $item = Cart::getItems()->firstWhere('id', $itemId);
     *    $item = Cart::getItems()->find($itemId); // если id это ключ коллекции
     *
     * 2. ФИЛЬТРАЦИЯ:
     *    $expensiveItems = Cart::getItems()->filter(fn($item) => $item->price > 1000);
     *    $highQuantity = Cart::getItems()->where('quantity', '>', 5);
     *
     * 3. ПРОВЕРКА:
     *    $hasProduct = Cart::getItems()->contains(fn($item) => $item->product_id === $productId);
     *    $isEmpty = Cart::getItems()->isEmpty();
     *    $count = Cart::getItems()->count();
     *
     * 4. ПОЛУЧЕНИЕ ДАННЫХ:
     *    $productIds = Cart::getItems()->pluck('product_id');
     *    $totalQuantity = Cart::getItems()->sum('quantity');
     *    $maxPrice = Cart::getItems()->max('price');
     *
     * 5. ИТЕРАЦИЯ:
     *    Cart::getItems()->each(fn($item) => doSomething($item));
     *    Cart::getItems()->map(fn($item) => $item->price * $item->quantity);
     */
    public function update(Request $request, $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'variation_attributes' => 'sometimes|array',
            'variation_attributes.*.attribute_slug' => 'required_with:variation_attributes|string',
            'variation_attributes.*.value_slug' => 'required_with:variation_attributes|string',
        ]);
        $itemId = (int) $itemId;

        $item = Cart::getItems()->first(function ($cartItem) use ($itemId) {
            return (int) $cartItem->id === $itemId;
        });

        if (!$item) {
            return response()->json(['message' => 'Товар не найден в корзине'], 404);
        }

        $productId = $item->product_id ?? $item->buyable->id;
        $product = Product::findOrFail($productId);

        $parentProduct = $product->isVariant() ? $product->parentProduct : $product;
        if ($product->isVariant() && !$parentProduct) {
            return response()->json(['message' => 'Родительский товар не найден'], 404);
        }

        $variationAttributes = $validated['variation_attributes'] ?? [];
        if ($parentProduct->isVariable() && !empty($variationAttributes)) {
            $attrs = [];
            foreach ($variationAttributes as $va) {
                $attrs[$va['attribute_slug']] = $va['value_slug'];
            }
            $variant = $parentProduct->getVariantByVariationAttributes($attrs);
            if (!$variant) {
                return response()->json([
                    'message' => 'Вариация товара не найдена. Проверьте выбранные параметры.',
                    'product_name' => $parentProduct->name,
                    'variation_attributes' => $attrs,
                ], 404);
            }
            $product = $variant;
        }

        try {
            $result = $this->updateCartItemQuantity->execute(
                $item,
                $product,
                $validated['quantity'],
                $this->resolveShippingLocationId($request),
            );
        } catch (CartItemNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (CartProductUnavailableException $e) {
            return $this->unavailableResponse($product, $e);
        }

        return response()->json([
            'item' => new CartItemResource($result->item),
            'message' => 'Товар обновлен' . $result->reasonMessage(),
            'requested_quantity' => $result->requested,
            'updated_quantity' => $result->applied,
            'was_adjusted' => $result->wasAdjusted(),
            'limit_reason' => $result->reason?->value,
        ]);
    }

    protected function unavailableResponse(Product $product, CartProductUnavailableException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'product_name' => $product->name,
            'stock' => $e->stock,
            'backorder' => $product->backorder ?? false,
        ], 422);
    }

    /**
     * Remove item from cart.
     */
    public function remove(Request $request, $itemId): JsonResponse
    {
        // Ищем элемент корзины по ID элемента корзины
        // Преобразуем $itemId в int для сравнения
        $itemId = (int) $itemId;

        $item = Cart::getItems()->first(function ($cartItem) use ($itemId) {
            return (int) $cartItem->id === $itemId;
        });

        if (!$item) {
            return response()->json(['message' => 'Товар не найден в корзине'], 404);
        }

        Cart::removeItem($item);

        return response()->json([
            'message' => 'Товар удален из корзины',
        ]);
    }

    /**
     * Clear cart.
     */
    public function clear(Request $request): JsonResponse
    {
        Cart::clear();

        return response()->json([
            'message' => 'Корзина очищена',
        ]);
    }

    /**
     * Get cart items count.
     */
    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'count' => Cart::itemCount(),
        ]);
    }

    protected function resolveShippingLocationId(Request $request): ?int
    {
        $shippingLocationId = $request->input('shipping_location_id');
        $legacyRegionId = $request->input('region_id');
        $locationId = $shippingLocationId ?? $legacyRegionId;
        if ($locationId === null) {
            return null;
        }

        if ($shippingLocationId === null && $legacyRegionId !== null) {
            Log::info('Using legacy region_id for stock availability resolution in cart', [
                'region_id' => $legacyRegionId,
                'path' => $request->path(),
            ]);
        }

        return (int) $locationId;
    }
}
