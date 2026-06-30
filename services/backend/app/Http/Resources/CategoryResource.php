<?php

namespace App\Http\Resources;

use App\Services\Seo\SeoApiTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Получаем медиа из коллекции 'image' или 'images' (для обратной совместимости)
        $media = $this->getFirstMedia('image') ?: $this->getFirstMedia('images');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug ?? \Str::slug($this->name),
            'description' => $this->description,
            'image' => $media ? $media->getUrl() : null,
            'image_thumb' => $media ? $media->getUrl('thumb') : null,
            'image_hd' => $media ? $media->getUrl('hd') : null,
            'image_fullhd' => $media ? $media->getUrl('fullhd') : null,
            'icon' => $this->icon,
            'seo' => SeoApiTransformer::forModel($this->resource),
            'products_count' => array_key_exists('products_count', $this->getAttributes())
                ? (int) $this->getAttributes()['products_count']
                : 0,
            'parent_id' => $this->parent_id,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'full_path' => $this->full_path,
            'full_path_array' => $this->when(property_exists($this->resource, 'full_path_array'), fn() => $this->full_path_array),
        ];
    }
}
