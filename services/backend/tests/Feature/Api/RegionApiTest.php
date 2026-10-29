<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class RegionApiTest extends TestCase
{
    public function test_old_region_api_is_retired(): void
    {
        foreach (['/regions', '/regions/tree', '/regions/detect?shipping_location_id=1'] as $path) {
            $this->getJson('/api/v1'.$path)->assertNotFound();
        }
    }
}

