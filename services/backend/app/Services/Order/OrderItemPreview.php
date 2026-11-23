<?php

namespace App\Services\Order;

use App\Models\Order\OrderItem;
use App\Models\Product\Product;

/**
 * Small, customer-facing order line. Never serialize the live ProductResource:
 * it calculates warehouse prices, availability and other unrelated state.
 */
class OrderItemPreview
{
    public function present(OrderItem $item): array
    {
        $product = $item->relationLoaded('product') ? $item->getRelation('product') : null;
        $name = trim((string) ($item->name ?: ($item->display_snapshot['name'] ?? '')));
        if ($name === '') {
            $name = $product?->name ?: 'Товар недоступен';
        }

        $image = $this->mainImage($product);

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'name' => $name,
            'quantity' => (int) $item->quantity,
            'price' => (float) $item->price,
            'total' => (float) $item->total,
            // Backward-compatible with OrderSummaryRow and OrderItemsList,
            // which expect item.product.image/thumbnail/name.
            'product' => [
                'id' => $item->product_id,
                'name' => $name,
                'image' => $image?->getUrl(),
                'thumbnail' => $image?->getAvailableUrl(['thumb']),
            ],
        ];
    }

    private function mainImage(?Product $product): ?\Spatie\MediaLibrary\MediaCollections\Models\Media
    {
        if (! $product) {
            return null;
        }

        $media = $product->getFirstMedia('images');
        if (! $media && $product->isVariant()) {
            $media = $product->parentProduct?->getFirstMedia('images');
        }

        return $media;
    }
}
