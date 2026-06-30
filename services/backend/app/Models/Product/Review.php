<?php

namespace App\Models\Product;

use App\Models\User;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ReviewFactory::new();
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Сбрасываем кэш товара при изменении отзыва
        static::saved(function ($review) {
            if ($review->product_id) {
                $product = \App\Models\Product\Product::find($review->product_id);
                if ($product) {
                    $product->flushCache();
                }
            }
        });

        static::deleted(function ($review) {
            if ($review->product_id) {
                $product = \App\Models\Product\Product::find($review->product_id);
                if ($product) {
                    $product->flushCache();
                }
            }
        });
    }

    protected $fillable = [
        'product_id',
        'user_id',
        'name',
        'email',
        'rating',
        'comment',
        'is_approved',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
    ];

    /**
     * Получить продукт, к которому относится отзыв
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Получить пользователя, оставившего отзыв
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope для получения только одобренных отзывов
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope для получения отзывов конкретного товара
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Получить отформатированную дату создания
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->created_at->format('d F Y');
    }
}