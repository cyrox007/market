<?php

namespace App\Services\Address;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class AddressDirectoryClient
{
    private string $lastListCacheStatus = 'live';

    private ?int $lastListCacheAge = null;

    /** Cache diagnostics for response headers; no changes to the JSON API. */
    public function lastListCacheStatus(): string
    {
        return $this->lastListCacheStatus;
    }

    public function lastListCacheAge(): ?int
    {
        return $this->lastListCacheAge;
    }

    public function status(): array
    {
        return $this->get(rtrim(config('address_directory.directory_path'), '/').'/status', unwrap: false);
    }

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

        // Canonicalized arguments share the same backend cache entry even if
        // the caller supplies query params in a different order.
        ksort($query);
        $cacheKey = 'address-directory:options:'.sha1($level.':'.json_encode($query));
        $ttl = max(1, (int) config('address_directory.options_ttl.'.$level,
            min(300, (int) config('address_directory.cache_ttl'))));
        // Autocomplete changes on every keystroke: keep those entries short.
        $staleTtl = max(0, (int) config('address_directory.stale_ttl', 604800));
        if (filled($query['q'] ?? null)) {
            $ttl = min($ttl, max(1, (int) config('address_directory.search_ttl', 300)));
            $staleTtl = min($staleTtl, max(0, (int) config('address_directory.search_stale_ttl', 86400)));
        }

        $items = $this->cachedList(
            $cacheKey,
            $ttl,
            $staleTtl,
            fn (): array => $this->get(rtrim(config('address_directory.directory_path'), '/').'/'.$level, $query,
                invalidSelectionField: isset($query['parentExternalId']) ? 'parentExternalId' : null),
            // Empty lists are normal during incremental building import, but
            // must not mask a newly transferred building or become stale data.
            $level !== 'buildings'
        );

        if ($level !== 'localities') {
            return $items;
        }

        // A legacy padded code is an alias only when its canonical record is
        // present with the same parent, name and locality type. Never merge by name alone.
        $canonical = [];
        foreach ($items as $item) {
            if (($item['source'] ?? null) === 'kladr' && preg_match('/^\d{13}$/', $item['externalId'] ?? '')) {
                $canonical[$item['externalId']] = $item;
            }
        }

