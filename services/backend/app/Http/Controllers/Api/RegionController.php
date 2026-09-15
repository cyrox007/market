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

    /**
     * Получить рекомендуемый регион для пользователя
     * 
     * Логика определения:
     * 1. Если пользователь авторизован и у него есть адрес по умолчанию - используем его регион
     * 2. Если передан city в запросе - ищем магазин по городу и берем его регион
     * 3. Определяем по IP адресу пользователя (геолокация)
     * 4. Возвращаем первый активный регион по умолчанию
     */
    public function detect(Request $request): JsonResponse
    {
        $locationFromRequest = $this->resolveLocationFromRequest($request);
        if ($locationFromRequest) {
            return response()->json([
                'region' => [
                    'id' => $locationFromRequest->id,
                    'name' => $locationFromRequest->name,
                    'type' => $locationFromRequest->type,
                ],
                'source' => 'request_location',
            ]);
        }

        $user = $request->user();
        $city = $request->get('city');
        $ip = $request->ip();
        
        // 1. Проверяем адрес пользователя
        if ($user) {
            $defaultAddress = $user->defaultAddress();
            if ($defaultAddress && $defaultAddress->shipping_location_id) {
                $region = ShippingLocation::where('id', $defaultAddress->shipping_location_id)
                    ->where('is_active', true)
                    ->first();
                
                if ($region) {
                    return response()->json([
                        'region' => [
                            'id' => $region->id,
                            'name' => $region->name,
                            'type' => $region->type,
                        ],
                        'source' => 'user_address',
                    ]);
                }
                
                // Если у адреса есть город, но нет shipping_location_id, ищем магазин по городу
                if ($defaultAddress->city && !$city) {
                    $city = $defaultAddress->city;
                }
            }
        }

        // 2. Проверяем город из запроса
        if ($city) {
            $cityClean = trim($city);
            
            // Сначала ищем точное совпадение по названию локации
            $exactMatch = ShippingLocation::where('is_active', true)
                ->where(function($query) use ($cityClean) {
                    $query->where('name', $cityClean)
                          ->orWhere('name', 'like', "г. {$cityClean}")
                          ->orWhere('name', 'like', "г.{$cityClean}")
                          ->orWhere('name', 'like', "{$cityClean}%");
                })
                ->whereIn('type', ['locality', 'region'])
                ->orderByRaw("CASE WHEN type = 'locality' THEN 1 ELSE 2 END")
                ->orderBy('sort_order')
                ->first();
            
            if ($exactMatch) {
                // Если нашли locality, возвращаем его родительский регион или сам locality
                $region = $exactMatch;
                if ($exactMatch->type === 'locality' && $exactMatch->parent_id) {
                    $parentRegion = ShippingLocation::where('id', $exactMatch->parent_id)
                        ->where('is_active', true)
                        ->where('type', 'region')
                        ->first();
                    if ($parentRegion) {
                        $region = $parentRegion;
                    }
                }
                
                return response()->json([
                    'region' => [
                        'id' => $region->id,
                        'name' => $region->name,
                        'type' => $region->type,
                    ],
                    'source' => 'city_name_match',
                ]);
            }
            
            // Если точного совпадения нет, ищем по магазинам
            $store = Store::where('city', 'like', "%{$cityClean}%")
                ->where('is_active', true)
                ->whereNotNull('shipping_location_id')
                ->first();
            
            if ($store && $store->shipping_location_id) {
                $region = ShippingLocation::where('id', $store->shipping_location_id)
                    ->where('is_active', true)
                    ->first();
                
                if ($region) {
                    return response()->json([
                        'region' => [
                            'id' => $region->id,
                            'name' => $region->name,
                            'type' => $region->type,
                        ],
                        'source' => 'store_city',
                    ]);
                }
            }
        }

        // 3. Определяем регион по IP адресу
        // Получаем реальный IP клиента (учитываем прокси в Docker)
        $clientIp = $request->header('X-Forwarded-For');
        if ($clientIp) {
            $clientIp = explode(',', $clientIp)[0];
            $clientIp = trim($clientIp);
        }
        if (!$clientIp || $clientIp === '127.0.0.1' || $clientIp === '::1') {
            $clientIp = $ip;
        }
        
        if ($clientIp && $clientIp !== '127.0.0.1' && $clientIp !== '::1' && !str_starts_with($clientIp, '172.') && !str_starts_with($clientIp, '192.168.')) {
            try {
                // Используем бесплатный API для определения города по IP
                // ip-api.com - бесплатный, до 45 запросов в минуту
                $ipApiUrl = "http://ip-api.com/json/{$clientIp}?fields=status,message,city,regionName,country";
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 2, // Таймаут 2 секунды
                        'ignore_errors' => true,
                    ]
                ]);
                
                $response = @file_get_contents($ipApiUrl, false, $context);
                if ($response) {
                    $ipData = json_decode($response, true);
                    
                    if (isset($ipData['status']) && $ipData['status'] === 'success' && !empty($ipData['city'])) {
                        $detectedCity = $ipData['city'];
                        
                        // Ищем магазин по городу из IP геолокации
                        $store = Store::where('city', 'like', "%{$detectedCity}%")
                            ->where('is_active', true)
                            ->whereNotNull('shipping_location_id')
                            ->first();
                        
                        if ($store && $store->shipping_location_id) {
                            $region = ShippingLocation::where('id', $store->shipping_location_id)
                                ->where('type', 'region')
                                ->where('is_active', true)
                                ->first();
                            
                            if ($region) {
                                return response()->json([
                                    'region' => [
                                        'id' => $region->id,
                                        'name' => $region->name,
                                        'type' => $region->type,
                                    ],
                                    'source' => 'ip_geolocation',
                                ]);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки определения по IP, продолжаем дальше
            }
        }

        // 4. Возвращаем первую активную локацию по умолчанию (приоритет городам, потом регионам)
        $defaultRegion = ShippingLocation::where('is_active', true)
            ->whereIn('type', ['locality', 'region'])
            ->orderByRaw("CASE WHEN type = 'locality' THEN 1 ELSE 2 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if ($defaultRegion) {
            return response()->json([
                'region' => [
                    'id' => $defaultRegion->id,
                    'name' => $defaultRegion->name,
                    'type' => $defaultRegion->type,
                ],
                'source' => 'default',
            ]);
        }

        return response()->json([
            'region' => null,
            'source' => 'none',
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
