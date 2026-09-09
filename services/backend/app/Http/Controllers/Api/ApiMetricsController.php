<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiMetrics\ApiMetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiMetricsController extends Controller
{
    public function __construct(
        protected ApiMetricsCollector $collector
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! config('api_metrics.metrics_route_enabled', true)) {
            Log::info('[ApiMetricsController] metrics route disabled');

            return response()->json(['message' => 'Metrics disabled'], 403);
        }

        // Р-5: fail-closed — без корректного токена метрики не отдаём (пустой конфиг-токен = закрыто).
        $configuredToken = (string) config('api_metrics.token', '');
        $providedToken = (string) ($request->header('X-Metrics-Token') ?? $request->query('token', ''));
        if ($configuredToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            $aggregates = $this->collector->getAggregates();
            Log::info('[ApiMetricsController] metrics requested', [
                'routes_count' => count($aggregates),
            ]);

            return response()->json([
                'routes' => $aggregates,
            ]);
        } catch (\Throwable $e) {
            Log::error('[ApiMetricsController] failed to get metrics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'routes' => [],
                'error' => 'Failed to retrieve metrics',
            ], 500);
        }
    }
}
