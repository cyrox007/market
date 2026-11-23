<?php

namespace App\Http\Resources;

use App\Services\Order\CustomerOrderPresentation;
use App\Services\Order\OrderItemPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $presentation = app(CustomerOrderPresentation::class)->present($this->resource);
        // Both the compact list and last_order load the same limited relations.
        // Reuse the resulting lightweight item array rather than serializing full products.
        $items = $this->resource->relationLoaded('items')
            ? $this->resource->items->sortBy('id')
                ->map(fn ($item): array => app(OrderItemPreview::class)->present($item))
                ->values()->all()
            : [];

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => $presentation['status']['label'],
            'total' => (float) $this->total,
            'delivery_type' => $this->delivery_type,
            'created_at' => $this->created_at?->toISOString(),
            'items_count' => $this->items_count,
            // Light items for the existing order UI; no catalog / shipping resources.
            'items' => $items,
            // First three lines for future small summary cards; total count stays above.
            'items_preview' => array_slice($items, 0, 3),
            'presentation' => $presentation,
        ];
    }
}
