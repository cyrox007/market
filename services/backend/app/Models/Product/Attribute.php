<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasFactory;

    /** Slug атрибута «Производитель» (импорт из 1С, фильтры, карточка товара) */
    public const SLUG_MANUFACTURER = 'proizvoditel';

    /** Slug атрибута «Вариант» — используется в вариациях, значение вручную или из импорта (опции) */
    public const SLUG_VARIANT = 'variant';

    protected $table = 'product_attributes';

    /**
     * Системный атрибут «Вариант» — хранит название торгового предложения (значение из модалки / импорта).
     * Создаётся автоматически, если в справочнике ещё нет записи.
     */
    public static function ensureVariantAttribute(): self
    {
        $attribute = static::firstOrCreate(
            ['slug' => self::SLUG_VARIANT],
            [
                'name' => 'Вариант',
                'type' => 'string',
                'is_filterable' => false,
                'is_use_in_variations' => true,
                'allow_custom_value' => true,
                'sort_order' => 4,
            ],
        );

        if (! $attribute->is_use_in_variations || ! $attribute->allow_custom_value) {
            $attribute->forceFill([
                'is_use_in_variations' => true,
                'allow_custom_value' => true,
            ])->saveQuietly();
        }

        return $attribute;
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Сбрасываем кэш товаров при изменении атрибута
        static::saved(function ($attribute) {
            \App\Models\Product\Product::flushAllProductCaches();
        });

        static::deleted(function ($attribute) {
            \App\Models\Product\Product::flushAllProductCaches();
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'type',
        'is_filterable',
        'is_required',
        'is_use_in_variations',
        'allow_custom_value',
        'sort_order',
        'is_multiple'
    ];

    protected $casts = [
        'is_multiple' => 'boolean',
        'is_filterable' => 'boolean',
        'is_required' => 'boolean',
        'is_use_in_variations' => 'boolean',
        'allow_custom_value' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: атрибуты, участвующие в торговых предложениях (вариациях)
     */
    public function scopeVariationAttributes($query)
    {
        return $query->where('is_use_in_variations', true)->orderBy('sort_order');
    }

    /**
     * Получить все значения характеристики
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id');
    }

    /**
     * Получить продукты с этой характеристикой
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_product_attributes',
            'attribute_id',
            'product_id'
        )->withPivot('attribute_value_id')->withTimestamps();
    }

    /**
     * Получить категории, для которых этот атрибут является атрибутом вариаций по умолчанию
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_variation_attributes',
            'attribute_id',
            'category_id'
        )->withTimestamps();
    }

    /**
     * Получить значения, отсортированные по sort_order
     */
    public function orderedValues(): HasMany
    {
        return $this->values()->orderBy('sort_order');
    }

}

