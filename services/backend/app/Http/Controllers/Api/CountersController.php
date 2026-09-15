<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Vanilo\Cart\Facades\Cart;

/**
 * Единый эндпоинт счётчиков шапки: корзина, избранное, сравнение — одним запросом,
 * чтобы шапка не делала три отдельных обращения. Данные по сессии (api-session).
 */
class CountersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'cart' => Cart::itemCount(),
            'wishlist' => count($request->session()->get('wishlist', [])),
            'compare' => count($request->session()->get('compare', [])),
        ]);
    }
}
