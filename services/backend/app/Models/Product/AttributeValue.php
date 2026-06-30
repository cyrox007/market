<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttributeValue extends Model
{
    use HasFactory;

    protected $table = 'product_attribute_values';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Сбрасываем кэш товаров при изменении значения атрибута
        static::saved(function ($attributeValue) {
            \App\Models\Product\Product::flushAllProductCaches();
        });

        static::deleted(function ($attributeValue) {
            \App\Models\Product\Product::flushAllProductCaches();
        });
    }

    protected $fillable = [
        'attribute_id',
        'value',
        'slug',
        'color_code',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Получить характеристику
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    /**
     * Получить продукты с этим значением (обычные характеристики)
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_product_attributes',
            'attribute_value_id',
            'product_id'
        )->withPivot('attribute_id')->withTimestamps();
    }

    /**
     * Получить вариации с этим значением (через product_variant_attributes)
     */
    public function variantProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_variant_attributes',
            'attribute_value_id',
            'product_id'
        )->withPivot('attribute_id')->withTimestamps();
    }
}

