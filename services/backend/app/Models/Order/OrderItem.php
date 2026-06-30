<?php

namespace App\Models\Order;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * Boot the model.
     * Автоматически заполняет product_type и name, если они не указаны
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($orderItem) {
            // Если product_type не указан, получаем его из продукта
            if (empty($orderItem->product_type) && $orderItem->product_id) {
                $product = Product::find($orderItem->product_id);
                if ($product) {
                    $orderItem->product_type = $product->morphTypeName();
                }
            }

            // Если name не указан, получаем его из продукта
            if (empty($orderItem->name) && $orderItem->product_id) {
                $product = Product::find($orderItem->product_id);
                if ($product) {
                    $orderItem->name = $product->getName();
                }
            }
        });
    }

    /**
     * Get the order that owns the item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product for the item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
