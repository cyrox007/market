<?php

namespace App\Services\Shipping;

use App\Models\Inventory\Warehouse;
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryProfile;

/** The same coverage predicate is used by stock, catalog ranking and delivery quotes. */
class WarehouseCoverage
{
    public function destination(?ShippingLocation $location = null, ?array $snapshot = null, ?array $point = null): array
    {
        $path = $snapshot['path'] ?? [];
        $ids = $location?->getAncestorsIds() ?? [];
        if (! $snapshot && $location) {
            foreach (ShippingLocation::whereIn('id', $ids)->get() as $node) {
                if (filled($node->code)) {
                    $path[] = ['kind' => $node->type, 'externalId' => (string) $node->code, 'kladrCode' => (string) $node->code];
                }
            }
        }

        return ['path' => $path, 'location_ids' => $ids, 'point' => $point,
            'specified' => $location !== null || $snapshot !== null || $point !== null];
    }

    public function profileMatches(WarehouseDeliveryProfile $profile, Warehouse $warehouse, array $destination): bool
    {
        if (! $warehouse->is_active || ! $profile->is_active || ! empty($profile->constraints)) {
            return false;
        }
        $territory = ! empty($profile->directory_territories)
            ? app(DirectoryCoverage::class)->matches($profile->directory_territories, $destination)
            : $profile->locations->pluck('id')->intersect($destination['location_ids'] ?? [])->isNotEmpty();
        $point = $destination['point'] ?? null;
        $distance = $point ? app(WarehouseDistance::class)->kilometres($warehouse, $point['latitude'], $point['longitude']) : null;
        $radius = $distance !== null && $profile->radius_km !== null && $distance <= (float) $profile->radius_km;

        return match ($profile->coverage_type) {
            'locations' => $territory,
            'radius' => $radius,
            'hybrid' => $territory && $radius,
            default => false,
        };
    }

    public function firstProfile(Warehouse $warehouse, array $destination): ?WarehouseDeliveryProfile
    {
        return $warehouse->deliveryProfiles->sortBy([['priority', 'asc'], ['id', 'asc']])
            ->first(fn ($profile) => $this->profileMatches($profile, $warehouse, $destination));
    }
}
