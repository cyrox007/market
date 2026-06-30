<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'external_id',
    ];

    /**
     * Товары этого производителя (только родительские, не вариации).
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'manufacturer_id')
            ->whereNull('parent_product_id');
    }
}
