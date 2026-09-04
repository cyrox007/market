<?php

namespace App\Actions\Integration\Integration1C;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncOrdersTo1CAction
{
    public function execute(array $orders): bool
    {
        if ($orders === []) {
            return true;
        }

        $configPrefix = 'services.integration_1c';

        if (! (bool) config($configPrefix . '.enabled', false)) {
            return false;
        }

        $baseUrl = rtrim((string) config($configPrefix . '.base_url', ''), '/');
        $path = (string) config($configPrefix . '.orders_path', '/api/v1/integration/1c/orders');
        $apiKey = (string) config($configPrefix . '.api_key', '');
        $timeout = (int) config($configPrefix . '.timeout', 15);

        if ($baseUrl === '') {
            return false;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        try {
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->post($baseUrl . '/' . ltrim($path, '/'), [
                    'orders' => $orders,
                ]);
        } catch (Throwable $e) {
            Log::warning('1C order sync exception', [
                'error' => $e->getMessage(),
                'orders_count' => count($orders),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('1C order sync failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'orders_count' => count($orders),
            ]);

            return false;
        }

        Log::info('1C order sync completed', [
            'orders_count' => count($orders),
        ]);

        return true;
    }
}
