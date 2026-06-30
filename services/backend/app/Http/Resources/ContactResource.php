<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'company_name' => $this->company_name,
            'address' => $this->address,
            'phones' => $this->phones ?? [],
            'emails' => $this->emails ?? [],
            'social_networks' => $this->social_networks ?? [],
            'map_embed' => $this->map_embed,
            'working_hours' => $this->working_hours ?? [],
        ];
    }
}


