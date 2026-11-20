<?php
namespace App\Services\Order;

use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Services\Product\ProductColors;

class OrderItemDisplay
{
    public function capture(Product $product, OrderItem $item): array
    {
        $specifications = $product->buildEffectiveSpecificationsForApi();
        $configurationSlugs = collect(config('order_item_display.configuration_slugs'))
            ->merge($product->attributes()->where('is_use_in_variations', true)->pluck('slug'))->unique();
        return [
            'version' => 1, 'captured_at' => now()->toISOString(),
            'name' => (string) $item->name, 'parent_name' => $product->isVariant() ? $product->parentProduct?->name : null,
            'article' => filled($product->external_id) ? trim($product->external_id) : null,
            'sku' => $product->sku,
            'colors' => app(ProductColors::class)->rows($product),
            'materials' => array_values(array_filter($specifications, fn ($row) => in_array($row['slug'], config('order_item_display.material_slugs'), true))),
            'configuration' => array_values(array_filter($specifications, fn ($row) => $configurationSlugs->contains($row['slug']) && ! in_array($row['slug'], config('order_item_display.material_slugs'), true))),
            'specifications' => $specifications,
            'bundle' => $item->pricing_snapshot['bundle'] ?? null,
        ];
    }

    public function present(OrderItem $item): array
    {
        if ($item->display_snapshot !== null) {
            return ['source' => 'snapshot', ...$item->display_snapshot];
        }
        // Legacy orders have no historical colors/configuration. Never substitute today's catalog data.
        return ['source' => 'legacy', 'version' => null, 'captured_at' => null, 'name' => $item->name,
            'parent_name' => null, 'article' => null, 'sku' => null, 'colors' => [], 'materials' => [],
            'configuration' => [], 'specifications' => [], 'bundle' => $item->pricing_snapshot['bundle'] ?? null];
    }
}
