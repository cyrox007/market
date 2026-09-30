<?php

namespace App\Http\Resources;

use App\Services\Seo\SeoApiTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SliderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $desktop = $this->getFirstMedia('image');
        $mobile = $this->getFirstMedia('mobile_image');

        $desktopOriginal = $this->mediaUrl($desktop) ?: $this->image_url;
        $desktopThumb = $this->mediaUrl($desktop, 'thumb') ?: $desktopOriginal;
        $desktopHd = $this->mediaUrl($desktop, 'hd') ?: $desktopOriginal;
        $desktopFullHd = $this->mediaUrl($desktop, 'fullhd') ?: $desktopOriginal;

        // Mobile сначала использует отдельный upload/URL, затем desktop fallback.
        $mobileOriginal = $this->mediaUrl($mobile)
            ?: $this->mobile_image_url
            ?: $desktopOriginal;
        $mobileThumb = $this->mediaUrl($mobile, 'thumb') ?: $mobileOriginal;
        $mobileHd = $this->mediaUrl($mobile, 'hd') ?: $mobileOriginal;
        $mobileFullHd = $this->mediaUrl($mobile, 'fullhd') ?: $mobileOriginal;

        return [
            'id' => $this->id,
            'placement' => $this->placement,
            'slot' => $this->slot,
            'title' => $this->title,
            'description' => $this->description,
            'link' => $this->link,
            'button_text' => $this->button_text,
            'badge_text' => $this->badge_text,
            'badge_link' => $this->badge_link,
            'badge_icon' => $this->badge_icon,
            'badge_tone' => $this->badge_tone,

            'image' => $desktopOriginal,
            'image_thumb' => $desktopThumb,
            'image_hd' => $desktopHd,
            'image_fullhd' => $desktopFullHd,

            'image_mobile' => $mobileOriginal,
            'image_mobile_thumb' => $mobileThumb,
            'image_mobile_hd' => $mobileHd,
            'image_mobile_fullhd' => $mobileFullHd,

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
