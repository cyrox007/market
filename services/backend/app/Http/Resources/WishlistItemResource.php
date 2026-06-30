<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product ?? $this->buyable ?? null;

        return [
            'id' => $this->id ?? $this->product_id ?? null,
            'product_id' => $this->product_id ?? ($product->id ?? null),
            'product' => $product ? new ProductResource($product) : null,
            'added_at' => $this->created_at?->toISOString() ?? null,
        ];
    }
}