        return array_values(array_filter($items, static function (array $item) use ($canonical): bool {
            $id = $item['externalId'] ?? '';
            if (($item['source'] ?? null) !== 'kladr' || ! preg_match('/^\d{13}00$/', $id)) {
                return true;
            }
            $other = $canonical[substr($id, 0, 13)] ?? null;
            $type = static fn (string $value): string => match (mb_strtolower(trim($value, ' .'))) {
                'г', 'город' => 'город',
                default => mb_strtolower(trim($value, ' .')),
            };

            return ! ($other && ($other['parentExternalId'] ?? null) === ($item['parentExternalId'] ?? null)
                && ($other['name'] ?? null) === ($item['name'] ?? null)
                && $type($other['type'] ?? '') === $type($item['type'] ?? ''));
        }));
    }

    public function searchBuildings(string $query, int $limit = 20): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $buildings = $this->cachedList(
            'address-directory:warehouse-search:'.sha1($query.':'.$limit),
            max(1, (int) config('address_directory.search_ttl', 300)),
            max(0, (int) config('address_directory.search_stale_ttl', 86400)),
            fn (): array => array_values(array_filter(
                $this->get(config('address_directory.search_path'), [
                    'q' => $query,
                    'limit' => max(1, min($limit, 50)),
                ]),
                static fn (array $item): bool => ($item['kind'] ?? null) === 'building'
            )),
            false
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
            ), invalidSelectionField: 'directory_external_id')
        );
    }

    /**
     * Two-tier cache: short-lived fresh listings, plus the last successful
     * non-empty answer for temporary UPSTREAM failures (stale-if-error).
     *
     * Only discovery lists use this fallback. Address hierarchies used for
     * delivery quotes and orders remain strict and expire normally.
     * Explicit invalid-selection responses (ValidationException) are never
     * hidden by old data.
     */
    private function cachedList(string $key, int $ttl, int $staleTtl, Closure $load, bool $cacheEmpty): array
    {
        $this->lastListCacheStatus = 'live';
        $this->lastListCacheAge = null;
        $staleKey = $key.':last-good';
        $cooldownKey = $key.':upstream-cooldown';
        $fresh = Cache::get($key);
        if (is_array($fresh) && ($cacheEmpty || $fresh !== [])) {
            $this->lastListCacheStatus = 'fresh';
            // Also seed the stale tier from existing installations with
            // cached fresh data but no last-good entry yet.
            if ($fresh !== [] && $staleTtl > 0 && ! Cache::has($staleKey)) {
                Cache::put($staleKey, ['items' => $fresh, 'cached_at' => now()->timestamp], $staleTtl);
            }
            return $fresh;
        }

        // After the first failure, don't make every visitor wait for an
        // upstream timeout. Recheck once the brief per-list cooldown expires.
        if ((bool) config('address_directory.stale_enabled', true)
            && Cache::get($cooldownKey) === true
            && ($lastGood = $this->lastGoodList($staleKey)) !== null) {
            return $lastGood;
        }

        try {
            $items = $load();
            Cache::forget($cooldownKey);
            if ($cacheEmpty || $items !== []) {
                Cache::put($key, $items, $items === [] ? min($ttl, 30) : $ttl);
            } else {
                Cache::forget($key);
            }
            if ($items !== [] && $staleTtl > 0) {
                Cache::put($staleKey, ['items' => $items, 'cached_at' => now()->timestamp], $staleTtl);
            }
            return $items;
        } catch (RuntimeException $error) {
            // An upstream 5xx, bad JSON or timeout can use a previously
            // successful LIST. A 4xx invalid address does not reach here.
            if ((bool) config('address_directory.stale_enabled', true) && $staleTtl > 0) {
                $lastGood = $this->lastGoodList($staleKey);
                if ($lastGood !== null) {
                    Cache::put($cooldownKey, true,
                        max(1, (int) config('address_directory.failure_cooldown', 30)));
                    if (Cache::add($key.':fallback-logged', true, 300)) {
                        Log::warning('Address directory listing served from last successful cache', [
                            'key_hash' => sha1($key),
                            'age_seconds' => $this->lastListCacheAge,
                            'upstream_error' => $error->getMessage(),
                        ]);
                    }
                    return $lastGood;
                }
            }
            throw $error;
        }
    }

    private function lastGoodList(string $key): ?array
    {
        $lastGood = Cache::get($key);
        if (! is_array($lastGood) || ! is_array($lastGood['items'] ?? null)
            || $lastGood['items'] === []) {
            return null;
        }
        $this->lastListCacheStatus = 'stale';
        $this->lastListCacheAge = max(0, now()->timestamp - (int) ($lastGood['cached_at'] ?? 0));
        return $lastGood['items'];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('address_directory.base_url'), '/'))
            ->acceptJson()
            ->connectTimeout((float) config('address_directory.connect_timeout'))
            ->timeout((float) config('address_directory.timeout'))
            ->retry(1, 150, throw: false);
    }

    private function get(string $path, array $query = [], bool $unwrap = true, ?string $invalidSelectionField = null): array
    {
        try {
            $response = $this->request()->get($path, $query);
        } catch (\Illuminate\Http\Client\ConnectionException $error) {
            throw new RuntimeException('Address directory connection failed', previous: $error);
        }

        if ($invalidSelectionField !== null && in_array($response->status(), [400, 404, 422], true)) {
            throw ValidationException::withMessages([$invalidSelectionField => 'Адрес не найден или больше недоступен. Выберите пункт из справочника заново.']);
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
