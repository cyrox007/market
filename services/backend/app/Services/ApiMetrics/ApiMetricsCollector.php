<?php

namespace App\Services\ApiMetrics;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ApiMetricsCollector
{
    public function record(string $routeKey, int $durationMs): void
    {
        if (! config('api_metrics.store_enabled', true)) {
            return;
        }

        try {
            $prefix = config('api_metrics.redis_prefix', 'api_metrics:');
            $ttl = config('api_metrics.ttl_seconds', 3600);
            $maxRequests = config('api_metrics.window_max_requests', 1000);
            $key = $prefix . 'route:' . $routeKey;

            $redis = Redis::connection();
            $redis->multi();
            $redis->lPush($key . ':samples', (string) $durationMs);
            $redis->lTrim($key . ':samples', 0, $maxRequests - 1);
            $redis->expire($key . ':samples', $ttl);
            $redis->exec();

            Log::debug('[ApiMetricsCollector] recorded', [
                'route_key' => $routeKey,
                'duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::error('[ApiMetricsCollector] failed to record', [
                'route_key' => $routeKey,
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, array{count: int, avg_ms: float, max_ms: int}>
     */
    public function getAggregates(): array
    {
        if (! config('api_metrics.store_enabled', true)) {
            return [];
        }

        try {
            $prefix = config('api_metrics.redis_prefix', 'api_metrics:');
            $redis = Redis::connection();
            $connectionPrefix = config('database.redis.options.prefix', '');
            $pattern = $connectionPrefix . $prefix . 'route:*:samples';
            $keys = $redis->keys($pattern);
            $result = [];

            foreach ($keys as $key) {
                $routeKey = (string) str_replace([$connectionPrefix . $prefix . 'route:', ':samples'], '', $key);
                $samples = $redis->lRange($key, 0, -1);
                if ($samples === false || count($samples) === 0) {
                    continue;
                }
                $values = array_map('intval', $samples);
                $result[$routeKey] = [
                    'count' => count($values),
                    'avg_ms' => round(array_sum($values) / count($values), 2),
                    'max_ms' => (int) max($values),
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('[ApiMetricsCollector] failed to get aggregates', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
