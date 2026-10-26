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
    public function current(Request $request): JsonResponse
    {
        $destination = app(\App\Services\Shipping\CatalogDestination::class)->resolve($request, null);
        $store = app(\App\Services\Inventory\LocalStoreResolver::class)->resolve($destination);

        return response()->json(['store' => $store ? new ApiStoreResource($store) : null]);
    }

    /**
     * Get all stores.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $city = $request->query('city');

        $revision = \Illuminate\Support\Facades\Cache::get('store_api_revision', 0);
        $stores = Store::cached('index'.($city ? "_city_{$city}" : '').'_v'.$revision, function () use ($city) {
            $query = Store::active()
                ->ordered();

            if ($city && $city !== 'Все города') {
                $query->byCity($city);
            }

            return $query->get();
        });

        $stores->load(['physicalSite', 'warehouse']);

        return ApiStoreResource::collection($stores);
    }

    /**
     * Get store by slug.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $revision = \Illuminate\Support\Facades\Cache::get('store_api_revision', 0);
        $store = Store::cached("show_{$slug}_v{$revision}", function () use ($slug) {
            return Store::where('slug', $slug)
                ->orWhere('id', $slug)
                ->active()
                ->firstOrFail();
        });

        $store->load(['physicalSite', 'warehouse']);

        return response()->json([
            'store' => new ApiStoreResource($store),
        ]);
    }

    /**
     * Get list of cities with stores.
     */
    public function cities(Request $request): JsonResponse
    {
        $revision = \Illuminate\Support\Facades\Cache::get('store_api_revision', 0);
        $cities = Store::cached('cities_v'.$revision, function () {
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
