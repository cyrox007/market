<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
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
            'order' => new OrderResource($this->whenLoaded('order')),
            'messages' => ChatMessageResource::collection($this->whenLoaded('messages')),
            'last_message_at' => $this->last_message_at?->toISOString(),
            'unread_count' => $this->when(
                $this->relationLoaded('messages'),
                fn() => $this->messages->where('is_read', false)
                    ->where('user_id', '!=', $request->user()?->id)
                    ->count()
            ),
        ];
    }
}
