<?php

namespace App\Services\Shipping;

use App\Models\Inventory\Warehouse;
use InvalidArgumentException;

/** Straight-line distance, not a road route or delivery-price calculation. */
class WarehouseDistance
{
    public function kilometres(Warehouse $warehouse, float $latitude, float $longitude): ?float
    {
        if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
            throw new InvalidArgumentException('Invalid destination coordinates');
        }
        if ($warehouse->latitude === null || $warehouse->longitude === null
            || abs((float) $warehouse->latitude) > 90 || abs((float) $warehouse->longitude) > 180) {
            return null;
        }
        $lat = deg2rad((float) $warehouse->latitude);
        $destination = deg2rad($latitude);
        $a = sin(($destination - $lat) / 2) ** 2
            + cos($lat) * cos($destination) * sin(deg2rad($longitude - (float) $warehouse->longitude) / 2) ** 2;

        return round(6371.0088 * 2 * asin(sqrt(max(0, min(1, $a)))), 3);
    }
}
