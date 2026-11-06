<?php

namespace App\Http\Resources;

use App\Services\Product\ProductColors;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InteriorIdeaResource extends JsonResource
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
            'image' => $this->getFirstMediaUrl('image'),
            'image_thumb' => $this->getFirstMediaUrl('image', 'thumb'),
            'image_main' => $this->getFirstMediaUrl('image', 'main'),
            'hotspots' => $this->when(
                $this->relationLoaded('hotspots'),
                fn() => tap($this->hotspots, fn ($hotspots) => app(ProductColors::class)->preload($hotspots->pluck('product')->filter()))->map(function ($hotspot) {
                    $product = $hotspot->relationLoaded('product') && $hotspot->product ? $hotspot->product : null;
                    $parent = $product && $product->isVariant() ? ($product->parentProduct ?? null) : null;
                    $linkProduct = $parent ?? $product;

                    $variantLinkParams = [];
                    if ($product && $product->isVariant()) {
                        $colorOptions = app(ProductColors::class)->selected($product);
                        if ($colorOptions !== []) {
                            $variantLinkParams['color_options'] = $colorOptions;
                        }
                        if (!empty($product->length) && !empty($product->width)) {
                            $variantLinkParams['size'] = (int) $product->length . 'x' . (int) $product->width;
                        }
                    }
                    
                    return [
                        'id' => $hotspot->id,
                        'x' => (float) $hotspot->x,
                        'y' => (float) $hotspot->y,
                        'product' => $linkProduct ? [
                            // Ссылка всегда на родителя (если hotspot указывает на вариацию)
                            'id' => $linkProduct->id,
                            'name' => $linkProduct->name,
                            'price' => (float) $linkProduct->price,
                            'image' => $linkProduct->getFirstMediaUrl('images'),
                            'image_thumb' => $linkProduct->getFirstMediaUrl('images', 'thumb'),
                            'slug' => $linkProduct->slug,
                            'full_path' => $linkProduct->full_path,

                            // Чтобы фронт мог выбрать конкретную вариацию
                            'variant_id' => $product && $product->isVariant() ? $product->id : null,
                            'variant_slug' => $product && $product->isVariant() ? $product->slug : null,
                            'variant_link_params' => $variantLinkParams,
                        ] : null,
                    ];
                })
            ),
        ];
    }
}
