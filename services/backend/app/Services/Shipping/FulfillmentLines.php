<?php

namespace App\Services\Shipping;

use App\Models\Product\Product;
use Illuminate\Validation\ValidationException;

class FulfillmentLines
{
    /** Resolve a root+variant pair to a concrete SKU; never guess a variant. */
    public function resolve(array $items): array
    {
        $lines = [];
        foreach ($items as $index => $item) {
            $root = Product::active()->find($item['product_id']);
            $sku = isset($item['variant_id']) ? Product::active()->where('parent_product_id', $root?->id)->find($item['variant_id']) : $root;
            if (! $root || ! $sku || ($sku->isVariant() && (! $sku->parentProduct || (string) $sku->parentProduct->state !== 'active')) || (! isset($item['variant_id']) && $root->isVariable() && $root->variants()->exists())) {
                throw ValidationException::withMessages(['items.'.$index.'.variant_id' => 'Выберите действующий вариант этого товара.']);
            }
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (! $quantity || $quantity < 1 || $quantity > 1000) {
                throw ValidationException::withMessages(['items.'.$index.'.quantity' => 'Количество должно быть целым числом от 1 до 1000.']);
            }
            $lines[] = ['product_id' => $sku->id, 'quantity' => $quantity];
        }

        return $lines;
    }
}
