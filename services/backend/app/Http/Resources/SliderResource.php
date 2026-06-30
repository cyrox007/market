<?php

namespace App\Http\Resources;

use App\Services\Seo\SeoApiTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SliderResource extends JsonResource
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
            'description' => $this->description,
            'link' => $this->link,
            'button_text' => $this->button_text,
            'badge_text' => $this->badge_text,
            'badge_link' => $this->badge_link,
            'badge_icon' => $this->badge_icon,
            'image' => $this->getFirstMediaUrl('image'),
            'image_thumb' => $this->getFirstMediaUrl('image', 'thumb'),
            'image_hd' => $this->getFirstMediaUrl('image', 'hd'),
            'image_fullhd' => $this->getFirstMediaUrl('image', 'fullhd'),
            'slug' => $this->slug,
            'full_path' => $this->full_path,
            'seo' => SeoApiTransformer::forModel($this->resource),
        ];
    }
}


