<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource as ApiStoreResource;
use App\Models\Page\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StoreController extends Controller
{
    /**
     * Get all stores.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $city = $request->query('city');

        $stores = Store::cached('index' . ($city ? "_city_{$city}" : ''), function () use ($city) {
            $query = Store::active()
                ->ordered();

            if ($city && $city !== 'Все города') {
                $query->byCity($city);
            }

            return $query->get();
        });

        return ApiStoreResource::collection($stores);
    }

    /**
     * Get store by slug.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $store = Store::cached("show_{$slug}", function () use ($slug) {
            return Store::where('slug', $slug)
                ->orWhere('id', $slug)
                ->active()
                ->firstOrFail();
        });

        return response()->json([
            'store' => new ApiStoreResource($store),
        ]);
    }

    /**
     * Get list of cities with stores.
     */
    public function cities(Request $request): JsonResponse
    {
        $cities = Store::cached('cities', function () {
            return Store::active()
                ->select('city')
                ->distinct()
                ->orderBy('city')
                ->pluck('city')
                ->toArray();
        });

        return response()->json([
            'cities' => $cities,
        ]);
    }
}
