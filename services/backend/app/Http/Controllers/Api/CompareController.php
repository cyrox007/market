<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompareItemResource;
use App\Http\Resources\ProductResource;
use App\Models\Product\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompareController extends Controller
{
    /**
     * Get compare list.
     */
    public function index(Request $request): JsonResponse
    {
        $productIds = $request->session()->get('compare', []);
        $products = Product::whereIn('id', $productIds)
            ->active()
            ->with(['attributeValues.attribute'])
            ->get();

        return response()->json([
            'products' => ProductResource::collection($products),
            'count' => count($productIds),
        ]);
    }

    /**
     * Add product to compare.
     */
    public function add(Request $request, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $compare = $request->session()->get('compare', []);

        if (in_array($productId, $compare)) {
            return response()->json([
                'message' => 'Товар уже в списке сравнения',
            ], 422);
        }

        // Максимум 5 товаров для сравнения
        if (count($compare) >= 5) {
            return response()->json([
                'message' => 'Максимум 5 товаров для сравнения',
            ], 422);
        }

        $compare[] = $productId;
        $request->session()->put('compare', $compare);

        return response()->json([
            'message' => 'Товар добавлен в сравнение',
            'count' => count($compare),
        ], 201);
    }

    /**
     * Remove product from compare.
     */
    public function remove(Request $request, int $productId): JsonResponse
    {
        $compare = $request->session()->get('compare', []);

        if (!in_array($productId, $compare)) {
            return response()->json([
                'message' => 'Товар не найден в списке сравнения',
            ], 404);
        }

        $compare = array_values(array_diff($compare, [$productId]));
        $request->session()->put('compare', $compare);

        return response()->json([
            'message' => 'Товар удален из сравнения',
            'count' => count($compare),
        ]);
    }

    /**
     * Clear compare list.
     */
    public function clear(Request $request): JsonResponse
    {
        $request->session()->forget('compare');

        return response()->json([
            'message' => 'Список сравнения очищен',
        ]);
    }

    /**
     * Get compare items count.
     */
    public function count(Request $request): JsonResponse
    {
        $count = count($request->session()->get('compare', []));

        return response()->json([
            'count' => $count,
        ]);
    }
}
