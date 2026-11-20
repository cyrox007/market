<?php
namespace App\Http\Resources;

use App\Services\Order\CustomerOrderPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $presentation = app(CustomerOrderPresentation::class)->present($this->resource);
        return [
            'id' => $this->id, 'number' => $this->number,
            'status' => $this->status, 'status_label' => $presentation['status']['label'],
            'total' => (float) $this->total, 'delivery_type' => $this->delivery_type,
            'created_at' => $this->created_at?->toISOString(), 'items_count' => $this->items_count,
            'presentation' => $presentation,
        ];
    }
}
