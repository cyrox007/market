<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page\Store;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RegionController extends Controller
{
    /**
     * Единый резолв локации из запроса: shipping_location_id имеет приоритет над region_id.
     */
    private function resolveLocationFromRequest(Request $request): ?ShippingLocation
    {
        $shippingLocationId = $request->input('shipping_location_id');
        $legacyRegionId = $request->input('region_id');
        $locationId = $shippingLocationId ?? $legacyRegionId;

        if (!$locationId) {
            return null;
        }

        Log::debug('Resolving location from region detect request', [
            'shipping_location_id' => $shippingLocationId,
            'region_id' => $legacyRegionId,
            'resolved_location_id' => $locationId,
            'source' => $shippingLocationId ? 'shipping_location_id' : 'region_id',
        ]);

        if ($shippingLocationId === null && $legacyRegionId !== null) {
            Log::info('Using legacy region_id for region detect request', [
                'region_id' => $legacyRegionId,
            ]);
        }

        $location = ShippingLocation::where('id', $locationId)
            ->where('is_active', true)
            ->first();

        if (!$location) {
            Log::warning('Invalid or inactive location id in region detect request', [
                'resolved_location_id' => $locationId,
                'shipping_location_id' => $shippingLocationId,
                'region_id' => $legacyRegionId,
            ]);
            return null;
        }

        return $location;
    }

    /** Bridge for the existing client; never infer the customer's city from warehouses or IP. */
    public function detect(Request $request): JsonResponse
    {
        $input = $request->validate([
            'locality_external_id' => 'nullable|string|max:128',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'city' => 'nullable|string|max:100',
        ]);
        $directory = app(\App\Services\Address\LocalityGeoDirectory::class);
        $resolver = app(\App\Services\Shipping\CustomerDestinationResolver::class);
        try {
            if (! empty($input['locality_external_id'])) {
                $locality = $directory->find($input['locality_external_id']);
                abort_unless($locality, 422, 'Выберите населённый пункт из справочника.');
                return response()->json([
                    'locality' => $locality, 'region' => $resolver->resolve($locality),
                    'source' => 'address_directory',
                ]);
            }
            if (isset($input['latitude'], $input['longitude'])) {
                return response()->json(array_merge(
                    $directory->nearby((float) $input['latitude'], (float) $input['longitude']),
                    ['region' => null, 'source' => 'coordinates']
                ));
            }
            if (! empty($input['city'])) {
                $matches = collect($directory->search(null, $input['city']))
                    ->filter(fn (array $row): bool => mb_strtolower($row['name']) === mb_strtolower(trim($input['city'])));
                if ($matches->count() === 1) {
                    $locality = $directory->find($matches->first()['externalId']);
                    if ($locality) {
                        return response()->json(['locality' => $locality, 'region' => $resolver->resolve($locality),
                            'source' => 'address_directory']);
                    }
                }
            }
        } catch (\RuntimeException $error) {
            report($error);
            return response()->json(['message' => 'Адресный справочник временно недоступен.'], 503);
        }
        // An explicit existing shipping choice remains valid; it is not a detected settlement.
        $location = $this->resolveLocationFromRequest($request);
        return response()->json([
            'region' => $location?->only(['id', 'name', 'type']),
            'source' => $location ? 'request_location' : 'none',
        ]);
    }

    /**
     * Получить список всех регионов (для обратной совместимости)
     */
    public function list(): JsonResponse
    {
        $regions = Cache::remember(ShippingLocation::REGIONS_CACHE_KEY, 86400, function () {
            return ShippingLocation::where('type', 'region')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'type']);
        });

        return response()->json([
            'data' => $regions,
        ]);
    }

    /**
     * Получить дерево локаций доставки с иерархией
     * Возвращает федеральные округа -> регионы -> города
     */
    public function tree(): JsonResponse
    {
        try {
            // Загружаем все активные локации с дочерними элементами
            // Если нет федеральных округов, возвращаем регионы как корневые элементы
            $federalDistricts = ShippingLocation::where('type', 'federal_district')
                ->where('is_active', true)
                ->with(['activeChildren' => function ($query) {
                    $query->orderBy('sort_order')->orderBy('name');
                }, 'activeChildren.activeChildren' => function ($query) {
                    $query->orderBy('sort_order')->orderBy('name');
                }])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'type', 'parent_id']);

            // Если нет федеральных округов, загружаем регионы как корневые элементы
            if ($federalDistricts->isEmpty()) {
                $regions = ShippingLocation::where('type', 'region')
                    ->where('is_active', true)
                    ->whereNull('parent_id')
                    ->with(['activeChildren' => function ($query) {
                        $query->orderBy('sort_order')->orderBy('name');
                    }])
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'type', 'parent_id']);

                $tree = $regions->map(function ($region) {
                    return [
                        'id' => $region->id,
                        'name' => $region->name,
                        'slug' => $region->slug,
                        'type' => $region->type,
                        'parent_id' => $region->parent_id,
                        'children' => $region->activeChildren->map(function ($locality) {
                            return [
                                'id' => $locality->id,
                                'name' => $locality->name,
                                'slug' => $locality->slug,
                                'type' => $locality->type,
                                'parent_id' => $locality->parent_id,
                            ];
                        })->values(),
                    ];
                });

                return response()->json([
                    'data' => $tree,
                ]);
            }

            $tree = $federalDistricts->map(function ($district) {
                return [
                    'id' => $district->id,
                    'name' => $district->name,
                    'slug' => $district->slug,
                    'type' => $district->type,
                    'parent_id' => $district->parent_id,
                    'children' => $district->activeChildren->map(function ($region) {
                        return [
                            'id' => $region->id,
                            'name' => $region->name,
                            'slug' => $region->slug,
                            'type' => $region->type,
                            'parent_id' => $region->parent_id,
                            'children' => $region->activeChildren->map(function ($locality) {
                                return [
                                    'id' => $locality->id,
                                    'name' => $locality->name,
                                    'slug' => $locality->slug,
                                    'type' => $locality->type,
                                    'parent_id' => $locality->parent_id,
                                ];
                            })->values(),
                        ];
                    })->values(),
                ];
            });

            return response()->json([
                'data' => $tree,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to load locations tree: ' . $e->getMessage());
            return response()->json([
                'data' => [],
                'error' => 'Failed to load locations',
            ], 500);
        }
    }
}
