<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class WarehouseDeliveryQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = $this->resource;
        $clean = self::sanitizeOption(...);
        $result['selected'] = $result['selected'] ? $clean($result['selected']) : null;
        $result['options'] = array_map($clean, $result['options']);
        $result['pickup'] = array_map($clean, $result['pickup'] ?? []);

        return $result;
    }

    /** Never leak internal fingerprints, including nested warehouse allocations. */
    public static function sanitizeOption(array $option): array
    {
        $private = ['profile_fingerprint', 'warehouse_fingerprint', 'store_fingerprint', 'global_stock_deltas'];
        $public = Arr::except($option, $private);
        if (isset($public['warehouse_allocations'])) {
            $public['warehouse_allocations'] = array_map(
                fn (array $source): array => Arr::except($source, $private),
                $public['warehouse_allocations']
            );
        }
        return $public;
    }
}
