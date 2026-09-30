<?php

declare(strict_types=1);

namespace App\Services\Inventory\Integrations;

use Illuminate\Support\Facades\Http;

/**
 * HTTP-клиент нового stock-only канала 1С.
 *
 * Не содержит бизнес-логики каталога и не изменяет модели.
 */
class OneCInventoryApiClient
{
    private const DEFAULT_BASE_URL = 'http://api.svetofor-mebel.ru';

    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private string $bulkPath;
    private string $productPathTemplate;

    public function __construct()
    {
        $cfg = config('catalog_import.config.svetofor_1c', []);
        $baseUrl = trim((string) ($cfg['base_url'] ?? ''));

        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL, '/');
        $this->apiKey = (string) ($cfg['api_key'] ?? '');
        $this->timeout = max(1, (int) ($cfg['stock_sync_timeout'] ?? $cfg['timeout'] ?? 30));
        $this->bulkPath = ltrim((string) ($cfg['stock_sync_path'] ?? '/api/v1/integration/1c/v2/cache/stocks'), '/');
        $this->productPathTemplate = (string) ($cfg['stock_product_path_template']
            ?? '/api/v1/integration/1c/v2/cache/products/{external_id}/stocks');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function fetchProductStocks(string $externalId): array
    {
        $path = str_replace('{external_id}', rawurlencode($externalId), $this->productPathTemplate);
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers())
            ->get($this->baseUrl . '/' . ltrim($path, '/'));

        if (! $response->successful()) {
            throw new \RuntimeException(
                "1C stock API failed for {$externalId}: {$response->status()} {$response->body()}"
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new \RuntimeException("1C stock API returned invalid JSON for {$externalId}.");
        }

        return array_values(array_filter($this->unwrapList($data), 'is_array'));
    }

    /**
     * Нормализованный bulk-ответ, сгруппированный по external_id товара.
     *
     * @return array<string,array{stocks:list<array<string,mixed>>,aggregate:?float}>
     */
    public function fetchBulkStocks(): array
    {
        if ($this->bulkPath === '') {
            throw new \RuntimeException('1C stock sync bulk path is not configured.');
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers())
            ->get($this->baseUrl . '/' . $this->bulkPath);

        if (! $response->successful()) {
            throw new \RuntimeException(
                '1C bulk stock API failed: ' . $response->status() . ' ' . $response->body()
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new \RuntimeException('1C bulk stock API returned invalid JSON.');
        }

        $groups = [];

        foreach ($this->unwrapList($data) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $externalId = $this->extractProductExternalId($item);
            if ($externalId === null) {
                continue;
            }

            $groups[$externalId] ??= ['stocks' => [], 'aggregate' => null];

            $nested = $item['stocks'] ?? $item['warehouses'] ?? $item['stockItems'] ?? $item['stock_items'] ?? null;

            if (is_array($nested)) {
                foreach ($this->unwrapList($nested) as $stockRow) {
                    if (is_array($stockRow)) {
                        $groups[$externalId]['stocks'][] = $stockRow;
                    }
                }
            } elseif ($this->looksLikeWarehouseStockRow($item)) {
                $groups[$externalId]['stocks'][] = $item;
            } else {
                $aggregate = $this->extractAggregateQuantity($item);
                if ($aggregate !== null) {
                    $groups[$externalId]['aggregate'] = $aggregate;
                }
            }
        }

        return $groups;
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        return $headers;
    }

    /**
     * @return list<mixed>
     */
    private function unwrapList(array $data): array
    {
        foreach (['data', 'items', 'stocks', 'results'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values($data[$key]);
            }
        }

        return array_is_list($data) ? array_values($data) : [$data];
    }

    private function extractProductExternalId(array $item): ?string
    {
        $value = $item['productExternalId']
            ?? $item['product_external_id']
            ?? $item['externalId']
            ?? $item['external_id']
            ?? $item['productCode']
            ?? $item['product_code']
            ?? null;

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function looksLikeWarehouseStockRow(array $item): bool
    {
        return isset($item['stockId'])
            || isset($item['stock_id'])
            || isset($item['warehouseId'])
            || isset($item['warehouse_id']);
    }

    private function extractAggregateQuantity(array $item): ?float
    {
        foreach (['stock', 'quantity', 'count'] as $key) {
            if (array_key_exists($key, $item) && is_numeric($item[$key])) {
                return (float) $item[$key];
            }
        }

        return null;
    }
}
