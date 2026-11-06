<?php

namespace App\Models\Product\Color;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use function Illuminate\Support\defer;

/**
 * Строка блока «Цвета товара»: цвет элемента (null — основной цвет).
 */
class ProductColorRow extends Model
{
    protected $fillable = ['product_id', 'color_element_id', 'color_solution_id', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    protected static function booted(): void
    {
        static::saved(fn (self $row) => $row->flushProductCaches());
        static::deleted(fn (self $row) => $row->flushProductCaches());
    }

    /** Сбросить кэш списков, фильтров и карточек товара и его родителя — один раз после ответа на всё сохранение. */
    private function flushProductCaches(): void
    {
        $productId = $this->product_id;
        defer(fn () => Product::flushAllProductCaches(), 'product-caches');
        defer(function () use ($productId): void {
            $product = Product::find($productId);
            $product?->flushCache();
            $product?->parentProduct?->flushCache();
        }, 'product-cache-'.$productId);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(ColorElement::class, 'color_element_id');
    }

    public function solution(): BelongsTo
    {
        return $this->belongsTo(ColorSolution::class, 'color_solution_id');
    }
}
