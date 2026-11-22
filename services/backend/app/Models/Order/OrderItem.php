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
        'pricing_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'total' => 'decimal:2',
            'pricing_snapshot' => 'array',
            'display_snapshot' => 'array',
        ];
    }

    /**
     * Boot the model.
     * Автоматически заполняет product_type и name, если они не указаны
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (self $item): void {
            if ($item->getRawOriginal('display_snapshot') !== null && $item->isDirty(['display_snapshot', 'product_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['display_snapshot' => 'Состав оформленного товара зафиксирован и не может быть подменён.']);
            }
        });
        static::creating(function (self $orderItem): void {
            $product = $orderItem->product_id ? Product::find($orderItem->product_id) : null;
            if ($product && empty($orderItem->product_type)) {
                $orderItem->product_type = $product->morphTypeName();
            }
            if ($product && empty($orderItem->name)) {
                $orderItem->name = $product->getName();
            }
            $orderItem->display_snapshot = $product
                ? app(\App\Services\Order\OrderItemDisplay::class)->capture($product, $orderItem) : null;
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
