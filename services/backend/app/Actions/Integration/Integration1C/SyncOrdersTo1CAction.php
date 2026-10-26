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
        $path = (string) config($configPrefix . '.orders_path', '/api/v2/orders/sync');
        $apiKey = (string) config($configPrefix . '.api_key', '');
        $timeout = (int) config($configPrefix . '.timeout', 15);

        if ($baseUrl === '') {
            return false;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Idempotency-Key' => hash('sha256', json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
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

        // The receiver returns HTTP 200 even when an individual order failed.
        $receipts = $response->json();
        if (! is_array($receipts) || ! array_is_list($receipts) || count($receipts) !== count($orders)) {
            Log::warning('1C order sync missing per-order acknowledgement', ['orders_count' => count($orders)]);
            return false;
        }
        $seen = [];
        foreach ($orders as $order) {
            $id = (int) ($order['orderId'] ?? 0);
            $matches = array_values(array_filter($receipts, fn ($receipt) => is_array($receipt)
                && (int) ($receipt['orderId'] ?? 0) === $id));
            if ($id <= 0 || isset($seen[$id]) || count($matches) !== 1
                || ! is_numeric($matches[0]['id'] ?? null) || (int) $matches[0]['id'] <= 0
                || ! in_array($matches[0]['status'] ?? null, array_column(\App\Models\Order\OrderStatus::cases(), 'value'), true)) {
                Log::warning('1C order sync rejected or unacknowledged order', ['order_id' => $id]);
                return false;
            }
            $seen[$id] = true;
        }

        Log::info('1C order sync completed', [
            'orders_count' => count($orders),
        ]);

        return true;
    }
}
