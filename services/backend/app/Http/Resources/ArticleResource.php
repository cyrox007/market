<?php

namespace App\Http\Resources;

use App\Services\Seo\SeoApiTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
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
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'published_at' => $this->published_at?->toIso8601String(),
            'image' => $this->getFirstMediaUrl('image'),
            'image_thumb' => $this->getFirstMediaUrl('image', 'thumb'),
            'image_hd' => $this->getFirstMediaUrl('image', 'hd'),
            'image_fullhd' => $this->getFirstMediaUrl('image', 'fullhd'),
            'gallery' => $this->getMedia('gallery')
                ->sortBy('order_column')
                ->map(function ($media) {
                    return [
                        'url' => $media->getUrl(),
                        'thumb' => $media->getUrl('thumb'),
                        'hd' => $media->getUrl('hd'),
                        'fullhd' => $media->getUrl('fullhd'),
                    ];
                }),
            'category' => $this->when(
                $this->relationLoaded('category') && $this->category,
                fn() => [
                    'id' => $this->category->id,
                    'title' => $this->category->title,
                    'slug' => $this->category->slug,
                ]
            ),
            'author' => $this->when(
                $this->relationLoaded('author') && $this->author,
                fn() => [
                    'id' => $this->author->id,
                    'name' => $this->author->name,
                ]
            ),
            'full_path' => $this->full_path,
            'seo' => SeoApiTransformer::forModel($this->resource),
        ];
    }
}


