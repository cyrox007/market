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
        $clean = fn (array $option): array => Arr::except($option, ['profile_fingerprint', 'warehouse_fingerprint', 'store_fingerprint', 'global_stock_deltas']);
        $result['selected'] = $result['selected'] ? $clean($result['selected']) : null;
        $result['options'] = array_map($clean, $result['options']);
        $result['pickup'] = array_map($clean, $result['pickup'] ?? []);

        return $result;
    }
}
