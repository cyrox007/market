<?php

namespace App\Http\Resources;

use App\Services\Seo\SeoApiTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $site = $this->physicalSite;
        $latitude = $site ? $site->latitude : $this->latitude;
        $longitude = $site ? $site->longitude : $this->longitude;
        $warehouse = $this->warehouse;
        $hasOwnStock = $warehouse && $warehouse->is_active && $warehouse->source_type === 'physical' && $warehouse->stock_mode === 'quantity'
            && $site && (int) $warehouse->physical_site_id === (int) $site->id;
        $pickupReady = $warehouse && app(\App\Services\Shipping\PickupReadiness::class)->ready($warehouse, $this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $site?->address ?? $this->address,
            'city' => $site?->city ?? $this->city,
            'physical_site_id' => $this->physical_site_id,
            'locality' => $site ? \App\Services\Address\AddressHierarchy::locality($site->address_snapshot ?? []) : null,
            'warehouse_id' => $this->warehouse_id,
            'has_own_stock' => (bool) $hasOwnStock,
            'gar_guid' => $site?->gar_guid,
            'kladr_code' => $site?->kladr_code,
            'phone' => $this->phone,
            'hours' => $this->hours,
            'opening_hours' => $this->opening_hours,
            'timezone' => $this->timezone ?? 'Europe/Moscow',
            // Configuration alone does not establish an actual pickup point.
            'pickup_enabled' => (bool) $pickupReady,
            'coordinates' => $site ? ($latitude !== null && $longitude !== null ? $latitude.','.$longitude : null) : $this->coordinates,
            'latitude' => $latitude !== null ? (float) $latitude : null,
            'longitude' => $longitude !== null ? (float) $longitude : null,
            'coordinates_array' => $site ? ($latitude !== null && $longitude !== null ? ['lat' => (float) $latitude, 'lng' => (float) $longitude] : null) : $this->coordinates_array,
            'yandex_map' => $this->yandex_map,
            'description' => $this->description,
            'image' => $this->getFirstMediaUrl('image'),
            'image_thumb' => $this->getFirstMediaUrl('image', 'thumb'),
            'image_hd' => $this->getFirstMediaUrl('image', 'hd'),
            'image_fullhd' => $this->getFirstMediaUrl('image', 'fullhd'),
            'full_path' => $this->full_path,
            'seo' => SeoApiTransformer::forModel($this->resource),
        ];
    }
}
