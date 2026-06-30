<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Product\Product;
use App\Models\Product\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    /**
     * Получить список отзывов для товара (только одобренные)
     * Для вариаций возвращаем отзывы родительского товара
     */
    public function index(Request $request, int $productId): AnonymousResourceCollection|JsonResponse
    {
        $product = Product::findOrFail($productId);

        // Если это вариация, используем ID родительского товара
        $targetProductId = $product->isVariant() && $product->parent_product_id
            ? $product->parent_product_id
            : $productId;

        $reviews = Review::forProduct($targetProductId)
            ->approved()
            ->orderBy('created_at', 'desc')
            ->get();

        \Log::info('Reviews fetched', [
            'product_id' => $productId,
            'is_variant' => $product->isVariant(),
            'target_product_id' => $targetProductId,
            'reviews_count' => $reviews->count(),
            'reviews' => $reviews->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'is_approved' => $r->is_approved])->toArray(),
        ]);

        // Возвращаем в формате { data: [...] } для консистентности с другими API
        return response()->json([
            'data' => ReviewResource::collection($reviews)->resolve(),
        ]);
    }

    /**
     * Создать отзыв для товара (публичный, без авторизации)
     * Для вариаций отзыв привязывается к родительскому товару
     */
    public function store(Request $request, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:5000',
        ]);

        // Если это вариация, используем ID родительского товара для отзыва
        $targetProductId = $product->isVariant() && $product->parent_product_id
            ? $product->parent_product_id
            : $productId;

        // Если пользователь авторизован, используем его ID
        $userId = auth()->id();

        try {
            $review = Review::create([
                'product_id' => $targetProductId, // Используем родительский товар для вариаций
                'user_id' => $userId,
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
                'is_approved' => false, // Требует модерации
            ]);

            \Log::info('Review created', [
                'review_id' => $review->id,
                'requested_product_id' => $productId,
                'is_variant' => $product->isVariant(),
                'target_product_id' => $targetProductId,
                'name' => $review->name,
                'is_approved' => $review->is_approved,
            ]);

            return response()->json([
                'message' => 'Отзыв успешно добавлен и ожидает модерации',
                'review' => new ReviewResource($review),
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to create review', [
                'product_id' => $productId,
                'target_product_id' => $targetProductId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Ошибка при создании отзыва',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

