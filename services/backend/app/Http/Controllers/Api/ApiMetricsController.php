<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiMetrics\ApiMetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ApiMetricsController extends Controller
{
    public function __construct(
        protected ApiMetricsCollector $collector
    ) {}

    public function __invoke(): JsonResponse
    {
        if (! config('api_metrics.metrics_route_enabled', true)) {
            Log::info('[ApiMetricsController] metrics route disabled');

            return response()->json(['message' => 'Metrics disabled'], 403);
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
