<?php

declare(strict_types=1);

namespace App\Services\Inventory\Integrations;

use Illuminate\Support\Facades\Http;

/**
 * Узкий HTTP-клиент остатков 1С.
 *
 * Не импортирует товар и не меняет модели приложения.
 */
class OneCInventoryStockClient
{
    private const DEFAULT_BASE_URL = 'http://api.svetofor-mebel.ru';

    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private string $productStocksPathTemplate;

    public function __construct()
    {
        $config = config('catalog_import.config.svetofor_1c', []);
        $baseUrl = trim((string) ($config['base_url'] ?? ''));

        $this->baseUrl = rtrim(
            $baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL,
            '/'
        );
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->timeout = max(
            1,
            (int) ($config['stock_refresh_timeout'] ?? $config['timeout'] ?? 30)
        );
        $this->productStocksPathTemplate = (string) (
            $config['stock_product_path_template']
            ?? '/api/v1/integration/1c/v2/cache/products/{external_id}/stocks'
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function fetchProductStocks(string $externalId): array
    {
        $externalId = trim($externalId);

        if ($externalId === '') {
            throw new \InvalidArgumentException('external_id 1С не может быть пустым.');
        }

        $path = str_replace(
            '{external_id}',
            rawurlencode($externalId),
            $this->productStocksPathTemplate
        );

        $response = Http::timeout($this->timeout)
            ->withHeaders($this->requestHeaders())
            ->get($this->baseUrl . '/' . ltrim($path, '/'));

        if (! $response->successful()) {
            throw new \RuntimeException(
                "1C stock API failed for {$externalId}: {$response->status()} {$response->body()}"
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException(
                "1C stock API returned invalid JSON for {$externalId}."
            );
        }

        $rows = $this->unwrapRows($payload);

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @return list<mixed>
     */
    private function unwrapRows(array $payload): array
    {
        foreach (['data', 'items', 'stocks', 'results'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
        }

        return array_is_list($payload) ? array_values($payload) : [$payload];
    }

    /**
     * @return array<string,string>
     */
    private function requestHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        return $headers;
    }
}
