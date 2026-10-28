<?php

namespace App\Services\Product;

use App\Models\Product\Product;
use Illuminate\Validation\ValidationException;

class BundlePricing
{
    /** Allocate the fixed set price in kopecks; split units when a line cannot divide evenly. */
    public function apply(array $composition, array $lines): array
    {
        $price = Product::findOrFail($composition['primary_sku_id'])->bundle_price;
        if ($price === null) {
            return $lines;
        }
        if (! is_numeric($price) || $price <= 0 || $price > 99999999.99) {
            throw ValidationException::withMessages(['bundle_price' => 'Недопустимая цена комплекта.']);
        }
        $target = (int) round((float) $price * 100) * $composition['bundle_quantity'];
        $weights = array_map(fn ($line) => max(0, (int) round($line['price'] * 100)) * $line['quantity'], $lines);
        $weight = array_sum($weights);
        if ($weight === 0) {
            $weights = array_column($lines, 'quantity');
            $weight = array_sum($weights);
        }
        $shares = [];
        foreach ($lines as $i => $line) {
            $exact = $target * ($weights[$i] / $weight);
            $shares[$i] = ['cents' => (int) floor($exact), 'fraction' => $exact - floor($exact)];
        }
        $remaining = $target - array_sum(array_column($shares, 'cents'));
        $indices = array_keys($lines);
        usort($indices, fn ($a, $b) => ($shares[$b]['fraction'] <=> $shares[$a]['fraction']) ?: ($lines[$a]['product_id'] <=> $lines[$b]['product_id']));
        for ($i = 0; $i < $remaining; $i++) {
            $shares[$indices[$i % count($indices)]]['cents']++;
        }
        $result = [];
        foreach ($lines as $i => $line) {
            $qty = (int) $line['quantity'];
            $cents = $shares[$i]['cents'];
            $unit = intdiv($cents, $qty);
            $extraUnits = $cents % $qty;
            foreach ([[$qty - $extraUnits, $unit], [$extraUnits, $unit + 1]] as [$units, $unitPrice]) {
                if ($units === 0) {
                    continue;
                }
                $result[] = [...$line, 'quantity' => $units, 'price' => $unitPrice / 100,
                    'pricing_snapshot' => [...($line['pricing_snapshot'] ?? []), 'bundle_pricing' => [
                        'mode' => 'fixed', 'price_per_bundle' => (float) $price,
                        'bundle_quantity' => $composition['bundle_quantity'],
                        'base_unit_price' => $line['price'],
                    ]],
                ];
            }
        }

        return $result;
    }

    /** Weighted prices for shipping thresholds, including split kopeck lines. */
    public function unitPrices(array $lines): array
    {
        return collect($lines)->groupBy('product_id')->map(fn ($rows) => $rows->sum(fn ($row) => $row['price'] * $row['quantity']) / $rows->sum('quantity'))->all();
    }
}
