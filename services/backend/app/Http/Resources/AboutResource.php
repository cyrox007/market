<?php

namespace App\Http\Resources;

use App\Services\Seo\SeoApiTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AboutResource extends JsonResource
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
            'hero_title' => $this->hero_title,
            'hero_description' => $this->hero_description,
            'hero_image' => $this->getFirstMediaUrl('hero_image'),
            'hero_image_thumb' => $this->getFirstMediaUrl('hero_image', 'thumb'),
            'hero_image_hd' => $this->getFirstMediaUrl('hero_image', 'hd'),
            'hero_image_fullhd' => $this->getFirstMediaUrl('hero_image', 'fullhd'),
            'story_title' => $this->story_title,
            'story_content' => $this->story_content,
            'story_images' => $this->getMedia('story_images')->map(function ($media) {
                return [
                    'url' => $media->getUrl(),
                    'thumb' => $media->getUrl('thumb'),
                    'hd' => $media->getUrl('hd'),
                    'fullhd' => $media->getUrl('fullhd'),
                ];
            }),
            'statistics' => $this->statistics ?? [],
            'team_members' => TeamMemberResource::collection($this->whenLoaded('teamMembers')),
            'advantages' => AdvantageResource::collection($this->whenLoaded('advantages')),
            'full_path' => $this->full_path,
            'seo' => SeoApiTransformer::forModel($this->resource),
        ];
    }
}




