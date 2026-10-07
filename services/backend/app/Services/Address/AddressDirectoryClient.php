<?php

namespace App\Services\Address;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AddressDirectoryClient
{
    public function searchBuildings(string $query, int $limit = 20): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        return Cache::remember(
            'address-directory:warehouse-search:'.sha1($query.':'.$limit),
            (int) config('address_directory.cache_ttl'),
            fn (): array => array_values(array_filter(
                $this->get(config('address_directory.search_path'), [
                    'q' => $query,
                    'limit' => max(1, min($limit, 50)),
                ]),
                static fn (array $item): bool => ($item['kind'] ?? null) === 'building'
            ))
        );
    }

    public function hierarchy(string $externalId): array
    {
        return Cache::remember(
            'address-directory:hierarchy:'.sha1($externalId),
            (int) config('address_directory.cache_ttl'),
            fn (): array => $this->get(str_replace(
                '{externalId}',
                rawurlencode($externalId),
                config('address_directory.hierarchy_path')
            ))
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('address_directory.base_url'), '/'))
            ->acceptJson()
            ->connectTimeout((float) config('address_directory.connect_timeout'))
            ->timeout((float) config('address_directory.timeout'))
            ->retry(1, 150, throw: false);
    }

    private function get(string $path, array $query = []): array
    {
        $response = $this->request()->get($path, $query);

        if (! $response->successful()) {
            throw new RuntimeException("Address directory unavailable: HTTP {$response->status()}");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Address directory returned invalid JSON');
        }

        foreach (['data', 'items', 'results'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return $payload;
    }
}
