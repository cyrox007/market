<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Шаблон (группа) характеристик: набор для добавления в товар одним действием.
 */
class AttributeTemplate extends Model
{
    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'applied_count' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AttributeTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Недостающие у товара характеристики шаблона, в порядке шаблона.
     *
     * @param  array<int>  $presentIds
     * @return list<int>
     */
    public function attributeIdsToAdd(array $presentIds, ?Product $product): array
    {
        return $this->items()
            ->whereNotIn('attribute_id', $presentIds)
            ->whereHas('attribute', fn ($query) => $query->assignableToProduct($product))
            ->pluck('attribute_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
