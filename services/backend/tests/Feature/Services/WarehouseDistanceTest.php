<?php

namespace Tests\Feature\Services;

use App\Models\Inventory\Warehouse;
use App\Services\Shipping\WarehouseDistance;
use Tests\TestCase;

class WarehouseDistanceTest extends TestCase
{
    public function test_same_point_has_zero_distance(): void
    {
        $warehouse = new Warehouse(['latitude' => 51.66, 'longitude' => 39.20]);
        $this->assertSame(0.0, app(WarehouseDistance::class)->kilometres($warehouse, 51.66, 39.20));
    }

    public function test_distance_is_in_kilometres(): void
    {
        $warehouse = new Warehouse(['latitude' => 0, 'longitude' => 0]);
        $this->assertEqualsWithDelta(111.195, app(WarehouseDistance::class)->kilometres($warehouse, 1, 0), 0.001);
    }

    public function test_missing_point_is_unknown_not_zero(): void
    {
        $this->assertNull(app(WarehouseDistance::class)->kilometres(new Warehouse, 51, 39));
    }

    public function test_invalid_destination_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(WarehouseDistance::class)->kilometres(new Warehouse, 91, 39);
    }
}
