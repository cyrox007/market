<?php

namespace App\Services\Order;

use App\Models\Product\Product;
use App\Services\Shipping\FulfillmentLines;
use Illuminate\Support\Collection;

class DirectCheckout
{
    public function items(array $items): Collection
    {
        return collect(app(FulfillmentLines::class)->resolve($items))->map(fn (array $line) => (object) [
            'buyable' => Product::findOrFail($line['product_id']),
            'product_id' => $line['product_id'],
            'quantity' => $line['quantity'],
        ]);
    }
}
