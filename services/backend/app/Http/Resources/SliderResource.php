<?php

namespace App\Http\Resources;

use App\Models\Page\Slider;
use App\Services\Seo\SeoApiTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SliderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $image = $this->getDisplayImageMedia();
        $mobileImage = $this->getDisplayMobileImageMedia();

        return [
            'id' => $this->id,
            'placement' => $this->placement,
            'title' => $this->display_title,
            'description' => $this->description,
            'link' => $this->display_link,
            'button_text' => $this->button_text,
            'badge_text' => $this->badge_text,
            'badge_link' => $this->badge_link,
            'badge_icon' => $this->badge_icon,

            'image' => $this->mediaUrl($image),
            'image_thumb' => $this->mediaUrl($image, 'thumb'),
            'image_hd' => $this->mediaUrl($image, 'hd'),
            'image_fullhd' => $this->mediaUrl($image, 'fullhd'),

            'image_mobile' => $this->mediaUrl($mobileImage),
            'image_mobile_thumb' => $this->mediaUrl($mobileImage, 'thumb'),
            'image_mobile_hd' => $this->mediaUrl($mobileImage, 'hd'),
            'image_mobile_fullhd' => $this->mediaUrl($mobileImage, 'fullhd'),

            'category_id' => $this->category_id,
            'category' => $this->when(
                $this->placement === Slider::PLACEMENT_HOME_CATEGORIES && $this->category,
                fn (): array => [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                    'full_path' => $this->category->full_path,
                ]
            ),

            'priority' => (int) $this->priority,
            'slug' => $this->slug,
            'full_path' => $this->full_path,
            'seo' => SeoApiTransformer::forModel($this->resource),
        ];
    }

    private function mediaUrl(?Media $media, ?string $conversion = null): ?string
    {
        if (! $media) {
            return null;
        }

        return $conversion ? $media->getUrl($conversion) : $media->getUrl();
    }
}
