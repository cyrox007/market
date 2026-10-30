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
        if (array_key_exists('delivery_days_min', $public)) {
            // This is an ETA range, not evidence that a date/time slot is free.
            $public['delivery_schedule'] = [
                'status' => ! empty($public['delivery_estimate_unavailable'])
                    || ! empty($public['has_preorders'])
                    ? 'requires_confirmation' : 'estimate_only',
                'date_selection_enabled' => false,
                'time_selection_enabled' => false,
                'confirmation_required' => true,
                'notice' => 'Срок ориентировочный. Дату и время доставки согласует менеджер после оформления заказа.',
            ];
        }
        if (isset($public['warehouse_allocations'])) {
            $public['warehouse_allocations'] = array_map(
                fn (array $source): array => Arr::except($source, $private),
                $public['warehouse_allocations']
            );
        }
        return $public;
    }
}
