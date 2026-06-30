<?php

namespace App\Actions\Inventory\Sync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncStocksTo1CAction
{
    public function execute(array $items): bool
    {
        if ($items === []) {
            return true;
        }

        $baseUrl = rtrim((string) config('catalog_import.config.svetofor_1c.base_url', ''), '/');
        $path = (string) config('catalog_import.config.svetofor_1c.stock_sync_path', '/api/v1/integration/1c/v2/cache/stocks');
        $apiKey = (string) config('catalog_import.config.svetofor_1c.api_key', '');
        $timeout = (int) config('catalog_import.config.svetofor_1c.stock_sync_timeout', 8);

        if ($baseUrl === '') {
            return false;
        }

        $headers = ['Accept' => 'application/json'];
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        try {
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->post($baseUrl . '/' . ltrim($path, '/'), ['items' => $items]);
        } catch (Throwable $e) {
            Log::warning('Inventory sync: exception during sync warehouse stocks to 1C', [
                'error' => $e->getMessage(),
                'items_count' => count($items),
                'timeout' => $timeout,
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Inventory sync: failed to sync warehouse stocks to 1C', [
                'status' => $response->status(),
                'body' => $response->body(),
                'items_count' => count($items),
            ]);

            return false;
        }

        Log::info('Inventory sync: stocks synced to 1C', [
            'items_count' => count($items),
        ]);

        return true;
    }
}

