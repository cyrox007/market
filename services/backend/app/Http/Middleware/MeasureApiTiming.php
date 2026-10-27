<?php

namespace App\Http\Middleware;

use App\Services\ApiMetrics\ApiMetricsCollector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureApiTiming
{
    public function __construct(
        protected ApiMetricsCollector $collector
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('api_metrics.enabled', true)) {
            return $next($request);
        }

        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $routeKey = $this->routeKey($request);

        if (config('api_metrics.header_response_time', true)) {
            $requestStart = (float) $request->server('REQUEST_TIME_FLOAT', $start);
            $phpMs = max($durationMs, (int) round((microtime(true) - $requestStart) * 1000));
            $response->headers->set('X-Response-Time', $phpMs . 'ms');
            $response->headers->set('Server-Timing', 'php;dur='.$phpMs.', route;dur='.$durationMs);
        }

        $threshold = config('api_metrics.log_slow_threshold_ms', 1000);
        if ($durationMs >= $threshold) {
            Log::warning('[MeasureApiTiming] slow request', [
                'method' => $request->method(),
                'uri' => $request->path(),
                'route_key' => $routeKey,
                'duration_ms' => $durationMs,
                'threshold_ms' => $threshold,
            ]);
        }
        // Redis observability must not delay sending the catalogue to the buyer.
        if (config('api_metrics.store_enabled', true)) {
            app()->terminating(fn () => $this->collector->record($routeKey, $durationMs));
        }

        return $response;
    }

    protected function routeKey(Request $request): string
    {
        $name = $request->route()?->getName();
        if ($name) {
            return $request->method() . ':' . $name;
        }

        return $request->method() . ':' . $request->path();
    }
}
