<?php

namespace App\Services\Address;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AddressDirectoryClient
{
    public function nearby(float $latitude, float $longitude): array
    {
        // Precise user coordinates are not persisted in the cache.
        return $this->get(rtrim(config('address_directory.directory_path'), '/').'/nearby', [
            'latitude' => $latitude, 'longitude' => $longitude,
        ], unwrap: false);
    }

    public function options(string $level, array $query = []): array
    {
        if (! in_array($level, ['regions', 'localities', 'streets', 'buildings'], true)) {
            throw new RuntimeException('Unsupported directory level');
        }

        return Cache::remember(
            'address-directory:options:'.sha1($level.':'.json_encode($query)),
            min(300, (int) config('address_directory.cache_ttl')),
            fn (): array => $this->get(rtrim(config('address_directory.directory_path'), '/').'/'.$level, $query)
        );
    }

    public function searchBuildings(string $query, int $limit = 20): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $buildings = Cache::remember(
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

        foreach ($buildings as $building) {
            if (filled($building['externalId'] ?? null) && filled($building['label'] ?? null)) {
                Cache::put(
                    'address-directory:label:'.sha1((string) $building['externalId']),
                    (string) $building['label'],
                    (int) config('address_directory.cache_ttl')
                );
            }
        }

        return $buildings;
    }

    public function cachedLabel(string $externalId): ?string
    {
        $label = Cache::get('address-directory:label:'.sha1($externalId));

        return is_string($label) && $label !== '' ? $label : null;
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

    private function get(string $path, array $query = [], bool $unwrap = true): array
    {
        try {
            $response = $this->request()->get($path, $query);
        } catch (\Illuminate\Http\Client\ConnectionException $error) {
            throw new RuntimeException('Address directory connection failed', previous: $error);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Address directory unavailable: HTTP {$response->status()}");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Address directory returned invalid JSON');
        }

        if (! $unwrap) {
            return $payload;
        }

        foreach (['data', 'items', 'results'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return $payload;
    }
}
