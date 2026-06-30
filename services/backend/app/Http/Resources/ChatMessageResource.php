<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
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
            'message' => $this->message,
            'user' => new UserResource($this->whenLoaded('user')),
            'is_read' => $this->is_read,
            'is_from_user' => $this->user_id === $request->user()?->id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
