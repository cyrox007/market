<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WishlistItemResource;
use App\Models\Product\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WishlistController extends Controller
{
    /**
     * Get wishlist contents.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $wishlist = $request->session()->get('wishlist', []);
        $productIds = array_keys($wishlist);

        if (empty($productIds)) {
            return WishlistItemResource::collection(collect());
        }

        $products = Product::whereIn('id', $productIds)
            ->active()
            ->get()
            ->keyBy('id');

        // Создаем коллекцию элементов wishlist с временем добавления
        $items = collect($wishlist)->map(function ($addedAt, $productId) use ($products) {
            $product = $products->get($productId);
            if (!$product) {
                return null;
            }

            return (object) [
                'id' => $productId,
                'product_id' => $productId,
                'product' => $product,
                'created_at' => $addedAt ? \Carbon\Carbon::parse($addedAt) : now(),
            ];
        })->filter();

        return WishlistItemResource::collection($items);
    }

    /**
     * Add product to wishlist.
     */
    public function add(Request $request, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $wishlist = $request->session()->get('wishlist', []);

        if (isset($wishlist[$productId])) {
            return response()->json([
                'message' => 'Товар уже в избранном',
            ], 422);
        }

        $wishlist[$productId] = now()->toISOString();
        $request->session()->put('wishlist', $wishlist);

        return response()->json([
            'message' => 'Товар добавлен в избранное',
        ], 201);
    }

    /**
     * Remove product from wishlist.
     */
    public function remove(Request $request, int $productId): JsonResponse
    {
        $wishlist = $request->session()->get('wishlist', []);

        if (!isset($wishlist[$productId])) {
            return response()->json([
                'message' => 'Товар не найден в избранном',
            ], 404);
        }

        unset($wishlist[$productId]);
        $request->session()->put('wishlist', $wishlist);

        return response()->json([
            'message' => 'Товар удален из избранного',
        ]);
    }

    /**
     * Toggle product in wishlist.
     */
    public function toggle(Request $request, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $wishlist = $request->session()->get('wishlist', []);

        if (isset($wishlist[$productId])) {
            unset($wishlist[$productId]);
            $added = false;
        } else {
            $wishlist[$productId] = now()->toISOString();
            $added = true;
        }

        $request->session()->put('wishlist', $wishlist);

        return response()->json([
            'in_wishlist' => $added,
            'message' => $added ? 'Товар добавлен в избранное' : 'Товар удален из избранного',
        ]);
    }

    /**
     * Get wishlist items count.
     */
    public function count(Request $request): JsonResponse
    {
        $count = count($request->session()->get('wishlist', []));

        return response()->json([
            'count' => $count,
        ]);
    }
}
