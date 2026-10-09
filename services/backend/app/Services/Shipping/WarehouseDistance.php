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
        $point = $warehouse->dispatchCoordinates();
        if ($point === null || abs($point['latitude']) > 90 || abs($point['longitude']) > 180) {
            return null;
        }
        $lat = deg2rad($point['latitude']);
        $destination = deg2rad($latitude);
        $a = sin(($destination - $lat) / 2) ** 2
            + cos($lat) * cos($destination) * sin(deg2rad($longitude - $point['longitude']) / 2) ** 2;

        return round(6371.0088 * 2 * asin(sqrt(max(0, min(1, $a)))), 3);
    }
}
