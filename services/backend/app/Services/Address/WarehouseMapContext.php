<?php

namespace App\Services\Address;

class WarehouseMapContext
{
    public function __construct(private readonly AddressDirectoryClient $directory) {}

    /** Viewport only: never used as warehouse coordinates or delivery origin. */
    public function focus(string $level, ?string $id, ?array $fallback = null, ?array $snapshot = null): ?array
    {
        if (blank($id)) {
            return $fallback;
        }
        try {
            $hierarchy = $snapshot ?? $this->directory->hierarchy(explode('::', $id, 2)[0]);
            $target = $hierarchy['target'] ?? [];
            if ($level === 'region') {
                return $this->bounds($this->directory->options('localities', ['parentExternalId' => $id, 'limit' => 200]), 'region', 9,
                    'Обзор региона по известным населённым пунктам. Это не точка склада.') ?? $fallback;
            }
            if ($level === 'locality') {
                return $this->point($target, 'locality', 12, 'Обзор населённого пункта. Выберите улицу и дом.') ?? $fallback;
            }
            if ($level === 'building' && in_array($target['coordinatePrecision'] ?? null, ['building', 'building_centroid', 'address_point', 'entrance'], true)) {
                $point = $this->point($target, 'building', 17, 'Координаты дома из справочника. Уточните точку склада при необходимости.');
                if ($point !== null) {
                    return $point;
                }
            }
            $street = $level === 'street' ? $target : collect($hierarchy['path'] ?? [])->last(fn (array $item): bool => ($item['kind'] ?? null) === 'street');
            if (filled($street['externalId'] ?? null)) {
                $bounds = $this->bounds($this->directory->options('buildings', ['parentExternalId' => $street['externalId'], 'limit' => 200]), 'street', 16,
                    'Обзор улицы по домам с известными координатами, не точка выбранного дома. Укажите склад на карте.');
                if ($bounds !== null) {
                    return $bounds;
                }
            }
            $city = collect($hierarchy['path'] ?? [])->last(fn (array $item): bool => ($item['kind'] ?? null) === 'locality');
            return $city ? $this->point($city, 'locality', 12, 'Координат улицы или дома пока нет. Показан населённый пункт; укажите склад на карте.') ?? $fallback : $fallback;
        } catch (\Throwable $error) {
            report($error);
            return $fallback;
        }
    }

    private function point(array $item, string $level, int $zoom, string $message): ?array
    {
        if (! $this->valid($item)) {
            return null;
        }
        return ['point' => [(float) $item['latitude'], (float) $item['longitude']], 'level' => $level, 'zoom' => $zoom, 'message' => $message];
    }

    private function bounds(array $items, string $level, int $zoom, string $message): ?array
    {
        $points = array_values(array_filter($items, fn (array $item): bool => $this->valid($item)));
        if ($points === []) {
            return null;
        }
        return ['bounds' => [[min(array_column($points, 'latitude')), min(array_column($points, 'longitude'))],
            [max(array_column($points, 'latitude')), max(array_column($points, 'longitude'))]], 'level' => $level, 'zoom' => $zoom, 'message' => $message];
    }

    private function valid(array $item): bool
    {
        return is_numeric($item['latitude'] ?? null) && is_numeric($item['longitude'] ?? null)
            && abs((float) $item['latitude']) <= 90 && abs((float) $item['longitude']) <= 180;
    }
}
