<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'city' => $this->city,
            'street' => $this->street,
            'house' => $this->house,
            'apartment' => $this->apartment,
            'entrance' => $this->entrance,
            'room' => $this->room,
            'floor' => $this->floor,
            'intercom' => $this->intercom,
            'comment' => $this->comment,
            'directory_external_id' => $this->directory_external_id,
            'gar_guid' => $this->gar_guid,
            'kladr_code' => $this->kladr_code,
            'address_snapshot' => $this->address_snapshot,
            'is_default' => $this->is_default,
            'full_address' => $this->full_address,
            'shipping_location_id' => $this->shipping_location_id,
            'shipping_location' => $this->whenLoaded('shippingLocation', function () {
                return $this->shippingLocation ? [
                    'id' => $this->shippingLocation->id,
                    'name' => $this->shippingLocation->name,
                ] : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
